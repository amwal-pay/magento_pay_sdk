<?php

namespace Amwal\Pay\Controller\ApplePay;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Amwal\Pay\Helper\AmwalPay;
use Amwal\Pay\Helper\ApplePayQuoteManager;

class PlaceOrder extends Action
{
    protected $resultJsonFactory;
    protected $cartManagement;
    protected $orderRepository;
    protected $transaction;
    protected $invoiceService;
    protected $storeManager;
    protected $helper;
    protected $amwal;
    protected $quoteManager;
    protected $email;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $orderRepository,
        Transaction $transaction,
        InvoiceService $invoiceService,
        StoreManagerInterface $storeManager,
        AmwalPay $helper,
        \Amwal\Pay\Model\AmwalPay $amwal,
        ApplePayQuoteManager $quoteManager,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $email
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cartManagement   = $cartManagement;
        $this->orderRepository  = $orderRepository;
        $this->transaction      = $transaction;
        $this->invoiceService   = $invoiceService;
        $this->storeManager     = $storeManager;
        $this->helper           = $helper;
        $this->amwal            = $amwal;
        $this->quoteManager     = $quoteManager;
        $this->email            = $email;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $transaction = $this->getRequest()->getParam('transaction');
            $responseCode = $this->getRequest()->getParam('responseCode');
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Applepay Callback Response: ', print_r($transaction, 1));
            // Prepare integrity parameters for secure hash validation
            $integrityParameters = [
                "amount" => $transaction['amount'],
                "currencyId" => $transaction['currencyId'],
                "customerId" => isset($transaction['customerId']) ? $transaction['customerId'] : '',
                "customerTokenId" => isset($transaction['customerTokenId']) ? $transaction['customerTokenId'] : '',
                "merchantId" => $this->amwal->getConfigData("merchant_id"),
                "merchantReference" => $transaction['merchantReference'],
                "responseCode" => $responseCode,
                "terminalId" => $this->amwal->getConfigData("terminal_id"),
                "transactionId" => $transaction['transactionId'],
                "transactionTime" => $transaction['transactionTime']
            ];

            // Generate secure hash value
            $secureHashValue = $this->helper->generateStringForFilter($integrityParameters, $this->amwal->getConfigData("secret_key"));
            $integrityParameters['secureHashValue'] = $secureHashValue;
            $integrityParameters['secureHashValueOld'] = $transaction['secureHashValue'];

            $info = 'Old Hash -- ' . $transaction['secureHashValue'] . '  New Hash -- ' . $secureHashValue;
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, $info);
            if ($secureHashValue != $transaction['secureHashValue']) {

                return $result->setData([
                    'success' => false,
                    'message' => 'Sorry, you are accessing wrong information'
                ]);
            }
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

            $quote->getPayment()->importData(['method' => 'amwal_pay']);

            $this->quoteManager->finalize($quote);

            $orderId = $this->cartManagement->placeOrder($quote->getId());
            $order   = $this->orderRepository->get($orderId);

             /** --------------------------------------------------
             * 7️⃣ ORDER STATUS + INVOICE
             * -------------------------------------------------- */
            $state = $this->amwal->getConfigData('complete_paid_order')
                ? \Magento\Sales\Model\Order::STATE_COMPLETE
                : \Magento\Sales\Model\Order::STATE_PROCESSING;
            $info = __('Order #') . $orderId . ' ' .__('AmwalPay Payment Successful') . ' ' . "<br/> Transaction ID: " . $transaction['transactionId'] . "<br/> Transaction Msg: <b style='color:DodgerBlue;'>" . $transaction['message'] . "</b>";
            $order->setState($state)
                ->setStatus($state)
                ->addStatusHistoryComment($info)
                ->save();

            // Set payment details
            $payment = $order->getPayment();
            $payment->setTransactionId($transaction['transactionId']);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
            $order->save();

            $this->addMagentoInvoice(
                $order,
                $orderId,
                $transaction['transactionId']
            );
            $this->messageManager->addSuccess($info);
            $this->email->send($order);

            return $result->setData([
                'success' => true,
                'redirect' => $this->storeManager->getStore()->getBaseUrl()
                    . 'checkout/onepage/success'
            ]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function addMagentoInvoice($order, $orderId, $transId)
    {
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice->getTotalQty()) {
                $invoice->setTransactionId($transId);
                $invoice->setRequestedCaptureCase(
                    \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE
                );
                $invoice->register();
                $this->transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            }
        }
    }
}
