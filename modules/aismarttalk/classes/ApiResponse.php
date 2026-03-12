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
 * Value object representing an API HTTP response.
 */
class ApiResponse
{
    /** @var int HTTP status code (0 if request failed entirely) */
    public $httpCode;

    /** @var string|null Raw response body */
    public $body;

    /** @var array|null Decoded JSON body */
    public $data;

    /** @var string|null cURL or transport error message */
    public $error;

    /** @var bool Whether the request was successful (2xx and no transport error) */
    public $success;

    public function __construct(int $httpCode, ?string $body, ?string $error = null)
    {
        $this->httpCode = $httpCode;
        $this->body = $body;
        $this->error = $error;
        $this->success = $httpCode >= 200 && $httpCode < 300 && empty($error);
        $this->data = ($body !== null && $body !== '') ? json_decode($body, true) : null;
    }

    /**
     * Check if response is successful (2xx status, no transport error)
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get a value from the decoded JSON data using dot notation
     *
     * @param string|null $key Dot-notation key (e.g. 'data.avatarUrl'), null returns full data
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->data ?? $default;
        }

        $data = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }
}
