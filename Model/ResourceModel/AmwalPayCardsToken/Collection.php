<?php 
namespace Amwal\Pay\Model\ResourceModel\AmwalPayCardsToken;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
	public function _construct(){
		$this->_init("Amwal\Pay\Model\AmwalPayCardsToken","Amwal\Pay\Model\ResourceModel\AmwalPayCardsToken");
	}
}