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

declare(strict_types=1);

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles webhook/trigger events for SmartFlow integration
 * Sends events to AI SmartTalk API when specific PrestaShop actions occur
 */
class WebhookHandler
{
    public const TRIGGER_ORDER_STATUS_CHANGED = 'ps_on_order_status_changed';
    public const TRIGGER_PAYMENT_RECEIVED = 'ps_on_payment_received';
    public const TRIGGER_PRODUCT_OUT_OF_STOCK = 'ps_on_product_out_of_stock';
    public const TRIGGER_RETURN_REQUESTED = 'ps_on_return_requested';
    public const TRIGGER_REVIEW_POSTED = 'ps_on_review_posted';
    public const TRIGGER_NEW_ORDER = 'ps_on_new_order';
    public const TRIGGER_CUSTOMER_REGISTERED = 'ps_on_customer_registered';
    public const TRIGGER_CART_UPDATED = 'ps_on_cart_updated';
    public const TRIGGER_REFUND_CREATED = 'ps_on_refund_created';
    public const TRIGGER_PRODUCT_CREATED = 'ps_on_product_created';

    /** @var int Timeout for webhook HTTP requests in seconds */
    private const REQUEST_TIMEOUT = 10;

    /** @var \Context PrestaShop context */
    private $context;

    /**
     * WebhookHandler constructor
     *
     * @param \Context|null $context PrestaShop context (injected dependency)
     */
    public function __construct(?\Context $context = null)
    {
        $this->context = $context ?? \Context::getContext();
    }

    /**
     * Check if webhooks are enabled (at least one trigger is active)
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return count(self::getEnabledTriggers()) > 0;
    }

    /**
     * Check if a specific trigger is enabled
     *
     * @param string $trigger The trigger name
     * @return bool
     */
    public static function isTriggerEnabled(string $trigger): bool
    {
        $enabledTriggers = self::getEnabledTriggers();
        return in_array($trigger, $enabledTriggers, true);
    }

    /**
     * Get list of enabled triggers
     *
     * @return array
     */
    public static function getEnabledTriggers(): array
    {
        $triggers = \Configuration::get('AI_SMART_TALK_WEBHOOKS_TRIGGERS');
        if (empty($triggers)) {
            return [];
        }

        $decoded = json_decode($triggers, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Save enabled triggers configuration
     *
     * @param array $triggers List of trigger names to enable
     * @return bool
     */
    public static function saveEnabledTriggers(array $triggers): bool
    {
        $validTriggers = [
            self::TRIGGER_ORDER_STATUS_CHANGED,
            self::TRIGGER_PAYMENT_RECEIVED,
            self::TRIGGER_PRODUCT_OUT_OF_STOCK,
            self::TRIGGER_RETURN_REQUESTED,
            self::TRIGGER_REVIEW_POSTED,
            self::TRIGGER_NEW_ORDER,
            self::TRIGGER_CUSTOMER_REGISTERED,
            self::TRIGGER_CART_UPDATED,
            self::TRIGGER_REFUND_CREATED,
            self::TRIGGER_PRODUCT_CREATED,
        ];

        $filtered = array_intersect($triggers, $validTriggers);
        return \Configuration::updateValue('AI_SMART_TALK_WEBHOOKS_TRIGGERS', json_encode(array_values($filtered)));
    }

    /**
     * Send a webhook event to AI SmartTalk API
     *
     * @param string $trigger The trigger type
     * @param array $payload The event payload data
     * @return bool Success status
     */
    public function sendWebhook(string $trigger, array $payload): bool
    {
        if (!self::isTriggerEnabled($trigger)) {
            return false;
        }

        $apiUrl = OAuthHandler::getBackendApiUrl();
        $chatModelId = OAuthHandler::getChatModelId() ?? \Configuration::get('CHAT_MODEL_ID');
        $accessToken = OAuthHandler::getAccessToken() ?? \Configuration::get('CHAT_MODEL_TOKEN');

        if (empty($apiUrl) || empty($chatModelId) || empty($accessToken)) {
            return false;
        }

        $webhookUrl = rtrim($apiUrl, '/') . '/api/v1/integrations/webhook';

        $data = [
            'trigger' => $trigger,
            'source' => 'PRESTASHOP',
            'chatModelId' => $chatModelId,
            'timestamp' => date('c'),
            'payload' => $payload,
            'siteIdentifier' => OAuthHandler::getSiteIdentifier(),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'x-chat-model-id: ' . $chatModelId,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk webhook error [' . $trigger . ']: ' . $error,
                3, null, 'WebhookHandler', null, true
            );
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk webhook failed [' . $trigger . ']. HTTP ' . $httpCode . ': ' . substr($response, 0, 500),
                3, null, 'WebhookHandler', null, true
            );
            return false;
        }

        return true;
    }

