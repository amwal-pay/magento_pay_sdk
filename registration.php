<?php

if (!defined('AMWAL_DEBUG_FILE')) {
    define('AMWAL_DEBUG_FILE', BP . '/var/log/amwalpay.log');
}

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Amwal_Pay',
    __DIR__
);