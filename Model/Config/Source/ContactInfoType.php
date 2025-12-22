<?php

namespace Amwal\Pay\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ContactInfoType implements ArrayInterface
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
                ['value' => '1', 'label' => __('All')],
                ['value' => '2', 'label' => __('Email Only')],
                ['value' => '3', 'label' => __('Phone Only')],
                ['value' => '4', 'label' => __('None')],
            ];
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            error_log('Error retrieving Contact Info Type options: ' . $e->getMessage());
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
                '1' => __('All'),
                '2'  => __('Email Only'),
                '3'  => __('Phone Only'),
                '4'  => __('None'),
            ];
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            error_log('Error retrieving Contact Info Type options as array: ' . $e->getMessage());
            return []; // Return an empty array in case of an error
        }
    }
}