    /**
     * Trigger: Order status changed
     *
     * @param \Order $order The order
     * @param \OrderState $newOrderState The new order state
     * @param \OrderState|null $oldOrderState The previous order state (if available)
     * @return bool
     */
    public function triggerOrderStatusChanged(\Order $order, \OrderState $newOrderState, ?\OrderState $oldOrderState = null): bool
    {
        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');

        $payload = [
            'order_id' => (int) $order->id,
            'order_reference' => $order->reference,
            'customer_id' => (int) $order->id_customer,
            'customer_email' => $this->getCustomerEmail((int) $order->id_customer),
            'new_status' => [
                'id' => (int) $newOrderState->id,
                'name' => $newOrderState->name[$langId] ?? $newOrderState->name[1] ?? '',
                'color' => $newOrderState->color,
                'paid' => (bool) $newOrderState->paid,
                'shipped' => (bool) $newOrderState->shipped,
                'delivered' => (bool) $newOrderState->delivery,
            ],
            'old_status' => null,
            'total_paid' => (float) $order->total_paid,
            'currency' => $this->getCurrencyIso((int) $order->id_currency),
        ];

        if ($oldOrderState !== null) {
            $payload['old_status'] = [
                'id' => (int) $oldOrderState->id,
                'name' => $oldOrderState->name[$langId] ?? $oldOrderState->name[1] ?? '',
                'color' => $oldOrderState->color,
            ];
        }

        return $this->sendWebhook(self::TRIGGER_ORDER_STATUS_CHANGED, $payload);
    }

    /**
     * Trigger: Payment received
     *
     * @param \Order $order The order
     * @param string $paymentMethod Payment method name
     * @param string $transactionId Transaction ID (if available)
     * @return bool
     */
    public function triggerPaymentReceived(\Order $order, string $paymentMethod = '', string $transactionId = ''): bool
    {
        $payload = [
            'order_id' => (int) $order->id,
            'order_reference' => $order->reference,
            'customer_id' => (int) $order->id_customer,
            'customer_email' => $this->getCustomerEmail((int) $order->id_customer),
            'amount' => (float) $order->total_paid,
            'currency' => $this->getCurrencyIso((int) $order->id_currency),
            'payment_method' => !empty($paymentMethod) ? $paymentMethod : $order->payment,
            'transaction_id' => $transactionId,
            'date_paid' => date('c'),
        ];

        return $this->sendWebhook(self::TRIGGER_PAYMENT_RECEIVED, $payload);
    }

    /**
     * Trigger: Product out of stock
     *
     * @param int $productId Product ID
     * @param int $combinationId Combination ID (0 for simple products)
     * @param int $previousQuantity Previous stock quantity
     * @return bool
     */
    public function triggerProductOutOfStock(int $productId, int $combinationId = 0, int $previousQuantity = 0): bool
    {
        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $product = new \Product($productId, false, $langId);

        if (!\Validate::isLoadedObject($product)) {
            return false;
        }

        $payload = [
            'product_id' => $productId,
            'combination_id' => $combinationId,
            'product_name' => $product->name,
            'product_reference' => $product->reference,
            'previous_quantity' => $previousQuantity,
            'current_quantity' => 0,
            'product_url' => $this->context->link->getProductLink($product),
        ];

        // Add combination details if applicable
        if ($combinationId > 0) {
            $combination = new \Combination($combinationId);
            if (\Validate::isLoadedObject($combination)) {
                $payload['combination_reference'] = $combination->reference;
                $payload['combination_attributes'] = $this->getCombinationAttributeNames($combinationId, $langId);
            }
        }

        return $this->sendWebhook(self::TRIGGER_PRODUCT_OUT_OF_STOCK, $payload);
    }

