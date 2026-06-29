<?php
/**
 * Copyright (c) 2026 AI SmartTalk
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2026 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once _PS_MODULE_DIR_ . 'aismarttalk/vendor/autoload.php';

use PrestaShop\AiSmartTalk\ChatbotSettingsBuilder;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Same-origin AJAX endpoint that lets the chatbot widget READ and MANAGE the
 * CURRENT logged-in customer's cart: add a line, list the cart, change a line's
 * quantity, remove a line.
 *
 * It implements AI SmartTalk's canonical cart contract: every action returns the
 * fresh canonical cart so the widget re-renders from a single source of truth.
 * A WooCommerce/other plugin can expose the same contract and the widget + loader
 * stay unchanged.
 *
 * Why a front controller: the widget UI runs in a cross-origin iframe and cannot
 * carry the storefront cookie. Its same-origin parent loader POSTs here, so
 * PrestaShop hydrates the customer/cart from the session cookie like any request.
 *
 * Version-safe across PrestaShop 1.6 → 9 (Cart::updateQty/deleteProduct,
 * Product::getDefaultAttribute, StockAvailable, locale-aware price formatting);
 * emits JSON directly (no ajaxRender/ajaxDie version drift).
 */
class AismarttalkCartModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;
    /** @var bool We enforce login ourselves to answer JSON 401 instead of redirecting. */
    public $auth = false;
    /** @var bool */
    public $guestAllowed = true;
    /** @var bool */
    public $ajax = true;

    /**
     * Runs after the framework has hydrated context (customer/cart) from the
     * session cookie. Everything returns JSON and exits.
     */
    public function postProcess()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? Tools::strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method !== 'POST') {
            $this->respond(405, ['error' => 'method_not_allowed']);
        }

        $context = $this->context;

        // Hard gate — only a logged-in customer may read/manage their own cart.
        if (!isset($context->customer) || !$context->customer->isLogged()) {
            $this->respond(401, ['error' => 'not_logged_in']);
        }

        // CSRF — token bound to this customer.
        $token = (string) Tools::getValue('token');
        if (!ChatbotSettingsBuilder::isCartTokenValid($token, (int) $context->customer->id)) {
            $this->respond(403, ['error' => 'invalid_token']);
        }

        $action = Tools::getValue('action', 'add');

        switch ($action) {
            case 'get':
                // Read-only — never create a cart just to look at it.
                break;
            case 'add':
                $this->doAdd($context);
                break;
            case 'update':
                $this->doUpdate($context);
                break;
            case 'remove':
                $this->doRemove($context);
                break;
            case 'voucher':
                $this->doVoucher($context);
                break;
            case 'recommendations':
                $this->doRecommendations($context); // responds + exits
                break;
            default:
                $this->respond(400, ['error' => 'unknown_action']);
        }

        $this->respond(200, ['ok' => true, 'cart' => $this->canonicalCart($context)]);
    }

    // -----------------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------------

    /**
     * Add a product (optionally a combination) to the cart.
     *
     * @param Context $context
     * @return void
     */
    private function doAdd($context)
    {
        // Canonical contract: productId / variantId map to PrestaShop's
        // id_product / id_product_attribute.
        $idProduct = (int) Tools::getValue('productId');
        $idProductAttribute = (int) Tools::getValue('variantId');
        $qty = max(1, min(100, (int) Tools::getValue('qty', 1)));
        if ($idProduct <= 0) {
            $this->respond(400, ['error' => 'invalid_product']);
        }

        $product = new Product($idProduct, false, (int) $context->language->id);
        if (!Validate::isLoadedObject($product) || !$product->active) {
            $this->respond(404, ['error' => 'product_not_found']);
        }

        // Resolve the combination: default when the product has combinations and
        // none (or an invalid one) was provided.
        if ($idProductAttribute > 0) {
            if (!$this->combinationBelongsToProduct($idProduct, $idProductAttribute)) {
                $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
            }
        } else {
            $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
        }

        $this->assertStock($context, $idProduct, $idProductAttribute, $qty, $product);

        $cart = $this->ensureCart($context);
        $added = $cart->updateQty($qty, $idProduct, $idProductAttribute > 0 ? $idProductAttribute : 0, false, 'up');
        if ($added === false) {
            $this->respond(409, ['error' => 'cannot_add']);
        }
    }

    /**
     * Set a cart line's quantity to an absolute value (0 removes it).
     *
     * @param Context $context
     * @return void
     */
    private function doUpdate($context)
    {
        $cart = $context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return; // nothing to update
        }
        $k = $this->parseKey((string) Tools::getValue('key'));
        if ($k['id_product'] <= 0) {
            $this->respond(400, ['error' => 'invalid_key']);
        }
        $qty = (int) Tools::getValue('qty');

        if ($qty <= 0) {
            $cart->deleteProduct($k['id_product'], $k['id_product_attribute'], $k['id_customization']);
            return;
        }
        $qty = min(100, $qty);

        $current = $this->currentLineQty($cart, $k);
        $delta = $qty - $current;
        if ($delta === 0) {
            return;
        }
        if ($delta > 0) {
            $product = new Product($k['id_product']);
            $this->assertStock($context, $k['id_product'], $k['id_product_attribute'], $qty, $product);
            $cart->updateQty($delta, $k['id_product'], $k['id_product_attribute'] ?: 0, (int) $k['id_customization'], 'up');
        } else {
            $cart->updateQty(abs($delta), $k['id_product'], $k['id_product_attribute'] ?: 0, (int) $k['id_customization'], 'down');
        }
    }

    /**
     * Remove a cart line entirely.
     *
     * @param Context $context
     * @return void
     */
    private function doRemove($context)
    {
        $cart = $context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return;
        }
        $k = $this->parseKey((string) Tools::getValue('key'));
        if ($k['id_product'] <= 0) {
            $this->respond(400, ['error' => 'invalid_key']);
        }
        $cart->deleteProduct($k['id_product'], $k['id_product_attribute'], $k['id_customization']);
    }

    /**
     * Apply or remove a discount voucher (CartRule). On an invalid code, returns
     * ok:false WITH the unchanged cart so the widget can show the error in place.
     *
     * @param Context $context
     * @return void
     */
    private function doVoucher($context)
    {
        $cart = $this->ensureCart($context);
        $op = Tools::getValue('op', 'apply');

        if ($op === 'remove') {
            // Canonical contract removes a voucher by its code (universal across
            // platforms); resolve it to the PrestaShop CartRule id.
            $code = trim((string) Tools::getValue('code'));
            $idRule = $code !== '' ? (int) CartRule::getIdByCode($code) : 0;
            if ($idRule) {
                $cart->removeCartRule($idRule);
            }
            return;
        }

        $code = trim((string) Tools::getValue('code'));
        if ($code === '') {
            $this->respond(400, ['error' => 'empty_code']);
        }

        $idRule = (int) CartRule::getIdByCode($code);
        $cartRule = $idRule ? new CartRule($idRule, (int) $context->language->id) : null;
        if (!$cartRule || !Validate::isLoadedObject($cartRule)) {
            $this->respond(200, [
                'ok' => false,
                'error' => 'invalid_code',
                'cart' => $this->canonicalCart($context),
            ]);
        }

        // checkValidity returns a (truthy) error message when invalid, false when OK.
        $error = $cartRule->checkValidity($context, false, true);
        if ($error) {
            $this->respond(200, [
                'ok' => false,
                'error' => 'voucher_invalid',
                'message' => is_string($error) ? $error : '',
                'cart' => $this->canonicalCart($context),
            ]);
        }

        $cart->addCartRule((int) $cartRule->id);
    }

    /**
     * Cross-sell: accessory products configured for the items already in the cart
     * (excluding what's already there). Fast, deterministic, no LLM latency.
     *
     * @param Context $context
     * @return void
     */
    private function doRecommendations($context)
    {
        $items = [];
        $cart = $context->cart;
        $idLang = (int) $context->language->id;

        if (Validate::isLoadedObject($cart)) {
            $inCart = [];
            $seen = [];
            $categories = [];
            $products = $cart->getProducts();
            foreach ($products as $p) {
                $inCart[(int) $p['id_product']] = true;
            }

            // 1) Configured accessories (true cross-sell) for each cart product.
            foreach ($products as $p) {
                $product = new Product((int) $p['id_product'], false, $idLang);
                if (!Validate::isLoadedObject($product)) {
                    continue;
                }
                $idCat = (int) $product->id_category_default;
                if ($idCat > 0) {
                    $categories[$idCat] = true;
                }
                $accessories = $product->getAccessories($idLang);
                if (is_array($accessories)) {
                    foreach ($accessories as $a) {
                        $id = (int) $a['id_product'];
                        if ($id <= 0 || isset($inCart[$id]) || isset($seen[$id]) || empty($a['active'])
                            || !$this->isOrderable($context, $id)) {
                            continue;
                        }
                        $seen[$id] = true;
                        $items[] = $this->recoItem($context, $a);
                        if (count($items) >= 8) {
                            break 2;
                        }
                    }
                }
            }

            // 2) Fallback: newest products from the cart items' categories, so the
            //    carousel is rarely empty even when no accessories are configured.
            if (count($items) < 4) {
                foreach (array_keys($categories) as $idCat) {
                    $category = new Category($idCat, $idLang);
                    if (!Validate::isLoadedObject($category)) {
                        continue;
                    }
                    $catProducts = $category->getProducts($idLang, 1, 12, 'date_add', 'desc');
                    if (!is_array($catProducts)) {
                        continue;
                    }
                    foreach ($catProducts as $cp) {
                        $id = (int) $cp['id_product'];
                        if ($id <= 0 || isset($inCart[$id]) || isset($seen[$id])
                            || !$this->isOrderable($context, $id)) {
                            continue;
                        }
                        $seen[$id] = true;
                        $items[] = $this->recoItem($context, $cp);
                        if (count($items) >= 8) {
                            break 2;
                        }
                    }
                }
            }
        }

        $this->respond(200, ['ok' => true, 'items' => $items]);
    }

    /**
     * Whether a product can be added to the cart (in stock, or out-of-stock
     * ordering allowed) — so we never recommend something that can't be bought.
     *
     * @param Context $context
     * @param int     $id
     * @return bool
     */
    private function isOrderable($context, $id)
    {
        $qty = (int) StockAvailable::getQuantityAvailableByProduct($id, null, (int) $context->shop->id);
        if ($qty > 0) {
            return true;
        }
        $product = new Product($id);

        return Validate::isLoadedObject($product)
            && (bool) Product::isAvailableWhenOutOfStock((int) $product->out_of_stock);
    }

    /**
     * @param Context $context
     * @param array   $a Accessory product row (getProductProperties-enriched).
     * @return array
     */
    private function recoItem($context, $a)
    {
        $id = (int) $a['id_product'];
        $image = (!empty($a['id_image']) && !empty($a['link_rewrite']))
            ? $context->link->getImageLink($a['link_rewrite'], $a['id_image'], 'home_default')
            : '';
        $price = isset($a['price_tax_incl'])
            ? (float) $a['price_tax_incl']
            : (isset($a['price']) ? (float) $a['price'] : 0);

        return [
            'productId' => (string) $id,
            'title' => isset($a['name']) ? $a['name'] : '',
            'image' => $image,
            'price' => $this->formatPrice($price, $context),
            'url' => $context->link->getProductLink($id),
        ];
    }

    // -----------------------------------------------------------------------
    // Canonical cart
    // -----------------------------------------------------------------------

    /**
     * Build the canonical cart payload from the current context cart.
     *
     * @param Context $context
     * @return array
     */
    private function canonicalCart($context)
    {
        $currency = isset($context->currency) ? $context->currency->iso_code : '';
        $checkoutUrl = $context->link->getPageLink('order', true);
        $cart = $context->cart;

        if (!Validate::isLoadedObject($cart)) {
            return [
                'currency' => $currency,
                'count' => 0,
                'items' => [],
                'totals' => $this->zeroTotals($context),
                'freeShipping' => $this->freeShippingInfo($context, null),
                'vouchers' => [],
                'checkoutUrl' => $checkoutUrl,
            ];
        }

        $items = [];
        foreach ($cart->getProducts() as $p) {
            $idPA = (int) $p['id_product_attribute'];
            $idCust = isset($p['id_customization']) ? (int) $p['id_customization'] : 0;

            $variantLabel = '';
            if (!empty($p['attributes'])) {
                $variantLabel = $p['attributes'];
            } elseif (!empty($p['attributes_small'])) {
                $variantLabel = $p['attributes_small'];
            }

            $image = '';
            if (!empty($p['id_image']) && isset($p['link_rewrite'])) {
                $image = $context->link->getImageLink($p['link_rewrite'], $p['id_image'], 'home_default');
            }

            $unit = isset($p['price_wt']) ? (float) $p['price_wt'] : (float) $p['price'];
            $lineTotal = isset($p['total_wt'])
                ? (float) $p['total_wt']
                : ($unit * (int) $p['cart_quantity']);

            $items[] = [
                'key' => (int) $p['id_product'] . ':' . $idPA . ':' . $idCust,
                'productId' => (string) $p['id_product'],
                'variantId' => $idPA > 0 ? (string) $idPA : null,
                'title' => isset($p['name']) ? $p['name'] : '',
                'variantLabel' => $variantLabel,
                'image' => $image,
                'url' => $context->link->getProductLink((int) $p['id_product']),
                'qty' => (int) $p['cart_quantity'],
                'unitPrice' => $this->formatPrice($unit, $context),
                'lineTotal' => $this->formatPrice($lineTotal, $context),
                'removable' => true,
                'maxQty' => (int) StockAvailable::getQuantityAvailableByProduct(
                    (int) $p['id_product'],
                    $idPA ?: null,
                    (int) $context->shop->id
                ),
            ];
        }

        $discount = (float) $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);

        $vouchers = [];
        foreach ($cart->getCartRules() as $cr) {
            $vouchers[] = [
                'id' => isset($cr['id_cart_rule']) ? (int) $cr['id_cart_rule'] : 0,
                'code' => isset($cr['code']) ? $cr['code'] : '',
                'name' => isset($cr['name']) ? $cr['name'] : '',
                'value' => $this->formatPrice(-1 * (float) (isset($cr['value_real']) ? $cr['value_real'] : 0), $context),
            ];
        }

        return [
            'currency' => $currency,
            'count' => (int) $cart->nbProducts(),
            'items' => $items,
            'totals' => [
                'subtotal' => $this->formatPrice((float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), $context),
                'discount' => $discount > 0 ? $this->formatPrice(-1 * $discount, $context) : null,
                'shipping' => $this->formatPrice((float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING), $context),
                'total' => $this->formatPrice((float) $cart->getOrderTotal(true, Cart::BOTH), $context),
            ],
            'freeShipping' => $this->freeShippingInfo($context, $cart),
            'vouchers' => $vouchers,
            'checkoutUrl' => $checkoutUrl,
        ];
    }

    /**
     * Free-shipping progress, based on the shop's PS_SHIPPING_FREE_PRICE threshold.
     * Returns null when no monetary threshold is configured. Amounts are converted
     * to the cart currency and pre-formatted; `progress` is a 0..1 ratio for the bar.
     *
     * @param Context   $context
     * @param Cart|null $cart
     * @return array|null
     */
    private function freeShippingInfo($context, $cart)
    {
        $threshold = (float) Configuration::get('PS_SHIPPING_FREE_PRICE');
        if ($threshold <= 0) {
            return null;
        }
        $thresholdConverted = (float) Tools::convertPrice($threshold, $context->currency);
        $subtotal = ($cart && Validate::isLoadedObject($cart))
            ? (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS)
            : 0.0;
        $remaining = max(0.0, $thresholdConverted - $subtotal);

        return [
            'qualified' => $remaining <= 0,
            'remaining' => $this->formatPrice($remaining, $context),
            'progress' => $thresholdConverted > 0 ? round(min(1, $subtotal / $thresholdConverted), 4) : 1,
        ];
    }

    /**
     * @param Context $context
     * @return array
     */
    private function zeroTotals($context)
    {
        $zero = $this->formatPrice(0, $context);

        return ['subtotal' => $zero, 'discount' => null, 'shipping' => $zero, 'total' => $zero];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Parse an opaque line key "idProduct:idProductAttribute:idCustomization".
     *
     * @param string $key
     * @return array{id_product:int,id_product_attribute:int,id_customization:int}
     */
    private function parseKey($key)
    {
        $parts = explode(':', (string) $key);

        return [
            'id_product' => isset($parts[0]) ? (int) $parts[0] : 0,
            'id_product_attribute' => isset($parts[1]) ? (int) $parts[1] : 0,
            'id_customization' => isset($parts[2]) ? (int) $parts[2] : 0,
        ];
    }

    /**
     * Current quantity of a specific cart line.
     *
     * @param Cart  $cart
     * @param array $k
     * @return int
     */
    private function currentLineQty($cart, $k)
    {
        foreach ($cart->getProducts() as $p) {
            $idCust = isset($p['id_customization']) ? (int) $p['id_customization'] : 0;
            if ((int) $p['id_product'] === $k['id_product']
                && (int) $p['id_product_attribute'] === $k['id_product_attribute']
                && $idCust === $k['id_customization']) {
                return (int) $p['cart_quantity'];
            }
        }

        return 0;
    }

    /**
     * Reject when there isn't enough stock and out-of-stock ordering is denied.
     *
     * @param Context $context
     * @param int     $idProduct
     * @param int     $idProductAttribute
     * @param int     $qty
     * @param Product $product
     * @return void
     */
    private function assertStock($context, $idProduct, $idProductAttribute, $qty, $product)
    {
        $available = (int) StockAvailable::getQuantityAvailableByProduct(
            $idProduct,
            $idProductAttribute > 0 ? $idProductAttribute : null,
            (int) $context->shop->id
        );
        $allowOutOfStock = (bool) Product::isAvailableWhenOutOfStock((int) $product->out_of_stock);
        if ($available < $qty && !$allowOutOfStock) {
            $this->respond(409, ['error' => 'out_of_stock', 'available' => max(0, $available)]);
        }
    }

    /**
     * Format a price for display in a version-safe way (modern Locale API when
     * available, else the legacy Tools helper, else a bare number).
     *
     * @param float   $price
     * @param Context $context
     * @return string
     */
    private function formatPrice($price, $context)
    {
        try {
            if (method_exists($context, 'getCurrentLocale')) {
                $locale = $context->getCurrentLocale();
                if ($locale) {
                    return $locale->formatPrice($price, $context->currency->iso_code);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }
        if (method_exists('Tools', 'displayPrice')) {
            return Tools::displayPrice($price);
        }

        return number_format((float) $price, 2);
    }

    /**
     * Get the session cart, creating and binding one to the cookie if absent.
     *
     * @param Context $context
     * @return Cart
     */
    private function ensureCart($context)
    {
        $cart = $context->cart;
        if (!Validate::isLoadedObject($cart)) {
            $cart = new Cart();
            $cart->id_lang = (int) $context->language->id;
            $cart->id_currency = (int) $context->currency->id;
            $cart->id_customer = (int) $context->customer->id;
            $cart->id_guest = (int) (isset($context->cookie->id_guest) ? $context->cookie->id_guest : 0);
            $cart->id_shop_group = (int) $context->shop->id_shop_group;
            $cart->id_shop = (int) $context->shop->id;
            $cart->add();
            $context->cart = $cart;
            $context->cookie->id_cart = (int) $cart->id;
            $context->cookie->write();
        } elseif (!(int) $cart->id_customer) {
            // Anonymous cart became a customer cart on login — attach it.
            $cart->id_customer = (int) $context->customer->id;
            $cart->update();
        }

        return $cart;
    }

    /**
     * @param int $idProduct
     * @param int $idProductAttribute
     * @return bool
     */
    private function combinationBelongsToProduct($idProduct, $idProductAttribute)
    {
        $sql = 'SELECT 1 FROM ' . _DB_PREFIX_ . 'product_attribute'
            . ' WHERE id_product_attribute = ' . (int) $idProductAttribute
            . ' AND id_product = ' . (int) $idProduct;

        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Emit a JSON response and stop. Avoids ajaxRender/ajaxDie version drift.
     *
     * @param int   $status
     * @param array $payload
     * @return void
     */
    private function respond($status, array $payload)
    {
        if (!headers_sent()) {
            http_response_code((int) $status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($payload);
        exit;
    }
}
