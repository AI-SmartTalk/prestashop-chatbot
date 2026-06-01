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

declare(strict_types=1);

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Builds the canonical `ProductDocument` payload (as defined by the AI SmartTalk
 * v1 ingestion API — `https://aismarttalk.tech/api-schemas/documents/v1.json`)
 * from the legacy snake_case array produced by `SynchProductsToAiSmartTalk`.
 *
 * Why a dedicated class:
 *  - the canonical shape is the public contract between this module and the
 *    AI SmartTalk server. Centralising every field mapping in one place keeps
 *    the mapping auditable against the JSON Schema artifact;
 *  - keeps the legacy `$documentDatas[] = [...]` payload usable by the old
 *    `/api/document/source` endpoint while the v1 path peels canonical fields
 *    on top of it, so the migration is transparent on the merchant side;
 *  - PrestaShop-agnostic — the only input is a plain array. Easy to unit-test
 *    without booting PrestaShop, exactly like the existing `PriceCalculator`.
 *
 * Promotion fields (DEV-842) are forwarded as part of the canonical block —
 * the server-side schema accepts them since v1.0 too, so no feature regression.
 */
class ProductDocumentBuilder
{
    public const PAYLOAD_VERSION = '1';

    public const STOCK_IN = 'in_stock';
    public const STOCK_OUT = 'out_of_stock';
    public const STOCK_PRE = 'preorder';
    public const STOCK_DISCONTINUED = 'discontinued';
    public const STOCK_UNKNOWN = 'unknown';

    /**
     * Convert a single entry of the module's legacy `$documentDatas[]` array
     * into the canonical `ProductDocument`.
     *
     * @param array<string,mixed> $legacy
     *
     * @return array<string,mixed>
     */
    public static function build(array $legacy): array
    {
        $externalId = self::extractExternalId($legacy);
        if ($externalId === null) {
            throw new \InvalidArgumentException(
                'PrestaShop product payload is missing id / id_product / reference'
            );
        }

        $title = self::trimText((string) ($legacy['title'] ?? $legacy['name'] ?? $externalId));

        // Required block — fields the canonical schema marks as non-nullable
        // identifiers / discriminators. Everything else flows through
        // `optional()` so a null value (invalid currency, no description,
        // simple product without variants…) gets omitted from the envelope
        // rather than sent as a noisy `null`.
        $availability = self::deriveAvailability($legacy);

        $document = [
            'type' => 'product',
            'externalId' => $externalId,
            'title' => $title !== '' ? $title : $externalId,
            'availability' => $availability,
            'variants' => self::buildVariants($externalId, $legacy['variants'] ?? []),
        ];

        $document += self::optional([
            'price' => self::normalisePrice($legacy['price'] ?? null),
            'currency' => self::normaliseCurrency($legacy['currency'] ?? null),
            'description' => self::cleanText($legacy['description'] ?? null),
            'descriptionShort' => self::cleanText($legacy['description_short'] ?? null),
            'sku' => self::nullableString($legacy['sku'] ?? $legacy['reference'] ?? null),
            'brand' => self::nullableString(
                $legacy['brand'] ?? $legacy['manufacturer'] ?? $legacy['manufacturer_name'] ?? null
            ),
            'reference' => self::nullableString($legacy['reference'] ?? null),
            'quantity' => self::normaliseQuantity($legacy['quantity'] ?? null),
            'restockDate' => self::deriveRestockDate($legacy, $availability),
            'image' => self::nullableString($legacy['image_url'] ?? $legacy['image'] ?? null),
            'url' => self::nullableString($legacy['url'] ?? null),
            'categories' => self::collectStringList($legacy['categories'] ?? null),
            'tags' => self::collectStringList($legacy['tags'] ?? null),
            'attributes' => self::normaliseAttributes($legacy['attributes'] ?? null),
        ]);

        $document += self::optional(self::extractPromotionFields($legacy));

        return $document;
    }

