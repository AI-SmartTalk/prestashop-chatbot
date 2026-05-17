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
 * Computes a product (or combination) price with full discount metadata.
 *
 * Why two calls to Product::getPriceStatic ?
 *   - PrestaShop natively supports CUMULATIVE discounts (specific_price + group
 *     reduction + catalog reduction). The single "$specific_price_output" we
 *     could capture only reflects ONE of them. To know the true catalog price
 *     before ALL discounts, the only reliable path is calling getPriceStatic
 *     a second time with $usereduc = false.
 *   - Performance impact: PS caches getPriceStatic internally per
 *     (id_product, id_pa, id_shop, id_group, …). On a 1000-product sync the
 *     second call hits the cache for everything but the first product. Cheap.
 *
 * Why we expose discountType :
 *   - "percentage" / "amount" come from ps_specific_price.reduction_type when a
 *     single specific_price drives the discount → admin UI / LLM can render
 *     the source faithfully.
 *   - "computed" means several reductions stacked → we only know the net %.
 *     Front should treat it as a plain "-N%" badge.
 */
class PriceCalculator
{
    /** Discounts smaller than this in the shop's currency unit are ignored. */
    const DISCOUNT_EPSILON = 0.0001;

    /**
     * Calculate a complete PriceInfo for a product (or one of its combinations).
     *
     * Passing $idProductAttribute = 0 calculates for the parent / default combination.
     *
     * @param int $idProduct
     * @param int $idProductAttribute  0 for the parent product
     * @param int $priceDecimals       Precision used by Product::getPriceStatic for rounding
     * @return PriceInfo
     */
    public static function calculate(int $idProduct, int $idProductAttribute = 0, int $priceDecimals = 2): PriceInfo
    {
        $idPa = $idProductAttribute > 0 ? $idProductAttribute : null;

        // 1) Final price WITH all reductions applied. We also capture
        //    $specificPriceOutput to know if a single specific_price drove the price.
        $specificPriceOutput = null;
        $finalPrice = (float) \Product::getPriceStatic(
            $idProduct,
            true,                  // usetax (TTC)
            $idPa,
            $priceDecimals,
            null,                  // divisor
            false,                 // only_reduc
            true,                  // usereduc — APPLY all discounts
            1,                     // quantity
            false,                 // force_associated_tax
            null,                  // id_customer
            null,                  // id_cart
            null,                  // id_address
            $specificPriceOutput,  // OUT
            true,                  // with_ecotax
            true,                  // use_group_reduction
            null                   // context
        );

        // 2) Catalog price WITHOUT reductions. This is the "barred" price the
        //    customer sees crossed out on a promotion card.
        $unused = null;
        $originalPrice = (float) \Product::getPriceStatic(
            $idProduct,
            true,
            $idPa,
            $priceDecimals,
            null,
            false,
            false,                 // usereduc = false — NO reductions
            1,
            false,
            null, null, null,
            $unused,
            true,
            true,
            null
        );

        $diff = $originalPrice - $finalPrice;
        $hasDiscount = $diff > self::DISCOUNT_EPSILON;

        if (!$hasDiscount) {
            return new PriceInfo($finalPrice, $finalPrice, 0.0, 0, false, 'none');
        }

        $discountPercent = $originalPrice > 0
            ? (int) round(100 * $diff / $originalPrice)
            : 0;

        return new PriceInfo(
            $finalPrice,
            $originalPrice,
            $diff,
            $discountPercent,
            true,
            self::resolveDiscountType($specificPriceOutput)
        );
    }

    /**
     * Map PrestaShop's specific_price output to a stable discountType label.
     *
     * Returns 'computed' when:
     *  - no specific_price drove the price (group / catalog reductions only)
     *  - several reductions stack and we can't attribute the net to a single rule
     */
    private static function resolveDiscountType($specificPriceOutput): string
    {
        if (!is_array($specificPriceOutput) || empty($specificPriceOutput)) {
            return 'computed';
        }

        $type = isset($specificPriceOutput['reduction_type'])
            ? (string) $specificPriceOutput['reduction_type']
            : '';

        if ($type === 'percentage' || $type === 'amount') {
            return $type;
        }

        return 'computed';
    }
}
