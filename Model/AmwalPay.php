<?php
namespace Amwal\Pay\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\HTTP\Client\Curl;

class AmwalPay extends AbstractMethod
{
    const CODE = 'amwal_pay';
    
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    protected $curl;
    protected $helper;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        PaymentLogger $logger, // <-- correct type
        Curl $curl,
        \Amwal\Pay\Helper\AmwalPay $helper,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger, // <-- pass PaymentLogger
            $resource,
            $resourceCollection,
            $data
        );

        $this->curl = $curl;
        $this->helper = $helper;
    }


    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $parentTransactionId = $payment->getParentTransactionId();
        if (!$parentTransactionId) {
            throw new LocalizedException(__('Transaction ID not found.'));
        }

        $merchantId  = parent::getConfigData('merchant_id');
        $terminalId  = parent::getConfigData('terminal_id');
        $secureKey   = parent::getConfigData('secret_key');
        $environment = parent::getConfigData('environment');
        $debug       = parent::getConfigData('debug');

        $baseUrl = $this->helper->getWebhookUrl($environment);

        try {
            $transaction = $this->getTransaction($baseUrl, $merchantId, $parentTransactionId, $secureKey);
            $this->helper->addLogs($debug, AMWAL_DEBUG_FILE, 'Transaction Details', print_r($transaction, true));

            if (empty($transaction['success'])) {
                return $this->handleError($transaction);
            }

            $actions = $transaction['data']['transactionActions'] ?? [];

            if (!empty($actions['canVoid']) || !empty($actions['canPartialVoid'])) {
                return $this->executeAction('Void', $baseUrl, $merchantId, $terminalId, $secureKey, $parentTransactionId, $amount, $payment);
            }

            if (!empty($actions['canRefund']) || !empty($actions['canPartialRefund'])) {
                return $this->executeAction('Refund', $baseUrl, $merchantId, $terminalId, $secureKey, $parentTransactionId, $amount, $payment);
            }

            throw new LocalizedException(__('Neither Void nor Refund allowed.'));
        } catch (\Exception $e) {
            $this->helper->addLogs($debug, AMWAL_DEBUG_FILE, 'Refund Exception', $e->getMessage());
            throw new LocalizedException(__('Refund failed: %1', $e->getMessage()));
        }
    }

    public function getTransaction($baseUrl, $merchantId, $transactionId, $secureKey)
    {
        $secureHash = $this->helper->generateStringForFilter([
            "merchantId" => $merchantId,
            "transactionId" => $transactionId
        ], $secureKey);

        return $this->post($baseUrl . 'Transaction/GetByTransactionId', [
            "merchantId" => $merchantId,
            "transactionId" => $transactionId,
            "secureHashValue" => $secureHash
        ]);
    }

    public function executeAction($type, $baseUrl, $merchantId, $terminalId, $secureKey, $parentTransactionId, $amount, $payment)
    {
        $transactionId = $this->generateGuid();
        $datetime = gmdate('Y-m-d\TH:i:s\Z');

        $secureHash = $this->helper->generateStringForFilter([
            "amount" => $amount,
            "currencyCode" => 512,
            "merchantId" => $merchantId,
            "requestDateTime" => $datetime,
            "terminalId" => $terminalId,
            "transactionId" => $transactionId,
            "transactionIdentifierType" => 2,
            "transactionIdentifierValue" => $parentTransactionId
        ], $secureKey);

        $response = $this->post($baseUrl . "Execute/{$type}", [
            "transactionId" => $transactionId,
            "transactionIdentifierValue" => $parentTransactionId,
            "transactionIdentifierType" => 2,
            "requestDateTime" => $datetime,
            "terminalId" => $terminalId,
            "merchantId" => $merchantId,
            "currencyCode" => 512,
            "amount" => $amount,
            "secureHashValue" => $secureHash
        ]);

        $this->helper->addLogs('1', AMWAL_DEBUG_FILE, "{$type} Response", print_r($response, true));

        if (!empty($response['success'])) {
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(true);
            $payment->addTransaction(Transaction::TYPE_REFUND);
            return $this;
        }

        return $this->handleError($response);
    }

    public function post($url, $payload)
    {
        $this->helper->addLogs('1', AMWAL_DEBUG_FILE, "POST Request to {$url}", print_r($payload, true));
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->post($url, json_encode($payload));
        return json_decode($this->curl->getBody(), true);
    }

    public function handleError($response)
    {
        if (!empty($response['errorList'][0])) {
            throw new LocalizedException(__($response['errorList'][0]));
        }
        if (!empty($response['message'])) {
            throw new LocalizedException(__($response['message']));
        }
        throw new LocalizedException(__('Unknown error occurred.'));
    }

    public function generateGuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}