<?php
namespace Amwal\Pay\Model\ResourceModel;
class AmwalPayCardsToken extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb 
{
    protected function _construct()
    {
        $this->_init('amwalpay_cards_token', 'id');
    }
}