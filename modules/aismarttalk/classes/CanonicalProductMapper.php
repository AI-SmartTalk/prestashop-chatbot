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
 * Maps PrestaShop product data into the AI SmartTalk canonical document
 * contract (v1) — the single, cross-platform shape every connector emits so the
 * backend ingests and the LLM reasons identically regardless of source.
 *
 * Key contract points enforced here:
 *  - prices are `Money { amount:int (minor units / cents), currency, display }`,
 *    so the backend can range-filter and sort exactly. The float price is
 *    converted to integer minor units via the currency's decimal precision;
 *  - field names are camelCase and the set is closed: the backend validates
 *    with a `.strict()` Zod schema, so emitting an unknown key would reject the
 *    whole document. Optional fields are omitted, not sent as noise;
 *  - PrestaShop combinations map to canonical `variants[]`; the backend expands
 *    each into an independently-filterable product document.
 *
 * The mapper reuses PriceCalculator, PriceFormatter, StockStatusHelper and
 * CombinationHelper — it adds no pricing/stock logic of its own.
 */
class CanonicalProductMapper
{
    /**
     * Convert a float/numeric amount to a canonical Money object, or null.
     *
     * @param mixed       $amount
     * @param int         $decimals Currency precision (2 = cents, 3 = milli-units…).
     * @param string      $currency ISO-4217 code.
     * @param string|null $sign     Optional currency sign for the display string.
     * @return array{amount:int,currency:string,display:string,decimals:int}|null
     */
    public static function toMoney($amount, int $decimals, string $currency, $sign = null): ?array
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            return null;
        }

        $minorUnits = (int) round(((float) $amount) * pow(10, $decimals));
        $display = PriceFormatter::format($amount, $decimals);
        if ($sign !== null && $sign !== '') {
            $display .= ' ' . $sign;
        }

        return [
            'amount' => $minorUnits,
            'currency' => strtoupper(trim($currency)),
            'display' => $display,
            // Currency precision so the backend can format minor units back to a
            // localized display without re-deriving it (EUR=2, JPY=0, LYD=3…).
            'decimals' => $decimals,
        ];
    }

    /**
     * Map a raw quantity to the canonical availability enum.
     */
    public static function availability(int $quantity): string
    {
        return $quantity > 0
            ? StockStatusHelper::STATUS_IN_STOCK
            : StockStatusHelper::STATUS_OUT_OF_STOCK;
    }

    /**
     * Convert legacy `{group,value}` attributes to canonical `{name,value}`.
     *
     * @param array $legacy
     * @return array<int,array{name:string,value:string}>
     */
    public static function mapAttributes(array $legacy): array
    {
        $out = [];
        foreach ($legacy as $attr) {
            $name = isset($attr['group']) ? trim((string) $attr['group']) : '';
            $value = isset($attr['value']) ? trim((string) $attr['value']) : '';
            if ($name !== '' && $value !== '') {
                $out[] = ['name' => $name, 'value' => $value];
            }
        }

        return $out;
    }

    /**
     * The canonical contract only accepts "percentage" | "amount" | null. The
     * PrestaShop "computed" / "none" labels collapse to null.
     */
    public static function discountType($legacy): ?string
    {
        return ($legacy === 'percentage' || $legacy === 'amount') ? $legacy : null;
    }

    /**
     * Map one legacy combination payload (from CombinationHelper) to a canonical
     * ProductVariant. Null/empty optional fields are dropped so the strict
     * backend schema accepts the object.
     *
     * @param array       $variant
     * @param int         $decimals
     * @param string      $currency
     * @param string|null $sign
     * @return array
     */
    public static function mapVariant(array $variant, int $decimals, string $currency, $sign): array
    {
        $quantity = isset($variant['quantity']) ? (int) $variant['quantity'] : 0;
        $hasDiscount = !empty($variant['original_price']);

        $mapped = [
            'externalId' => (string) ($variant['id_product_attribute'] ?? ''),
            'sku' => self::nullIfEmpty($variant['reference'] ?? null),
            'gtin' => self::nullIfEmpty($variant['ean13'] ?? null) ?: self::nullIfEmpty($variant['upc'] ?? null),
            'price' => self::toMoney($variant['price'] ?? null, $decimals, $currency, $sign),
            'image' => self::nullIfEmpty($variant['image_url'] ?? null),
            'attributes' => self::mapAttributes($variant['attributes'] ?? []),
            'availability' => self::availability($quantity),
            'quantity' => $quantity,
        ];

        if ($hasDiscount) {
            $mapped['originalPrice'] = self::toMoney($variant['original_price'], $decimals, $currency, $sign);
            $mapped['discountPercent'] = isset($variant['discount_percent']) ? (int) $variant['discount_percent'] : null;
            $mapped['discountAmount'] = self::toMoney($variant['discount_amount'] ?? null, $decimals, $currency, $sign);
            $mapped['discountType'] = self::discountType($variant['discount_type'] ?? null);
        }

        // Drop null optionals (externalId/attributes/availability/quantity stay).
        return array_filter($mapped, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Build the canonical product document.
     *
     * @param array $args {
     *   idProduct:int, name:string, description:?string, descriptionShort:?string,
     *   reference:?string, brand:?string, decimals:int, currency:string, sign:?string,
     *   url:?string, imageUrl:?string, quantity:int, availableDate:?string,
     *   categories:string[], variants:array (legacy CombinationHelper output),
     *   priceInfo:PriceInfo
     * }
     * @return array
     */
    public static function map(array $args): array
    {
        /** @var PriceInfo $priceInfo */
        $priceInfo = $args['priceInfo'];
        $decimals = (int) $args['decimals'];
        $currency = (string) $args['currency'];
        $sign = $args['sign'] ?? null;
        $quantity = (int) $args['quantity'];

        $doc = [
            'type' => 'product',
            'externalId' => (string) $args['idProduct'],
            'title' => (string) $args['name'],
            'description' => self::nullIfEmpty($args['description'] ?? null),
            'descriptionShort' => self::nullIfEmpty($args['descriptionShort'] ?? null),
            'reference' => self::nullIfEmpty($args['reference'] ?? null),
            'brand' => self::nullIfEmpty($args['brand'] ?? null),
            'price' => self::toMoney($priceInfo->finalPrice, $decimals, $currency, $sign),
            'availability' => self::availability($quantity),
            'quantity' => $quantity,
            'restockDate' => $quantity > 0
                ? null
                : StockStatusHelper::normalizeDate($args['availableDate'] ?? null),
            // Canonical CategoryRef[] objects (externalId + name + parentExternalId)
            // so the backend can upsert the Category entities, resolve the hierarchy
            // and auto-attach the product. See self::productCategories().
            'categories' => self::filterCategoryRefs($args['categories'] ?? []),
            'url' => self::nullIfEmpty($args['url'] ?? null),
            'image' => self::nullIfEmpty($args['imageUrl'] ?? null),
            'variants' => array_map(
                function ($variant) use ($decimals, $currency, $sign) {
                    return self::mapVariant($variant, $decimals, $currency, $sign);
                },
                $args['variants'] ?? []
            ),
        ];

        if ($priceInfo->hasDiscount) {
            $doc['originalPrice'] = self::toMoney($priceInfo->originalPrice, $decimals, $currency, $sign);
            $doc['discountPercent'] = (int) $priceInfo->discountPercent;
            $doc['discountAmount'] = self::toMoney($priceInfo->discountAmount, $decimals, $currency, $sign);
            $doc['discountType'] = self::discountType($priceInfo->discountType);
        }

        return $doc;
    }

    /**
     * Fetch the product's categories as canonical CategoryRef objects
     * (`{externalId, name, parentExternalId}`) so the backend can upsert the
     * Category entities, rebuild the hierarchy and auto-attach the product.
     *
     * The PrestaShop virtual Root category (id 1) is excluded — it carries no
     * merchandising meaning. The real shop root (e.g. "Home") is kept and emitted
     * with `parentExternalId = null` so it anchors the tree. One query per product
     * — acceptable for an offline sync.
     *
     * @param int $idProduct
     * @param int $idLang
     * @return array<int,array{externalId:string,name:string,parentExternalId:?string}>
     */
    public static function productCategories(int $idProduct, int $idLang): array
    {
        $sql = 'SELECT DISTINCT c.id_category, c.id_parent, cl.name
                FROM ' . _DB_PREFIX_ . 'category_product cp
                INNER JOIN ' . _DB_PREFIX_ . 'category c
                    ON c.id_category = cp.id_category
                INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl
                    ON cl.id_category = cp.id_category AND cl.id_lang = ' . (int) $idLang . '
                WHERE cp.id_product = ' . (int) $idProduct . '
                    AND c.id_category > 1
                    AND cl.name IS NOT NULL AND cl.name != ""';

        $rows = \Db::getInstance()->executeS($sql);
        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return self::rowsToCategoryRefs($rows);
    }

    /**
     * Convert raw `{id_category, id_parent, name}` rows to canonical CategoryRefs.
     * A parent pointing at the virtual Root (id 1) becomes `null` so the category
     * anchors the tree instead of referencing a category we never emit.
     *
     * @param array $rows
     * @return array<int,array{externalId:string,name:string,parentExternalId:?string}>
     */
    public static function rowsToCategoryRefs(array $rows): array
    {
        $refs = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id_category'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id <= 1 || $name === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $parentId = (int) ($row['id_parent'] ?? 0);
            $refs[] = [
                'externalId' => (string) $id,
                'name' => $name,
                'parentExternalId' => $parentId > 1 ? (string) $parentId : null,
            ];
        }

        return array_values($refs);
    }

    /**
     * Normalize a caller-provided categories value into canonical CategoryRefs.
     * Accepts the object form (already a CategoryRef) and tolerates legacy bare
     * name strings so older call-sites keep working.
     *
     * @param array $categories
     * @return array<int,array>
     */
    private static function filterCategoryRefs(array $categories): array
    {
        $out = [];
        foreach ($categories as $c) {
            if (is_array($c) && isset($c['name']) && trim((string) $c['name']) !== '') {
                $out[] = $c;
            } elseif (is_string($c) && trim($c) !== '') {
                $out[] = ['name' => trim($c)];
            }
        }

        return array_values($out);
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private static function nullIfEmpty($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
