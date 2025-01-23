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
    protected $online;
    protected $OriginPrice;
    protected $api_id;
    protected $api_has_iframe;
    protected $api_has_items;
    protected $api_has_delivery;
    protected $api_handles_shipping_fees;
    protected $response;
    protected $checkoutSession;
    protected $localeResolver;
    protected $helper;
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\App\ResourceConnection $resource,
        \Amwal\Pay\Model\AmwalPay $online,
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
        $this->online = $online;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
        $this->localeResolver = $localeResolver;
        $this->helper = $helper;
    }
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    public function execute()
    {
        // echo "ss";exit;
        $this->response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        $this->response->setHttpResponseCode(200);
        $order_id = $this->checkoutSession->getLastOrderId();
        try {
            $order = $this->order->load($order_id);
            if ($order) {
                $fullLocale = $this->localeResolver->getLocale();
                $locale = substr($fullLocale, 0, 2);
                $datetime = date('YmdHis');
                $merchant_id = $this->online->getConfigData('merchant_id');
                $terminal_id = $this->online->getConfigData('terminal_id');
                $hmac_key = $this->online->getConfigData('secret_key');
                $debug = $this->online->getConfigData('debug');
                $amount = $order->getBaseTotalDue();
                $merchantReference = $order->getIncrementId() . '_' . date("ymds");
                $secret_key = $this->helper->generateString(
                    $amount,
                    512,
                    $merchant_id,
                    $merchantReference,
                    $terminal_id,
                    $hmac_key,
                    $datetime
                );
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
                $this->helper->addLogs($debug, AMWAL_DEBUG_FILE, 'Payment Request: ', print_r($data, 1));

                $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
                    ->addStatusHistoryComment(__("Order Created: Awaiting payment"))
                    ->save();

                $this->response->setData([
                    'success' => true,
                    'jsonData' => $data,
                ]);
            }

        } catch (\Exception $e) {
            $this->response->setData([
                'success' => false,
                'detail' => $e->getMessage(),

            ]);
        }

        return $this->response;
    }
}