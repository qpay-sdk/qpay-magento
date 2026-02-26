<?php

namespace QPay\Payment\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use PHPUnit\Framework\TestCase;
use QPay\Payment\Model\QPayClient;

class QPayClientTest extends TestCase
{
    private array $configValues = [];
    private array $httpCalls = [];

    private function createClient(?callable $postCallback = null): QPayClient
    {
        $this->configValues = [
            'payment/qpay/base_url' => 'https://merchant.qpay.mn',
            'payment/qpay/username' => 'test_user',
            'payment/qpay/password' => 'test_pass',
            'payment/qpay/invoice_code' => 'TEST_INVOICE',
        ];

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(function (string $path) {
            return $this->configValues[$path] ?? '';
        });

        $curl = new Curl();
        $this->httpCalls = [];

        $curl->_postCallback = function (string $url, $body, array $headers) use ($postCallback) {
            $this->httpCalls[] = [
                'url' => $url,
                'body' => $body,
                'headers' => $headers,
            ];

            if ($postCallback) {
                return $postCallback($url, $body, $headers);
            }

            // Default: auth endpoint returns a token
            if (str_contains($url, '/v2/auth/token')) {
                return [
                    'body' => json_encode([
                        'access_token' => 'test_token_abc',
                        'expires_in' => 3600,
                    ]),
                    'status' => 200,
                ];
            }

            return ['body' => '{}', 'status' => 200];
        };

        return new QPayClient($config, $curl);
    }

    // --- Authentication tests ---

    public function test_get_token_sends_basic_auth_header(): void
    {
        $client = $this->createClient();
        $client->createInvoice(['amount' => 1000]);

        $authCall = $this->httpCalls[0];
        $this->assertEquals('https://merchant.qpay.mn/v2/auth/token', $authCall['url']);

        $expectedAuth = 'Basic ' . base64_encode('test_user:test_pass');
        $this->assertEquals($expectedAuth, $authCall['headers']['Authorization']);
    }

    public function test_get_token_sends_json_content_type(): void
    {
        $client = $this->createClient();
        $client->createInvoice(['amount' => 1000]);

        $authCall = $this->httpCalls[0];
        $this->assertEquals('application/json', $authCall['headers']['Content-Type']);
    }

    public function test_token_is_cached_for_subsequent_requests(): void
    {
        $client = $this->createClient();

        // First request triggers auth
        $client->createInvoice(['amount' => 1000]);
        // Second request should reuse cached token
        $client->createInvoice(['amount' => 2000]);

        // Should have: 1 auth + 1 invoice + 1 invoice = 3 calls (not 2 auth calls)
        $this->assertCount(3, $this->httpCalls);

        // First call is auth
        $this->assertStringContainsString('/v2/auth/token', $this->httpCalls[0]['url']);
        // Second call is invoice
        $this->assertStringContainsString('/v2/invoice', $this->httpCalls[1]['url']);
        // Third call is invoice (no second auth)
        $this->assertStringContainsString('/v2/invoice', $this->httpCalls[2]['url']);
    }

    public function test_token_refreshes_when_expired(): void
    {
        $callCount = 0;
        $client = $this->createClient(function (string $url) use (&$callCount) {
            $callCount++;
            if (str_contains($url, '/v2/auth/token')) {
                return [
                    'body' => json_encode([
                        'access_token' => 'token_' . $callCount,
                        // Set expires_in to 0 so token expires immediately
                        'expires_in' => 0,
                    ]),
                    'status' => 200,
                ];
            }
            return ['body' => json_encode(['invoice_id' => 'inv_1']), 'status' => 200];
        });

        $client->createInvoice(['amount' => 1000]);
        $client->createInvoice(['amount' => 2000]);

        // With expired token, should have 2 auth calls + 2 invoice calls = 4
        $this->assertCount(4, $this->httpCalls);
        $this->assertStringContainsString('/v2/auth/token', $this->httpCalls[0]['url']);
        $this->assertStringContainsString('/v2/auth/token', $this->httpCalls[2]['url']);
    }

    public function test_get_token_returns_empty_string_on_missing_access_token(): void
    {
        $client = $this->createClient(function (string $url) {
            if (str_contains($url, '/v2/auth/token')) {
                return ['body' => json_encode([]), 'status' => 200];
            }
            return ['body' => '{}', 'status' => 200];
        });

        $client->createInvoice(['amount' => 1000]);

        // Second call should have Bearer with empty token
        $invoiceCall = $this->httpCalls[1];
        $this->assertEquals('Bearer ', $invoiceCall['headers']['Authorization']);
    }

    // --- createInvoice tests ---

    public function test_create_invoice_sends_post_to_correct_url(): void
    {
        $client = $this->createClient();
        $client->createInvoice(['amount' => 5000, 'invoice_code' => 'TEST']);

        $invoiceCall = $this->httpCalls[1];
        $this->assertEquals('https://merchant.qpay.mn/v2/invoice', $invoiceCall['url']);
    }

    public function test_create_invoice_sends_bearer_token(): void
    {
        $client = $this->createClient();
        $client->createInvoice(['amount' => 5000]);

        $invoiceCall = $this->httpCalls[1];
        $this->assertEquals('Bearer test_token_abc', $invoiceCall['headers']['Authorization']);
    }

