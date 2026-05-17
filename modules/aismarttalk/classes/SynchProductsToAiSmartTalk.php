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
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $defaultShopId = MultistoreHelper::getDefaultShopId();

        // Get default currency information
        $defaultCurrencyId = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCurrency = new \Currency($defaultCurrencyId);
        $currencySign = $defaultCurrency->sign ?: '€';
        // Decimal precision for this currency (3 for LYD/BHD, 2 for EUR/USD, 0 for JPY, ...).
        // Without this, json_encode strips trailing zeros and prices like "12.000 LYD" become "12 LYD".
        $priceDecimals = PriceFormatter::decimalsFromCurrency($defaultCurrency);

        $documentDatas = [];
        $synchronizedProductIds = [];
        foreach ($products as $product) {
            $bestShopId = (int) ($product['best_shop_id'] ?: $defaultShopId);
            $psProduct = new \Product($product['id_product'], false, $defaultLangId, $bestShopId);
            $productUrl = $link->getProductLink($psProduct, null, null, null, $defaultLangId, $bestShopId);

            $linkRewrite = is_array($psProduct->link_rewrite)
                ? ($psProduct->link_rewrite[$defaultLangId] ?? reset($psProduct->link_rewrite))
                : ($psProduct->link_rewrite ?: '');

            $imageUrl = null;
            if (!empty($product['id_image'])) {
                $imageUrl = $this->getContext()->link->getImageLink($linkRewrite, $product['id_image']);
            }

            // Resolve final + original price in one shot. PriceCalculator owns
            // the double-call to getPriceStatic and the discount math so the
            // logic stays identical between parent products and combinations.
            $priceInfo = PriceCalculator::calculate(
                (int) $product['id_product'],
                0,
                $priceDecimals
            );

            // Keep the legacy has_special_price flag — driven by SQL detection
            // of an active ps_specific_price row. Combined with priceInfo it
            // also catches group/catalog reductions that don't sit in specific_price.
            $hasSpecialPrice = !empty($product['specific_price'])
                || !empty($product['price_reduction'])
                || $priceInfo->hasDiscount;

            // Format dates
            $priceFrom = !empty($product['price_from']) && $product['price_from'] !== '0000-00-00 00:00:00' ? $product['price_from'] : null;
            $priceTo = !empty($product['price_to']) && $product['price_to'] !== '0000-00-00 00:00:00' ? $product['price_to'] : null;

            // Variants are only fetched for products that have combinations.
            // Empty array for simple products keeps the payload shape stable for the backend.
            $variants = CombinationHelper::getVariants(
                (int) $product['id_product'],
                $defaultLangId,
                $bestShopId,
                $priceDecimals,
                $link,
                $linkRewrite
            );

            $documentDatas[] = [
                'id' => $product['id_product'],
                'title' => $product['name'],
                'description' => strip_tags($product['description']),
                'description_short' => strip_tags($product['description_short']),
                'reference' => $product['reference'],
                'price' => PriceFormatter::format($priceInfo->finalPrice, $priceDecimals),
                'price_decimals' => $priceDecimals,
                'currency' => $product['currency_code'] ?? 'EUR',
                'currency_sign' => $currencySign,
                // Promotion fields — present only when the product is actually discounted.
                // Backend / LLM / front all treat their absence as "no promo".
                'original_price' => $priceInfo->hasDiscount
                    ? PriceFormatter::format($priceInfo->originalPrice, $priceDecimals)
                    : null,
                'discount_percent' => $priceInfo->hasDiscount ? $priceInfo->discountPercent : null,
                'discount_amount' => $priceInfo->hasDiscount
                    ? PriceFormatter::format($priceInfo->discountAmount, $priceDecimals)
                    : null,
                'discount_type' => $priceInfo->hasDiscount ? $priceInfo->discountType : null,
                'has_special_price' => $hasSpecialPrice,
                'price_from' => $priceFrom,
                'price_to' => $priceTo,
                'url' => $productUrl,
                'image_url' => $imageUrl,
                'variants' => $variants,
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

    /**
     * Push a batch of products to the canonical v1 sync endpoint.
     *
     * Why this routes through `/api/v1/integrations/prestashop/sync`:
     *  - the legacy `/api/document/source` accepts an `any[]` payload with no
     *    schema enforcement — every plugin sends a different shape, the server
     *    duck-types fields, and rejections come back as silent 200s. The v1
     *    endpoint validates each document against the canonical
     *    `ProductDocument` schema and returns a `{accepted, rejected[]}`
     *    report so we can surface granular failures in the merchant admin;
     *  - authentication moves to standard `Authorization: Bearer` + the
     *    `x-aismarttalk-plugin` telemetry header (handled by ApiClient), so
     *    the body no longer carries `chatModelId` / `chatModelToken`.
     *
     * Backward-compat note: the legacy endpoint stays alive on the backend.
     * Old plugin versions deployed at merchants continue to function
     * unchanged — only the merchants who upgrade to this module version
     * benefit from the v1 pipeline.
     */
    private function postToApi($documentDatas)
    {
        $client = ApiClient::fromConfig();

        // Translate the legacy snake_case payload into the canonical shape
        // expected by the v1 endpoint. `ProductDocumentBuilder` owns every
        // field mapping (incl. promo block from DEV-842), so this class can
        // stay focused on orchestrating PrestaShop data sourcing.
        $canonical = [];
        foreach ($documentDatas as $legacy) {
            try {
                $canonical[] = ProductDocumentBuilder::build($legacy);
            } catch (\InvalidArgumentException $e) {
                // A product with no id should never reach this point — guard
                // against it so a single bad row never blocks the whole batch.
                \PrestaShopLogger::addLog(
                    'AI SmartTalk sync: skipped malformed product — ' . $e->getMessage(),
                    2
                );
            }
        }

        if ($canonical === []) {
            return $this->lastResponseBody ?: '{"status":"ok","accepted":0}';
        }

        $envelope = ProductDocumentBuilder::buildSyncEnvelope(
            $canonical,
            $client->getSiteIdentifier()
        );

        $response = $client->post('/api/v1/integrations/prestashop/sync', $envelope);

        if (!$response->isSuccess()) {
            MultistoreHelper::updateConfig(
                'AI_SMART_TALK_ERROR',
                $response->error ?: 'HTTP ' . $response->httpCode
            );
            return false;
        }

        MultistoreHelper::deleteConfig('AI_SMART_TALK_ERROR');

        if ($response->get('status') === 'error') {
            MultistoreHelper::updateConfig('AI_SMART_TALK_ERROR', $response->get('message'));
            return false;
        }

        // Surface per-document rejections in the PrestaShop logs so a buggy
        // product is visible to the merchant team without having to inspect
        // the backend dashboards. We don't fail the batch — `partial` is a
        // valid outcome on the v1 endpoint and the other documents succeeded.
        $rejected = $response->get('rejected');
        if (is_array($rejected) && $rejected !== []) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk sync: ' . count($rejected) . ' documents rejected by API. First: '
                . json_encode($rejected[0]),
                2
            );
        }

        return $response->body;
    }

    /**
     * Kept so tests asserting against a previous response body keep working
     * across refactors. Not part of the public contract.
     *
     * @var string|null
     */
    private $lastResponseBody = null;

    /**
     * Get products to synchronize across ALL shops (union, deduplicated).
     * Product data (name, price, image, URL) prefers the default shop, with fallback to any shop.
     * A product is included if it is active + in stock in at least one shop.
     */
    private function getProductsToSynchronize()
    {
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $defaultShopId = MultistoreHelper::getDefaultShopId();
        $defaultCurrencyId = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $allShopIds = MultistoreHelper::getAllShopIds();
        $shopIdList = implode(',', array_map('intval', $allShopIds));

        // Build a stock-availability subquery: product is in stock in at least one shop,
        // either at the parent level (id_product_attribute = 0) OR via any of its combinations.
        // Excluding combination rows here would mark stores that sell only via declinations
        // (e.g. ALL products are variants of a parent) as fully out-of-stock.
        $stockExistsConditions = [];
        foreach ($allShopIds as $sid) {
            $shopGroupId = (int) \Shop::getGroupFromShop($sid);
            $stockExistsConditions[] = 'EXISTS (
                SELECT 1 FROM ' . _DB_PREFIX_ . 'stock_available sa_check
                WHERE sa_check.id_product = p.id_product
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

        // For product_lang, category_lang, image_shop: prefer default shop, fallback to any shop with data
        $plShopSubquery = '(SELECT pl2.id_shop FROM ' . _DB_PREFIX_ . 'product_lang pl2
            WHERE pl2.id_product = p.id_product AND pl2.id_lang = ' . $defaultLangId . '
            AND pl2.name IS NOT NULL AND pl2.name != ""
            ORDER BY (pl2.id_shop = ' . $defaultShopId . ') DESC, pl2.id_shop ASC
            LIMIT 1)';

        $clShopSubquery = '(SELECT cl2.id_shop FROM ' . _DB_PREFIX_ . 'category_lang cl2
            WHERE cl2.id_category = p.id_category_default AND cl2.id_lang = ' . $defaultLangId . '
            ORDER BY (cl2.id_shop = ' . $defaultShopId . ') DESC, cl2.id_shop ASC
            LIMIT 1)';

        $imsShopSubquery = '(SELECT ims2.id_shop FROM ' . _DB_PREFIX_ . 'image_shop ims2
            WHERE ims2.id_product = p.id_product AND ims2.cover = 1
            ORDER BY (ims2.id_shop = ' . $defaultShopId . ') DESC, ims2.id_shop ASC
            LIMIT 1)';

        $sql = 'SELECT p.id_product, pl.name, pl.description, pl.description_short,
                   p.reference, p.price, cl.link_rewrite, i.id_image,
                   p.active,
                   p.available_date,
                   pl.id_shop as best_shop_id,
                   c.iso_code as currency_code,
                   sp.price as specific_price,
                   sp.from as price_from,
                   sp.to as price_to,
                   sp.reduction as price_reduction,
                   sp.reduction_type
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON p.id_product = pl.id_product AND pl.id_lang = ' . $defaultLangId . '
                AND pl.id_shop = ' . $plShopSubquery . '
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl
                ON p.id_category_default = cl.id_category AND cl.id_lang = ' . $defaultLangId . '
                AND cl.id_shop = ' . $clShopSubquery . '
            LEFT JOIN ' . _DB_PREFIX_ . 'image_shop ims
                ON p.id_product = ims.id_product AND ims.cover = 1
                AND ims.id_shop = ' . $imsShopSubquery . '
            LEFT JOIN ' . _DB_PREFIX_ . 'image i ON i.id_image = ims.id_image
            LEFT JOIN ' . _DB_PREFIX_ . 'currency c ON c.id_currency = ' . $defaultCurrencyId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'specific_price sp ON sp.id_specific_price = (
                SELECT sp2.id_specific_price
                FROM ' . _DB_PREFIX_ . 'specific_price sp2
                WHERE sp2.id_product = p.id_product
                    AND (sp2.from = "0000-00-00 00:00:00" OR sp2.from <= NOW())
                    AND (sp2.to = "0000-00-00 00:00:00" OR sp2.to >= NOW())
                    AND (sp2.id_shop IN (' . $shopIdList . ') OR sp2.id_shop = 0)
                ORDER BY (sp2.id_shop = ' . $defaultShopId . ') DESC, sp2.id_shop DESC, sp2.id_specific_price DESC
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
