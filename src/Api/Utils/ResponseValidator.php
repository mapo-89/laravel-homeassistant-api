<?php

namespace Mapo89\LaravelHomeassistantApi\Api\Utils;

use Mapo89\LaravelHomeassistantApi\Exceptions\HomeAssistantException;

final class ResponseValidator
{
    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function records(array $data, string $description): array
    {
        if (! array_is_list($data)) {
            throw new HomeAssistantException('Home Assistant returned invalid '.$description.'.');
        }
        foreach ($data as $entry) {
            if (! is_array($entry)) {
                throw new HomeAssistantException('Home Assistant returned invalid '.$description.'.');
            }
        }

        return $data;
    }

    public static function timestamp(mixed $value, string $description): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        try {
            if (! is_string($value) || $value === '') {
                throw new \InvalidArgumentException;
            }
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new \InvalidArgumentException;
            }

            return $date;
        } catch (\Exception) {
            throw new HomeAssistantException('Home Assistant returned an invalid '.$description.' timestamp.');
        }
    }
}
