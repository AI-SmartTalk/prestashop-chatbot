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
 * Authenticates the cart/checkout endpoints.
 *
 * Two trust paths share the same per-site HMAC secret (minted at OAuth connect):
 *   1. Server-to-server API calls from the AI SmartTalk backend are signed with a
 *      header signature bound to the chatModel id + a timestamp (replay window).
 *   2. The browser-facing cart-restore link carries a short-lived capability token
 *      bound to the cart id (so a leaked link can't be tampered with or reused forever).
 */
class CartGuard
{
    /** Allowed clock skew (seconds) for the server-to-server signature. */
    const SIGNATURE_TTL = 300;

    /** Validity window (seconds) for a browser cart-restore link. */
    const RESTORE_TTL = 86400;

    /**
     * Verify the HMAC signature of an inbound server-to-server request.
     *
     * @param string $rawBody Raw request body (exact bytes that were signed)
     *
     * @return array{ok: bool, error: string}
     */
    public static function verifySignature(string $rawBody): array
    {
        $secret = OAuthHandler::getHmacSecret();
        if (empty($secret)) {
            return ['ok' => false, 'error' => 'Cart endpoint is not configured (missing shared secret). Reconnect the module.'];
        }

        $chatModelHeader = self::header('X-AIST-ChatModel');
        $timestampHeader = self::header('X-AIST-Timestamp');
        $signatureHeader = self::header('X-AIST-Signature');

        if (empty($chatModelHeader) || empty($timestampHeader) || empty($signatureHeader)) {
            return ['ok' => false, 'error' => 'Missing authentication headers.'];
        }

        $expectedChatModel = OAuthHandler::getChatModelId();
        if (empty($expectedChatModel) || !hash_equals((string) $expectedChatModel, (string) $chatModelHeader)) {
            return ['ok' => false, 'error' => 'Chat model mismatch.'];
        }

        $timestamp = (int) $timestampHeader;
        if (abs(time() - $timestamp) > self::SIGNATURE_TTL) {
            return ['ok' => false, 'error' => 'Request expired.'];
        }

        $expected = hash_hmac('sha256', $chatModelHeader . '.' . $timestamp . '.' . $rawBody, $secret);
        if (!hash_equals($expected, (string) $signatureHeader)) {
            return ['ok' => false, 'error' => 'Invalid signature.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Build a signed, time-bound token for a browser cart-restore link.
     */
    public static function makeRestoreToken(int $cartId, int $timestamp): string
    {
        $secret = OAuthHandler::getHmacSecret();

        return hash_hmac('sha256', 'restore.' . $cartId . '.' . $timestamp, (string) $secret);
    }

    /**
     * Verify a browser cart-restore token (signature + freshness).
     */
    public static function verifyRestoreToken(int $cartId, int $timestamp, string $token): bool
    {
        $secret = OAuthHandler::getHmacSecret();
        if (empty($secret)) {
            return false;
        }
        if (abs(time() - $timestamp) > self::RESTORE_TTL) {
            return false;
        }

        $expected = self::makeRestoreToken($cartId, $timestamp);

        return hash_equals($expected, $token);
    }

    /**
     * Read an HTTP request header in a server-agnostic way.
     */
    private static function header(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return (string) $_SERVER[$serverKey];
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return (string) $value;
                }
            }
        }

        return '';
    }
}
