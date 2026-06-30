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

    private function cleanProducts()
    {
        $hasSpecificIds = is_array($this->productIds) && !empty($this->productIds);
        $productIds = $hasSpecificIds ? $this->productIds : $this->fetchAllProductIds();

        // Canonical sync stores each combination as its own document with
        // externalId "<product>::<combination>" (see the backend
        // VARIANT_ID_SEPARATOR). Expand the product ids so cleanup targets the
        // variant documents too — otherwise removing a product would orphan its
        // variant documents.
        $externalIds = $this->expandWithVariantIds($productIds);

        $client = ApiClient::fromConfig();

        // Unified product endpoint shared by every connector (source in body).
        $response = $client->post('/api/v1/products/cleanup', [
            'source' => 'prestashop',
            'siteIdentifier' => $client->getSiteIdentifier(),
            // delete-ids: purge the listed products; keep-only: full snapshot,
            // delete everything NOT in the list. Mirrors the previous semantics.
            'mode' => $hasSpecificIds ? 'delete-ids' : 'keep-only',
            'externalIds' => $externalIds,
        ]);

        if (!$response->isSuccess()) {
            MultistoreHelper::updateConfig('CLEAN_PRODUCT_DOCUMENTS_ERROR', $response->error ?: 'HTTP ' . $response->httpCode);
        } elseif ($response->get('status') === 'error') {
            MultistoreHelper::updateConfig('CLEAN_PRODUCT_DOCUMENTS_ERROR', $response->get('message'));
        } else {
            MultistoreHelper::deleteConfig('CLEAN_PRODUCT_DOCUMENTS_ERROR');
        }
    }

    /**
     * Expand a list of product ids to also include every combination's
     * canonical externalId ("<product>::<id_product_attribute>"), so cleanup
     * reaches both the parent product and its variant documents.
     *
     * @param string[] $productIds
     * @return string[]
     */
    private function expandWithVariantIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $productIds));

        $rows = \Db::getInstance()->executeS(
            'SELECT id_product, id_product_attribute
             FROM ' . _DB_PREFIX_ . 'product_attribute
             WHERE id_product IN (' . $idList . ')'
        );

        $externalIds = array_map('strval', $productIds);
        foreach ($rows ?: [] as $row) {
            // Keep in sync with the backend VARIANT_ID_SEPARATOR ("::").
            $externalIds[] = (string) $row['id_product'] . '::' . (string) $row['id_product_attribute'];
        }

        return array_values(array_unique($externalIds));
    }
}
