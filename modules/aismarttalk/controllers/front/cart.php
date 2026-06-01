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
 * Cart Front Controller
 *
 * Two roles:
 *   - POST (server-to-server, HMAC-signed): the AI SmartTalk backend builds the
 *     shopper's cart (add / get / checkoutUrl) using native PrestaShop classes.
 *   - GET ?action=restore (browser, capability token): loads the prepared cart into
 *     the shopper's session and forwards to the native checkout for payment.
 *
 * @author    AI SmartTalk <contact@aismarttalk.tech>
 * @copyright 2026 AI SmartTalk
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'aismarttalk/vendor/autoload.php';

use PrestaShop\AiSmartTalk\CartGuard;
use PrestaShop\AiSmartTalk\CartService;

class AismarttalkCartModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = false;
    public $guestAllowed = true;

    public function initContent()
    {
        // Browser-facing capability link: restore the prepared cart, then checkout.
        if (Tools::getValue('action') === 'restore') {
            $this->handleRestore();

            return;
        }

        // Server-to-server JSON API (HMAC-signed).
        $this->handleApi();
    }

    /**
     * Handle the signed JSON API (add / get / checkoutUrl).
     */
    private function handleApi(): void
    {
        $rawBody = (string) file_get_contents('php://input');

        $auth = CartGuard::verifySignature($rawBody);
        if (!$auth['ok']) {
            $this->respond(401, ['success' => false, 'error' => $auth['error']]);

            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->respond(400, ['success' => false, 'error' => 'Invalid JSON body.']);

            return;
        }

        $action = isset($payload['action']) ? (string) $payload['action'] : '';
        $customerId = isset($payload['customerId']) ? (int) $payload['customerId'] : 0;
        $email = isset($payload['email']) ? (string) $payload['email'] : null;
        $cartId = isset($payload['cartId']) ? (int) $payload['cartId'] : null;
        $langIso = isset($payload['langIso']) ? (string) $payload['langIso'] : null;

        try {
            $resolvedCustomerId = CartService::resolveCustomerId($customerId, $email);
            $cart = CartService::getOrCreateCart($this->context, $resolvedCustomerId, $cartId, $langIso);

            $warnings = [];

            if ($action === 'add') {
                $products = isset($payload['products']) && is_array($payload['products'])
                    ? $payload['products']
                    : [];
                if (empty($products)) {
                    $this->respond(400, ['success' => false, 'error' => 'No products provided.']);

                    return;
                }
                $warnings = CartService::addProducts($cart, $products);
            } elseif ($action !== 'get' && $action !== 'checkoutUrl') {
                $this->respond(400, ['success' => false, 'error' => 'Unknown action: ' . $action]);

                return;
            }

            $snapshot = CartService::snapshot($cart);

            // A successful add that put nothing in an empty cart is an error.
            if ($action === 'add' && $snapshot['itemCount'] === 0) {
                $this->respond(400, [
                    'success' => false,
                    'error' => !empty($warnings) ? implode('; ', $warnings) : 'No product could be added to the cart.',
                ]);

                return;
            }

            $checkoutUrl = null;
            if ($action === 'checkoutUrl') {
                if ($snapshot['itemCount'] === 0) {
                    $this->respond(400, ['success' => false, 'error' => 'The cart is empty.']);

                    return;
                }
                $checkoutUrl = CartService::buildCheckoutUrl($cart);
            }

            $response = array_merge($snapshot, [
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
                'warnings' => $warnings,
                'message' => $this->buildMessage($action, $snapshot, $checkoutUrl, $warnings),
            ]);

            $this->respond(200, $response);
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'AI SmartTalk cart error: ' . $e->getMessage(),
                3,
                null,
                'AiSmartTalk',
                null,
                true
            );
            $this->respond(500, ['success' => false, 'error' => 'Cart operation failed.']);
        }
    }

    /**
     * Restore a prepared cart into the shopper's session and forward to checkout.
     */
    private function handleRestore(): void
    {
        $cartId = (int) Tools::getValue('cartId');
        $timestamp = (int) Tools::getValue('ts');
        $token = (string) Tools::getValue('t');

        if (!$cartId || !CartGuard::verifyRestoreToken($cartId, $timestamp, $token)) {
            Tools::redirect($this->context->link->getPageLink('index'));

            return;
        }

        $cart = new Cart($cartId);
        if (!Validate::isLoadedObject($cart) || $cart->orderExists()) {
            Tools::redirect($this->context->link->getPageLink('index'));

            return;
        }

        // Load the prepared cart into the current session so the native checkout uses it.
        $this->context->cookie->id_cart = (int) $cart->id;
        $this->context->cookie->write();

        Tools::redirect($this->context->link->getPageLink('order', true, (int) $cart->id_lang));
    }

    /**
     * Build a concise, shopper-facing message the assistant can relay.
     *
     * @param array<string, mixed> $snapshot
     * @param string[]             $warnings
     */
    private function buildMessage(string $action, array $snapshot, ?string $checkoutUrl, array $warnings): string
    {
        $count = (int) $snapshot['itemCount'];
        $total = (string) $snapshot['total'];

        if ($action === 'checkoutUrl') {
            $message = sprintf('Your cart has %d item(s) for a total of %s.', $count, $total);
            if ($checkoutUrl) {
                $message .= ' Complete your order here: ' . $checkoutUrl;
            }

            return $message;
        }

        if ($action === 'add') {
            $message = sprintf('Added to your cart. You now have %d item(s) for a total of %s.', $count, $total);
        } else {
            $message = sprintf('Your cart has %d item(s) for a total of %s.', $count, $total);
        }

        if (!empty($warnings)) {
            $message .= ' Note: ' . implode('; ', $warnings) . '.';
        }

        return $message;
    }

    /**
     * Emit a JSON response and stop — never render the storefront template.
     *
     * @param array<string, mixed> $data
     */
    private function respond(int $status, array $data): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
            header('Cache-Control: no-store');
        }
        echo json_encode($data);
        exit;
    }
}
