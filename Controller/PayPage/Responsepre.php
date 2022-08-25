<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Gateway\Http\PaytabsHelpers;

/**
 * Class Index
 */
class Responsepre extends Action
{
    use PaytabsHelpers;

    // protected $resultRedirect;
    private $paytabs;

    protected $quoteRepository;


    /**
     * @var \Psr\Log\LoggerInterface
     */
    // protected $_logger;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        // \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Framework\Controller\Result\Raw $rawResultFactory,

        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->quoteRepository = $quoteRepository;
        // $this->jsonResultFactory = $jsonResultFactory;
        $this->rawResultFactory = $rawResultFactory;

        // $this->_logger = $logger;
        // $this->resultRedirect = $context->getResultFactory();
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {

        PaytabsHelper::log("Return pre triggered", 1);

        //

        $result = $this->rawResultFactory;//->create();
        $result->setContents('Done - Loading...');

        return $result;

        // return $this->pageFactory->create();
    }
}
