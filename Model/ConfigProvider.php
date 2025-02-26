<?php

namespace Amwal\Pay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $method; // Payment method code
    protected $scopeConfig; // Scope configuration interface
    protected $storeManager; // Store manager interface

    /**
     * Constructor to initialize dependencies
     *
     * @param ScopeConfig           $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(ScopeConfig $scopeConfig, StoreManagerInterface $storeManager)
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Retrieve configuration data for the payment method
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        try {
            return [
                'payment' => [
                    AmwalPay::CODE => [
                        'environment' => $this->getConfigData(AmwalPay::CODE, 'environment'),
                        'callback' => $this->storeManager->getStore()->getBaseUrl() . 'amwal/checkout/callback',
                        'base_url' => $this->storeManager->getStore()->getBaseUrl(),
                    ],
                ],
            ];
        } catch (NoSuchEntityException $e) {
            // Handle the exception if the store or configuration is not found
            error_log('Error retrieving payment configuration: ' . $e->getMessage());
            return []; // Return an empty array in case of an error
        }
    }

    /**
     * Retrieve specific configuration data for the payment method
     *
     * @param string $paymentCode The payment method code
     * @param string $field The specific field to retrieve
     * @return mixed The configuration value
     */
    private function getConfigData($paymentCode, $field)
    {
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('payment/' . $paymentCode . '/' . $field, $storeScope);
    }
}