    public function test_create_invoice_sends_json_body(): void
    {
        $data = [
            'invoice_code' => 'TEST_INVOICE',
            'amount' => 5000,
            'callback_url' => 'https://example.com/callback',
        ];

        $client = $this->createClient();
        $client->createInvoice($data);

        $invoiceCall = $this->httpCalls[1];
        $sentBody = json_decode($invoiceCall['body'], true);
        $this->assertEquals('TEST_INVOICE', $sentBody['invoice_code']);
        $this->assertEquals(5000, $sentBody['amount']);
    }

    public function test_create_invoice_returns_decoded_response(): void
    {
        $client = $this->createClient(function (string $url) {
            if (str_contains($url, '/v2/auth/token')) {
                return [
                    'body' => json_encode(['access_token' => 'tok', 'expires_in' => 3600]),
                    'status' => 200,
                ];
            }
            return [
                'body' => json_encode([
                    'invoice_id' => 'inv_123',
                    'qr_text' => 'qr_data',
                    'qr_image' => 'base64_img',
                    'urls' => [['name' => 'Khan Bank', 'link' => 'https://khan.mn']],
                ]),
                'status' => 200,
            ];
        });

        $result = $client->createInvoice(['amount' => 1000]);

        $this->assertIsArray($result);
        $this->assertEquals('inv_123', $result['invoice_id']);
        $this->assertEquals('qr_data', $result['qr_text']);
        $this->assertCount(1, $result['urls']);
    }

    // --- checkPayment tests ---

    public function test_check_payment_sends_post_to_correct_url(): void
    {
        $client = $this->createClient();
        $client->checkPayment('inv_123');

        $checkCall = $this->httpCalls[1];
        $this->assertEquals('https://merchant.qpay.mn/v2/payment/check', $checkCall['url']);
    }

    public function test_check_payment_sends_correct_body(): void
    {
        $client = $this->createClient();
        $client->checkPayment('inv_123');

        $checkCall = $this->httpCalls[1];
        $sentBody = json_decode($checkCall['body'], true);
        $this->assertEquals('INVOICE', $sentBody['object_type']);
        $this->assertEquals('inv_123', $sentBody['object_id']);
    }

    public function test_check_payment_returns_paid_result(): void
    {
        $client = $this->createClient(function (string $url) {
            if (str_contains($url, '/v2/auth/token')) {
                return [
                    'body' => json_encode(['access_token' => 'tok', 'expires_in' => 3600]),
                    'status' => 200,
                ];
            }
            return [
                'body' => json_encode([
                    'count' => 1,
                    'paid_amount' => 5000.0,
                    'rows' => [
                        ['payment_id' => 'pay_1', 'payment_status' => 'PAID', 'payment_amount' => 5000.0],
                    ],
                ]),
                'status' => 200,
            ];
        });

        $result = $client->checkPayment('inv_456');

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['count']);
        $this->assertNotEmpty($result['rows']);
        $this->assertEquals('PAID', $result['rows'][0]['payment_status']);
    }

    public function test_check_payment_returns_empty_rows_when_unpaid(): void
    {
        $client = $this->createClient(function (string $url) {
            if (str_contains($url, '/v2/auth/token')) {
                return [
                    'body' => json_encode(['access_token' => 'tok', 'expires_in' => 3600]),
                    'status' => 200,
                ];
            }
            return [
                'body' => json_encode([
                    'count' => 0,
                    'paid_amount' => 0,
                    'rows' => [],
                ]),
                'status' => 200,
            ];
        });

        $result = $client->checkPayment('inv_789');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['rows']);
    }

    public function test_check_payment_sends_bearer_token(): void
    {
        $client = $this->createClient();
        $client->checkPayment('inv_123');

        $checkCall = $this->httpCalls[1];
        $this->assertEquals('Bearer test_token_abc', $checkCall['headers']['Authorization']);
    }

    // --- Config reading tests ---

    public function test_client_reads_config_values(): void
    {
        $this->configValues = [];

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(function (string $path) {
            return match ($path) {
                'payment/qpay/base_url' => 'https://sandbox.qpay.mn',
                'payment/qpay/username' => 'sandbox_user',
                'payment/qpay/password' => 'sandbox_pass',
                default => '',
            };
        });

        $curl = new Curl();
        $curl->_postCallback = function (string $url, $body, array $headers) {
            $this->httpCalls[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

            if (str_contains($url, '/v2/auth/token')) {
                return [
                    'body' => json_encode(['access_token' => 'sandbox_tok', 'expires_in' => 3600]),
                    'status' => 200,
                ];
            }
            return ['body' => '{}', 'status' => 200];
        };

        $client = new QPayClient($config, $curl);
        $client->createInvoice(['amount' => 100]);

        // Should use sandbox URL
        $this->assertStringStartsWith('https://sandbox.qpay.mn', $this->httpCalls[0]['url']);

        // Should use sandbox credentials
        $expectedAuth = 'Basic ' . base64_encode('sandbox_user:sandbox_pass');
        $this->assertEquals($expectedAuth, $this->httpCalls[0]['headers']['Authorization']);
    }
}
