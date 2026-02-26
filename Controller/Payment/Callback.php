<?php

namespace QPay\Payment\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use QPay\Payment\Model\QPayClient;

class Callback implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private QPayClient $qpayClient;

    public function __construct(RequestInterface $request, JsonFactory $jsonFactory, QPayClient $qpayClient)
    {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->qpayClient = $qpayClient;
    }

    public function execute()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $invoiceId = $body['invoice_id'] ?? '';

        $result = $this->jsonFactory->create();
        if (empty($invoiceId)) {
            return $result->setData(['error' => 'Missing invoice_id']);
        }

        $check = $this->qpayClient->checkPayment($invoiceId);
        $paid = !empty($check['rows']);
        return $result->setData(['status' => $paid ? 'paid' : 'unpaid']);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
