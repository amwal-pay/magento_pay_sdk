<?php

namespace Amwal\Pay\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Color extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setData('type', 'color');
        return $element->getElementHtml();
    }
}