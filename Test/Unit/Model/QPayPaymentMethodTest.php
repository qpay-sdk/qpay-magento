<?php

namespace QPay\Payment\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use QPay\Payment\Model\QPayPaymentMethod;

class QPayPaymentMethodTest extends TestCase
{
    private function createPaymentMethod(): QPayPaymentMethod
    {
        return new QPayPaymentMethod();
    }

    public function test_payment_code_is_qpay(): void
    {
        $method = $this->createPaymentMethod();
        $this->assertEquals('qpay', $method->getCode());
    }

    public function test_is_not_offline(): void
    {
        $method = $this->createPaymentMethod();
        $this->assertFalse($method->isOffline());
    }

    public function test_can_capture(): void
    {
        $method = $this->createPaymentMethod();
        $this->assertTrue($method->canCapture());
    }

    public function test_cannot_refund(): void
    {
        $method = $this->createPaymentMethod();
        $this->assertFalse($method->canRefund());
    }
}