    /**
     * Build the sync envelope expected by `POST /api/v1/integrations/prestashop/sync`.
     *
     * @param array<int,array<string,mixed>> $canonicalDocuments
     */
    public static function buildSyncEnvelope(
        array $canonicalDocuments,
        ?string $siteIdentifier = null,
        ?string $categoryId = null
    ): array {
        $envelope = [
            'source' => 'PRESTASHOP',
            'payloadVersion' => self::PAYLOAD_VERSION,
            'documents' => array_values($canonicalDocuments),
        ];
        if ($siteIdentifier !== null && $siteIdentifier !== '') {
            $envelope['siteIdentifier'] = $siteIdentifier;
        }
        if ($categoryId !== null && $categoryId !== '') {
            $envelope['categoryId'] = $categoryId;
        }
        return $envelope;
    }

    // ===== Variants =========================================================

    /**
     * @param array<int,array<string,mixed>>|mixed $rawVariants
     * @return array<int,array<string,mixed>>
     */
    private static function buildVariants(string $parentExternalId, $rawVariants): array
    {
        if (!is_array($rawVariants)) {
            return [];
        }

        $variants = [];
        foreach ($rawVariants as $variant) {
            if (!is_array($variant)) {
                continue;
            }

            $idPa = $variant['externalId']
                ?? $variant['id_product_attribute']
                ?? $variant['id']
                ?? null;
            if ($idPa === null) {
                continue;
            }
            $idPaStr = trim((string) $idPa);
            if ($idPaStr === '') {
                continue;
            }

            $composed = (strpos($idPaStr, ':') !== false)
                ? $idPaStr
                : ($parentExternalId . ':' . $idPaStr);

            $attributes = self::normaliseAttributes($variant['attributes'] ?? null);

            $variantDoc = [
                'externalId' => $composed,
                'stockStatus' => self::normaliseStock(
                    $variant['stock_status'] ?? $variant['in_stock'] ?? null
                ),
            ];

            $variantDoc += self::optional([
                'title' => $attributes !== []
                    ? self::renderAttributesSummary($attributes)
                    : self::nullableString($variant['title'] ?? null),
                'sku' => self::nullableString(
                    $variant['sku'] ?? $variant['reference'] ?? null
                ),
                'price' => self::normalisePrice($variant['price'] ?? null),
                'image' => self::nullableString(
                    $variant['image_url'] ?? $variant['image'] ?? null
                ),
                'attributes' => $attributes,
                'quantity' => self::normaliseQuantity($variant['quantity'] ?? null),
                'ean13' => self::nullableString($variant['ean13'] ?? null),
                'upc' => self::nullableString($variant['upc'] ?? null),
                'isbn' => self::nullableString($variant['isbn'] ?? null),
            ]);

            $variantDoc += self::extractPromotionFields($variant);

            $variants[] = $variantDoc;
        }
        return $variants;
    }

