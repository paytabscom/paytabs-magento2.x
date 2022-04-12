<?php

namespace PayTabs\PayPage\Logger\Handler;

use Magento\Framework\Filesystem\Driver\File as FileSystem;


class PayTabsLogger extends \Monolog\Logger
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new PayTabsLogger();
        }

        return self::$instance;
    }


    private function __construct()
    {
        $handler = new ErrorHandler(new FileSystem(), null, PAYTABS_DEBUG_FILE);

        parent::__construct('PayTabs', [$handler]);
    }
}
