<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Mapo89\LaravelHomeassistantApi\HomeassistantApi;
use Mapo89\LaravelHomeassistantApi\HomeassistantApiServiceProvider;
use Orchestra\Testbench\TestCase;

final class ResponseValidationTest extends TestCase
{
    private const URL = 'https://homeassistant.example.test';

    private const TOKEN = 'synthetic-response-test-token';

    protected function getPackageProviders($app): array
    {
        return [HomeassistantApiServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['homeassistant-api' => ['url' => self::URL, 'token' => self::TOKEN]]);
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_url_aliases_share_cached_states_and_invalidation_after_state_and_service_changes(): void
    {
        $states = [['entity_id' => 'light.kitchen', 'state' => 'off']];
        Http::fake(function (Request $request) use (&$states) {
            $this->assertStringStartsWith(self::URL.'/api/', $request->url());
            if ($request->method() === 'DELETE') {
                $states = [];

                return Http::response(['message' => 'Entity removed.']);
            }
            if (str_contains($request->url(), '/services/')) {
                $states[0]['state'] = 'off';

                return Http::response($states);
            }
            if ($request->method() === 'POST') {
                $states[0]['state'] = $request['state'];

                return Http::response($states[0]);
            }

            return Http::response($states);
        });
        $clients = array_map(fn ($url) => HomeassistantApi::make(['url' => $url, 'token' => self::TOKEN]), [
            self::URL, self::URL.'/api/', 'HTTPS://HOMEASSISTANT.EXAMPLE.TEST:443/', self::URL.'/api',
        ]);

        foreach ($clients as $client) {
            $this->assertSame('off', $client->states()->all()[0]->state);
        }
        Http::assertSentCount(1);

        $clients[1]->states()->createOrUpdate('light.kitchen', 'on');
        foreach ($clients as $client) {
            $this->assertSame('on', $client->states()->all()[0]->state);
        }

        $clients[2]->services()->turnOff('light.kitchen');
        foreach ($clients as $client) {
            $this->assertSame('off', $client->states()->all()[0]->state);
        }

        $clients[3]->states()->delete('light.kitchen');
        foreach ($clients as $client) {
            $this->assertSame([], $client->states()->all());
        }
        Http::assertSentCount(7);
    }

    public function test_custom_base_paths_and_nondefault_ports_remain_separate(): void
    {
        Http::fake(fn (Request $request) => Http::response([
            ['entity_id' => 'sensor.origin', 'state' => $request->url()],
        ]));
        $paths = [
            ['http://HOMEASSISTANT.EXAMPLE.TEST:80/proxy', 'http://homeassistant.example.test/proxy/api/states'],
            ['http://homeassistant.example.test/proxy/api/', 'http://homeassistant.example.test/proxy/api/states'],
            ['http://homeassistant.example.test:8123/proxy', 'http://homeassistant.example.test:8123/proxy/api/states'],
            ['http://homeassistant.example.test/Proxy', 'http://homeassistant.example.test/Proxy/api/states'],
            ['http://[::1]:8123/', 'http://[::1]:8123/api/states'],
            ['http://[::1]:8123/api', 'http://[::1]:8123/api/states'],
        ];
        foreach ($paths as [$url, $expected]) {
            $this->assertSame($expected, HomeassistantApi::make(['url' => $url, 'token' => self::TOKEN])->states()->all()[0]->state);
        }
        Http::assertSentCount(4);
    }

    public function test_invalid_base_urls_are_rejected_without_sending_or_exposing_query_secrets(): void
    {
        Http::fake();
        foreach (['homeassistant.example.test', 'file:///tmp/example', self::URL.'?token='.self::TOKEN,
            self::URL.'#fragment', 'http://homeassistant.example.test:99999'] as $url) {
            try {
                HomeassistantApi::make(['url' => $url, 'token' => self::TOKEN])->system()->verify();
                $this->fail('An invalid API base URL must not result in an HTTP request.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_missing_or_malformed_history_filters_are_rejected_before_http(): void
    {
        Http::fake();
        $history = HomeassistantApi::make()->history();
        foreach (['get', 'flat'] as $method) {
            $calls = [fn () => $history->{$method}()];
            foreach ([[], [''], [null], [5], ['sensor'], ['sensor.one,sensor.two'], ['light/kitchen']] as $ids) {
                $calls[] = fn () => $history->{$method}(entityIds: $ids);
            }
            foreach ($calls as $call) {
                try {
                    $call();
                    $this->fail('History requires a valid nonempty entity filter.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringStartsWith('History ', $exception->getMessage());
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_single_states_reject_missing_fields_wrong_types_and_invalid_dates(): void
    {
        $state = ['entity_id' => 'sensor.example', 'state' => 'on'];
        $bodies = [
            ['message' => self::TOKEN], [], ['entity_id' => 'sensor.example'], ['state' => 'on'],
            [...$state, 'entity_id' => ''], [...$state, 'state' => 5],
            [...$state, 'attributes' => self::TOKEN], [...$state, 'attributes' => null],
            [...$state, 'context' => self::TOKEN], [...$state, 'context' => ['id' => []]],
            [...$state, 'last_updated' => []], [...$state, 'last_changed' => self::TOKEN],
            [...$state, 'last_reported' => ''], [...$state, 'last_updated' => '2026-02-30T00:00:00Z'],
        ];
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body);
        }
        Http::fake(['*' => $sequence]);

        foreach ($bodies as $body) {
            try {
                HomeassistantApi::make()->states()->get('sensor.example');
                $this->fail('Invalid state objects must not create empty or partially valid DTOs.');
            } catch (HomeAssistantException $exception) {
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
        Http::assertSentCount(count($bodies));
    }

    public function test_history_rejects_unexpected_groups_states_and_mixed_entities(): void
    {
        $state = ['entity_id' => 'sensor.example', 'state' => 'on', 'last_changed' => '2026-10-01T00:00:00Z'];
        $bodies = [
            ['message' => self::TOKEN], [self::TOKEN], [['message' => self::TOKEN]], [[self::TOKEN]],
            [[['state' => 'on', 'last_changed' => '2026-10-01T00:00:00Z']]],
            [[$state, self::TOKEN]], [[$state, ['state' => 'off']]],
            [[$state, [...$state, 'entity_id' => 'sensor.other']]],
            [$state], [[$state, ['state' => [], 'last_changed' => '2026-10-01T01:00:00Z']]],
        ];
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body);
        }
        Http::fake(['*' => $sequence]);

        foreach ($bodies as $body) {
            try {
                HomeassistantApi::make()->history()->get(entityIds: ['sensor.example']);
                $this->fail('Unexpected history shapes must raise a package exception.');
            } catch (HomeAssistantException $exception) {
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
        Http::assertSentCount(count($bodies));
    }

    public function test_history_preserves_empty_state_values_minimal_entries_and_empty_groups(): void
    {
        Http::fake(['*' => Http::response([[], [
            ['entity_id' => 'sensor.example', 'state' => '', 'last_changed' => '2026-10-01T00:00:00Z'],
            ['state' => '0', 'last_changed' => '2026-10-01T01:00:00Z'],
        ]])]);
        $states = HomeassistantApi::make()->history()->flat(entityIds: ['sensor.example'], minimal: true);

        $this->assertCount(2, $states);
        $this->assertSame('', $states[0]->state);
        $this->assertSame('0', $states[1]->state);
        $this->assertSame('sensor.example', $states[1]->entityId);
        $this->assertTrue($states[1]->lastChanged->equalTo($states[1]->lastUpdated));
    }

    public function test_states_get_without_an_id_returns_all_states_and_with_an_id_returns_one_state(): void
    {
        $state = ['entity_id' => 'light.kitchen', 'state' => 'on'];
        Http::fake([
            self::URL.'/api/states' => Http::response([$state]),
            self::URL.'/api/states/light.kitchen' => Http::response($state),
        ]);
        $states = HomeassistantApi::make()->states();

        $all = $states->get();
        $this->assertCount(1, $all);
        $this->assertSame('light.kitchen', $all[0]->entity_id);
        $this->assertEquals($all, $states->all());
        $this->assertSame('on', $states->get('light.kitchen')->state);
        $this->assertSame('on', $states->whereDomain('light')->get()[0]->state);
        Http::assertSentCount(2);
    }

    public function test_invalid_logbook_filters_and_state_entity_ids_never_send_requests(): void
    {
        Http::fake();
        $api = HomeassistantApi::make();
        foreach ([[], [''], [null], ['light'], ['light.one,light.two'], ['light.kitchen?query=1']] as $ids) {
            try {
                $api->logbook()->get($ids);
                $this->fail('Logbook requires a nonempty valid entity selection.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringStartsWith('Logbook ', $exception->getMessage());
            }
        }
        foreach (['', '../light.kitchen', 'light.kitchen?query=1'] as $id) {
            foreach ([fn () => $api->states()->get($id),
                fn () => $api->states()->createOrUpdate($id, 'on'),
                fn () => $api->states()->delete($id)] as $operation) {
                try {
                    $operation();
                    $this->fail('Invalid state entity IDs must be rejected.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertSame('Entity ID must use the format domain.object_id.', $exception->getMessage());
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_events_reject_unexpected_collections_records_and_listener_counts(): void
    {
        $bodies = [['message' => self::TOKEN], [self::TOKEN], [[]], [['event' => []]],
            [['event' => 'example', 'listener_count' => []]], [['event' => 'example', 'listener_count' => -1]]];
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body);
        }
        Http::fake(['*' => $sequence]);
        foreach ($bodies as $body) {
            $this->assertInvalidResponse(fn () => HomeassistantApi::make()->events()->all());
        }
        Http::assertSentCount(count($bodies));
    }

    public function test_logbook_rejects_unexpected_collections_entries_and_timestamps(): void
    {
        $when = '2026-10-01T10:00:00Z';
        $bodies = [['message' => self::TOKEN], [self::TOKEN], [[]], [['when' => []]],
            [['when' => self::TOKEN]], [['when' => '2026-02-30T00:00:00Z']],
            [['when' => $when, 'entity_id' => []]], [['when' => $when, 'message' => false]]];
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body);
        }
        Http::fake(['*' => $sequence]);
        foreach ($bodies as $body) {
            $this->assertInvalidResponse(fn () => HomeassistantApi::make()->logbook()->get(['light.kitchen']));
        }
        Http::assertSentCount(count($bodies));
    }

    public function test_services_reject_unexpected_state_lists_and_response_wrappers(): void
    {
        $cases = [
            [false, ['message' => self::TOKEN]], [false, [self::TOKEN]], [false, [[]]],
            [false, [['entity_id' => 'light.kitchen', 'state' => []]]],
            [true, []], [true, ['changed_states' => []]],
            [true, ['changed_states' => ['message' => self::TOKEN], 'service_response' => []]],
            [true, ['changed_states' => [], 'service_response' => self::TOKEN]],
            [true, ['changed_states' => [], 'service_response' => ['unexpected-list']]],
            [true, ['changed_states' => [[]], 'service_response' => null]],
        ];
        $sequence = Http::sequence();
        foreach ($cases as [$returnResponse, $body]) {
            $sequence->push($body);
        }
        Http::fake(['*' => $sequence]);
        foreach ($cases as [$returnResponse, $body]) {
            $this->assertInvalidResponse(fn () => HomeassistantApi::make()->services()->call(
                'light', 'turn_on', ['entity_id' => 'light.kitchen'], returnResponse: $returnResponse
            ));
        }
        Http::assertSentCount(count($cases));
    }

    public function test_valid_event_logbook_and_service_metadata_remain_available(): void
    {
        $when = '2026-10-01T10:00:00.123456Z';
        $service = ['changed_states' => [['entity_id' => 'light.kitchen', 'state' => '', 'extra' => 'preserved']],
            'service_response' => null];
        Http::fake([
            self::URL.'/api/events' => Http::response([
                ['event' => 'example', 'listener_count' => 2], ['event' => 'without_count'],
            ]),
            self::URL.'/api/logbook/*' => Http::response([['when' => $when, 'name' => 'Kitchen', 'state' => null]]),
            self::URL.'/api/services/*' => Http::response($service),
        ]);
        $api = HomeassistantApi::make();
        $events = $api->events()->all();
        $this->assertSame('example', $events[0]->event);
        $this->assertSame('2', $events[0]->listenerCount);
        $this->assertSame('0', $events[1]->listenerCount);
        $entry = $api->logbook()->get(['light.kitchen'])[0];
        $this->assertSame($when, $entry->when);
        $this->assertSame('Kitchen', $entry->name);
        $this->assertNull($entry->state);
        $this->assertNull($entry->entityId);
        $this->assertSame($service, $api->services()->call('light', 'turn_on', returnResponse: true));
        Http::assertSentCount(3);
    }

    public function test_event_firing_does_not_accept_a_missing_or_invalid_confirmation(): void
    {
        Http::fake(['*' => Http::sequence()->push([])->push(['message' => []])]);
        $this->assertInvalidResponse(fn () => HomeassistantApi::make()->events()->fire('example'));
        $this->assertInvalidResponse(fn () => HomeassistantApi::make()->events()->fire('example'));
        Http::assertSentCount(2);
    }

    public function test_invalid_timeouts_are_rejected_before_requests_or_accidental_unbounded_waits(): void
    {
        Http::fake();
        foreach (['connect_timeout', 'timeout'] as $key) {
            foreach (['invalid', self::TOKEN, 0, -1, 0.0001, 1e308, INF, NAN, true, false, [], null] as $value) {
                try {
                    HomeassistantApi::make(['url' => self::URL, 'token' => self::TOKEN, $key => $value])->system()->verify();
                    $this->fail('Invalid timeouts must be rejected before building a request.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringContainsString($key, $exception->getMessage());
                    $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_numeric_strings_fractional_timeouts_and_defaults_are_passed_to_http(): void
    {
        $options = [];
        Http::fake(function (Request $request, array $requestOptions) use (&$options) {
            $options[] = [$requestOptions['connect_timeout'], $requestOptions['timeout']];

            return Http::response(['message' => 'API running.']);
        });
        HomeassistantApi::make(['url' => self::URL, 'token' => self::TOKEN,
            'connect_timeout' => '0.25', 'timeout' => 0.5])->system()->verify();
        HomeassistantApi::make()->system()->verify();
        config(['homeassistant-api.connect_timeout' => '1.5', 'homeassistant-api.timeout' => 4.5]);
        HomeassistantApi::make()->system()->verify();

        $this->assertSame([[0.25, 0.5], [3.0, 8.0], [1.5, 4.5]], $options);
        Http::assertSentCount(3);
    }

    private function assertInvalidResponse(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Unexpected response structures must raise a package exception.');
        } catch (HomeAssistantException $exception) {
            $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
