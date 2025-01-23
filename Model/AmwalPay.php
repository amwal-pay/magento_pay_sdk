<?php
namespace Amwal\Pay\Model;
class AmwalPay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'amwal_pay';
    protected $_code = self::CODE;
}