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
 * Same-origin AJAX endpoint that adds a product to the CURRENT logged-in
 * customer's cart, driven from the chatbot widget's product cards.
 *
 * Why a front controller: the AI SmartTalk widget UI runs in a cross-origin
 * iframe and cannot carry the storefront cookie. The widget's parent loader
 * (injected same-origin on the storefront) POSTs here, so PrestaShop hydrates
 * the customer/cart from the session cookie just like any storefront request.
 *
 * Version-safe across PrestaShop 1.6 → 9: relies only on the stable
 * Cart::updateQty / Product::getDefaultAttribute / StockAvailable APIs and
 * emits JSON directly (no ajaxRender/ajaxDie version differences).
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

        // 1) Hard gate — only a logged-in customer may add to their own cart.
        if (!isset($context->customer) || !$context->customer->isLogged()) {
            $this->respond(401, ['error' => 'not_logged_in']);
        }

        // 2) CSRF — token bound to this customer.
        $token = (string) Tools::getValue('token');
        if (!ChatbotSettingsBuilder::isCartTokenValid($token, (int) $context->customer->id)) {
            $this->respond(403, ['error' => 'invalid_token']);
        }

        // 3) Inputs.
        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $qty = (int) Tools::getValue('qty', 1);
        $qty = max(1, min(100, $qty));
        if ($idProduct <= 0) {
            $this->respond(400, ['error' => 'invalid_product']);
        }

        // 4) Product must exist and be active.
        $product = new Product($idProduct, false, (int) $context->language->id);
        if (!Validate::isLoadedObject($product) || !$product->active) {
            $this->respond(404, ['error' => 'product_not_found']);
        }

        // 5) Resolve the combination: take the default when the product has
        //    combinations and none (or an invalid one) was provided.
        if ($idProductAttribute > 0) {
            if (!$this->combinationBelongsToProduct($idProduct, $idProductAttribute)) {
                $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
            }
        } else {
            $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
        }

        // 6) Stock pre-check honouring the out-of-stock policy.
        $available = (int) StockAvailable::getQuantityAvailableByProduct(
            $idProduct,
            $idProductAttribute > 0 ? $idProductAttribute : null,
            (int) $context->shop->id
        );
        $allowOutOfStock = (bool) Product::isAvailableWhenOutOfStock((int) $product->out_of_stock);
        if ($available < $qty && !$allowOutOfStock) {
            $this->respond(409, ['error' => 'out_of_stock', 'available' => max(0, $available)]);
        }

        // 7) Ensure a persisted cart bound to the session.
        $cart = $this->ensureCart($context);

        // 8) Add the line (stable signature 1.6 → 9: stop at $operator).
        $added = $cart->updateQty($qty, $idProduct, $idProductAttribute > 0 ? $idProductAttribute : 0, false, 'up');
        if ($added === false) {
            $this->respond(409, ['error' => 'cannot_add']);
        }

        // 9) Confirm + lightweight summary for the widget toast.
        $this->respond(200, [
            'ok' => true,
            'cartCount' => (int) $cart->nbProducts(),
            'cartTotal' => $this->formatPrice((float) $cart->getOrderTotal(true, Cart::BOTH), $context),
            'cartUrl' => $context->link->getPageLink('cart', true, null, ['action' => 'show']),
        ]);
    }

    /**
     * Format a price for display in a version-safe way (the modern Locale API
     * when available, else the legacy Tools helper, else a bare number).
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
