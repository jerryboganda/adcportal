<?php

namespace App\Services;

use App\Models\FailedJob;

/**
 * Read-only inspection of failed background work (master-prompt §81).
 *
 * The hard rule: a failed job's payload can carry patient data, so it is never
 * returned. What an operator gets instead is the job class, where and when it
 * failed, how many attempts it took, the exception type and its first line,
 * and the NAMES of the properties the payload carries — structure, never
 * values. That is enough to tell whether the job was touching clinical data at
 * all, which is exactly what an operator needs to decide how to act.
 */
class JobInspector
{
    private const MAX_PROPERTY_NAMES = 12;

    private const MAX_SUMMARY = 300;

    /** @return array<int, array> newest failures first */
    public static function list(int $limit = 25): array
    {
        return FailedJob::query()
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (FailedJob $job) => self::shape($job))
            ->all();
    }

    public static function shape(FailedJob $job): array
    {
        $payload = json_decode((string) $job->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $exception = (string) $job->exception;

        return [
            'id' => (string) $job->id,
            'uuid' => (string) $job->uuid,
            'queue' => $job->queue,
            'connection' => $job->connection,
            'jobClass' => self::jobClass($payload),
            'attempts' => (int) ($payload['attempts'] ?? 0),
            'failedAt' => $job->failed_at?->toIso8601String(),
            'exceptionType' => self::exceptionType($exception),
            'exceptionSummary' => self::firstLine($exception),
            'payloadBytes' => strlen((string) $job->payload),
            'payloadPropertyNames' => self::propertyNames($payload),
        ];
    }

    private static function jobClass(array $payload): ?string
    {
        $class = $payload['data']['commandName']
            ?? $payload['displayName']
            ?? $payload['job']
            ?? null;

        return is_string($class) && $class !== '' ? $class : null;
    }

    private static function exceptionType(string $exception): ?string
    {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_\\\\]*)/', $exception, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function firstLine(string $exception): ?string
    {
        $line = trim(strtok($exception, "\n") ?: '');

        if ($line === '') {
            return null;
        }

        return mb_strlen($line) > self::MAX_SUMMARY ? mb_substr($line, 0, self::MAX_SUMMARY).'…' : $line;
    }

    /**
     * Property names declared by the job class — read from the class
     * definition with reflection, never from the payload.
     *
     * This is what makes inspection safe: the serialized command is never
     * unserialized (so inspecting a failed job cannot execute a payload or
     * trigger a gadget chain), and no value is ever decoded — a regex over the
     * serialized string would happily capture property *values* too, which is
     * exactly the leak this method exists to avoid.
     *
     * @return array<int, string>
     */
    private static function propertyNames(array $payload): array
    {
        $class = $payload['data']['commandName'] ?? $payload['displayName'] ?? null;

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return [];
        }

        $names = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $names[] = $property->getName();
        }

        $names = array_values(array_unique($names));
        sort($names);

        return array_slice($names, 0, self::MAX_PROPERTY_NAMES);
    }
}
