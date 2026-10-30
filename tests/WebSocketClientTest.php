<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Tests;

use Mapo89\LaravelHomeassistantApi\Api\Utils\WebSocketClient;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebSocket\Client;
use WebSocket\Message\Ping;
use WebSocket\Message\Pong;
use WebSocket\Message\Text;

final class WebSocketClientTest extends TestCase
{
    private const TOKEN = 'synthetic-private-ha-token';

    private const URL = 'https://homeassistant.example.test';

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
        $this->assertSame('wss://homeassistant.example.test/api/websocket', $service->url);
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

    public function test_reads_restart_requirements_and_filters_invalid_sources(): void
    {
        $sent = [];
        $client = $this->conversation([
            $this->resultResponse(1, null),
            ['id' => 1, 'type' => 'event', 'event' => [
                'home_assistant_restart_required' => true, 'host_reboot_required' => false,
                'home_assistant_restart_sources' => ['config', null, 'integration', 1],
            ]],
        ], $sent);

        $this->assertSame(['restartRequired' => true, 'rebootRequired' => false,
            'sources' => ['config', 'integration']], $this->service($client)->systemState(self::URL, self::TOKEN));
        $this->assertSame(['type' => 'auth', 'access_token' => self::TOKEN], $sent[0]);
        $this->assertSame(['id' => 1, 'type' => 'subscribe_system_state'], $sent[1]);
    }

    public function test_registry_batches_unique_update_entities_and_returns_only_requested_metadata(): void
    {
        $ids = array_map(fn ($i) => 'update.device_'.$i, range(1, 21));
        $entries = [];
        foreach ($ids as $id) {
            $entries[$id] = ['platform' => 'test', 'unique_id' => $id, 'extra' => 'not-returned'];
        }
        $entries['update.unrequested'] = ['platform' => 'test', 'unique_id' => 'unrequested'];
        $sent = [];
        $client = $this->conversation([
            $this->resultResponse(1, $entries), $this->resultResponse(2, ['update.device_21' => $entries['update.device_21']]),
        ], $sent);

        $result = $this->service($client)->updateEntityRegistry(self::URL, self::TOKEN,
            [...$ids, $ids[0], 'light.kitchen', '../invalid', null]);

        $this->assertSame($ids, array_keys($result));
        $this->assertSame(['platform' => 'test', 'unique_id' => 'update.device_1'], $result[$ids[0]]);
        $this->assertSame(['id' => 1, 'type' => 'config/entity_registry/get_entries',
            'entity_ids' => array_slice($ids, 0, 20)], $sent[1]);
        $this->assertSame(['id' => 2, 'type' => 'config/entity_registry/get_entries',
            'entity_ids' => [$ids[20]]], $sent[2]);
    }

    public function test_invalid_registry_selection_does_not_open_a_connection(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('connect');

        $this->assertSame([], $this->service($client)->updateEntityRegistry(self::URL, self::TOKEN,
            ['light.kitchen', 'update.invalid/path', 123]));
    }

    public function test_registry_omits_missing_or_invalid_metadata(): void
    {
        $sent = [];
        $client = $this->conversation([$this->resultResponse(1, [
            'update.missing' => null,
            'update.invalid' => ['platform' => false, 'unique_id' => 'invalid'],
            'update.valid' => ['platform' => 'test', 'unique_id' => 'valid'],
        ])], $sent);

        $this->assertSame(['update.valid' => ['platform' => 'test', 'unique_id' => 'valid']],
            $this->service($client)->updateEntityRegistry(self::URL, self::TOKEN,
                ['update.missing', 'update.invalid', 'update.valid']));
    }

    public function test_reads_only_the_current_tokens_metadata(): void
    {
        $sent = [];
        $client = $this->conversation([
            $this->resultResponse(1, ['id' => 'user-id']),
            $this->resultResponse(2, [
                ['id' => 'other-id', 'is_current' => false, 'type' => 'normal'],
                ['id' => 'current-id', 'is_current' => true, 'type' => 'long_lived_access_token',
                    'client_name' => 'Package', 'last_used_ip' => 'not-returned'],
            ]),
        ], $sent);

        $this->assertSame(['userId' => 'user-id', 'tokenId' => 'current-id',
            'type' => 'long_lived_access_token', 'clientName' => 'Package'],
            $this->service($client)->accessTokenInfo(self::URL, self::TOKEN));
        $this->assertSame(['id' => 1, 'type' => 'auth/current_user'], $sent[1]);
        $this->assertSame(['id' => 2, 'type' => 'auth/refresh_tokens'], $sent[2]);
    }

    public function test_creates_a_long_lived_token_with_the_requested_name_and_lifetime(): void
    {
        $sent = [];
        $token = 'synthetic-new-long-lived-token';
        $client = $this->conversation([$this->resultResponse(1, $token)], $sent);

        $this->assertSame($token, $this->service($client)->createLongLivedToken(self::URL, self::TOKEN, 'Package', 90));
        $this->assertSame(['id' => 1, 'type' => 'auth/long_lived_access_token',
            'client_name' => 'Package', 'lifespan' => 90], $sent[1]);
    }

    public function test_deletes_only_the_requested_refresh_token(): void
    {
        $sent = [];
        $client = $this->conversation([$this->resultResponse(1, [])], $sent);

        $this->service($client)->deleteRefreshToken(self::URL, self::TOKEN, 'refresh-token-id');
        $this->assertSame(['id' => 1, 'type' => 'auth/delete_refresh_token',
            'refresh_token_id' => 'refresh-token-id'], $sent[1]);
    }

