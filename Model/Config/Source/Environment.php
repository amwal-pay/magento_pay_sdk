<?php

namespace Amwal\Pay\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface
{
    /**
     * Provide available options as a value-label pair
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'prod', 'label' => __('Production')],
            ['value' => 'sit', 'label' => __('SIT')],
            ['value' => 'uat', 'label' => __('UAT')],
        ];
    }

    /**
     * Provide available options as a key-value pair
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'prod' => __('Production'),
            'sit'  => __('SIT'),
            'uat'  => __('UAT'),
        ];
    }
}
