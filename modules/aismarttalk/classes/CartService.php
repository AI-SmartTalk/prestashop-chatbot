<?php
/**
 * Copyright (c) 2026 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2026 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Native cart operations for the AI SmartTalk agent.
 *
 * Built on PrestaShop's own Cart / Product / Customer classes so prices, taxes,
 * stock checks and the checkout URL are exactly what the storefront produces. The
 * agent prepares the cart; the shopper completes payment on the native checkout.
 */
class CartService
{
    /**
     * Resolve a PrestaShop customer id from an explicit id or an email.
     * Returns 0 for a guest cart.
     */
    public static function resolveCustomerId(?int $customerId, ?string $email): int
    {
        if ($customerId && $customerId > 0) {
            $customer = new \Customer($customerId);
            if (\Validate::isLoadedObject($customer)) {
                return (int) $customer->id;
            }
        }

        if (!empty($email) && \Validate::isEmail($email)) {
            $existing = (int) \Customer::customerExists($email, true);
            if ($existing > 0) {
                return $existing;
            }
        }

        return 0;
    }

    /**
     * Load the shopper's working cart, or create a fresh one.
     *
     * Priority: an explicit (still-open) cart id → the customer's last unordered
     * cart → a new cart. This keeps repeated add-to-cart calls accumulating into
     * the same cart across conversation turns.
     */
    public static function getOrCreateCart(\Context $context, int $customerId, ?int $cartId, ?string $langIso): \Cart
    {
        if ($cartId) {
            $cart = new \Cart((int) $cartId);
            if (\Validate::isLoadedObject($cart) && !$cart->orderExists()
                && ($customerId === 0 || (int) $cart->id_customer === $customerId)) {
                return $cart;
            }
        }

        if ($customerId > 0) {
            $lastCartId = (int) \Cart::lastNoneOrderedCart($customerId);
            if ($lastCartId) {
                $cart = new \Cart($lastCartId);
                if (\Validate::isLoadedObject($cart)) {
                    return $cart;
                }
            }
        }

        $cart = new \Cart();
        $cart->id_lang = self::resolveLangId($langIso, $context);
        $cart->id_currency = (int) $context->currency->id;
        $cart->id_shop = (int) $context->shop->id;
        $cart->id_shop_group = (int) $context->shop->id_shop_group;

        if ($customerId > 0) {
            $customer = new \Customer($customerId);
            $cart->id_customer = $customerId;
            $cart->secure_key = $customer->secure_key;
            $addressId = (int) \Address::getFirstCustomerAddressId($customerId);
            if ($addressId) {
                $cart->id_address_delivery = $addressId;
                $cart->id_address_invoice = $addressId;
            }
        } else {
            $cart->id_guest = (int) $context->cookie->id_guest;
            $cart->secure_key = !empty($context->cookie->secure_key)
                ? $context->cookie->secure_key
                : md5(uniqid((string) mt_rand(), true));
        }

        $cart->add();

        return $cart;
    }

    /**
     * Add line items to the cart using the native quantity update (runs stock and
     * availability checks). Returns a list of human-readable errors for skipped lines.
     *
     * @param array<int, array{productId?: int, attributeId?: int, quantity?: int}> $products
     *
     * @return string[] errors
     */
    public static function addProducts(\Cart $cart, array $products): array
    {
        $errors = [];

        foreach ($products as $line) {
            $idProduct = (int) ($line['productId'] ?? 0);
            $idAttribute = (int) ($line['attributeId'] ?? 0);
            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            if ($idProduct <= 0) {
                continue;
            }

            $product = new \Product($idProduct, false, (int) $cart->id_lang);
            if (!\Validate::isLoadedObject($product) || !$product->active) {
                $errors[] = 'Product #' . $idProduct . ' is unavailable';
                continue;
            }

            // Honour stock unless the product/shop allows ordering out of stock.
            $available = \Product::getQuantity($idProduct, $idAttribute ?: null);
            $allowOos = \Product::isAvailableWhenOutOfStock((int) $product->out_of_stock);
            if (!$allowOos && $available < $quantity) {
                $errors[] = 'Not enough stock for "' . $product->name . '" (' . (int) $available . ' left)';
                continue;
            }

            $result = $cart->updateQty($quantity, $idProduct, $idAttribute ?: null, false, 'up');
            if ($result === false || (is_array($result) && !empty($result))) {
                $errors[] = 'Could not add "' . $product->name . '" to the cart';
            }
        }

        return $errors;
    }

    /**
     * Normalized cart snapshot consumed by the backend / agent.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(\Cart $cart): array
    {
        $currency = new \Currency((int) $cart->id_currency);
        $rows = $cart->getProducts(true);

        $items = [];
        $count = 0;
        foreach ($rows as $row) {
            $count += (int) $row['cart_quantity'];
            $name = $row['name'];
            if (!empty($row['attributes_small'])) {
                $name .= ' - ' . $row['attributes_small'];
            } elseif (!empty($row['attributes'])) {
                $name .= ' - ' . $row['attributes'];
            }
            $items[] = [
                'productId' => (int) $row['id_product'],
                'attributeId' => (int) ($row['id_product_attribute'] ?? 0),
                'name' => $name,
                'quantity' => (int) $row['cart_quantity'],
                'unitPrice' => \Tools::displayPrice((float) $row['price_wt'], $currency),
                'totalPrice' => \Tools::displayPrice((float) $row['total_wt'], $currency),
            ];
        }

        $total = (float) $cart->getOrderTotal(true, \Cart::BOTH);

        return [
            'cartId' => (int) $cart->id,
            'currency' => $currency->iso_code,
            'currencySign' => $currency->sign,
            'itemCount' => $count,
            'total' => \Tools::displayPrice($total, $currency),
            'totalRaw' => round($total, 2),
            'items' => $items,
        ];
    }

    /**
     * Build the browser cart-restore / checkout URL pointing back to this module's
     * `restore` action, signed and time-bound (see CartGuard).
     */
    public static function buildCheckoutUrl(\Cart $cart): string
    {
        $timestamp = time();
        $token = CartGuard::makeRestoreToken((int) $cart->id, $timestamp);
        $base = rtrim(\Tools::getShopDomainSsl(true), '/');

        return $base . '/index.php?fc=module&module=aismarttalk&controller=cart&action=restore'
            . '&cartId=' . (int) $cart->id
            . '&ts=' . $timestamp
            . '&t=' . $token;
    }

    /**
     * Resolve a front-office language id from an ISO code, falling back to context.
     */
    private static function resolveLangId(?string $langIso, \Context $context): int
    {
        if (!empty($langIso)) {
            $id = (int) \Language::getIdByIso(\Tools::substr($langIso, 0, 2));
            if ($id) {
                return $id;
            }
        }

        return (int) $context->language->id;
    }
}
