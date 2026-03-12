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

    private function fetchAllProductIds()
    {
        $sql = 'SELECT id_product FROM ' . _DB_PREFIX_ . 'product WHERE active = 1';
        $products = \Db::getInstance()->executeS($sql);

        return array_map(function ($product) {
            return (string) $product['id_product'];
        }, $products);
    }

    private function cleanProducts()
    {
        $productIds = $this->productIds ? $this->productIds : $this->fetchAllProductIds();

        $client = ApiClient::fromConfig();

        $response = $client->post('/api/document/clean', [
            'productIds' => $productIds,
            'chatModelId' => $client->getChatModelId(),
            'chatModelToken' => $client->getAccessToken(),
            'deleteFromIds' => [] !== $this->productIds ? true : false,
            'source' => 'PRESTASHOP',
            'siteIdentifier' => $client->getSiteIdentifier(),
        ]);

        if (!$response->isSuccess()) {
            \Configuration::updateValue('CLEAN_PRODUCT_DOCUMENTS_ERROR', $response->error ?: 'HTTP ' . $response->httpCode);
        } elseif ($response->get('status') === 'error') {
            \Configuration::updateValue('CLEAN_PRODUCT_DOCUMENTS_ERROR', $response->get('message'));
        } else {
            \Configuration::deleteByName('CLEAN_PRODUCT_DOCUMENTS_ERROR');
        }
    }
}
