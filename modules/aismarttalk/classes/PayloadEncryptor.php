<?php
/**
 * Copyright (c) 2026 AI SmartTalk
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
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
 * AES-256-GCM payload encryption for API communication.
 * Encrypts sensitive data before sending to AI SmartTalk API.
 */
class PayloadEncryptor
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const HKDF_INFO = 'aismarttalk-payload-encryption-v1';

    /**
     * Encrypt a payload array. Returns the encrypted envelope.
     * Returns null only if credentials are missing or encryption fails.
     *
     * @param array  $payload     The data to encrypt (will be JSON-encoded)
     * @param string $token       The access token (used to derive key)
     * @param string $chatModelId The chat model ID (used as HKDF salt)
     *
     * @return array|null Encrypted envelope {v, iv, tag, data} or null on failure
     */
    public static function encrypt(array $payload, $token, $chatModelId)
    {
        if (empty($token) || empty($chatModelId)) {
            return null;
        }

        $key = self::deriveKey($token, $chatModelId);
        $iv = random_bytes(self::IV_LENGTH);
        $plaintext = json_encode($payload);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk payload encryption failed: ' . openssl_error_string(),
                3, null, 'PayloadEncryptor', null, true
            );

            return null;
        }

        return [
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ];
    }

    /**
     * Derive a 256-bit encryption key from the access token using HKDF.
     *
     * @param string $token       The access token
     * @param string $chatModelId Used as salt
     *
     * @return string 32-byte raw key
     */
    private static function deriveKey($token, $chatModelId)
    {
        return hash_hkdf('sha256', $token, 32, self::HKDF_INFO, $chatModelId);
    }
}