    /**
     * @param array<string,string> $attributes
     */
    private static function renderAttributesSummary(array $attributes): ?string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = $key . ': ' . $value;
        }
        return $parts === [] ? null : implode(', ', $parts);
    }

    // ===== Field normalisers ================================================

    private static function extractExternalId(array $product): ?string
    {
        $candidates = [
            $product['externalId'] ?? null,
            $product['id'] ?? null,
            $product['id_product'] ?? null,
            $product['product_id'] ?? null,
            $product['reference'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    /**
     * The legacy payload does not carry an explicit availability label —
     * combinations track `in_stock`, but the parent product just has a
     * `quantity` array implicit through the variants. We infer:
     *   - explicit `availability` if present
     *   - `in_stock` if at least one variant has `in_stock=true`
     *   - `in_stock` if `quantity > 0`
     *   - `out_of_stock` if `quantity` is set to 0
     *   - `unknown` otherwise (e.g. simple product without stock fetched)
     */
    private static function deriveAvailability(array $legacy): string
    {
        if (isset($legacy['availability'])) {
            return self::normaliseStock($legacy['availability']);
        }
        if (isset($legacy['stock_status'])) {
            return self::normaliseStock($legacy['stock_status']);
        }
        if (!empty($legacy['variants']) && is_array($legacy['variants'])) {
            foreach ($legacy['variants'] as $variant) {
                if (is_array($variant) && !empty($variant['in_stock'])) {
                    return self::STOCK_IN;
                }
            }
        }
        if (isset($legacy['quantity']) && is_numeric($legacy['quantity'])) {
            return ((int) $legacy['quantity']) > 0 ? self::STOCK_IN : self::STOCK_OUT;
        }
        return self::STOCK_UNKNOWN;
    }

    /**
     * Resolve the expected back-in-stock date.
     *
     * Only meaningful when the product is NOT in stock — an in-stock product has
     * nothing to restock. Reads `restock_date` / `restockDate` (the sync feeds it
     * from PrestaShop's `product.available_date`). Returns an ISO-8601 day
     * ("YYYY-MM-DD") or null.
     *
     * @param array<string,mixed> $legacy
     */
    private static function deriveRestockDate(array $legacy, string $availability): ?string
    {
        if ($availability === self::STOCK_IN) {
            return null;
        }
        $raw = $legacy['restock_date'] ?? $legacy['restockDate'] ?? null;
        return self::normaliseRestockDate($raw);
    }

    /**
     * Normalise a raw date to a bare ISO-8601 day ("YYYY-MM-DD") or null.
     *
     * Accepts a date or datetime; rejects empty values, zero-dates and impossible
     * calendar dates. Parity with the canonical server schema (OptionalIsoDate)
     * and the other connectors so AI SmartTalk gets one shape from every source.
     *
     * @param mixed $raw
     */
    private static function normaliseRestockDate($raw): ?string
    {
        if ($raw === null || !is_scalar($raw)) {
            return null;
        }
        $trimmed = trim((string) $raw);
        if ($trimmed === '' || strpos($trimmed, '0000-00-00') === 0) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $trimmed, $m) !== 1) {
            return null;
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        if ($year <= 0 || !checkdate($month, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function normalisePrice($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return (string) $raw;
        }
        $trimmed = trim((string) $raw);
        return $trimmed === '' ? null : $trimmed;
    }

    private static function normaliseCurrency($raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $upper = strtoupper(trim($raw));
        if ($upper === '' || !preg_match('/^[A-Z]{3}$/', $upper)) {
            return null;
        }
        return $upper;
    }

    private static function normaliseStock($raw): string
    {
        if ($raw === null || $raw === '') {
            return self::STOCK_UNKNOWN;
        }
        if (is_bool($raw)) {
            return $raw ? self::STOCK_IN : self::STOCK_OUT;
        }
        if (is_numeric($raw)) {
            return ((float) $raw) > 0 ? self::STOCK_IN : self::STOCK_OUT;
        }
        $key = strtolower(trim((string) $raw));
        $key = str_replace([' ', '-'], '_', $key);
        $aliases = [
            'in_stock' => self::STOCK_IN,
            'in' => self::STOCK_IN,
            'available' => self::STOCK_IN,
            'yes' => self::STOCK_IN,
            'out_of_stock' => self::STOCK_OUT,
            'out' => self::STOCK_OUT,
            'unavailable' => self::STOCK_OUT,
            'no' => self::STOCK_OUT,
            'preorder' => self::STOCK_PRE,
            'back_order' => self::STOCK_PRE,
            'discontinued' => self::STOCK_DISCONTINUED,
        ];
        return $aliases[$key] ?? self::STOCK_UNKNOWN;
    }

    private static function normaliseQuantity($raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw)) {
            return null;
        }
        $value = (int) floor((float) $raw);
        return $value < 0 ? 0 : $value;
    }

    /**
     * @return array<int,string>
     */
    private static function collectStringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if ($item === null) {
                continue;
            }
            $value = trim((string) $item);
            if ($value !== '' && strlen($value) <= 256) {
                $out[] = $value;
            }
        }
        return $out;
    }

    /**
     * Accepts either an associative map (`["Color" => "Red"]`) or the legacy
     * pair-list shape (`[["group" => "Color", "value" => "Red"]]`).
     *
     * @return array<string,string>
     */
    private static function normaliseAttributes($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        // Pair-list form (the one CombinationHelper produces).
        if ($raw !== [] && array_keys($raw) === range(0, count($raw) - 1)) {
            $out = [];
            foreach ($raw as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $key = trim((string) ($entry['group'] ?? $entry['name'] ?? ''));
                $val = trim((string) ($entry['value'] ?? ''));
                if ($key !== '' && $val !== '') {
                    $out[$key] = $val;
                }
            }
            return $out;
        }
        // Associative form.
        $out = [];
        foreach ($raw as $k => $v) {
            $key = trim((string) $k);
            $val = $v === null ? '' : trim((string) $v);
            if ($key !== '' && $val !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    /**
     * @return array{originalPrice:?string, discountPercent:?float, discountAmount:?string, discountType:?string, discountStartsAt:?string, discountEndsAt:?string}
     */
    private static function extractPromotionFields(array $entry): array
    {
        return [
            'originalPrice' => self::normalisePrice(
                $entry['originalPrice'] ?? $entry['original_price'] ?? null
            ),
            'discountPercent' => self::normalisePercentage(
                $entry['discountPercent'] ?? $entry['discount_percent'] ?? null
            ),
            'discountAmount' => self::normalisePrice(
                $entry['discountAmount'] ?? $entry['discount_amount'] ?? null
            ),
            'discountType' => self::normaliseDiscountType(
                $entry['discountType'] ?? $entry['discount_type'] ?? null
            ),
            'discountStartsAt' => self::toIsoDateTime(
                $entry['discountStartsAt'] ?? $entry['price_from'] ?? $entry['priceFrom'] ?? null
            ),
            'discountEndsAt' => self::toIsoDateTime(
                $entry['discountEndsAt'] ?? $entry['price_to'] ?? $entry['priceTo'] ?? null
            ),
        ];
    }

    private static function normalisePercentage($raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $value = (float) $raw;
        } else {
            $value = (float) rtrim(trim((string) $raw), '%');
            if (!is_numeric(rtrim(trim((string) $raw), '%'))) {
                return null;
            }
        }
        if (!is_finite($value)) {
            return null;
        }
        return max(0.0, min(100.0, $value));
    }

    private static function normaliseDiscountType($raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $key = strtolower(trim($raw));
        if ($key === 'percentage' || $key === 'percent' || $key === '%') {
            return 'percentage';
        }
        if ($key === 'amount' || $key === 'fixed' || $key === 'absolute') {
            return 'amount';
        }
        return null;
    }

    /**
     * Accept already-ISO strings, or convert PrestaShop MySQL timestamps
     * (`2026-05-17 18:30:00`) by appending `Z`. The legacy
     * `'0000-00-00 00:00:00'` sentinel is treated as null.
     */
    private static function toIsoDateTime($raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || strpos($trimmed, '0000-00-00') === 0) {
            return null;
        }
        if (preg_match('/T.*(Z|[+-]\d{2}:\d{2})$/', $trimmed) === 1) {
            return $trimmed;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})$/', $trimmed, $m) === 1) {
            return $m[1] . 'T' . $m[2] . 'Z';
        }
        return null;
    }

    private static function nullableString($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $value = trim((string) $raw);
        return $value === '' ? null : $value;
    }

    private static function cleanText($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $value = preg_replace('/\s+/', ' ', (string) $raw);
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function trimText($raw): string
    {
        $value = preg_replace('/\s+/', ' ', (string) $raw);
        return trim((string) $value);
    }

    /**
     * Drops keys whose value is null or [] — keeps the envelope tight and lets
     * the server-side Zod defaults kick in.
     *
     * @param array<string,mixed> $candidate
     * @return array<string,mixed>
     */
    private static function optional(array $candidate): array
    {
        $out = [];
        foreach ($candidate as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value) && $value === []) {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }
}
