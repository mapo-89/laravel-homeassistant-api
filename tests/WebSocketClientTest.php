<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Tests;

use Mapo89\LaravelHomeassistantApi\Api\Utils\WebSocketClient;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebSocket\Client;
use WebSocket\Message\Text;

final class WebSocketClientTest extends TestCase
{
    private const TOKEN = 'synthetic-private-ha-token';

    private const URL = 'https://generated.chico-online.de';

    public function test_updates_only_external_url_and_verifies_readback_with_one_connection(): void
    {
        $client = $this->createMock(Client::class);
        $sent = [];
        $client->expects($this->once())->method('connect');
        $client->expects($this->once())->method('disconnect');
        $client->method('text')->willReturnCallback(function (string $message) use (&$sent): Text {
            $sent[] = json_decode($message, true);

            return new Text($message);
        });
        $client->method('receive')->willReturnOnConsecutiveCalls(...array_map(fn ($data) => new Text(json_encode($data)), [
            ['type' => 'auth_required'], ['type' => 'auth_ok'],
            ['id' => 1, 'type' => 'result', 'success' => true, 'result' => null],
            ['id' => 2, 'type' => 'result', 'success' => true, 'result' => ['external_url' => self::URL, 'internal_url' => 'http://homeassistant.local:8123']],
        ]));
        $service = $this->service($client);
        $this->assertSame(self::URL, $service->setExternalUrl(self::URL, self::TOKEN, self::URL)['external_url']);
        $this->assertSame('wss://generated.chico-online.de/api/websocket', $service->url);
        $this->assertSame([
            ['type' => 'auth', 'access_token' => self::TOKEN],
            ['id' => 1, 'type' => 'config/core/update', 'external_url' => self::URL],
            ['id' => 2, 'type' => 'get_config'],
        ], $sent);
    }

    public function test_authentication_denial_command_errors_wrong_ids_and_readback_mismatches_are_sanitized(): void
    {
        $scenarios = [
            [['type' => 'auth_required'], ['type' => 'auth_invalid', 'message' => self::TOKEN]],
            [['type' => 'auth_required'], ['type' => 'auth_ok'], ['type' => 'result', 'id' => 1, 'success' => false, 'error' => ['message' => self::TOKEN]]],
            [['type' => 'auth_required'], ['type' => 'auth_ok'], ['type' => 'result', 'id' => 99, 'success' => true]],
            [['type' => 'auth_required'], ['type' => 'auth_ok'], ['type' => 'result', 'id' => 1, 'success' => true], ['type' => 'result', 'id' => 2, 'success' => true, 'result' => ['external_url' => 'https://other.test']]],
        ];
        foreach ($scenarios as $scenario) {
            $client = $this->createMock(Client::class);
            $client->expects($this->once())->method('disconnect');
            $client->method('text')->willReturnCallback(fn (string $text) => new Text($text));
            $client->method('receive')->willReturnOnConsecutiveCalls(...array_map(fn ($data) => new Text(json_encode($data)), $scenario));
            try {
                $this->service($client)->setExternalUrl(self::URL, self::TOKEN, self::URL);
                $this->fail('Invalid configuration must not be accepted.');
            } catch (HomeAssistantException $exception) {
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function test_invalid_urls_never_open_a_connection(): void
    {
        foreach (['https://user:password@example.test', 'https://example.test/?token=secret', 'file:///etc/passwd', 'https://example.test/unexpected'] as $url) {
            $client = $this->createMock(Client::class);
            $client->expects($this->never())->method('connect');
            try {
                $this->service($client)->setExternalUrl($url, self::TOKEN, self::URL);
                $this->fail('Invalid origin must be rejected.');
            } catch (HomeAssistantException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_transport_failure_closes_connection_and_does_not_expose_token(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('connect')->willThrowException(new RuntimeException(self::TOKEN));
        $client->expects($this->once())->method('disconnect');
        $this->expectException(HomeAssistantException::class);
        $this->expectExceptionMessage('Home Assistant external URL could not be configured.');
        $this->service($client)->setExternalUrl(self::URL, self::TOKEN, self::URL);
    }

    private function service(Client $client): WebSocketClient
    {
        return new class($client) extends WebSocketClient
        {
            public string $url = '';

            public function __construct(private Client $client) {}

            protected function createClient(string $url): Client
            {
                $this->url = $url;

                return $this->client;
            }
        };
    }
}
