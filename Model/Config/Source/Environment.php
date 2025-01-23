<?php

namespace Amwal\Pay\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface
{
    /**
     * Provide available options as a value-label pair
     *
     * @return array An array of options with value-label pairs
     */
    public function toOptionArray()
    {
        try {
            return [
                ['value' => 'prod', 'label' => __('Production')],
                ['value' => 'sit', 'label' => __('SIT')],
                ['value' => 'uat', 'label' => __('UAT')],
            ];
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            error_log('Error retrieving environment options: ' . $e->getMessage());
            return []; // Return an empty array in case of an error
        }
    }

    /**
     * Provide available options as a key-value pair
     *
     * @return array An associative array of options
     */
    public function toArray()
    {
        try {
            return [
                'prod' => __('Production'),
                'sit'  => __('SIT'),
                'uat'  => __('UAT'),
            ];
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            error_log('Error retrieving environment options as array: ' . $e->getMessage());
            return []; // Return an empty array in case of an error
        }
    }
}