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
    protected $method;
    protected $scopeConfig;
    protected $storeManager;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Session                         $customerSession
     * @param Url                             $urlBuilder
     */
    /**
     * @param ScopeConfig           $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(ScopeConfig $scopeConfig, StoreManagerInterface $storeManager)
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }
    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        return [
            'payment' => [
                AmwalPay::CODE => [
                    'environment' =>  $this->getConfigData(AmwalPay::CODE, 'environment'),
                    'callback' =>  $this->storeManager->getStore()->getBaseUrl().'amwal/checkout/callback'
                ],
            ],
        ];
    }

    /**
     * @param $paymentCode
     * @param $field
     *
     * @return mixed
     */
    private function getConfigData($paymentCode, $field)
    {
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('payment/' . $paymentCode . '/' . $field, $storeScope);
    }
}
