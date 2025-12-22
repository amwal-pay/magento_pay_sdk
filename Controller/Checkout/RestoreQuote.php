<?php
namespace Amwal\Pay\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;

class RestoreQuote extends Action
{
    protected $checkoutSession;

    public function __construct(
        Context $context,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
            $order = $this->checkoutSession->getLastRealOrder();
            $quoteFactory = $this->_objectManager->create('\Magento\Quote\Model\QuoteFactory');
            $quote = $quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->checkoutSession->replaceQuote($quote);
                $this->checkoutSession->restoreQuote();
                $error = 'AmwalPay: Press Cancel Button On Payment Page';
                if ($order->getState() === $order::STATE_PENDING_PAYMENT) {
                    $order->registerCancellation($error);
                } else {
                    $order->addStatusHistoryComment($error);
                }
            } 
            $params = $this->getRequest()->getParams();
            if(isset($params['offsite'])){
                $this->_redirect('checkout/cart', ['_secure' => false]);
            }
            return $this->getResponse()->representJson(json_encode(['success' => true]));
    }
}
