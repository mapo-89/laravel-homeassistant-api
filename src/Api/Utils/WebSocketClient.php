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

    /** @return array<string, mixed> */
    public function systemState(string $baseUrl, string $token): array
    {
        $client = null;
        $deadline = microtime(true) + 8;
        try {
            if ($token === '') {
                throw new HomeAssistantException('Home Assistant authentication is required.');
            }
            $client = $this->createClient($this->socketUrl($baseUrl));
            $client->connect();
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_required') {
                throw new HomeAssistantException('Unexpected authentication response.');
            }
            $client->text(json_encode(['type' => 'auth', 'access_token' => $token], JSON_THROW_ON_ERROR));
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_ok') {
                throw new HomeAssistantException('Home Assistant authentication failed.');
            }
            $client->text(json_encode(['id' => 1, 'type' => 'subscribe_system_state'], JSON_THROW_ON_ERROR));
            $this->result($client, $deadline, 1);
            $event = $this->read($client, $deadline);
            $state = $event['event'] ?? null;
            if (($event['type'] ?? null) !== 'event' || ($event['id'] ?? null) !== 1 || ! is_array($state)
                || ! is_bool($state['home_assistant_restart_required'] ?? null) || ! is_bool($state['host_reboot_required'] ?? null)) {
                throw new HomeAssistantException('Invalid system state.');
            }

            return [
                'restartRequired' => $state['home_assistant_restart_required'],
                'rebootRequired' => $state['host_reboot_required'],
                'sources' => array_values(array_filter(is_array($state['home_assistant_restart_sources'] ?? null) ? $state['home_assistant_restart_sources'] : [], 'is_string')),
            ];
        } catch (Throwable) {
            throw new HomeAssistantException('Home Assistant system state could not be read.');
        } finally {
            if ($client !== null) {
                try {
                    $client->disconnect();
                } catch (Throwable) {
                }
            }
        }
    }

    /**
     * Read only the integration identifiers of the requested update entities.
     *
     * @param  list<string>  $entityIds
     * @return array<string, array{platform: string, unique_id: string}>
     */
    public function updateEntityRegistry(string $baseUrl, string $token, array $entityIds): array
    {
        $entityIds = array_values(array_unique(array_filter($entityIds, fn ($id): bool => is_string($id) && preg_match('/^update\.[a-z0-9_]+$/D', $id) === 1)));
        if ($entityIds === []) {
            return [];
        }
        $client = null;
        $deadline = microtime(true) + 8;
        try {
            if ($token === '') {
                throw new HomeAssistantException('Home Assistant authentication is required.');
            }
            $client = $this->createClient($this->socketUrl($baseUrl));
            $client->connect();
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_required') {
                throw new HomeAssistantException('Unexpected authentication response.');
            }
            $client->text(json_encode(['type' => 'auth', 'access_token' => $token], JSON_THROW_ON_ERROR));
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_ok') {
                throw new HomeAssistantException('Home Assistant authentication failed.');
            }
            $entries = [];
            foreach (array_chunk($entityIds, 20) as $index => $batch) {
                $id = $index + 1;
                $client->text(json_encode(['id' => $id, 'type' => 'config/entity_registry/get_entries', 'entity_ids' => $batch], JSON_THROW_ON_ERROR));
                $result = $this->result($client, $deadline, $id);
                if (! is_array($result)) {
                    throw new HomeAssistantException('Invalid entity registry.');
                }
                foreach ($batch as $entityId) {
                    $entry = $result[$entityId] ?? null;
                    if (is_array($entry) && is_string($entry['platform'] ?? null) && is_string($entry['unique_id'] ?? null)) {
                        $entries[$entityId] = ['platform' => $entry['platform'], 'unique_id' => $entry['unique_id']];
                    }
                }
            }

            return $entries;
        } catch (Throwable) {
            throw new HomeAssistantException('Home Assistant update entity registry could not be read.');
        } finally {
            if ($client !== null) {
                try {
                    $client->disconnect();
                } catch (Throwable) {
                }
            }
        }
    }

    /** @return array{userId: string, tokenId: string, type: string, clientName: string|null} */
    public function accessTokenInfo(string $baseUrl, string $token): array
    {
        return $this->authenticated($baseUrl, $token, function (Client $client, float $deadline): array {
            $client->text(json_encode(['id' => 1, 'type' => 'auth/current_user'], JSON_THROW_ON_ERROR));
            $user = $this->result($client, $deadline, 1);
            $client->text(json_encode(['id' => 2, 'type' => 'auth/refresh_tokens'], JSON_THROW_ON_ERROR));
            $tokens = $this->result($client, $deadline, 2);
            if (! is_array($user) || ! is_string($user['id'] ?? null) || $user['id'] === '' || ! is_array($tokens)) {
                throw new HomeAssistantException('Invalid token information.');
            }
            foreach ($tokens as $entry) {
                if (is_array($entry) && ($entry['is_current'] ?? null) === true && is_string($entry['id'] ?? null) && $entry['id'] !== '' && is_string($entry['type'] ?? null)) {
                    return ['userId' => $user['id'], 'tokenId' => $entry['id'], 'type' => $entry['type'], 'clientName' => is_string($entry['client_name'] ?? null) ? $entry['client_name'] : null];
                }
            }
            throw new HomeAssistantException('Current token could not be identified.');
        });
    }

    public function createLongLivedToken(string $baseUrl, string $token, string $clientName, int $lifespan = 365): string
    {
        if ($clientName === '' || strlen($clientName) > 128 || $lifespan < 1 || $lifespan > 3650) {
            throw new HomeAssistantException('Invalid token lifetime or client name.');
        }

        return $this->authenticated($baseUrl, $token, function (Client $client, float $deadline) use ($clientName, $lifespan): string {
            $client->text(json_encode(['id' => 1, 'type' => 'auth/long_lived_access_token', 'client_name' => $clientName, 'lifespan' => $lifespan], JSON_THROW_ON_ERROR));
            $result = $this->result($client, $deadline, 1);
            if (! is_string($result) || strlen($result) < 20 || strlen($result) > 4000) {
                throw new HomeAssistantException('Invalid generated token.');
            }

            return $result;
        });
    }

    public function deleteRefreshToken(string $baseUrl, string $token, string $refreshTokenId): void
    {
        if (! preg_match('/\A[a-zA-Z0-9-]{1,128}\z/D', $refreshTokenId)) {
            throw new HomeAssistantException('Invalid token identifier.');
        }
        $this->authenticated($baseUrl, $token, function (Client $client, float $deadline) use ($refreshTokenId): void {
            $client->text(json_encode(['id' => 1, 'type' => 'auth/delete_refresh_token', 'refresh_token_id' => $refreshTokenId], JSON_THROW_ON_ERROR));
            $this->result($client, $deadline, 1);
        });
    }

    private function authenticated(string $baseUrl, string $token, callable $operation): mixed
    {
        $client = null;
        $deadline = microtime(true) + 8;
        try {
            if ($token === '') {
                throw new HomeAssistantException('Authentication is required.');
            }
            $client = $this->createClient($this->socketUrl($baseUrl));
            $client->connect();
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_required') {
                throw new HomeAssistantException('Unexpected authentication response.');
            }
            $client->text(json_encode(['type' => 'auth', 'access_token' => $token], JSON_THROW_ON_ERROR));
            if (($this->read($client, $deadline)['type'] ?? null) !== 'auth_ok') {
                throw new HomeAssistantException('Authentication failed.');
            }

            return $operation($client, $deadline);
        } catch (Throwable) {
            throw new HomeAssistantException('Home Assistant token operation could not be completed.');
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
