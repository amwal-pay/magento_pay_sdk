<?php

namespace Amwal\Pay\Controller\ApplePay;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Amwal\Pay\Helper\AmwalPay;
use Amwal\Pay\Helper\ApplePayQuoteManager;

class ApplePayInit extends Action
{
    protected $resultJsonFactory;
    protected $logger;
    protected $amwal;
    protected $helper;
    protected $quoteManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        \Amwal\Pay\Model\AmwalPay $amwal,
        AmwalPay $helper,
        ApplePayQuoteManager $quoteManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger            = $logger;
        $this->amwal             = $amwal;
        $this->helper            = $helper;
        $this->quoteManager      = $quoteManager;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->quoteManager->loadQuote(
                $this->getRequest()->getParam('quote_id')
            );

            $this->quoteManager->prepareCustomer(
                $quote,
                $this->getRequest()->getParam('email')
            );

            $this->quoteManager->applyAddresses(
                $quote,
                json_decode($this->getRequest()->getParam('address'), true)
            );

            $this->quoteManager->applyShippingMethod(
                $quote,
                json_decode($this->getRequest()->getParam('method'), true)
            );

            $this->quoteManager->finalize($quote);
            $amount = number_format((float)$quote->getGrandTotal(), 3, '.', '');
            $datetime = date('YmdHis');

            $payload = [
                'MID' => $this->amwal->getConfigData('merchant_id'),
                'TID' => $this->amwal->getConfigData('terminal_id'),
                'CurrencyId' => 512,
                'AmountTrxn' => $amount,
                'MerchantReference' => (string)$quote->getId(),
                'LanguageId' => 'en',
                'PaymentViewType' => 1,
                'TrxDateTime' => $datetime,
                'ApplePayElementId' => 'apple_pay_button'
            ];

            $payload['SecureHash'] = $this->helper->generateString(
                $amount,
                512,
                $payload['MID'],
                $payload['MerchantReference'],
                $payload['TID'],
                $this->amwal->getConfigData('secret_key'),
                $datetime,
                ''
            );

            return $result->setData([
                'success' => true,
                'payload' => $payload
            ]);

        } catch (\Exception $e) {
            $this->logger->critical('ApplePayInit Error', ['message' => $e->getMessage()]);
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
