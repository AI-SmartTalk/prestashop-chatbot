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
 * Immutable value object describing the price of a product or combination,
 * including any discount information.
 *
 * Plain PHP-7.2-compatible class (no readonly props, no constructor promotion).
 */
class PriceInfo
{
    /** @var float Final price the customer actually pays, with reductions applied. */
    public $finalPrice;

    /** @var float Catalog price before any reduction. Equal to finalPrice when no discount. */
    public $originalPrice;

    /** @var float Absolute discount amount (originalPrice - finalPrice). 0 when no discount. */
    public $discountAmount;

    /**
     * Discount as an integer percentage (1-99). 0 when no discount.
     * Rounded half-away-from-zero — what the customer expects to see on a "-N%" badge.
     */
    public $discountPercent;

    /** @var bool True if originalPrice > finalPrice by more than a rounding margin. */
    public $hasDiscount;

    /**
     * @var string Source of the discount:
     *  - 'percentage'  : ps_specific_price.reduction_type = percentage
     *  - 'amount'      : ps_specific_price.reduction_type = amount
     *  - 'computed'    : combined group + catalog + multiple specific_prices, no single source
     *  - 'none'        : hasDiscount = false
     */
    public $discountType;

    public function __construct(
        float $finalPrice,
        float $originalPrice,
        float $discountAmount,
        int $discountPercent,
        bool $hasDiscount,
        string $discountType
    ) {
        $this->finalPrice = $finalPrice;
        $this->originalPrice = $originalPrice;
        $this->discountAmount = $discountAmount;
        $this->discountPercent = $discountPercent;
        $this->hasDiscount = $hasDiscount;
        $this->discountType = $discountType;
    }
}
