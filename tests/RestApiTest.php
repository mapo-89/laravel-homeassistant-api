<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mapo89\LaravelHomeassistantApi\Api\Utils\ApiClient;
use Mapo89\LaravelHomeassistantApi\Exceptions\EntityNotFoundException;
use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;
use Mapo89\LaravelHomeassistantApi\Exceptions\ServiceCallFailedException;
use Mapo89\LaravelHomeassistantApi\Exceptions\UnauthorizedException;
use Mapo89\LaravelHomeassistantApi\HomeassistantApi;
use Mapo89\LaravelHomeassistantApi\HomeassistantApiServiceProvider;
use Orchestra\Testbench\TestCase;
use RuntimeException;

final class RestApiTest extends TestCase
{
    private const URL = 'https://homeassistant.example.test';

    private const TOKEN = 'synthetic-review-token';

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

    public function test_artisan_lists_state_objects_for_all_entities_and_a_single_entity(): void
    {
        $state = ['entity_id' => 'light.kitchen', 'state' => 'on'];
        Http::fake([
            self::URL.'/api/states' => Http::response([$state]),
            self::URL.'/api/states/light.kitchen' => Http::response($state),
        ]);

        $this->artisan('ha:call')
            ->expectsOutput('Listing all states:')
            ->expectsOutput('light.kitchen => on')
            ->assertExitCode(0);

        $this->artisan('ha:call', ['--entity' => 'light.kitchen'])
            ->expectsOutput('Listing state for light.kitchen')
            ->expectsOutput('light.kitchen => on')
            ->assertExitCode(0);
        Http::assertSentCount(2);
    }

