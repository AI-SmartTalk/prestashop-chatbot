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

class CleanProductDocuments
{
    private $productIds;

    public function __invoke($args = [])
    {
        foreach ($args as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }
        $this->cleanProducts();
    }

    /**
     * Fetch all active product IDs across all shops (deduplicated).
     */
    private function fetchAllProductIds()
    {
        $allShopIds = MultistoreHelper::getAllShopIds();
        $shopIdList = implode(',', array_map('intval', $allShopIds));

        $sql = 'SELECT DISTINCT ps.id_product
                FROM ' . _DB_PREFIX_ . 'product_shop ps
                WHERE ps.active = 1
                    AND ps.id_shop IN (' . $shopIdList . ')';
        $products = \Db::getInstance()->executeS($sql);

        return array_map(function ($product) {
            return (string) $product['id_product'];
        }, $products ?: []);
    }

    /**
     * Map the legacy `deleteFromIds` semantics to the v1 cleanup modes:
     *  - `delete-ids` deletes exactly the externalIds passed in (incremental
     *    cleanup hooked from PrestaShop's product delete event);
     *  - `keep-only` is authoritative: deletes the documents whose externalId
     *    is NOT in the list (a full diff against the merchant's live
     *    catalogue).
     *
     * The v1 endpoint refuses an empty `keep-only` list unless `?force=true`
     * is passed — a safety net against accidental catalogue-wide wipes during
     * maintenance windows where queries briefly return zero. The merchant
     * explicitly running cleanup on a literally empty catalogue is the only
     * legitimate empty case, so we surface force only there.
     */
    private function cleanProducts()
    {
        $hasSpecificIds = is_array($this->productIds) && !empty($this->productIds);
        $productIds = $hasSpecificIds
            ? array_values(array_map('strval', $this->productIds))
            : $this->fetchAllProductIds();

        $client = ApiClient::fromConfig();
        $siteIdentifier = $client->getSiteIdentifier();

        $mode = $hasSpecificIds ? 'delete-ids' : 'keep-only';
        $force = (!$hasSpecificIds && $productIds === []);

        $envelope = [
            'source' => 'PRESTASHOP',
            'mode' => $mode,
            'externalIds' => $productIds,
        ];
        if ($siteIdentifier !== null && $siteIdentifier !== '') {
            $envelope['siteIdentifier'] = $siteIdentifier;
        }

        $path = '/api/v1/integrations/prestashop/cleanup' . ($force ? '?force=true' : '');
        $response = $client->post($path, $envelope);

        if (!$response->isSuccess()) {
            MultistoreHelper::updateConfig(
                'CLEAN_PRODUCT_DOCUMENTS_ERROR',
                $response->error ?: 'HTTP ' . $response->httpCode
            );
        } elseif ($response->get('status') === 'error') {
            MultistoreHelper::updateConfig(
                'CLEAN_PRODUCT_DOCUMENTS_ERROR',
                $response->get('message')
            );
        } else {
            MultistoreHelper::deleteConfig('CLEAN_PRODUCT_DOCUMENTS_ERROR');
        }
    }
}
