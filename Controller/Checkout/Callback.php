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

	public function execute()
	{
		$response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
		$params = $this->getRequest()->getParams();

		$orderId = substr($params['merchantReference'], 0, -9);
		$order = $this->orderFactory->create()->loadByIncrementId($orderId);
		
		if (empty($order->getId())) {
			$this->redirectWithError('Sorry, you are accessing wrong information');
		}

		$storeCode = $order->getStore()->getCode();
		$baseUrl = $this->storeManager->getStore($storeCode)->getBaseUrl();

		$isPaymentApproved = false;

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

		$this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Callback Response: ', print_r($integrityParameters, 1));
		$secureHashValue = $this->helper->generateStringForFilter($integrityParameters, $this->amwal->getConfigData("secret_key"));
		$integrityParameters['secureHashValue'] = $secureHashValue;
		$integrityParameters['secureHashValueOld'] = $params['secureHashValue'];

		if ($secureHashValue == $params['secureHashValue']) {
			$isPaymentApproved = true;
		}
		// echo "<pre>";print_r($isPaymentApproved);exit;

		$this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'Payment', $isPaymentApproved ? 'Approved' : 'Canceled');
		if ($isPaymentApproved) {

			$response_url = $baseUrl . 'checkout/onepage/success';
			$this->markOrderAsPaid($order, $orderId, $params['transactionId'], 'Payment successful');
			$response->setUrl($response_url);
			return $response;

		} else {

			$error = 'Sorry, Your order has been failed.';
			$msg = 'Order #' . $orderId . ' ' . $error;
			$this->cancelOrder($order, $msg);
			$this->redirectWithError($msg);
		}
	}


	/**
	 * Mark order as paid
	 *
	 * @param Order  $order
	 * @param string $orderId
	 */
	public function markOrderAsPaid($order, $orderId, $transId, $info = null)
	{
		$msg = 'Order #' . $orderId . ' ' . $info;
		$this->messageManager->addSuccess($msg);
		$complete_paid_order = $this->amwal->getConfigData("complete_paid_order");
		if ($complete_paid_order) {
			$status = \Magento\Sales\Model\Order::STATE_COMPLETE;
		} else {
			$status = \Magento\Sales\Model\Order::STATE_PROCESSING;
		}
		//set order status
		$order->setState($status)
			->setStatus($status)
			->addStatusHistoryComment($msg)
			->setIsCustomerNotified(true);

		$order->save();
		//set payment
		$payment = $order->getPayment();
		$payment->setTransactionId($transId);
		$payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
		$order->save();
		$this->addMagentoInvoice($order, $orderId, $transId);
		//send email
		$this->email->send($order);
	}

	/**
	 * Create magento invoice
	 *
	 * @param Order  $order
	 * @param string $orderId
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
				$order->addStatusHistoryComment("AmwalPay: Can not create the Magento invoice without products.");
			}
		} else {
			$order->addStatusHistoryComment("AmwalPay: Can not create the Magento invoice.");
		}
	}

	/**
	 * Cancel the order and restore the magento qoute for failed Payment
	 *
	 * @param Order  $order
	 * @param string $error     
	 * @param boolean $webhook
	 */
	public function cancelOrder($order, $error)
	{
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
				->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
			$order->save();

			$this->redirectWithError($error);

		} else {
			$this->redirectWithError('Error, There is something went wong with the qoute!');
		}
	}

	/** 
	 * redirect to cart page with error message
	 * 
	 * @param string  $error
	 */
	public function redirectWithError($error)
	{
		$this->messageManager->addErrorMessage($error);
		$this->_redirect('checkout/cart', ['_secure' => false]);
	}

	public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
	{
		return null;
	}

	public function validateForCsrf(RequestInterface $request): ?bool
	{
		return true;
	}
}