    /**
     * Trigger: Return requested
     *
     * @param \OrderReturn $orderReturn The order return object
     * @return bool
     */
    public function triggerReturnRequested(\OrderReturn $orderReturn): bool
    {
        $order = new \Order((int) $orderReturn->id_order);
        if (!\Validate::isLoadedObject($order)) {
            return false;
        }

        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');

        // Get return products from order_return_detail → order_detail
        $returnProducts = [];
        $orderReturnDetails = \OrderReturn::getOrdersReturnDetail($orderReturn->id);
        foreach ($orderReturnDetails as $detail) {
            $orderDetail = new \OrderDetail((int) $detail['id_order_detail']);
            $returnProducts[] = [
                'product_id' => (int) $orderDetail->product_id,
                'product_name' => $orderDetail->product_name ?? '',
                'product_reference' => $orderDetail->product_reference ?? '',
                'quantity' => (int) $detail['product_quantity'],
                'unit_price' => (float) $orderDetail->unit_price_tax_incl,
            ];
        }

        // Get return state
        $returnState = new \OrderReturnState((int) $orderReturn->state, $langId);

        $payload = [
            'return_id' => (int) $orderReturn->id,
            'order_id' => (int) $orderReturn->id_order,
            'order_reference' => $order->reference,
            'customer_id' => (int) $orderReturn->id_customer,
            'customer_email' => $this->getCustomerEmail((int) $orderReturn->id_customer),
            'state' => [
                'id' => (int) $orderReturn->state,
                'name' => $returnState->name ?? '',
            ],
            'question' => $orderReturn->question,
            'products' => $returnProducts,
            'date_add' => $orderReturn->date_add,
        ];

        return $this->sendWebhook(self::TRIGGER_RETURN_REQUESTED, $payload);
    }

    /**
     * Trigger: Review posted
     *
     * @param array $commentData Product comment data
     * @return bool
     */
    public function triggerReviewPosted(array $commentData): bool
    {
        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');

        $productId = (int) ($commentData['id_product'] ?? 0);
        $product = new \Product($productId, false, $langId);

        $payload = [
            'product_id' => $productId,
            'product_name' => \Validate::isLoadedObject($product) ? $product->name : '',
            'product_url' => \Validate::isLoadedObject($product) ? $this->context->link->getProductLink($product) : '',
            'customer_id' => (int) ($commentData['id_customer'] ?? 0),
            'customer_name' => $commentData['customer_name'] ?? '',
            'rating' => (int) ($commentData['grade'] ?? 0),
            'title' => $commentData['title'] ?? '',
            'content' => $commentData['content'] ?? '',
            'date_add' => $commentData['date_add'] ?? date('c'),
            'validated' => (bool) ($commentData['validate'] ?? false),
        ];

        return $this->sendWebhook(self::TRIGGER_REVIEW_POSTED, $payload);
    }

    /**
     * Trigger: New order placed
     *
     * @param \Order $order The order
     * @param \Customer $customer The customer
     * @param \OrderState $orderStatus Current order state
     * @return bool
     */
    public function triggerNewOrder(\Order $order, \Customer $customer, ?\OrderState $orderStatus = null): bool
    {
        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $currency = new \Currency((int) $order->id_currency);

        $products = [];
        foreach ($order->getProducts() as $product) {
            $products[] = [
                'product_id' => (int) $product['product_id'],
                'product_name' => $product['product_name'] ?? '',
                'product_reference' => $product['product_reference'] ?? '',
                'quantity' => (int) $product['product_quantity'],
                'unit_price' => (float) $product['unit_price_tax_incl'],
                'total_price' => (float) $product['total_price_tax_incl'],
            ];
        }

        $payload = [
            'order_id' => (int) $order->id,
            'order_reference' => $order->reference,
            'customer_id' => (int) $customer->id,
            'customer_email' => $customer->email,
            'customer_firstname' => $customer->firstname,
            'customer_lastname' => $customer->lastname,
            'total_paid' => (float) $order->total_paid_tax_incl,
            'total_products' => (float) $order->total_products_wt,
            'total_shipping' => (float) $order->total_shipping_tax_incl,
            'currency' => $currency->iso_code ?? '',
            'payment_method' => $order->payment,
            'status' => $orderStatus ? ($orderStatus->name[$langId] ?? $orderStatus->name[1] ?? '') : '',
            'products' => $products,
            'date_add' => $order->date_add,
        ];

        return $this->sendWebhook(self::TRIGGER_NEW_ORDER, $payload);
    }

