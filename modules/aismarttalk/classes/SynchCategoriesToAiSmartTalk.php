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
 * Up-front category-tree sync.
 *
 * Posts the shop's full category tree to the backend BEFORE products are synced,
 * so every product can reference real Category ids and be auto-attached during
 * ingestion. The tree is emitted as canonical CategoryRef objects
 * (`{externalId, name, parentExternalId}`); the backend upserts them
 * idempotently and rebuilds the hierarchy from the parent pointers.
 *
 * Endpoint: POST {apiUrl}/api/v1/categories/sync — same Bearer + x-chat-model-id
 * auth as the product sync (via ApiClient). Re-running is safe (idempotent).
 */
class SynchCategoriesToAiSmartTalk
{
    /** Backend cap: at most 5000 categories per request. */
    const BATCH_SIZE = 5000;

    /** @var \Context */
    private $context;

    /**
     * @param \Context $context PrestaShop context (injected dependency)
     */
    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    /**
     * Sync the whole category tree.
     *
     * @return int|false Number of categories sent on success, false on error.
     */
    public function __invoke()
    {
        $categories = $this->getCategoryRefs();
        if (empty($categories)) {
            return 0;
        }

        foreach (array_chunk($categories, self::BATCH_SIZE) as $batch) {
            if (!$this->postBatch($batch)) {
                return false;
            }
        }

        return count($categories);
    }

    /**
     * Build the canonical CategoryRef[] for every active category across all
     * shops (deduplicated). Excludes the virtual Root (id 1) and maps a parent
     * pointing at Root to `null` so the tree is anchored on the real shop roots.
     *
     * @return array<int,array{externalId:string,name:string,parentExternalId:?string}>
     */
    private function getCategoryRefs(): array
    {
        $defaultLangId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $defaultShopId = MultistoreHelper::getDefaultShopId();
        $allShopIds = MultistoreHelper::getAllShopIds();
        $shopIdList = implode(',', array_map('intval', $allShopIds));

        // Name with default-shop preference and fallback to any shop holding a
        // translation (strict GROUP BY compatible; matches SyncFilterHelper).
        $nameSubquery = '(SELECT cl_sub.name FROM ' . _DB_PREFIX_ . 'category_lang cl_sub
                          WHERE cl_sub.id_category = c.id_category AND cl_sub.id_lang = ' . $defaultLangId . '
                          ORDER BY (cl_sub.id_shop = ' . $defaultShopId . ') DESC, cl_sub.id_shop ASC
                          LIMIT 1)';

        $sql = 'SELECT c.id_category, c.id_parent, ' . $nameSubquery . ' AS name
                FROM ' . _DB_PREFIX_ . 'category c
                WHERE c.active = 1
                    AND c.id_category > 1
                    AND EXISTS (
                        SELECT 1 FROM ' . _DB_PREFIX_ . 'category_shop cs
                        WHERE cs.id_category = c.id_category AND cs.id_shop IN (' . $shopIdList . ')
                    )
                    AND ' . $nameSubquery . ' IS NOT NULL
                ORDER BY c.level_depth ASC';

        $rows = \Db::getInstance()->executeS($sql);
        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return CanonicalProductMapper::rowsToCategoryRefs($rows);
    }

    /**
     * POST one batch of CategoryRefs to the backend sync endpoint.
     *
     * @param array $categories
     * @return bool True on success (200/202), false on error.
     */
    private function postBatch(array $categories): bool
    {
        $client = ApiClient::fromConfig();

        $response = $client->post('/api/v1/categories/sync', [
            'payloadVersion' => '1',
            'categories' => array_values($categories),
        ]);

        if (!$response->isSuccess()) {
            MultistoreHelper::updateConfig(
                'AI_SMART_TALK_ERROR',
                $response->error ?: 'HTTP ' . $response->httpCode . ' on category sync'
            );
            return false;
        }

        if ($response->get('status') === 'error') {
            MultistoreHelper::updateConfig('AI_SMART_TALK_ERROR', $response->get('message'));
            return false;
        }

        return true;
    }
}
