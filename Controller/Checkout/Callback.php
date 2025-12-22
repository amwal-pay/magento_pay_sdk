<?php
namespace Amwal\Pay\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Amwal\Pay\Helper\AmwalPay;
use Amwal\Pay\Model\AmwalPayCardsToken;
use Amwal\Pay\Model\ResourceModel\AmwalPayCardsToken\CollectionFactory;
class Callback extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $order;
    protected $invoiceService;
    protected $resultFactory;
    protected $amwal;
    protected $creditmemoFactory;
    protected $creditmemoService;
    protected $orderFactory;
    protected $checkoutSession;
    protected $transaction;
    protected $stockManagement;
    protected $resourceConnection;
    protected $deploymentConfig;
    protected $quoteFactory;
    protected $email;
    protected $storeManager;
    protected $customer;
    protected $helper;
    protected $amwalpayCardsCollection;
    protected $amwalpayCardsTokenModel;

    /**
     * Constructor to initialize dependencies
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Amwal\Pay\Model\AmwalPay $amwal
     * @param \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory
     * @param \Magento\Sales\Model\Service\CreditmemoService $creditmemoService
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $email
     * @param \Magento\Customer\Model\Customer $customer
     * @param AmwalPay $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Amwal\Pay\Model\AmwalPay $amwal,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $email,
        \Magento\Customer\Model\Customer $customer,
        AmwalPay $helper,
        CollectionFactory $amwalpayCardsCollection,
        AmwalPayCardsToken $amwalpayCardsTokenModel
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->invoiceService = $invoiceService;
        $this->resultFactory = $context->getResultFactory();
        $this->storeManager = $storeManager;
        $this->amwal = $amwal;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->transaction = $transaction;
        $this->stockManagement = $stockManagement;
        $this->resourceConnection = $resourceConnection;
        $this->deploymentConfig = $deploymentConfig;
        $this->quoteFactory = $quoteFactory;
        $this->email = $email;
        $this->customer = $customer;
        $this->helper = $helper;
        $this->amwalpayCardsCollection = $amwalpayCardsCollection;
        $this->amwalpayCardsTokenModel = $amwalpayCardsTokenModel;
    }

    /**
     * Execute the callback process after payment
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if ($this->helper->sanitizeVar('REQUEST_METHOD', 'SERVER') === 'POST') {
            $this->callCloudNotification();
        } else if ($this->helper->sanitizeVar('REQUEST_METHOD', 'SERVER') === 'GET' && !empty($this->getRequest()->getParams())) {
            return $this->callBack();
        } else {
            $this->redirectWithError("This Server is not ready to handle your request right now.");
        }
    }
    protected function callBack()
    {
        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $params = $this->getRequest()->getParams();
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Callback Response: ', print_r($params, 1));

        // Extract order ID from merchantReference
        $orderId = substr($params['merchantReference'], 0, -9);
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        // Check if the order exists
        if (empty($order->getId())) {
            $this->redirectWithError(__('Sorry, you are accessing wrong information'));
            return $response; // Early return
        }

        $storeCode = $order->getStore()->getCode();
        $baseUrl = $this->storeManager->getStore($storeCode)->getBaseUrl();
        $customerId = isset($params['customerId']) ? $params['customerId'] : '';
        // Prepare integrity parameters for secure hash validation
        $integrityParameters = [
            "amount" => $params['amount'],
            "currencyId" => $params['currencyId'],
            "customerId" => $customerId,
            "customerTokenId" => isset($params['customerTokenId']) ? $params['customerTokenId'] : '',
            "merchantId" => $this->amwal->getConfigData("merchant_id"),
            "merchantReference" => $params['merchantReference'],
            "responseCode" => $params['responseCode'],
            "terminalId" => $this->amwal->getConfigData("terminal_id"),
            "transactionId" => $params['transactionId'],
            "transactionTime" => $params['transactionTime']
        ];

        // Generate secure hash value
        $secureHashValue = $this->helper->generateStringForFilter($integrityParameters, $this->amwal->getConfigData("secret_key"));
        $integrityParameters['secureHashValue'] = $secureHashValue;
        $integrityParameters['secureHashValueOld'] = $params['secureHashValue'];
<<<<<<< HEAD

        $info = 'Old Hash -- ' . $params['secureHashValue'] . '  New Hash -- ' . $secureHashValue;
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, $info);
        $this->saveCardToken($customerId, $order);
        // Validate secure hash
        if ($secureHashValue != $params['secureHashValue']) {
            $this->redirectWithError(__('Sorry, you are accessing wrong information'));
            return $response;
=======
        // Log the callback response
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Callback Response: ', print_r($integrityParameters, 1));
        // Validate secure hash
        if (($params['responseCode'] === '00' && $secureHashValue == $params['secureHashValue'])) {
            $isPaymentApproved = true;
>>>>>>> c5b80109967affd71a1cab6a7e6cc7a5502d2bfc
        }
        $info = "<br/> Transaction ID: " . AmwalPay::sanitizeVar('transactionId') . "<br/> Transaction Msg: <b style='color:DodgerBlue;'>" . $params['message'] . "</b>";

        // Handle payment approval or failure
        if ($params['responseCode'] === '00') {
            $response_url = $baseUrl . 'checkout/onepage/success';
            $this->markOrderAsPaid($order, $orderId, $params['transactionId'], __('AmwalPay Payment Successful').' '.$info);
            $response->setUrl($response_url);
            return $response;
        } else {
            $error = __('AmwalPay Payment Failed.');
            $msg = __('Order #') . $orderId . ' ' . $error;
            $this->cancelOrder($order, $msg);
            $this->redirectWithError($msg);
        }
    }
    protected function callCloudNotification()
    {

        $post_data = file_get_contents('php://input');
        $json_data = json_decode($post_data, true);
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'In Cloud Notification Response: ', print_r($json_data, 1));

        // Validate payload
        if (empty($json_data)) {
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Empty JSON data');
            return 'Invalid payload';
        }
        // Validate Merchant & Terminal IDs
        if ($json_data['MerchantId'] != $this->amwal->getConfigData("merchant_id") || $json_data['TerminalId'] != $this->amwal->getConfigData("terminal_id")) {
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Merchant/Terminal mismatch');
            return 'Configuration mismatch';
        }
        list($orderId, ) = explode('_', $json_data['MerchantReference']);
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (empty($order)) {
            return "Order is not found with #: $orderId";
        }
        // Integrity check
        $integrityParameters = [
            "Amount" => $json_data['Amount'],
            "AuthorizationDateTime" => $json_data['AuthorizationDateTime'],
            "CurrencyId" => $json_data['CurrencyId'],
            "DateTimeLocalTrxn" => $json_data['DateTimeLocalTrxn'],
            "MerchantId" => $this->amwal->getConfigData("merchant_id"),
            "MerchantReference" => $json_data['MerchantReference'],
            "Message" => $json_data['Message'],
            "PaidThrough" => $json_data['PaidThrough'],
            "ResponseCode" => $json_data['ResponseCode'],
            "SystemReference" => $json_data['SystemReference'],
            "TerminalId" => $this->amwal->getConfigData("terminal_id"),
            "TxnType" => $json_data['TxnType']
        ];

        $secureHashValue = $this->helper->generateStringForFilter($integrityParameters, $this->amwal->getConfigData("secret_key"));
        $integrityParameters['secureHashValue'] = $secureHashValue;
        $integrityParameters['secureHashValueOld'] = $json_data['SecureHash'];
        $info = 'Old Hash -- ' . $json_data['SecureHash'] . '  New Hash -- ' . $secureHashValue;
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, $info);
        if ($secureHashValue != $json_data['SecureHash']) {
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Invalid Hash');
            return 'Invalid Hash';
        }

        $info = "<br/>Transaction ID: {$json_data['SystemReference']}<br/> Transaction done by: {$json_data['PaidThrough']} </br> Transaction Msg: <b style='color:DodgerBlue;'>{$json_data['Message']}</b>";

        $msg = 'AmwalPay Cloud Notification for Order #' . $orderId;
        if ($json_data['ResponseCode'] === '00') {
            $note = 'AmwalPay Payment Successful ';
            $msg = $msg . ' ' . $note;
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, $msg);
            $this->markOrderAsPaid($order, $orderId, $json_data['SystemReference'], $info);
        } else {
            $note = 'AmwalPay Payment Failed ';
            $msg = $msg . ' ' . $note . "<br/>";
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, $msg);
            $this->cancelOrder($order, $note . $info);
        }
        return "Order updated: $orderId";

    }
    public function saveCardToken($customerTokenId, $order)
    {
        if ($order->getCustomerId()) {
            $customer_id = $order->getCustomerId();
            if (!empty($customerTokenId) && $customerTokenId !== 'null') {
                $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, ' Customer save Card Token , for User -- ' . $customer_id, json_encode($customerTokenId));
                $existingToken = $this->amwalpayCardsCollection->create()
                    ->addFieldToFilter('user_id', $customer_id)
                    ->addFieldToFilter('merchant_id', $this->amwal->getConfigData("merchant_id"))
                    ->addFieldToFilter('environment', $this->amwal->getConfigData("environment"))
                    ->getFirstItem();

                if ($existingToken->getId()) {
                    // Update existing token
                    $existingToken->setData('token', $customerTokenId);
                    $existingToken->save();
                } else {
                    // Add a new record by creating a fresh instance of the model
                    $newToken = $this->amwalpayCardsTokenModel->setData(
                        [
                            'user_id' => $customer_id,
                            'token' => $customerTokenId,
                            'merchant_id' => $this->amwal->getConfigData("merchant_id"),
                            'environment' => $this->amwal->getConfigData("environment"),
                        ]
                    );
                    $newToken->save(); // Save the new token
                }
                return "Token Saved: user id:" . $customer_id;
            }
        }
    }

    /**
     * Mark order as paid and update its status
     *
     * @param Order  $order
     * @param string $orderId
     * @param string $transId
     * @param string|null $info
     */
    public function markOrderAsPaid($order, $orderId, $transId, $info = null)
    {
        $msg = __('Order #') . $orderId . ' ' . $info;
        $this->messageManager->addSuccess($msg);
        $complete_paid_order = $this->amwal->getConfigData("complete_paid_order");
        $status = $complete_paid_order ? \Magento\Sales\Model\Order::STATE_COMPLETE : \Magento\Sales\Model\Order::STATE_PROCESSING;

        // Set order status
        $order->setState($status)
            ->setStatus($status)
            ->addStatusHistoryComment($msg)
            ->setIsCustomerNotified(true)
            ->save();

        // Set payment details
        $payment = $order->getPayment();
        $payment->setTransactionId($transId);
        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
        $order->save();

        // Create invoice and send email
        $this->addMagentoInvoice($order, $orderId, $transId);
        $this->email->send($order);
    }

    /**
     * Create a Magento invoice for the order
     *
     * @param Order  $order
     * @param string $orderId
     * @param string $transId
     */
    public function addMagentoInvoice($order, $orderId, $transId)
    {
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice->getTotalQty()) {
                $invoice->setTransactionId($transId);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $transaction = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                $transaction->save();
            } else {
                $order->addStatusHistoryComment(__("AmwalPay: Cannot create the Magento invoice without products."));
            }
        } else {
            $order->addStatusHistoryComment(__("AmwalPay: Cannot create the Magento invoice."));
        }
    }

    /**
     * Cancel the order and restore the Magento quote for failed payment
     *
     * @param Order  $order
     * @param string $error
     */
    public function cancelOrder($order, $error)
    {
        try {
            $quoteFactory = $this->_objectManager->create('\Magento\Quote\Model\QuoteFactory');
            $quote = $quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->checkoutSession->replaceQuote($quote);
                $this->checkoutSession->restoreQuote();

                if ($order->getState() === $order::STATE_PENDING_PAYMENT) {
                    $order->registerCancellation($error);
                } else {
                    $order->addStatusHistoryComment($error);
                }
                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED)
                    ->save();

                $this->redirectWithError($error);
            } else {
                $this->redirectWithError(__('Error, There is something wrong with the quote!'));
            }
        } catch (\Exception $e) {
            $this->redirectWithError(__('An error occurred while canceling the order: ') . $e->getMessage());
        }
    }

    /** 
     * Redirect to cart page with an error message
     * 
     * @param string $error
     */
    public function redirectWithError($error)
    {
        $this->messageManager->addErrorMessage($error);
        $this->_redirect('checkout/cart', ['_secure' => false]);
    }

    /**
     * Create a CSRF validation exception if needed
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null; // No CSRF validation needed
    }

    /**
     * Validate the request for CSRF
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true; // CSRF validation is always true
    }
}