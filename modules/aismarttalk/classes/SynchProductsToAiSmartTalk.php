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

class SynchProductsToAiSmartTalk
{
    private $forceSync = false;
    private $productIds = [];

    /** @var \Context */
    private $context;

    /**
     * @param \Context $context PrestaShop context (injected dependency)
     */
    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    public function __invoke($args = [])
    {
        foreach ($args as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }

        // Si c'est une re-synchronisation forcée, nettoyer d'abord les produits en rupture de stock
        if ($this->forceSync === true && empty($this->productIds)) {
            $this->cleanOutOfStockProducts();
        }

        return $this->sendProductsToApi();
    }

    /**
     * Get the injected context
     *
     * @return \Context
     *
     * @throws \RuntimeException if context was not injected
     */
    private function getContext()
    {
        if ($this->context === null) {
            throw new \RuntimeException('Context must be injected via constructor');
        }

        return $this->context;
    }

    /**
     * @return int|false Number of synced products on success, false on error
     */
    private function sendProductsToApi()
    {
        $products = $this->getProductsToSynchronize();

        if (empty($products)) {
            return 0;
        }

        $link = $this->getContext()->link;

        // Get default currency information
        $defaultCurrencyId = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCurrency = new \Currency($defaultCurrencyId);
        $currencySign = $defaultCurrency->sign ?: '€';

        $documentDatas = [];
        $synchronizedProductIds = [];
        foreach ($products as $product) {
            $psProduct = new \Product($product['id_product']);
            $productUrl = $link->getProductLink($psProduct);

            $imageUrl = null;
            if (!empty($product['id_image'])) {
                $defaultLangId = \Configuration::get('PS_LANG_DEFAULT');
                $imageUrl = $this->getContext()->link->getImageLink($psProduct->link_rewrite[$defaultLangId], $product['id_image']);
            }

            // Calculate final price considering specific prices (promotions)
            $finalPrice = $psProduct->getPrice();
            $hasSpecialPrice = !empty($product['specific_price']) || !empty($product['price_reduction']);

            // Format dates
            $priceFrom = !empty($product['price_from']) && $product['price_from'] !== '0000-00-00 00:00:00' ? $product['price_from'] : null;
            $priceTo = !empty($product['price_to']) && $product['price_to'] !== '0000-00-00 00:00:00' ? $product['price_to'] : null;

            $documentDatas[] = [
                'id' => $product['id_product'],
                'title' => $product['name'],
                'description' => strip_tags($product['description']),
                'description_short' => strip_tags($product['description_short']),
                'reference' => $product['reference'],
                'price' => $finalPrice,
                'currency' => $product['currency_code'] ?? 'EUR',
                'currency_sign' => $currencySign,
                'has_special_price' => $hasSpecialPrice,
                'price_from' => $priceFrom,
                'price_to' => $priceTo,
                'url' => $productUrl,
                'image_url' => $imageUrl,
            ];

            if (count($documentDatas) === 10) {
                if (!$this->postIfDataExists($documentDatas)) {
                    return false;
                }

                $documentDatas = [];
            }

            $synchronizedProductIds[] = $product['id_product'];
        }

        if (!$this->postIfDataExists($documentDatas)) {
            return false;
        }

        if (count($synchronizedProductIds) > 0) {
            $this->markProductsAsSynchronized($synchronizedProductIds);
        }

        return count($synchronizedProductIds);
    }

    private function postIfDataExists($documentDatas)
    {
        if (count($documentDatas) > 0 && false === $this->postToApi($documentDatas)) {
            return false;
        }

        return true;
    }

    public function markProductsAsSynchronized($productIds)
    {
        $idShop = (int) $this->getContext()->shop->id;
        return AiSmartTalkProductSync::markProductsAsSynced($productIds, $idShop);
    }

    private function postToApi($documentDatas)
    {
        $client = ApiClient::fromConfig();

        $response = $client->post('/api/document/source', [
            'documentDatas' => $documentDatas,
            'chatModelId' => $client->getChatModelId(),
            'chatModelToken' => $client->getAccessToken(),
            'source' => 'PRESTASHOP',
            'siteIdentifier' => $client->getSiteIdentifier(),
        ]);

        if (!$response->isSuccess()) {
            MultistoreHelper::updateConfig('AI_SMART_TALK_ERROR', $response->error ?: 'HTTP ' . $response->httpCode);
            return false;
        }

        MultistoreHelper::deleteConfig('AI_SMART_TALK_ERROR');

        if ($response->get('status') === 'error') {
            MultistoreHelper::updateConfig('AI_SMART_TALK_ERROR', $response->get('message'));
            return false;
        }

        return $response->body;
    }

