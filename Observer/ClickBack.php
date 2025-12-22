<?php

namespace Amwal\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;

class ClickBack implements ObserverInterface
{

    /**
     * @var checkoutSession
     */
    private $checkoutSession;

    /**
     * @param Session $checkoutSession
     */
    public function __construct(Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Restore quote when click on Go Back button
     *
     * @param  Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($lastRealOrder->getPayment()) {
            if ($lastRealOrder->getData('state') === Order::STATE_PENDING_PAYMENT && $lastRealOrder->getData('status') === Order::STATE_PENDING_PAYMENT) {
                $this->checkoutSession->restoreQuote();
            }
        }
    }
}
