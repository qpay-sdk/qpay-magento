<?php

namespace QPay\Payment\Model;

use Magento\Payment\Model\Method\AbstractMethod;

class QPayPaymentMethod extends AbstractMethod
{
    protected $_code = 'qpay';
    protected $_isOffline = false;
    protected $_canCapture = true;
    protected $_canRefund = false;
}
