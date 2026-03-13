<?php

namespace QPay\Payment\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use QPay\Payment\Model\QPayClient;

class Callback implements HttpGetActionInterface
{
    private RequestInterface $request;
    private RawFactory $rawFactory;
    private QPayClient $qpayClient;

    public function __construct(RequestInterface $request, RawFactory $rawFactory, QPayClient $qpayClient)
    {
        $this->request = $request;
        $this->rawFactory = $rawFactory;
        $this->qpayClient = $qpayClient;
    }

    public function execute()
    {
        $paymentId = $this->request->getParam('qpay_payment_id', '');

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/plain');

        if (empty($paymentId)) {
            $result->setHttpResponseCode(400);
            $result->setContents('Missing qpay_payment_id');
            return $result;
        }

        $this->qpayClient->checkPayment($paymentId);

        $result->setHttpResponseCode(200);
        $result->setContents('SUCCESS');
        return $result;
    }
}
