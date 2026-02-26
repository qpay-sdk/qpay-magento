<?php

/**
 * PHPUnit bootstrap for QPay Magento 2 module tests.
 *
 * Stubs Magento framework interfaces/classes so tests can run
 * without a full Magento 2 installation.
 */

// --- Magento Framework stubs ---

namespace Magento\Framework\App\Config;

interface ScopeConfigInterface
{
    public function getValue(string $path, string $scopeType = 'default', $scopeCode = null);
    public function isSetFlag(string $path, string $scopeType = 'default', $scopeCode = null): bool;
}

namespace Magento\Framework\HTTP\Client;

class Curl
{
    private array $headers = [];
    private string $body = '';
    private int $status = 200;

    /** @var callable|null */
    public $_postCallback = null;

    public function addHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function post(string $url, $body): void
    {
        if ($this->_postCallback) {
            $result = ($this->_postCallback)($url, $body, $this->headers);
            $this->body = $result['body'] ?? '';
            $this->status = $result['status'] ?? 200;
            // Reset headers after each request to match real Curl behavior
            $this->headers = [];
        }
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }
}

namespace Magento\Payment\Model\Method;

abstract class AbstractMethod
{
    protected string $_code = '';
    protected bool $_isOffline = true;
    protected bool $_canCapture = false;
    protected bool $_canRefund = false;

    public function getCode(): string
    {
        return $this->_code;
    }

    public function isOffline(): bool
    {
        return $this->_isOffline;
    }

    public function canCapture(): bool
    {
        return $this->_canCapture;
    }

    public function canRefund(): bool
    {
        return $this->_canRefund;
    }
}

namespace Magento\Framework\Component;

class ComponentRegistrar
{
    const MODULE = 'module';
    public static function register(string $type, string $name, string $path): void {}
}

// Bring in the actual source files
namespace;

require_once dirname(__DIR__, 2) . '/Model/QPayClient.php';
require_once dirname(__DIR__, 2) . '/Model/QPayPaymentMethod.php';
