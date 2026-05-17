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
 * Centralized HTTP client for AI SmartTalk API communication.
 * Replaces all direct curl calls across the plugin.
 */
class ApiClient
{
    /** @var string Base API URL (no trailing slash) */
    private $baseUrl;

    /** @var string|null OAuth access token */
    private $accessToken;

    /** @var string|null Chat model identifier */
    private $chatModelId;

    /** @var string|null Site identifier for multi-site support */
    private $siteIdentifier;

    /**
     * Plugin telemetry tag — sent verbatim as the `x-aismarttalk-plugin`
     * header on every request. The backend reads this from its structured
     * logs to build adoption dashboards per merchant (which version of the
     * module is calling, who is still on legacy endpoints, etc.).
     *
     * @var string|null
     */
    private $producerTag;

    /**
     * @param string      $baseUrl        Base API URL
     * @param string|null $accessToken    OAuth access token
     * @param string|null $chatModelId    Chat model ID
     * @param string|null $siteIdentifier Site identifier
     * @param string|null $producerTag    Telemetry tag `<plugin>/<version>`
     */
    public function __construct(
        string $baseUrl,
        ?string $accessToken = null,
        ?string $chatModelId = null,
        ?string $siteIdentifier = null,
        ?string $producerTag = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->accessToken = $accessToken;
        $this->chatModelId = $chatModelId;
        $this->siteIdentifier = $siteIdentifier;
        $this->producerTag = self::sanitiseProducer($producerTag);
    }

    /**
     * Create an ApiClient from current PrestaShop configuration.
     * Encapsulates the credential-fetching pattern used across the plugin.
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        return new self(
            OAuthHandler::getBackendApiUrl(),
            OAuthHandler::getAccessToken() ?: (MultistoreHelper::getConfig('CHAT_MODEL_TOKEN') ?: null),
            OAuthHandler::getChatModelId() ?: (MultistoreHelper::getConfig('CHAT_MODEL_ID') ?: null),
            OAuthHandler::getSiteIdentifier(),
            self::resolveProducerTag()
        );
    }

    /**
     * Best-effort lookup of the module version for the telemetry header. Falls
     * back to `prestashop/unknown` outside of a PrestaShop runtime (tests,
     * scripted contexts) so the header is always present and analyzable.
     */
    private static function resolveProducerTag(): string
    {
        if (class_exists('Module', false)) {
            try {
                /** @var \Module|false $module */
                $module = \Module::getInstanceByName('aismarttalk');
                if ($module instanceof \Module && !empty($module->version)) {
                    return 'prestashop/' . $module->version;
                }
            } catch (\Throwable $_e) {
                // Swallow — telemetry must never break a sync call.
            }
        }
        return 'prestashop/unknown';
    }

    /**
     * Sanitise the producer string against the server-side regex
     * `^[A-Za-z0-9._\/+\-]{1,128}$`. Anything outside that alphabet (a stray
     * space in a custom build version, for example) would silently drop the
     * header server-side — we strip it proactively here so the merchant
     * always appears in adoption dashboards.
     */
    private static function sanitiseProducer(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $sanitised = preg_replace('/[^A-Za-z0-9._\/+\-]/', '', $trimmed);
        if ($sanitised === null || $sanitised === '') {
            return null;
        }
        return substr($sanitised, 0, 128);
    }

    /**
     * Check if this client has valid credentials for authenticated requests.
     *
     * @return bool
     */
    public function hasCredentials(): bool
    {
        return !empty($this->baseUrl) && !empty($this->accessToken) && !empty($this->chatModelId);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getChatModelId(): ?string
    {
        return $this->chatModelId;
    }

    public function getSiteIdentifier(): ?string
    {
        return $this->siteIdentifier;
    }

    /**
     * Perform an authenticated GET request.
     *
     * @param string $path    URL path (appended to baseUrl)
     * @param int    $timeout Timeout in seconds
     * @return ApiResponse
     */
    public function get(string $path, int $timeout = 10): ApiResponse
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
            CURLOPT_HTTPHEADER => $this->buildAuthHeaders(),
        ]);

        return $this->execute($ch);
    }

    /**
     * Perform an authenticated POST request with JSON body.
     *
     * @param string $path    URL path (appended to baseUrl)
     * @param array  $data    Data to send (will be JSON-encoded)
     * @param int    $timeout Timeout in seconds
     * @return ApiResponse
     */
    public function post(string $path, array $data, int $timeout = 30): ApiResponse
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $this->buildAuthHeaders(),
        ]);

        return $this->execute($ch);
    }

    /**
     * Perform an authenticated multipart file upload.
     *
     * @param string    $path    URL path (appended to baseUrl)
     * @param \CURLFile $file    The file to upload
     * @param int       $timeout Timeout in seconds
     * @return ApiResponse
     */
    public function upload(string $path, \CURLFile $file, int $timeout = 60): ApiResponse
    {
        // For multipart uploads, don't set Content-Type (curl sets it with boundary)
        $headers = [];
        if (!empty($this->accessToken)) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        if (!empty($this->chatModelId)) {
            $headers[] = 'x-chat-model-id: ' . $this->chatModelId;
        }
        if (!empty($this->producerTag)) {
            $headers[] = 'x-aismarttalk-plugin: ' . $this->producerTag;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $file],
            CURLOPT_HTTPHEADER => $headers,
        ]);

        return $this->execute($ch);
    }

    /**
     * Build authentication headers for JSON requests.
     *
     * @return array
     */
    private function buildAuthHeaders(): array
    {
        $headers = ['Content-Type: application/json'];

        if (!empty($this->accessToken)) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        if (!empty($this->chatModelId)) {
            $headers[] = 'x-chat-model-id: ' . $this->chatModelId;
        }
        if (!empty($this->producerTag)) {
            $headers[] = 'x-aismarttalk-plugin: ' . $this->producerTag;
        }

        return $headers;
    }

    /**
     * @return string|null The telemetry tag sent on every authenticated request.
     */
    public function getProducerTag(): ?string
    {
        return $this->producerTag;
    }

    /**
     * Execute a prepared curl handle and return an ApiResponse.
     *
     * @param resource $ch curl handle
     * @return ApiResponse
     */
    private function execute($ch): ApiResponse
    {
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return new ApiResponse(0, null, $error ?: 'cURL request failed');
        }

        return new ApiResponse($httpCode, (string) $body, !empty($error) ? $error : null);
    }
}
