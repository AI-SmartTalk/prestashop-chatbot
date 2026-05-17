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
 * Builds variant payloads for products that have combinations (declinations).
 *
 * A combination is a specific variant of a product (e.g. "Color: Red / Size: M")
 * with its own SKU, price impact, stock, and optionally its own image. Stores like
 * the Libyan client where ALL products are sold as combinations require this data
 * to be synchronized — the parent product alone is meaningless (no stock, generic price).
 */
class CombinationHelper
{
    /**
     * Returns true if a product has at least one combination row.
     *
     * Uses raw SQL instead of \Product::hasAttributes() so it works in test contexts
     * without the full PrestaShop class layer.
     *
     * @param int $idProduct
     * @return bool
     */
    public static function hasCombinations(int $idProduct): bool
    {
        // No trailing LIMIT 1 — Db::getValue() ultimately calls getRow() which
        // unconditionally appends "LIMIT 1" itself. A double LIMIT throws
        // "syntax error near 'LIMIT 1'" on MySQL.
        $sql = 'SELECT 1 FROM ' . _DB_PREFIX_ . 'product_attribute
                WHERE id_product = ' . (int) $idProduct;

        return (bool) \Db::getInstance()->getValue($sql);
    }

    /**
     * Build the full variant array for a product (one entry per combination).
     *
     * Each entry includes:
     *   - id_product_attribute, reference, ean13, upc
     *   - price (final, formatted as STRING with currency precision)
     *   - quantity (in the resolved shop)
     *   - attributes [ ['group' => 'Color', 'value' => 'Red'], ... ]
     *   - image_url when the combination has its own image
     *
     * Returns [] when the product has no combinations — callers should treat
     * a missing/empty array as "simple product, use parent price".
     *
     * @param int                 $idProduct
     * @param int                 $idLang
     * @param int                 $idShop
     * @param int                 $priceDecimals  Currency decimal precision
     * @param \Link|null          $link           PrestaShop link helper (for image URLs)
     * @param string|array|null   $linkRewrite    Parent product link_rewrite (used as image fallback name)
     * @return array
     */
    public static function getVariants(
        int $idProduct,
        int $idLang,
        int $idShop,
        int $priceDecimals,
        $link = null,
        $linkRewrite = null
    ): array {
        if (!self::hasCombinations($idProduct)) {
            return [];
        }

        // \Product is a PrestaShop core class; constructor signature has been stable since 1.7.
        $product = new \Product($idProduct, false, $idLang, $idShop);

        // getAttributeCombinations returns ONE ROW PER ATTRIBUTE PER COMBINATION:
        // the same id_product_attribute appears once for "Color", once for "Size", etc.
        // We group by id_product_attribute and collect the attributes into a list.
        $rows = $product->getAttributeCombinations($idLang);
        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $variants = [];
        foreach ($rows as $row) {
            $idPa = (int) ($row['id_product_attribute'] ?? 0);
            if ($idPa <= 0) {
                continue;
            }

            if (!isset($variants[$idPa])) {
                $variants[$idPa] = self::buildVariantSkeleton(
                    $idProduct,
                    $idPa,
                    $idShop,
                    $priceDecimals,
                    $row,
                    $link,
                    $linkRewrite
                );
            }

            $groupName = isset($row['group_name']) ? (string) $row['group_name'] : '';
            $attrName = isset($row['attribute_name']) ? (string) $row['attribute_name'] : '';

            if ($attrName !== '') {
                $variants[$idPa]['attributes'][] = [
                    'group' => $groupName,
                    'value' => $attrName,
                ];
            }
        }

        return array_values($variants);
    }

    /**
     * Compute the skeleton for one variant (everything that doesn't depend on the per-attribute row).
     *
     * @param int               $idProduct
     * @param int               $idProductAttribute
     * @param int               $idShop
     * @param int               $priceDecimals
     * @param array             $row           First row from getAttributeCombinations for this combination
     * @param \Link|null        $link
     * @param string|array|null $linkRewrite
     * @return array
     */
    private static function buildVariantSkeleton(
        int $idProduct,
        int $idProductAttribute,
        int $idShop,
        int $priceDecimals,
        array $row,
        $link,
        $linkRewrite
    ): array {
        // Reuse the same PriceCalculator as parent products so a variant's
        // discount semantics (specific_price tied to id_product_attribute,
        // group reduction, etc.) are calculated identically.
        $priceInfo = PriceCalculator::calculate($idProduct, $idProductAttribute, $priceDecimals);

        $quantity = (int) \StockAvailable::getQuantityAvailableByProduct(
            $idProduct,
            $idProductAttribute,
            $idShop
        );

        $variantImageUrl = self::resolveVariantImageUrl($idProductAttribute, $link, $linkRewrite);

        return [
            'id_product_attribute' => $idProductAttribute,
            'reference' => isset($row['reference']) ? (string) $row['reference'] : '',
            'ean13' => isset($row['ean13']) ? (string) $row['ean13'] : '',
            'upc' => isset($row['upc']) ? (string) $row['upc'] : '',
            'price' => PriceFormatter::format($priceInfo->finalPrice, $priceDecimals),
            // Promotion fields are null when the variant is not discounted, mirroring
            // the parent product shape so the backend treats them uniformly.
            'original_price' => $priceInfo->hasDiscount
                ? PriceFormatter::format($priceInfo->originalPrice, $priceDecimals)
                : null,
            'discount_percent' => $priceInfo->hasDiscount ? $priceInfo->discountPercent : null,
            'discount_amount' => $priceInfo->hasDiscount
                ? PriceFormatter::format($priceInfo->discountAmount, $priceDecimals)
                : null,
            'discount_type' => $priceInfo->hasDiscount ? $priceInfo->discountType : null,
            'quantity' => $quantity,
            'in_stock' => $quantity > 0,
            'attributes' => [],
            'image_url' => $variantImageUrl,
        ];
    }

    /**
     * Resolve the cover image URL for a specific combination, if any.
     * Returns null when no per-combination image is set (caller falls back to parent image).
     *
     * @param int               $idProductAttribute
     * @param \Link|null        $link
     * @param string|array|null $linkRewrite
     * @return string|null
     */
    private static function resolveVariantImageUrl(int $idProductAttribute, $link, $linkRewrite): ?string
    {
        if ($link === null) {
            return null;
        }

        // No trailing LIMIT 1 — Db::getValue() appends one via getRow().
        $sql = 'SELECT id_image FROM ' . _DB_PREFIX_ . 'product_attribute_image
                WHERE id_product_attribute = ' . (int) $idProductAttribute . '
                ORDER BY id_image ASC';

        $imageId = \Db::getInstance()->getValue($sql);
        if (empty($imageId)) {
            return null;
        }

        $linkRewriteStr = '';
        if (is_array($linkRewrite)) {
            $linkRewriteStr = (string) reset($linkRewrite);
        } elseif ($linkRewrite !== null) {
            $linkRewriteStr = (string) $linkRewrite;
        }

        return $link->getImageLink($linkRewriteStr, (int) $imageId);
    }
}
