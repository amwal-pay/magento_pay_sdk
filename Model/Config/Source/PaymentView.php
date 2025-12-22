<?php

namespace Amwal\Pay\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class PaymentView implements ArrayInterface
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
                ['value' => '1', 'label' => __('Onsite Pop Up')],
                ['value' => '2', 'label' => __('Onsite Full Page')],
                ['value' => '3', 'label' => __('Offsite Redirection')],
            ];
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            error_log('Error retrieving payment view options: ' . $e->getMessage());
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
                '1' => __('Onsite Pop Up'),
                '2'  => __('Onsite Full Page'),
                '3'  => __('Offsite Redirection'),
            ];
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            error_log('Error retrieving payment view options as array: ' . $e->getMessage());
            return []; // Return an empty array in case of an error
        }
    }
}