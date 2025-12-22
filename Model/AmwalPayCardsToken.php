<?php
namespace Amwal\Pay\Model;

class AmwalPayCardsToken extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init('Amwal\Pay\Model\ResourceModel\AmwalPayCardsToken');
    }
}