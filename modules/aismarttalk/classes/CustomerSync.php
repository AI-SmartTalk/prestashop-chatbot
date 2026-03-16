<?php
/**
 * Copyright (c) 2026 AI SmartTalk
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    AI SmartTalk
 * @copyright 2026
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace PrestaShop\AiSmartTalk;

// Make sure PrestaShop is loaded
if (!defined('_PS_VERSION_')) {
    exit;
}



/**
 * Class CustomerSync
 * Handles batch syncing of PrestaShop customers to AI SmartTalk CRM.
 * Mirrors the Product Sync pattern: tracking table, debounce, sync+clean.
 */
class CustomerSync
{
    /** @var int Number of customers to sync per batch */
    private $batchSize = 50;

    /** @var int Total count of customers to sync */
    private $totalCustomers = 0;

    /** @var int Number of customers processed so far */
    private $processedCustomers = 0;

    /** @var \Context PrestaShop context */
    private $context;

    /** Customer sync consent filter options */
    const CONSENT_ALL = 'all';
    const CONSENT_NEWSLETTER = 'newsletter';
    const CONSENT_OPTIN = 'optin';
    const CONSENT_NEWSLETTER_OR_OPTIN = 'newsletter_or_optin';
    const CONSENT_NEWSLETTER_AND_OPTIN = 'newsletter_and_optin';

    /**
     * CustomerSync constructor.
     *
     * @param \Context|null $context PrestaShop context (injected dependency)
     */
    public function __construct(\Context $context = null)
    {
        $this->context = $context;
    }

    /**
     * Get the injected context
     *
     * @return \Context
     *
     * @throws \RuntimeException if context was not injected
     */
    private function getContext()
    {
        if ($this->context === null) {
            throw new \RuntimeException('Context must be injected via constructor');
        }

        return $this->context;
    }

    /**
     * Sync a single customer: export to CRM and mark as synced in tracking table.
     *
     * @param \Customer $customer
     *
     * @return bool
     */
    public function syncCustomer(\Customer $customer)
    {
        if ($this->exportCustomerBatch([$customer])) {
            AiSmartTalkCustomerSync::markAsSynced((int) $customer->id);

            return true;
        }

        return false;
    }

    /**
     * Remove a single customer from CRM and mark as not synced in tracking table.
     *
     * @param \Customer $customer
     *
     * @return bool
     */
    public function unsyncCustomer(\Customer $customer)
    {
        $result = $this->removeCustomer($customer->email);
        AiSmartTalkCustomerSync::markAsNotSynced((int) $customer->id);

        return $result;
    }

    /**
     * Sync or remove a customer based on consent filter.
     * If customer matches consent -> sync. If not -> remove from CRM.
     * Includes debounce check.
     *
     * @param \Customer $customer
     *
     * @return void
     */
    public function syncOrRemove(\Customer $customer)
    {
        // Debounce: skip if synced less than 3 seconds ago
        if (!AiSmartTalkCustomerSync::canSync((int) $customer->id)) {
            return;
        }

        if (self::customerMatchesConsentFilter($customer)) {
            $this->syncCustomer($customer);
        } else {
            $this->unsyncCustomer($customer);
        }
    }

