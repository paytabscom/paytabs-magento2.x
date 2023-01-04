<?php

namespace ClickPay\PayPage\Logger\Handler;

use Magento\Framework\Filesystem\Driver\File as FileSystem;


class ClickPayLogger extends \Monolog\Logger
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new ClickPayLogger();
        }

        return self::$instance;
    }


    private function __construct()
    {
        $handler = new ErrorHandler(new FileSystem(), null, ClickPay_DEBUG_FILE);

        parent::__construct('ClickPay', [$handler]);
    }
}