    public function test_both_template_accessors_render_plain_text_without_changing_whitespace(): void
    {
        $rendered = "  kitchen\n";
        Http::fake([self::URL.'/api/template' => Http::response($rendered)]);
        $api = HomeassistantApi::make();

        $this->assertSame($rendered, $api->template()->render('{{ room }}'));
        $this->assertSame($rendered, $api->templates()->render('{{ room }}'));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['template'] === '{{ room }}');
        Http::assertSentCount(2);
    }

    public function test_events_without_data_send_an_empty_json_object_and_keep_nonempty_payloads(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Event fired.'])]);
        $events = HomeassistantApi::make()->events();

        $this->assertTrue($events->fire('empty_event'));
        $this->assertTrue($events->fire('populated_event', ['name' => 'Kitchen', 'items' => []]));

        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'/api/events/empty_event'
            && $request->method() === 'POST' && $request->body() === '{}');
        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'/api/events/populated_event'
            && json_decode($request->body(), true) === ['name' => 'Kitchen', 'items' => []]);
        Http::assertSentCount(2);
    }

    public function test_minimal_history_keeps_entity_grouping_and_uses_last_changed_when_last_updated_is_absent(): void
    {
        $first = [
            'entity_id' => 'sensor.temperature', 'state' => '20',
            'last_changed' => '2026-10-01T10:00:00Z', 'last_updated' => '2026-10-01T10:05:00Z',
            'attributes' => ['unit_of_measurement' => '°C'],
        ];
        Http::fake(['*' => Http::response([
            [$first, ['state' => '21', 'last_changed' => '2026-10-01T11:00:00Z'], $first],
            [[...$first, 'entity_id' => 'sensor.other']],
        ])]);

        $history = HomeassistantApi::make()->history()->get(
            entityIds: ['sensor.temperature', 'sensor.other'], minimal: true
        );

        $this->assertSame(['sensor.temperature', 'sensor.other'], array_keys($history));
        $middle = $history['sensor.temperature'][1];
        $this->assertSame('sensor.temperature', $middle->entityId);
        $this->assertSame('21', $middle->state);
        $this->assertSame([], $middle->attributes);
        $this->assertTrue($middle->lastChanged->equalTo($middle->lastUpdated));
        $this->assertSame('2026-10-01T11:00:00+00:00', $middle->lastUpdated->toIso8601String());
        $this->assertSame('2026-10-01T10:05:00+00:00', $history['sensor.temperature'][0]->lastUpdated->toIso8601String());
        $this->assertSame(['unit_of_measurement' => '°C'], $history['sensor.temperature'][0]->attributes);
        Http::assertSent(fn (Request $request) => $request['minimal_response'] === '1'
            && $request['filter_entity_id'] === 'sensor.temperature,sensor.other');
    }

    public function test_history_explicitly_sends_both_significance_settings_and_preserves_the_period(): void
    {
        Http::fake(['*' => Http::response([])]);
        $history = HomeassistantApi::make()->history();
        $start = Carbon::parse('2026-10-01T10:00:00Z');
        $end = Carbon::parse('2026-10-01T12:00:00Z');

        $this->assertSame([], $history->get($start, $end, ['sensor.temperature'], significantOnly: false));
        $this->assertSame([], $history->flat($start, $end, ['sensor.temperature'], significantOnly: true));
        foreach (['0', '1'] as $value) {
            Http::assertSent(fn (Request $request) => $request['significant_changes_only'] === $value
                && $request['end_time'] === $end->toIso8601String()
                && str_contains($request->url(), '/api/history/period/'.$start->toIso8601String()));
        }
        Http::assertSentCount(2);
    }

    public function test_logbook_sends_the_start_in_the_path_and_the_end_as_a_query_parameter(): void
    {
        Http::fake(['*' => Http::response([[
            'entity_id' => 'light.kitchen', 'name' => 'Kitchen', 'when' => '2026-10-01T10:00:00+02:00',
        ]])]);
        $start = CarbonImmutable::parse('2026-10-01T10:00:00+02:00');
        $end = $start->addHours(2);

        $entries = HomeassistantApi::make()->logbook()->get(['light.kitchen'], $start, $end);

        $this->assertSame('light.kitchen', $entries[0]->entityId);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/logbook/'.$start->toIso8601String())
            && $request['end_time'] === $end->toIso8601String()
            && $request['entity'] === 'light.kitchen'
            && ! array_key_exists('start_time', $request->data()));
    }

    public function test_history_accepts_immutable_dates_for_grouped_and_flat_results(): void
    {
        Http::fake(['*' => Http::response([[[
            'entity_id' => 'sensor.temperature', 'state' => '20',
            'last_changed' => '2026-10-01T10:00:00Z', 'last_updated' => '2026-10-01T10:00:00Z',
        ]]])]);
        $start = CarbonImmutable::parse('2026-10-01T10:00:00+02:00');
        $end = $start->addHours(2);
        $history = HomeassistantApi::make()->history();

        $grouped = $history->get($start, $end, ['sensor.temperature']);
        $flat = $history->flat($start, $end, ['sensor.temperature']);

        $this->assertSame('20', $grouped['sensor.temperature'][0]->state);
        $this->assertSame('sensor.temperature', $flat[0]->entityId);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/history/period/'.$start->toIso8601String())
            && $request['end_time'] === $end->toIso8601String());
    }

    public function test_static_configuration_retains_http_timeouts_and_can_disable_state_caching(): void
    {
        config(['homeassistant-api.timeout' => 30, 'homeassistant-api.connect_timeout' => 5,
            'homeassistant-api.cache_responses' => false]);
        Http::fake(function (Request $request, array $options) {
            $this->assertEquals(30, $options['timeout']);
            $this->assertEquals(5, $options['connect_timeout']);

            return Http::response([]);
        });

        $this->assertFalse((new ApiClient)->getConfig()['cache_responses']);
        $api = HomeassistantApi::make();
        $api->states()->all();
        $api->states()->all();
        Http::assertSentCount(2);
    }

    public function test_state_cache_is_isolated_by_url_and_token(): void
    {
        Http::fake(fn (Request $request) => Http::response([
            ['entity_id' => 'sensor.identity', 'state' => $request->url().'|'.$request->header('Authorization')[0]],
        ]));
        $configs = [
            ['url' => self::URL, 'token' => self::TOKEN],
            ['url' => self::URL, 'token' => 'other-token'],
            ['url' => 'https://other.example.test', 'token' => self::TOKEN],
        ];
        foreach ($configs as $config) {
            $api = HomeassistantApi::make($config);
            $expected = $config['url'].'/api/states|Bearer '.$config['token'];
            $this->assertSame($expected, $api->states()->all()[0]->state);
            $this->assertSame($expected, $api->states()->all()[0]->state);
        }
        Http::assertSentCount(3);
    }

    public function test_successful_state_writes_and_deletions_invalidate_cached_lists_even_when_writer_caching_is_disabled(): void
    {
        $states = ['sensor.example' => ['entity_id' => 'sensor.example', 'state' => 'off']];
        Http::fake(function (Request $request) use (&$states) {
            if ($request->method() === 'POST') {
                $states['sensor.example']['state'] = $request['state'];

                return Http::response($states['sensor.example']);
            }
            if ($request->method() === 'DELETE') {
                unset($states['sensor.example']);

                return Http::response(['message' => 'Entity removed.']);
            }

            return Http::response(array_values($states));
        });
        $reader = HomeassistantApi::make();
        $writer = HomeassistantApi::make(['url' => self::URL, 'token' => self::TOKEN, 'cache_responses' => false]);

        $this->assertSame('off', $reader->states()->all()[0]->state);
        $this->assertSame('on', $writer->states()->createOrUpdate('sensor.example', 'on')->state);
        $this->assertSame('on', $reader->states()->all()[0]->state);
        $this->assertTrue($writer->states()->delete('sensor.example'));
        $this->assertSame([], $reader->states()->all());
        Http::assertSentCount(5);
    }

    public function test_state_cache_invalidation_preserves_other_urls_tokens_and_unrelated_cache_entries(): void
    {
        $state = ['entity_id' => 'sensor.example', 'state' => 'off'];
        Http::fake(function (Request $request) use (&$state) {
            if ($request->method() === 'POST') {
                $state['state'] = $request['state'];

                return Http::response($state);
            }

            return Http::response([$state]);
        });
        $clients = [
            HomeassistantApi::make(),
            HomeassistantApi::make(['url' => self::URL, 'token' => 'other-token']),
            HomeassistantApi::make(['url' => 'https://other.example.test', 'token' => self::TOKEN]),
        ];
        Cache::put('unrelated', 'preserved', 60);
        foreach ($clients as $client) {
            $this->assertSame('off', $client->states()->all()[0]->state);
        }

        $clients[0]->states()->createOrUpdate('sensor.example', 'on');

        $this->assertSame('on', $clients[0]->states()->all()[0]->state);
        $this->assertSame('off', $clients[1]->states()->all()[0]->state);
        $this->assertSame('off', $clients[2]->states()->all()[0]->state);
        $this->assertSame('preserved', Cache::get('unrelated'));
        Http::assertSentCount(5);
    }

    public function test_failed_state_and_service_mutations_leave_the_cached_list_available(): void
    {
        Http::fake(fn (Request $request) => $request->method() === 'GET'
            ? Http::response([['entity_id' => 'sensor.example', 'state' => 'off']])
            : Http::response(['message' => 'Operation failed.'], 500));
        $api = HomeassistantApi::make();
        $this->assertSame('off', $api->states()->all()[0]->state);

        foreach ([fn () => $api->states()->createOrUpdate('sensor.example', 'on'),
            fn () => $api->states()->delete('sensor.example'),
            fn () => $api->services()->call('homeassistant', 'turn_on', ['entity_id' => 'sensor.example'])] as $operation) {
            try {
                $operation();
                $this->fail('A failed mutation must be rejected.');
            } catch (ServiceCallFailedException $exception) {
                $this->assertSame(500, $exception->getCode());
            }
            $this->assertSame('off', $api->states()->all()[0]->state);
        }
        Http::assertSentCount(4);
    }

    public function test_cache_invalidation_errors_cannot_hide_successful_state_or_service_mutations(): void
    {
        Cache::shouldReceive('forget')->times(6)->andThrow(new RuntimeException(self::TOKEN));
        Http::fake(fn (Request $request) => match (true) {
            $request->method() === 'DELETE' => Http::response('', 204),
            str_contains($request->url(), '/services/') => Http::response([]),
            default => Http::response(['entity_id' => 'sensor.example', 'state' => 'on']),
        });

        foreach ([true, false] as $cacheResponses) {
            $api = HomeassistantApi::make(['url' => self::URL, 'token' => self::TOKEN,
                'cache_responses' => $cacheResponses]);
            $this->assertSame('on', $api->states()->createOrUpdate('sensor.example', 'on')->state);
            $this->assertTrue($api->states()->delete('sensor.example'));
            $this->assertSame([], $api->services()->call('light', 'turn_on', ['entity_id' => 'light.kitchen']));
        }
        Http::assertSentCount(6);
    }

    public function test_cache_read_errors_fall_back_to_http_without_retrying_http_errors(): void
    {
        Cache::shouldReceive('get')->twice()->andThrow(new RuntimeException(self::TOKEN));
        Http::fake(['*' => Http::sequence()
            ->push([['entity_id' => 'sensor.example', 'state' => 'on']])
            ->push(['message' => self::TOKEN], 503)]);
        $api = HomeassistantApi::make();

        $this->assertSame('on', $api->states()->all()[0]->state);
        try {
            $api->states()->all();
            $this->fail('An HTTP failure must not be swallowed or retried.');
        } catch (ServiceCallFailedException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
        }
        Http::assertSentCount(2);
    }

    public function test_cache_storage_errors_do_not_discard_fresh_states(): void
    {
        Cache::shouldReceive('get')->twice()->andReturnNull();
        Cache::shouldReceive('put')->twice()->andThrow(new RuntimeException(self::TOKEN));
        Http::fake(['*' => Http::sequence()
            ->push([['entity_id' => 'sensor.example', 'state' => 'off']])
            ->push([['entity_id' => 'sensor.example', 'state' => 'on']])]);

        $api = HomeassistantApi::make();
        $this->assertSame('off', $api->states()->all()[0]->state);
        $this->assertSame('on', $api->states()->all()[0]->state);
        Http::assertSentCount(2);
    }

    public function test_disabling_state_caching_skips_cache_reads_and_storage(): void
    {
        Cache::shouldReceive('get')->never();
        Cache::shouldReceive('put')->never();
        Http::fake(['*' => Http::response([])]);
        $api = HomeassistantApi::make(['url' => self::URL, 'token' => self::TOKEN, 'cache_responses' => false]);

        $this->assertSame([], $api->states()->all());
        $this->assertSame([], $api->states()->all());
        Http::assertSentCount(2);
    }

    public function test_successful_service_commands_invalidate_cached_state_lists(): void
    {
        $state = ['entity_id' => 'light.kitchen', 'state' => 'off'];
        Http::fake(function (Request $request) use (&$state) {
            if ($request->method() === 'POST') {
                $state['state'] = match (basename($request->url())) {
                    'turn_on' => 'on',
                    'turn_off' => 'off',
                    'toggle' => $state['state'] === 'off' ? 'on' : 'off',
                };

                return Http::response([$state]);
            }

            return Http::response([$state]);
        });
        $api = HomeassistantApi::make();
        $this->assertSame('off', $api->states()->all()[0]->state);

        $operations = [
            [fn () => $api->services()->turnOn('light.kitchen'), 'on'],
            [fn () => $api->services()->turnOff('light.kitchen'), 'off'],
            [fn () => $api->services()->toggle('light.kitchen'), 'on'],
            [fn () => $api->services()->call('light', 'turn_off', ['entity_id' => 'light.kitchen']), 'off'],
        ];
        foreach ($operations as [$operation, $expected]) {
            $operation();
            $this->assertSame($expected, $api->states()->all()[0]->state);
            $this->assertSame($expected, $api->states()->all()[0]->state);
        }
        Http::assertSentCount(9);
    }

    public function test_response_services_use_a_query_flag_and_leave_service_data_unchanged(): void
    {
        $result = ['changed_states' => [], 'service_response' => ['weather.home' => ['forecast' => []]]];
        Http::fake(fn (Request $request) => Http::response(
            str_contains($request->url(), '?return_response') ? $result : []
        ));
        $services = HomeassistantApi::make()->services();
        $data = ['entity_id' => 'weather.home', 'type' => 'daily'];

        $this->assertSame($result, $services->call('weather', 'get_forecasts', $data, returnResponse: true));
        $this->assertSame([], $services->call('light', 'turn_on', ['entity_id' => 'light.kitchen']));
        $this->assertSame([], $services->call('light', 'turn_off', ['entity_id' => 'light.kitchen'], returnResponse: false));
        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'/api/services/weather/get_forecasts?return_response'
            && $request->method() === 'POST' && json_decode($request->body(), true) === $data);
        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'/api/services/light/turn_on');
        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'/api/services/light/turn_off');
        Http::assertSentCount(3);
    }

    public function test_service_convenience_methods_reject_invalid_entity_ids_before_sending(): void
    {
        Http::fake();
        $services = HomeassistantApi::make()->services();
        foreach (['turnOn', 'turnOff', 'toggle'] as $method) {
            foreach (['', 'light', '.kitchen', 'light.', 'light.kitchen.extra', 'light/kitchen',
                'Light.kitchen', "light.kitchen\n", 'light.kitchen?query=1', '_light.kitchen',
                'light._kitchen', 'light.kitchen_', 'light.kitchen__two'] as $entityId) {
                try {
                    $services->{$method}($entityId);
                    $this->fail('Invalid entity IDs must be rejected.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertSame('Entity ID must use the format domain.object_id.', $exception->getMessage());
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_service_convenience_methods_keep_valid_entity_ids_and_additional_data(): void
    {
        Http::fake(['*' => Http::response([])]);
        $services = HomeassistantApi::make()->services();
        foreach (['turnOn' => 'turn_on', 'turnOff' => 'turn_off', 'toggle' => 'toggle'] as $method => $service) {
            $this->assertSame([], $services->{$method}('light.kitchen_2', ['brightness' => 80, 'entity_id' => 'light.other']));
            Http::assertSent(fn (Request $request) => $request->url() === self::URL.'/api/services/light/'.$service
                && $request['entity_id'] === 'light.kitchen_2' && $request['brightness'] === 80);
        }
        Http::assertSentCount(3);
    }

    public function test_state_and_service_endpoints_keep_the_injected_instance_configuration(): void
    {
        $config = ['url' => 'https://other.example.test', 'token' => 'other-synthetic-token'];
        $state = ['entity_id' => 'light.kitchen', 'state' => 'on'];
        Http::fake(fn (Request $request) => Http::response(
            str_contains($request->url(), '/services/') ? [$state] : $state
        ));
        $api = HomeassistantApi::make($config);

        $this->assertSame($config, $api->states()->getConfig());
        $this->assertSame($config, $api->services()->getConfig());
        $this->assertSame('on', $api->states()->get('light.kitchen')->state);
        $this->assertSame([$state], $api->services()->turnOn('light.kitchen'));
        Http::assertSent(fn (Request $request) => $request->url() === $config['url'].'/api/states/light.kitchen'
            && $request->hasHeader('Authorization', 'Bearer '.$config['token']));
        Http::assertSent(fn (Request $request) => $request->url() === $config['url'].'/api/services/light/turn_on'
            && $request->hasHeader('Authorization', 'Bearer '.$config['token']));
        Http::assertSentCount(2);
    }

    public function test_malformed_and_scalar_json_responses_raise_sanitized_package_exceptions(): void
    {
        $bodies = ['<html>'.self::TOKEN.'</html>', '{invalid-json:'.self::TOKEN, '',
            'null', 'true', '123', json_encode(self::TOKEN)];
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body, 200, ['Content-Type' => 'application/json']);
        }
        Http::fake(['*' => $sequence]);

        foreach ($bodies as $body) {
            try {
                HomeassistantApi::make()->system()->verify();
                $this->fail('Invalid JSON responses must be rejected.');
            } catch (HomeAssistantException $exception) {
                $this->assertSame('Home Assistant returned an invalid JSON response.', $exception->getMessage());
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
        Http::assertSentCount(count($bodies));
    }

    public function test_http_errors_have_the_expected_types_without_exposing_response_bodies(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => self::TOKEN], 401)
            ->push(['message' => self::TOKEN], 404)
            ->push(['message' => self::TOKEN], 500)]);
        foreach ([401 => UnauthorizedException::class, 404 => EntityNotFoundException::class,
            500 => ServiceCallFailedException::class] as $status => $expected) {
            try {
                HomeassistantApi::make()->system()->verify();
                $this->fail('An HTTP error must be rejected.');
            } catch (HomeAssistantException $exception) {
                $this->assertInstanceOf($expected, $exception);
                $this->assertSame($status, $exception->getCode());
                $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
    }

    public function test_connection_failures_do_not_expose_transport_details(): void
    {
        Http::fake(function () {
            throw new ConnectionException(self::TOKEN);
        });
        try {
            HomeassistantApi::make()->system()->verify();
            $this->fail('A connection error must be rejected.');
        } catch (HomeAssistantException $exception) {
            $this->assertSame('Home Assistant could not be reached.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