    /**
     * Get products to synchronize across ALL shops (union, deduplicated).
     * Product data (name, price, image, URL) comes from the default shop context.
     * A product is included if it is active + in stock in at least one shop.
     */
    private function getProductsToSynchronize()
    {
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $defaultShopId = MultistoreHelper::getDefaultShopId();
        $defaultCurrencyId = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $allShopIds = MultistoreHelper::getAllShopIds();
        $shopIdList = implode(',', array_map('intval', $allShopIds));

        // Build a stock-availability subquery: product is in stock in at least one shop
        $stockExistsConditions = [];
        foreach ($allShopIds as $sid) {
            $shopGroupId = (int) \Shop::getGroupFromShop($sid);
            $stockExistsConditions[] = 'EXISTS (
                SELECT 1 FROM ' . _DB_PREFIX_ . 'stock_available sa_check
                WHERE sa_check.id_product = p.id_product
                    AND sa_check.id_product_attribute = 0
                    AND (sa_check.id_shop = ' . (int) $sid . '
                         OR (sa_check.id_shop = 0 AND sa_check.id_shop_group = ' . $shopGroupId . '))
                    AND sa_check.quantity > 0
            )';
        }
        $stockCondition = '(' . implode(' OR ', $stockExistsConditions) . ')';

        // Use EXISTS instead of JOIN to avoid row multiplication (strict GROUP BY compatible)
        $activeInAnyShop = 'EXISTS (
            SELECT 1 FROM ' . _DB_PREFIX_ . 'product_shop ps_any
            WHERE ps_any.id_product = p.id_product
                AND ps_any.id_shop IN (' . $shopIdList . ')
                AND ps_any.active = 1
        )';

        $sql = 'SELECT p.id_product, pl.name, pl.description, pl.description_short,
                   p.reference, p.price, cl.link_rewrite, i.id_image,
                   p.active,
                   p.available_date,
                   c.iso_code as currency_code,
                   sp.price as specific_price,
                   sp.from as price_from,
                   sp.to as price_to,
                   sp.reduction as price_reduction,
                   sp.reduction_type
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $defaultLangId . '
                AND pl.id_shop = ' . $defaultShopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl
                ON p.id_category_default = cl.id_category AND cl.id_lang = ' . $defaultLangId . '
                AND cl.id_shop = ' . $defaultShopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'image_shop ims
                ON p.id_product = ims.id_product AND ims.cover = 1
                AND ims.id_shop = ' . $defaultShopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'image i ON i.id_image = ims.id_image
            LEFT JOIN ' . _DB_PREFIX_ . 'currency c ON c.id_currency = ' . $defaultCurrencyId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'specific_price sp ON sp.id_specific_price = (
                SELECT sp2.id_specific_price
                FROM ' . _DB_PREFIX_ . 'specific_price sp2
                WHERE sp2.id_product = p.id_product
                    AND (sp2.from = "0000-00-00 00:00:00" OR sp2.from <= NOW())
                    AND (sp2.to = "0000-00-00 00:00:00" OR sp2.to >= NOW())
                    AND (sp2.id_shop = ' . $defaultShopId . ' OR sp2.id_shop = 0)
                ORDER BY sp2.id_shop DESC, sp2.id_specific_price DESC
                LIMIT 1
            )
            LEFT JOIN ' . _DB_PREFIX_ . 'aismarttalk_product_sync aps ON p.id_product = aps.id_product
                AND aps.id_shop = ' . $defaultShopId . '
            WHERE pl.name IS NOT NULL
                AND ' . $activeInAnyShop . '
                AND ' . $stockCondition;

        // If not forcing sync, only get products that are not yet synced
        if ($this->forceSync === false) {
            $sql .= ' AND (aps.synced IS NULL OR aps.synced = 0)';
        }

        // Filter by specific product IDs if provided
        if (!empty($this->productIds)) {
            $safeIds = array_map('intval', $this->productIds);
            $sql .= ' AND p.id_product IN (' . implode(',', $safeIds) . ')';
        }

        // Apply category filters (global config)
        $categoryFilter = SyncFilterHelper::buildCategoryFilterSQL($defaultShopId);
        if (!empty($categoryFilter)) {
            $sql .= $categoryFilter;
        }

        $sql .= ' GROUP BY p.id_product';

        $products = \Db::getInstance()->executeS($sql);

        return $products ?: [];
    }

    /**
     * Nettoie les produits hors stock ou inactifs d'AI SmartTalk lors d'une re-synchronisation.
     * Un produit n'est nettoyé que s'il est inactif/hors stock dans TOUS les shops.
     */
    private function cleanOutOfStockProducts()
    {
        $defaultShopId = MultistoreHelper::getDefaultShopId();

        // Get all products currently synced
        $sql = 'SELECT DISTINCT aps.id_product
                FROM ' . _DB_PREFIX_ . 'aismarttalk_product_sync aps
                WHERE aps.synced = 1';
        $syncedProducts = \Db::getInstance()->executeS($sql);

        if (empty($syncedProducts)) {
            return;
        }

        $toClean = [];
        foreach ($syncedProducts as $row) {
            $idProduct = (int) $row['id_product'];
            // Product should be cleaned if not active+in stock in any shop
            if (!MultistoreHelper::isProductActiveInAnyShop($idProduct)) {
                $toClean[] = $idProduct;
            }
        }

        if (!empty($toClean)) {
            $cleanProductDocuments = new CleanProductDocuments();
            $cleanProductDocuments(['productIds' => array_map('strval', $toClean)]);

            // Mark as not synced in all shops
            foreach (MultistoreHelper::getAllShopIds() as $shopId) {
                AiSmartTalkProductSync::markProductsAsNotSynced($toClean, $shopId);
            }
        }
    }
}
