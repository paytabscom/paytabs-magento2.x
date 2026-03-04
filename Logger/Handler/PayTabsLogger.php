<?php

namespace PayTabs\PayPage\Logger\Handler;

use Magento\Framework\Filesystem\Driver\File as FileSystem;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;

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
        $handler = new ErrorHandler(new FileSystem(), null, PaytabsCore::getLogFile());

        parent::__construct('PayTabs', [$handler]);
    }
}
