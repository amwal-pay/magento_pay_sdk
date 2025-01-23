<?php
namespace Amwal\Pay\Controller\Checkout;

/**
 * AmwalPay Payment Method
 */
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Framework\Locale\ResolverInterface;
use Amwal\Pay\Helper\AmwalPay;

class Smartbox extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $statusFactory;
    protected $order;
    protected $statusResourceFactory;
    protected $resource;
    protected $amwal;
    protected $response;
    protected $checkoutSession;
    protected $localeResolver;
    protected $helper;

    /**
     * Constructor to initialize dependencies
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Amwal\Pay\Model\AmwalPay $amwal
     * @param StatusFactory $statusFactory
     * @param StatusResourceFactory $statusResourceFactory
     * @param ResolverInterface $localeResolver
     * @param AmwalPay $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\App\ResourceConnection $resource,
        \Amwal\Pay\Model\AmwalPay $amwal,
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory,
        ResolverInterface $localeResolver,
        AmwalPay $helper
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->resultFactory = $context->getResultFactory();
        $this->order = $order;
        $this->resource = $resource;
        $this->amwal = $amwal;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
        $this->localeResolver = $localeResolver;
        $this->helper = $helper;
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

    /**
     * Execute the payment process
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $this->response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $this->response->setHttpResponseCode(200);
        $order_id = $this->checkoutSession->getLastOrderId();

        try {
            // Load the order using the order ID
            $order = $this->order->load($order_id);
            if ($order) {
                // Get locale and other necessary data
                $fullLocale = $this->localeResolver->getLocale();
                $locale = substr($fullLocale, 0, 2);
                $datetime = date('YmdHis');
                $merchant_id = $this->amwal->getConfigData('merchant_id');
                $terminal_id = $this->amwal->getConfigData('terminal_id');
                $hmac_key = $this->amwal->getConfigData('secret_key');
                $debug = $this->amwal->getConfigData('debug');
                $amount = $order->getBaseTotalDue();
                $merchantReference = $order->getIncrementId() . '_' . date("ymds");

                // Generate secure hash
                $secret_key = $this->helper->generateString(
                    $amount,
                    512,
                    $merchant_id,
                    $merchantReference,
                    $terminal_id,
                    $hmac_key,
                    $datetime
                );

                // Prepare data for the payment request
                $data = (object) [
                    'AmountTrxn' => "$amount",
                    'MerchantReference' => "$merchantReference",
                    'MID' => $merchant_id,
                    'TID' => $terminal_id,
                    'CurrencyId' => 512,
                    'LanguageId' => $locale,
                    'SecureHash' => $secret_key,
                    'TrxDateTime' => $datetime,
                    'PaymentViewType' => 1,
                    'RequestSource' => 'Checkout_Magento',
                    'SessionToken' => '',
                ];

                // Log the payment request
                $this->helper->addLogs($debug, AMWAL_DEBUG_FILE, 'Payment Request: ', print_r($data, 1));

                // Update order status to pending payment
                $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
                    ->addStatusHistoryComment(__("Order Created: Awaiting payment"))
                    ->save();

                // Set response data
                $this->response->setData([
                    'success' => true,
                    'jsonData' => $data,
                ]);
            } else {
                // Handle case where order is not found
                throw new \Exception(__('Order not found.'));
            }

        } catch (\Exception $e) {
            // Handle exceptions and set error response
            $this->response->setData([
                'success' => false,
                'detail' => __('An error occurred: ') . $e->getMessage(),
            ]);
        }

        return $this->response; // Return the JSON response
    }
}