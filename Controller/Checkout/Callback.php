<?php
namespace Amwal\Pay\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Amwal\Pay\Helper\AmwalPay;

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
    protected $hmac;
    protected $email;
    protected $storeManager;
    protected $customer;
    protected $helper;

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
        AmwalPay $helper
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->invoiceService = $invoiceService;
        $this->resultFactory = $context->getResultFactory();
        $this->storeManager = $storeManager;
        $this->amwal = $amwal;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->hmac = $amwal->getConfigData("hmac");
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
    }

    /**
     * Execute the callback process after payment
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $params = $this->getRequest()->getParams();

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
        $isPaymentApproved = false;

        // Prepare integrity parameters for secure hash validation
        $integrityParameters = [
            "amount" => $params['amount'],
            "currencyId" => $params['currencyId'],
            "customerId" => $params['customerId'],
            "customerTokenId" => $params['customerTokenId'],
            "merchantId" => $this->amwal->getConfigData("merchant_id"),
            "merchantReference" => $params['merchantReference'],
            "responseCode" => $params['responseCode'],
            "terminalId" => $this->amwal->getConfigData("terminal_id"),
            "transactionId" => $params['transactionId'],
            "transactionTime" => $params['transactionTime']
        ];

        // Log the callback response
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Callback Response: ', print_r($integrityParameters, 1));
        
        // Generate secure hash value
        $secureHashValue = $this->helper->generateStringForFilter($integrityParameters, $this->amwal->getConfigData("secret_key"));
        $integrityParameters['secureHashValue'] = $secureHashValue;
        $integrityParameters['secureHashValueOld'] = $params['secureHashValue'];

        // Validate secure hash
        if ($secureHashValue == $params['secureHashValue']) {
            $isPaymentApproved = true;
        }

        // Log payment approval status
        $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Payment', $isPaymentApproved ? 'Approved' : 'Canceled');

        // Handle payment approval or failure
        if ($isPaymentApproved) {
            $response_url = $baseUrl . 'checkout/onepage/success';
            $this->markOrderAsPaid($order, $orderId, $params['transactionId'], __('Payment successful'));
            $response->setUrl($response_url);
            return $response;
        } else {
            $error = __('Sorry, Your order has been failed.');
            $msg = __('Order #') . $orderId . ' ' . $error;
            $this->cancelOrder($order, $msg);
            $this->redirectWithError($msg);
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