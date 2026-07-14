<?php

if (!defined('AMWAL_DEBUG_FILE')) {
    define(
        'AMWAL_DEBUG_FILE',
        defined('BP')
            ? BP . '/var/log/amwalpay.log'
            : sys_get_temp_dir() . '/amwalpay.log'
    );
}

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Amwal_Pay',
    __DIR__
);