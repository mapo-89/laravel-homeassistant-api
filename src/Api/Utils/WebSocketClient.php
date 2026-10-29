<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Phrity\Net\Context;
use Throwable;
use WebSocket\Client;
use WebSocket\Configuration;
use WebSocket\Message\Ping;
use WebSocket\Message\Pong;
use WebSocket\Message\Text;
use WebSocket\Middleware\PingResponder;

class WebSocketClient
{
    /** @return array<string, mixed> */
    public function setExternalUrl(string $baseUrl, string $token, string $externalUrl): array
    {
        $socketUrl = $this->socketUrl($baseUrl);
        $this->socketUrl($externalUrl);
        if ($token === '') {
            throw new HomeAssistantException('Home Assistant authentication is required.');
        }
        $client = null;
        $deadline = microtime(true) + 8;
        try {
            $client = $this->createClient($socketUrl);
            $client->connect();
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_required') {
                throw new HomeAssistantException('Unexpected Home Assistant authentication response.');
            }
            $client->text(json_encode(['type' => 'auth', 'access_token' => $token], JSON_THROW_ON_ERROR));
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_ok') {
                throw new HomeAssistantException('Home Assistant authentication failed.');
            }
            $client->text(json_encode(['id' => 1, 'type' => 'config/core/update', 'external_url' => $externalUrl], JSON_THROW_ON_ERROR));
            $this->result($client, $deadline, 1);
            $client->text(json_encode(['id' => 2, 'type' => 'get_config'], JSON_THROW_ON_ERROR));
            $config = $this->result($client, $deadline, 2);
            if (! is_array($config) || ($config['external_url'] ?? null) !== $externalUrl) {
                throw new HomeAssistantException('Home Assistant did not confirm the external URL.');
            }

            return $config;
        } catch (Throwable) {
            throw new HomeAssistantException('Home Assistant external URL could not be configured.');
        } finally {
            if ($client !== null) {
                try {
                    $client->disconnect();
                } catch (Throwable) {
                }
            }
        }
    }

    protected function createClient(string $url): Client
    {
        $context = (new Context)->setOptions(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
        $client = new Client($url, new Configuration(context: $context, timeout: 2, maxReceivedFrameSize: 65536, maxReceivedMessageSize: 262144));
        $client->addMiddleware(new PingResponder);

        return $client;
    }

    private function socketUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts)) !== []
            || ! in_array($parts['path'] ?? '', ['', '/', '/api', '/api/'], true)) {
            throw new HomeAssistantException('A Home Assistant origin URL is required.');
        }

        return ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/api/websocket';
    }

    /** @return array<string, mixed> */
    private function read(Client $client, float $deadline): array
    {
        for ($attempt = 0; $attempt < 16 && microtime(true) < $deadline; $attempt++) {
            $message = $client->receive();
            if ($message instanceof Ping || $message instanceof Pong) {
                continue;
            }
            if (! $message instanceof Text) {
                break;
            }
            $data = json_decode($message->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data) || array_is_list($data)) {
                break;
            }

            return $data;
        }
        throw new HomeAssistantException('Invalid or timed out Home Assistant response.');
    }

    private function result(Client $client, float $deadline, int $id): mixed
    {
        $response = $this->read($client, $deadline);
        if (($response['type'] ?? null) !== 'result' || ($response['id'] ?? null) !== $id || ($response['success'] ?? null) !== true) {
            throw new HomeAssistantException('Home Assistant command was not confirmed.');
        }

        return $response['result'] ?? null;
    }
}
