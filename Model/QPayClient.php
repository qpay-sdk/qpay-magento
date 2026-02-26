<?php

namespace QPay\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;

class QPayClient
{
    private ScopeConfigInterface $config;
    private Curl $curl;
    private ?string $token = null;
    private int $tokenExpiry = 0;

    public function __construct(ScopeConfigInterface $config, Curl $curl)
    {
        $this->config = $config;
        $this->curl = $curl;
    }

    private function getConfigValue(string $field): string
    {
        return (string) $this->config->getValue('payment/qpay/' . $field);
    }

    private function getToken(): string
    {
        if ($this->token && time() < $this->tokenExpiry) return $this->token;

        $this->curl->addHeader('Authorization', 'Basic ' . base64_encode($this->getConfigValue('username') . ':' . $this->getConfigValue('password')));
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($this->getConfigValue('base_url') . '/v2/auth/token', '');

        $data = json_decode($this->curl->getBody(), true);
        $this->token = $data['access_token'] ?? '';
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600) - 30;
        return $this->token;
    }

    public function createInvoice(array $data): ?array
    {
        $token = $this->getToken();
        $this->curl->addHeader('Authorization', 'Bearer ' . $token);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($this->getConfigValue('base_url') . '/v2/invoice', json_encode($data));
        return json_decode($this->curl->getBody(), true);
    }

    public function checkPayment(string $invoiceId): ?array
    {
        $token = $this->getToken();
        $this->curl->addHeader('Authorization', 'Bearer ' . $token);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($this->getConfigValue('base_url') . '/v2/payment/check', json_encode([
            'object_type' => 'INVOICE',
            'object_id' => $invoiceId,
        ]));
        return json_decode($this->curl->getBody(), true);
    }
}
