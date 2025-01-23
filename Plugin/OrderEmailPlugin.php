<?php

namespace Amwal\Pay\Plugin;

use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;

class OrderEmailPlugin
{
    /**
     * Don't send email in case of amwalpay is used and the order is pending
     *
     * @param  OrderSender $subject
     * @param  callable    $proceed
     * @param  Order       $order
     * @param  boolean     $forceSyncMode
     * @return boolean
     */
    public function aroundSend(OrderSender $subject, callable $proceed, Order $order, $forceSyncMode = false)
    {
        $payment = $order->getPayment()->getMethodInstance()->getCode();

        $paymentCode = ($payment === 'amwal_pay');
        if ($paymentCode &&  ($order->getState() === Order::STATE_NEW || $order->getState() === Order::STATE_PENDING_PAYMENT)) {
            return false;
        }
        return $proceed($order, $forceSyncMode);
    }
}