    /**
     * Trigger: Customer registered
     *
     * @param \Customer $customer The new customer
     * @return bool
     */
    public function triggerCustomerRegistered(\Customer $customer): bool
    {
        $payload = [
            'customer_id' => (int) $customer->id,
            'email' => $customer->email,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'birthday' => $customer->birthday ?? '',
            'newsletter' => (bool) $customer->newsletter,
            'optin' => (bool) $customer->optin,
            'date_add' => $customer->date_add,
        ];

        return $this->sendWebhook(self::TRIGGER_CUSTOMER_REGISTERED, $payload);
    }

    /**
     * Trigger: Cart updated
     *
     * @param \Cart $cart The cart
     * @return bool
     */
    public function triggerCartUpdated(\Cart $cart): bool
    {
        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $products = [];

        foreach ($cart->getProducts(true, false, null, true, true) as $product) {
            $products[] = [
                'product_id' => (int) $product['id_product'],
                'product_name' => $product['name'] ?? '',
                'quantity' => (int) $product['cart_quantity'],
                'unit_price' => (float) ($product['price_wt'] ?? 0),
                'total_price' => (float) ($product['total_wt'] ?? 0),
            ];
        }

        $customer = null;
        if ($cart->id_customer) {
            $customer = new \Customer((int) $cart->id_customer);
        }

        $payload = [
            'cart_id' => (int) $cart->id,
            'customer_id' => (int) $cart->id_customer,
            'customer_email' => ($customer && \Validate::isLoadedObject($customer)) ? $customer->email : '',
            'products' => $products,
            'products_count' => count($products),
            'total' => (float) $cart->getOrderTotal(true),
            'date_upd' => $cart->date_upd,
        ];

        return $this->sendWebhook(self::TRIGGER_CART_UPDATED, $payload);
    }

    /**
     * Trigger: Refund/credit slip created
     *
     * @param \Order $order The order
     * @param array $productList Product refund data
     * @param array $qtyList Quantity list
     * @return bool
     */
    public function triggerRefundCreated(\Order $order, array $productList = [], array $qtyList = []): bool
    {
        $products = [];
        if (!empty($productList)) {
            foreach ($productList as $index => $productRefund) {
                $orderDetailId = is_array($productRefund) ? ($productRefund['id_order_detail'] ?? 0) : $productRefund;
                $quantity = $qtyList[$index] ?? 0;

                if ($orderDetailId) {
                    $orderDetail = new \OrderDetail((int) $orderDetailId);
                    $products[] = [
                        'product_id' => (int) $orderDetail->product_id,
                        'product_name' => $orderDetail->product_name ?? '',
                        'quantity_refunded' => (int) (is_array($productRefund) ? ($productRefund['quantity'] ?? $quantity) : $quantity),
                        'amount_refunded' => (float) (is_array($productRefund) ? ($productRefund['amount'] ?? 0) : 0),
                    ];
                }
            }
        }

        $payload = [
            'order_id' => (int) $order->id,
            'order_reference' => $order->reference,
            'customer_id' => (int) $order->id_customer,
            'customer_email' => $this->getCustomerEmail((int) $order->id_customer),
            'total_paid' => (float) $order->total_paid_tax_incl,
            'currency' => $this->getCurrencyIso((int) $order->id_currency),
            'products' => $products,
            'date_refund' => date('c'),
        ];

        return $this->sendWebhook(self::TRIGGER_REFUND_CREATED, $payload);
    }

    /**
     * Trigger: Product created
     *
     * @param int $productId The new product ID
     * @param \Product|null $product The product object
     * @return bool
     */
    public function triggerProductCreated(int $productId, ?\Product $product = null): bool
    {
        $langId = (int) \Configuration::get('PS_LANG_DEFAULT');

        if (!$product) {
            $product = new \Product($productId, false, $langId);
        }

        if (!\Validate::isLoadedObject($product)) {
            return false;
        }

        $payload = [
            'product_id' => $productId,
            'product_name' => $product->name,
            'product_reference' => $product->reference ?? '',
            'product_description' => strip_tags($product->description[$langId] ?? $product->description ?? ''),
            'product_description_short' => strip_tags($product->description_short[$langId] ?? $product->description_short ?? ''),
            'product_price' => (float) $product->price,
            'product_url' => $this->context->link->getProductLink($product),
            'active' => (bool) $product->active,
            'date_add' => $product->date_add,
        ];

        return $this->sendWebhook(self::TRIGGER_PRODUCT_CREATED, $payload);
    }

