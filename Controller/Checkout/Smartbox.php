<?php
namespace Amwal\Pay\Controller\Checkout;

/**
 * AmwalPay Payment Method
 */
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Framework\Locale\ResolverInterface;
use Amwal\Pay\Helper\AmwalPay;
use Magento\Store\Model\StoreManagerInterface;
use Amwal\Pay\Model\ResourceModel\AmwalPayCardsToken\CollectionFactory;
use Magento\Catalog\Helper\Data as TaxHelper;

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
    protected $storeManager;
    protected $amwalpayCardsCollection;
    protected $scopeConfig;
    protected $taxHelper;
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
        AmwalPay $helper,
        StoreManagerInterface $storeManager,
        CollectionFactory $amwalpayCardsCollection,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        TaxHelper $taxHelper,
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
        $this->storeManager = $storeManager;
        $this->amwalpayCardsCollection = $amwalpayCardsCollection;
        $this->scopeConfig = $scopeConfig;
        $this->taxHelper = $taxHelper;
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
                $payment_view = $this->amwal->getConfigData('payment_view');
                $contact_info_type = $this->amwal->getConfigData('contact_info_type');
                $has_items = $this->amwal->getConfigData('has_items');
                $amount = $order->getBaseTotalDue();
                $merchantReference = $order->getIncrementId() . '_' . date("ymds");
                if ($order->getCustomerId()) {
                    // echo "<pre>";print_r($this->getUserTokens($order->getCustomerId()));exit;
                    $sessionToken = $this->getUserTokens($order->getCustomerId());
                } else {
                    $sessionToken = '';
                }

                // Generate secure hash
                $secret_key = $this->helper->generateString(
                    $amount,
                    512,
                    $merchant_id,
                    $merchantReference,
                    $terminal_id,
                    $hmac_key,
                    $datetime,
                    $sessionToken
                );
                $urls = (isset($payment_view) && in_array($payment_view, [1, 2]))
                    ? ['', '']
                    : [$this->storeManager->getStore()->getBaseUrl() . 'amwal/checkout/callback', $this->storeManager->getStore()->getBaseUrl() . 'amwal/checkout/restorequote?offsite=1'];

                list($returnUrl, $cancelUrl) = $urls;
                // Prepare data for the payment request
                $data = [
                    'AmountTrxn' => "$amount",
                    'MerchantReference' => "$merchantReference",
                    'MID' => $merchant_id,
                    'TID' => $terminal_id,
                    'CurrencyId' => 512,
                    'LanguageId' => $locale,
                    'SecureHash' => $secret_key,
                    'TrxDateTime' => $datetime,
                    'PaymentViewType' => (isset($payment_view) && in_array($payment_view, [1, 2])) ? $payment_view : 1,
                    'RequestSource' => 'Checkout_Magento',
                    'SessionToken' => $sessionToken,
                    'ReturnUrl' => $returnUrl,
                    'CancelUrl' => $cancelUrl,
                    'ContactInfoType' => isset($contact_info_type) ? $contact_info_type : '1'
                ];
                if ($has_items) {
                    $data['OrderItems'] = $this->getInvoiceItems($order);
                }
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
    public function getUserTokens($customer_id)
    {
        $token = '';

        $result = $this->amwalpayCardsCollection->create()
            ->addFieldToFilter('user_id', $customer_id)
            ->addFieldToFilter('merchant_id', $this->amwal->getConfigData("merchant_id"))
            ->addFieldToFilter('environment', $this->amwal->getConfigData("environment"))
            ->getFirstItem();

        // return 
        if (isset($result) && !empty($result)) {

            $data['customerId'] = $result['token'];
            $data['merchantId'] = $this->amwal->getConfigData("merchant_id");
            $data['secureHashValue'] = $this->helper->generateStringForFilter($data, $this->amwal->getConfigData("secret_key"));
            $webhook_url = $this->helper->getWebhookUrl($this->amwal->getConfigData("environment"));
            $sessionTokenRes = $this->helper->HttpRequest($webhook_url . 'Customer/GetSmartboxDirectCallSessionToken', $data);
            $this->helper->addLogs($this->amwal->getConfigData("debug"), AMWAL_DEBUG_FILE, 'In api Customer/GetSmartboxDirectCallSessionToken: ', print_r($sessionTokenRes, 1));
            if (isset($sessionTokenRes) && isset($sessionTokenRes->data) && isset($sessionTokenRes->data->sessionToken)) {
                $token = $sessionTokenRes->data->sessionToken;
            }
        }
        return $token;
    }
    public function getInvoiceItems($order)
    {
        $items = [];

        $storeId = $order->getStoreId();
        $priceIncTax = $this->scopeConfig->getValue(
            'tax/calculation/price_includes_tax',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $amount = 0;

        // ----------------------------------------------------
        // ✅ ORDER ITEMS (Correct Price from Order, NOT Product)
        // ----------------------------------------------------
        foreach ($order->getAllVisibleItems() as $item) {

            $qty = (int) $item->getQtyOrdered();

            // Use order price (already correct including discounts)
            $unitPrice = $priceIncTax
                ? $item->getPriceInclTax()
                : $item->getPrice();

            $unitPrice = round($unitPrice, 3);

            $items[] = [
                'Name' => mb_strimwidth($item->getName(), 0, 45, '...'),
                'DescriptionTwo' => 'Quantity: ' . $qty,
                'Price' => 'OMR ' . $unitPrice,
            ];

            $amount += ($unitPrice * $qty);
        }


        // ----------------------------------------------------
        // ✅ DISCOUNT
        // ----------------------------------------------------
        $discount = abs((float) $order->getBaseDiscountAmount());

        if ($discount > 0) {
            $items[] = [
                'Name' => 'Discount Amount',
                'DescriptionTwo' => 'Quantity: 1',
                'Price' => 'OMR -' . $discount,
            ];
            $amount -= $discount;
        }


        // ----------------------------------------------------
        // ✅ SHIPPING
        // ----------------------------------------------------
        $shipping = (float) $order->getBaseShippingInclTax();

        if ($shipping > 0) {
            $items[] = [
                'Name' => 'Shipping Amount',
                'DescriptionTwo' => 'Quantity: 1',
                'Price' => 'OMR ' . $shipping,
            ];
            $amount += $shipping;
        }


        // ----------------------------------------------------
        // ✅ ADDITIONAL FEES (MageWorx or others)
        // ----------------------------------------------------
        $extraFees = (float) $order->getBaseMageworxFeeAmount();
        if ($extraFees > 0) {
            $items[] = [
                'Name' => 'Additional Fees',
                'DescriptionTwo' => 'Quantity: 1',
                'Price' => 'OMR ' . $extraFees,
            ];
            $amount += $extraFees;
        }

        $productFees = (float) $order->getBaseMageworxProductFeeAmount();
        if ($productFees > 0) {
            $items[] = [
                'Name' => 'Additional Product Fees',
                'DescriptionTwo' => 'Quantity: 1',
                'Price' => 'OMR ' . $productFees,
            ];
            $amount += $productFees;
        }


        // ----------------------------------------------------
        // ✅ TAX (exact from order)
        // ----------------------------------------------------
        $tax = (float) $order->getBaseTaxAmount();

        if ($tax > 0) {
            $items[] = [
                'Name' => 'Tax Amount',
                'DescriptionTwo' => 'Quantity: 1',
                'Price' => 'OMR ' . $tax,
            ];
            $amount += $tax;
        }

        return $items;
    }
}