    public function test_invalid_token_arguments_never_open_a_connection(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('connect');
        $service = $this->service($client);
        $operations = [
            fn () => $service->createLongLivedToken(self::URL, self::TOKEN, ''),
            fn () => $service->createLongLivedToken(self::URL, self::TOKEN, str_repeat('a', 129)),
            fn () => $service->createLongLivedToken(self::URL, self::TOKEN, 'Package', 0),
            fn () => $service->createLongLivedToken(self::URL, self::TOKEN, 'Package', 3651),
            fn () => $service->deleteRefreshToken(self::URL, self::TOKEN, '../invalid'),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Invalid token arguments must be rejected.');
            } catch (HomeAssistantException $exception) {
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function test_all_operations_sanitize_authentication_command_protocol_and_transport_failures(): void
    {
        foreach (['external', 'system', 'registry', 'info', 'create', 'delete'] as $operation) {
            $scenarios = [
                [['type' => 'auth_required'], ['type' => 'auth_invalid', 'message' => self::TOKEN]],
                [['type' => 'auth_required'], ['type' => 'auth_ok'],
                    ['id' => 1, 'type' => 'result', 'success' => false, 'error' => ['message' => self::TOKEN]]],
                [['type' => 'auth_required'], ['type' => 'auth_ok'], $this->resultResponse(99, self::TOKEN)],
                [['type' => 'auth_required'], ['type' => 'auth_ok'], new Text('invalid-json-'.self::TOKEN)],
            ];
            foreach ($scenarios as $scenario) {
                $sent = [];
                $client = $this->conversation($scenario, $sent, false);
                $this->assertSanitizedFailure($operation, $this->service($client));
            }

            $client = $this->createMock(Client::class);
            $client->method('connect')->willThrowException(new RuntimeException(self::TOKEN));
            $client->expects($this->once())->method('disconnect');
            $this->assertSanitizedFailure($operation, $this->service($client));
        }
    }

    public function test_invalid_operation_results_are_rejected_after_authentication(): void
    {
        $scenarios = [
            'system' => [$this->resultResponse(1, null), ['id' => 1, 'type' => 'event', 'event' => [
                'home_assistant_restart_required' => 'true', 'host_reboot_required' => false]]],
            'registry' => [$this->resultResponse(1, self::TOKEN)],
            'info' => [$this->resultResponse(1, ['id' => 'user-id']), $this->resultResponse(2, [])],
            'create' => [$this->resultResponse(1, 'too-short')],
        ];
        foreach ($scenarios as $operation => $responses) {
            $sent = [];
            $this->assertSanitizedFailure($operation, $this->service($this->conversation($responses, $sent)));
        }
    }

    public function test_ping_and_pong_frames_do_not_interrupt_authentication_or_results(): void
    {
        $sent = [];
        $client = $this->conversation([
            new Ping('ping'), ['type' => 'auth_required'], new Pong('pong'), ['type' => 'auth_ok'],
            new Ping('ping'), $this->resultResponse(1, 'synthetic-new-long-lived-token'),
        ], $sent, false);

        $this->assertSame('synthetic-new-long-lived-token',
            $this->service($client)->createLongLivedToken(self::URL, self::TOKEN, 'Package'));
    }

    public function test_disconnect_errors_do_not_replace_a_successful_result(): void
    {
        $sent = [];
        $client = $this->conversation([$this->resultResponse(1, 'synthetic-new-long-lived-token')], $sent);
        $client->method('disconnect')->willThrowException(new RuntimeException(self::TOKEN));

        $this->assertSame('synthetic-new-long-lived-token',
            $this->service($client)->createLongLivedToken(self::URL, self::TOKEN, 'Package'));
    }

    private function assertSanitizedFailure(string $operation, WebSocketClient $service): void
    {
        try {
            match ($operation) {
                'external' => $service->setExternalUrl(self::URL, self::TOKEN, self::URL),
                'system' => $service->systemState(self::URL, self::TOKEN),
                'registry' => $service->updateEntityRegistry(self::URL, self::TOKEN, ['update.device']),
                'info' => $service->accessTokenInfo(self::URL, self::TOKEN),
                'create' => $service->createLongLivedToken(self::URL, self::TOKEN, 'Package'),
                'delete' => $service->deleteRefreshToken(self::URL, self::TOKEN, 'refresh-token-id'),
            };
            $this->fail('An invalid WebSocket operation must be rejected.');
        } catch (HomeAssistantException $exception) {
            $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $expected = match ($operation) {
                'external' => 'Home Assistant external URL could not be configured.',
                'system' => 'Home Assistant system state could not be read.',
                'registry' => 'Home Assistant update entity registry could not be read.',
                default => 'Home Assistant token operation could not be completed.',
            };
            $this->assertSame($expected, $exception->getMessage());
        }
    }

    private function resultResponse(int $id, mixed $result): array
    {
        return ['id' => $id, 'type' => 'result', 'success' => true, 'result' => $result];
    }

    private function conversation(array $responses, array &$sent, bool $authenticate = true): Client
    {
        if ($authenticate) {
            $responses = [['type' => 'auth_required'], ['type' => 'auth_ok'], ...$responses];
        }
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('connect');
        $client->expects($this->once())->method('disconnect');
        $client->method('text')->willReturnCallback(function (string $message) use (&$sent): Text {
            $sent[] = json_decode($message, true);

            return new Text($message);
        });
        $client->method('receive')->willReturnOnConsecutiveCalls(...array_map(
            fn ($data) => is_array($data) ? new Text(json_encode($data)) : $data, $responses
        ));

        return $client;
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