    /**
     * Get customer email by ID
     *
     * @param int $customerId
     * @return string
     */
    private function getCustomerEmail(int $customerId): string
    {
        $customer = new \Customer($customerId);
        return \Validate::isLoadedObject($customer) ? $customer->email : '';
    }

    /**
     * Get currency ISO code by ID
     *
     * @param int $currencyId
     * @return string
     */
    private function getCurrencyIso(int $currencyId): string
    {
        $currency = new \Currency($currencyId);
        return \Validate::isLoadedObject($currency) ? $currency->iso_code : '';
    }

    /**
     * Get combination attribute names
     *
     * @param int $combinationId
     * @param int $langId
     * @return string
     */
    private function getCombinationAttributeNames(int $combinationId, int $langId): string
    {
        $combination = new \Combination($combinationId);
        if (!\Validate::isLoadedObject($combination)) {
            return '';
        }

        $attributes = $combination->getAttributesName($langId);
        $names = [];
        foreach ($attributes as $attr) {
            $names[] = $attr['name'];
        }

        return implode(', ', $names);
    }

    /**
     * Get all available triggers with descriptions
     *
     * @return array
     */
    public static function getAvailableTriggers(): array
    {
        return [
            self::TRIGGER_ORDER_STATUS_CHANGED => [
                'name' => 'Order Status Changed',
                'description' => 'Triggered when an order status is updated',
                'payload_fields' => ['order_id', 'order_reference', 'customer_id', 'customer_email', 'new_status', 'old_status', 'total_paid', 'currency'],
            ],
            self::TRIGGER_PAYMENT_RECEIVED => [
                'name' => 'Payment Received',
                'description' => 'Triggered when a payment is confirmed',
                'payload_fields' => ['order_id', 'order_reference', 'customer_id', 'customer_email', 'amount', 'currency', 'payment_method', 'transaction_id'],
            ],
            self::TRIGGER_PRODUCT_OUT_OF_STOCK => [
                'name' => 'Product Out of Stock',
                'description' => 'Triggered when a product stock reaches zero',
                'payload_fields' => ['product_id', 'product_name', 'product_reference', 'previous_quantity', 'current_quantity', 'product_url'],
            ],
            self::TRIGGER_RETURN_REQUESTED => [
                'name' => 'Return Requested',
                'description' => 'Triggered when a customer requests a product return',
                'payload_fields' => ['return_id', 'order_id', 'order_reference', 'customer_id', 'customer_email', 'state', 'question', 'products'],
            ],
            self::TRIGGER_REVIEW_POSTED => [
                'name' => 'Review Posted',
                'description' => 'Triggered when a customer posts a product review',
                'payload_fields' => ['product_id', 'product_name', 'customer_id', 'customer_name', 'rating', 'title', 'content'],
            ],
            self::TRIGGER_NEW_ORDER => [
                'name' => 'New Order',
                'description' => 'Triggered when a new order is placed',
                'payload_fields' => ['order_id', 'order_reference', 'customer_id', 'customer_email', 'customer_firstname', 'customer_lastname', 'total_paid', 'currency', 'payment_method', 'products'],
            ],
            self::TRIGGER_CUSTOMER_REGISTERED => [
                'name' => 'Customer Registered',
                'description' => 'Triggered when a new customer creates an account',
                'payload_fields' => ['customer_id', 'email', 'firstname', 'lastname', 'birthday', 'newsletter'],
            ],
            self::TRIGGER_CART_UPDATED => [
                'name' => 'Cart Updated',
                'description' => 'Triggered when a cart is created or modified',
                'payload_fields' => ['cart_id', 'customer_id', 'customer_email', 'products', 'products_count', 'total'],
            ],
            self::TRIGGER_REFUND_CREATED => [
                'name' => 'Refund Created',
                'description' => 'Triggered when a credit slip / refund is created',
                'payload_fields' => ['order_id', 'order_reference', 'customer_id', 'customer_email', 'total_paid', 'currency', 'products'],
            ],
            self::TRIGGER_PRODUCT_CREATED => [
                'name' => 'Product Created',
                'description' => 'Triggered when a new product is added to the catalog',
                'payload_fields' => ['product_id', 'product_name', 'product_reference', 'product_description', 'product_price', 'product_url'],
            ],
        ];
    }
}
