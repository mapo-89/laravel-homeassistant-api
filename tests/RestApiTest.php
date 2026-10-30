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
        Http::fake(['*' => Http::response([['entity_id' => 'light.kitchen', 'name' => 'Kitchen']])]);
        $start = CarbonImmutable::parse('2026-10-01T10:00:00+02:00');
        $end = $start->addHours(2);

        $entries = HomeassistantApi::make()->logbook()->get(['light.kitchen'], $start, $end);

        $this->assertSame('light.kitchen', $entries[0]->entityId);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/logbook/'.$start->toIso8601String())
            && $request['end_time'] === $end->toIso8601String()
            && $request['entity'] === 'light.kitchen'
            && ! array_key_exists('start_time', $request->data()));
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
        Http::fake(function () { throw new ConnectionException(self::TOKEN); });
        try {
            HomeassistantApi::make()->system()->verify();
            $this->fail('A connection error must be rejected.');
        } catch (HomeAssistantException $exception) {
            $this->assertSame('Home Assistant could not be reached.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
