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
 * Reference implementation of the unified "stock" block of the AI SmartTalk
 * product document standard (see docs/PRODUCT_DOCUMENT_SCHEMA.md).
 *
 * Every commerce connector (PrestaShop, WooCommerce, Shopify, Joomla) must emit
 * the exact same shape so the search tool and the LLM can reason about stock the
 * same way regardless of the source platform:
 *
 *   "stock": {
 *       "status":       "in_stock" | "out_of_stock",
 *       "quantity":     int|null,        // null when the platform doesn't track it
 *       "restock_date": "YYYY-MM-DD"|null
 *   }
 *
 * The whole point of isolating the normalization here is that it carries NO
 * PrestaShop dependency — feed it a raw quantity and a raw availability date and
 * it returns the standardized block. That keeps the contract unit-testable and
 * trivial to port to the other connectors.
 */
class StockStatusHelper
{
    const STATUS_IN_STOCK = 'in_stock';
    const STATUS_OUT_OF_STOCK = 'out_of_stock';

    /**
     * MySQL/zero-date sentinels that PrestaShop stores when no availability date
     * is set. They must normalize to null, never to a bogus "0000-..." string.
     */
    const EMPTY_DATES = ['', '0000-00-00', '0000-00-00 00:00:00'];

    /**
     * Build the standardized stock block from a raw quantity and an optional
     * raw availability/restock date.
     *
     * Rules (identical across every connector):
     *  - status is driven solely by quantity: > 0 => in_stock, otherwise out_of_stock.
     *  - restock_date is only meaningful when the product is out of stock; for an
     *    in-stock product it is always null (there is nothing to "restock").
     *  - restock_date is normalized to a bare ISO-8601 day (YYYY-MM-DD) or null.
     *
     * @param int|float|string|null $quantity     Total available quantity (any numeric form).
     * @param string|null           $availableDate Raw availability date from the platform.
     *
     * @return array{status: string, quantity: int, restock_date: string|null}
     */
    public static function normalize($quantity, $availableDate = null): array
    {
        $quantity = (int) $quantity;
        $inStock = $quantity > 0;

        return [
            'status' => $inStock ? self::STATUS_IN_STOCK : self::STATUS_OUT_OF_STOCK,
            'quantity' => $quantity,
            // A restock date only makes sense while the product is unavailable.
            'restock_date' => $inStock ? null : self::normalizeDate($availableDate),
        ];
    }

    /**
     * Normalize a raw platform date to a bare ISO-8601 day (YYYY-MM-DD) or null.
     *
     * Returns null for empty values, zero-dates, or anything unparseable so the
     * payload never carries a misleading "0000-00-00" or a malformed string.
     *
     * @param string|null $rawDate
     * @return string|null
     */
    public static function normalizeDate($rawDate): ?string
    {
        if ($rawDate === null) {
            return null;
        }

        $rawDate = trim((string) $rawDate);

        if (in_array($rawDate, self::EMPTY_DATES, true)) {
            return null;
        }

        // Keep only the date part of a "YYYY-MM-DD HH:MM:SS" datetime.
        $datePart = substr($rawDate, 0, 10);

        // Validate it is a real calendar date; reject anything else.
        $parts = explode('-', $datePart);
        if (count($parts) !== 3) {
            return null;
        }

        list($year, $month, $day) = array_map('intval', $parts);
        if (!checkdate($month, $day, $year) || $year <= 0) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