    /**
     * Export a batch of customers to AI SmartTalk.
     *
     * @param \Customer[] $customers Array of PrestaShop Customer objects
     *
     * @return bool True on success, false otherwise
     */
    public function exportCustomerBatch(array $customers)
    {
        $client = ApiClient::fromConfig();

        // Map PrestaShop customer data to the expected AI SmartTalk format
        $customerData = array_map([$this, 'mapCustomerData'], $customers);

        // Prepare the payload
        $payload = [
            'customers' => $customerData,
            'chatModelId' => $client->getChatModelId(),
            'chatModelToken' => $client->getAccessToken(),
            'source' => 'PRESTASHOP',
            'siteIdentifier' => $client->getSiteIdentifier(),
        ];

        // Encrypt sensitive data if enabled
        $encrypted = PayloadEncryptor::encrypt(
            ['customers' => $customerData],
            $client->getAccessToken(),
            $client->getChatModelId()
        );
        if ($encrypted !== null) {
            $payload['encrypted'] = $encrypted;
            unset($payload['customers']);
        }

        $response = $client->post('/api/v1/crm/importCustomer', $payload);

        if (!$response->isSuccess()) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk customer sync ' . ($response->error ? 'cURL error: ' . $response->error : 'failed. HTTP code: ' . $response->httpCode),
                3, null, 'Customer', null, true
            );
            return false;
        }

        return true;
    }

    /**
     * Map a PrestaShop Customer object to AI SmartTalk format
     *
     * @param \Customer $customer The customer object to map
     *
     * @return array The mapped customer data
     */
    private function mapCustomerData(\Customer $customer)
    {
        // Get addresses associated with this customer
        $addresses = $customer->getAddresses((int) $this->getContext()->language->id);

        // Get the first address if available
        $firstAddress = !empty($addresses) ? reset($addresses) : null;

        $mappedData = [
            'externalId' => (string) $customer->id,
            'email' => $customer->email,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
            'phone' => $firstAddress ? $firstAddress['phone'] : null,
            'address' => $firstAddress ? $firstAddress['address1'] : null,
            'city' => $firstAddress ? $firstAddress['city'] : null,
            'country' => $firstAddress ? $firstAddress['country'] : null,
            'postalCode' => $firstAddress ? $firstAddress['postcode'] : null,
            'newsletter' => (bool) $customer->newsletter,
            'optin' => (bool) $customer->optin,
        ];

        return $mappedData;
    }

    /**
     * Check if a customer matches the configured consent filter.
     *
     * @param \Customer $customer The customer to check
     *
     * @return bool True if the customer passes the consent filter
     */
    public static function customerMatchesConsentFilter(\Customer $customer)
    {
        // Never sync inactive customers regardless of consent filter
        if (!(bool) $customer->active) {
            return false;
        }

        $consentFilter = MultistoreHelper::getConfig('AI_SMART_TALK_CUSTOMER_SYNC_CONSENT') ?: self::CONSENT_ALL;

        switch ($consentFilter) {
            case self::CONSENT_NEWSLETTER:
                return (bool) $customer->newsletter;
            case self::CONSENT_OPTIN:
                return (bool) $customer->optin;
            case self::CONSENT_NEWSLETTER_OR_OPTIN:
                return (bool) $customer->newsletter || (bool) $customer->optin;
            case self::CONSENT_NEWSLETTER_AND_OPTIN:
                return (bool) $customer->newsletter && (bool) $customer->optin;
            case self::CONSENT_ALL:
            default:
                return true;
        }
    }

    /**
     * Remove a customer from AI SmartTalk CRM by email.
     *
     * @param string $email The customer email to remove
     *
     * @return bool True on success, false otherwise
     */
    public function removeCustomer($email)
    {
        $client = ApiClient::fromConfig();

        $payload = [
            'email' => $email,
            'chatModelId' => $client->getChatModelId(),
            'chatModelToken' => $client->getAccessToken(),
            'source' => 'PRESTASHOP',
            'siteIdentifier' => $client->getSiteIdentifier(),
        ];

        // Encrypt sensitive data if enabled
        $encrypted = PayloadEncryptor::encrypt(
            ['email' => $email],
            $client->getAccessToken(),
            $client->getChatModelId()
        );
        if ($encrypted !== null) {
            $payload['encrypted'] = $encrypted;
            unset($payload['email']);
        }

        $response = $client->post('/api/v1/crm/removeCustomer', $payload);

        if (!$response->isSuccess()) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk customer remove ' . ($response->error ? 'cURL error: ' . $response->error : 'failed. HTTP code: ' . $response->httpCode),
                3, null, 'Customer', null, true
            );
            return false;
        }

        return true;
    }

    /**
     * Sync all customers: export those matching consent filter, remove those that don't.
     * Mirrors the Product Sync "Sync All" pattern.
     *
     * @return array Status with keys: success, total, synced, removed, errors
     */
    public function syncAllCustomers()
    {
        try {
            // Retrieve all active customers in PrestaShop
            $allCustomers = \Customer::getCustomers(true);
            $this->totalCustomers = count($allCustomers);
            $this->processedCustomers = 0;

            $toSync = [];
            $toRemove = [];

            // Partition customers based on consent filter
            foreach ($allCustomers as $cData) {
                $customer = new \Customer((int) $cData['id_customer']);
                if (self::customerMatchesConsentFilter($customer)) {
                    $toSync[] = $customer;
                } else {
                    $toRemove[] = $customer;
                }
            }

            // Also find previously synced customers that no longer exist (deleted)
            $syncedIds = AiSmartTalkCustomerSync::getSyncedCustomerIds();
            $activeIds = array_column($allCustomers, 'id_customer');
            $orphanedIds = array_diff($syncedIds, $activeIds);
            foreach ($orphanedIds as $orphanedId) {
                AiSmartTalkCustomerSync::markAsNotSynced((int) $orphanedId);
            }

            $errors = [];
            $syncedCount = 0;
            $removedCount = 0;

            // Sync matching customers in batches
            $batches = array_chunk($toSync, $this->batchSize);
            foreach ($batches as $batch) {
                if ($this->exportCustomerBatch($batch)) {
                    foreach ($batch as $customer) {
                        AiSmartTalkCustomerSync::markAsSynced((int) $customer->id);
                    }
                    $syncedCount += count($batch);
                } else {
                    $errors[] = sprintf('Failed to sync batch of %d customers', count($batch));
                }
                $this->processedCustomers += count($batch);
            }

            // Remove customers that don't match consent filter but were previously synced
            foreach ($toRemove as $customer) {
                if (AiSmartTalkCustomerSync::getByCustomerId((int) $customer->id) !== null) {
                    $wasSynced = AiSmartTalkCustomerSync::getByCustomerId((int) $customer->id);
                    if ($wasSynced && $wasSynced->synced) {
                        if ($this->removeCustomer($customer->email)) {
                            $removedCount++;
                        } else {
                            $errors[] = sprintf('Failed to remove customer #%d from CRM', $customer->id);
                        }
                    }
                    AiSmartTalkCustomerSync::markAsNotSynced((int) $customer->id);
                }
                $this->processedCustomers++;
            }

            return [
                'success' => empty($errors),
                'total' => $this->totalCustomers,
                'synced' => $syncedCount,
                'removed' => $removedCount,
                'processed' => $this->processedCustomers,
                'errors' => $errors,
            ];
        } catch (\PrestaShopException $e) {
            \PrestaShopLogger::addLog(
                'AI SmartTalk customer sync exception: ' . $e->getMessage(),
                3,
                null,
                'Customer',
                null,
                true
            );

            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        } catch (\Exception $e) {
            // Catch any other exceptions
            \PrestaShopLogger::addLog(
                'AI SmartTalk customer sync general exception: ' . $e->getMessage(),
                3,
                null,
                'Customer',
                null,
                true
            );

            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get current sync progress in terms of total customers and processed customers.
     *
     * @return array Progress with keys: total, processed, percentage
     */
    public function getProgress()
    {
        return [
            'total' => $this->totalCustomers,
            'processed' => $this->processedCustomers,
            'percentage' => $this->totalCustomers > 0
                ? round(($this->processedCustomers / $this->totalCustomers) * 100)
                : 0,
        ];
    }
}
