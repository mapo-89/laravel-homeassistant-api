<?php

declare(strict_types=1);

namespace Mapo89\LaravelHomeassistantApi\Tests;

use Composer\Autoload\ClassLoader;
use Mapo89\LaravelHomeassistantApi\DTOs\State;
use Mapo89\LaravelHomeassistantApi\DTOs\StateContext;
use PHPUnit\Framework\TestCase;

final class StateDtoTest extends TestCase
{
    public function test_state_context_has_an_independently_resolvable_autoload_file(): void
    {
        $file = false;
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if ($file = $loader->findFile(StateContext::class)) {
                break;
            }
        }
        $this->assertNotFalse($file);
        $this->assertSame(realpath(__DIR__.'/../src/DTOs/StateContext.php'), realpath($file));

        $context = new StateContext(['id' => 'context-id']);
        $this->assertSame('context-id', $context->id);
        $this->assertNull($context->parent_id);
        $this->assertNull($context->user_id);
    }

    public function test_valid_states_preserve_context_raw_dates_and_nullable_metadata(): void
    {
        $timestamp = '2026-10-01T12:00:00.123456+02:00';
        $state = new State([
            'entity_id' => 'sensor.example', 'state' => '', 'attributes' => ['level' => 0],
            'last_changed' => $timestamp, 'last_updated' => null,
            'context' => ['id' => 'context-id', 'parent_id' => 'parent-id', 'user_id' => null],
        ]);

        $this->assertSame('', $state->state);
        $this->assertSame(['level' => 0], $state->attributes);
        $this->assertSame($timestamp, $state->last_changed_raw);
        $this->assertSame($timestamp, $state->last_changed->format('Y-m-d\TH:i:s.uP'));
        $this->assertNull($state->last_updated);
        $this->assertNull($state->last_reported);
        $this->assertInstanceOf(StateContext::class, $state->context);
        $this->assertSame('parent-id', $state->context->parent_id);
        $this->assertNull($state->context->user_id);
    }
}
