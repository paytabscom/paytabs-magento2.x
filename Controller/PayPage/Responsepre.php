<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

use ClickPay\PayPage\Gateway\Http\ClickpayCore;
use ClickPay\PayPage\Gateway\Http\ClickpayHelper;


/**
 * Class Index
 */
class Responsepre extends Action
{
    private $Clickpay;

    protected $quoteRepository;


    /**
     * @param Context $context
     */
    public function __construct(
        Context $context,

        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\Controller\Result\Raw $rawResultFactory

    ) {
        parent::__construct($context);

        $this->quoteRepository = $quoteRepository;
        $this->rawResultFactory = $rawResultFactory;

        $this->Clickpay = new \ClickPay\PayPage\Gateway\Http\Client\Api;
        new ClickpayCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        ClickpayHelper::log("Return pre triggered", 1);

        //

        $_p_tran_ref = 'tranRef';
        $_p_cart_id = 'cartId';
        $transactionId = $this->getRequest()->getParam($_p_tran_ref, null);
        $pOrderId = $this->getRequest()->getParam($_p_cart_id, null);

        ClickpayHelper::log("Return pre [$pOrderId] [$transactionId]", 1);

        //

        // $pOrderId = substr($pOrderId, 1);

        /*
        $quote = $this->quoteRepository->get($pOrderId);
        $payment = $quote->getPayment();
        $payment
            ->setTransactionId($transactionId)
            ->setAdditionalInformation(
                'pt_registered_transaction',
                $transactionId
            )
            ->save();*/
        //

        $result = $this->rawResultFactory; //->create();
        $result->setContents('Done - Loading...');

        return $result;
    }
}