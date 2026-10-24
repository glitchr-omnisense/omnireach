<?php

namespace Omnireach\Support;

/**
 * A decoded JSON answer, read with types: a provider asks for the string,
 * the number, the date or the list it expects at a dotted path, and gets
 * null (or an empty list) when the answer holds something else.
 */
final class Data
{
    /** @param array<mixed> $values */
    public function __construct(public readonly array $values = [])
    {
    }

    public static function of(mixed $value): self
    {
        return new self(\is_array($value) ? $value : []);
    }

    /** The value at a dotted path ("venue.city.name"), as it is. */
    public function raw(string $path): mixed
    {
        $value = $this->values;
        foreach (explode('.', $path) as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    public function has(string $path): bool
    {
        return null !== $this->raw($path);
    }

    /** A string; null when absent or empty. */
    public function string(string $path): ?string
    {
        $value = $this->raw($path);
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return \is_scalar($value) && '' !== trim((string) $value) ? trim((string) $value) : null;
    }

    public function int(string $path): ?int
    {
        $value = $this->raw($path);

        return is_numeric($value) ? (int) $value : null;
    }

    public function float(string $path): ?float
    {
        $value = $this->raw($path);

        return is_numeric($value) ? (float) $value : null;
    }

    /** true, 1, "1", "true", "yes". */
    public function bool(string $path): bool
    {
        $value = $this->raw($path);

        return true === $value || (\is_scalar($value) && \in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true));
    }

    /**
     * A date: a UNIX timestamp, or a string PHP reads ("2026-11-14 20:00:00",
     * ISO 8601), in $timezone when it names none. Null when absent, empty,
     * unreadable or "0000-00-00...".
     */
    public function date(string $path, ?\DateTimeZone $timezone = null): ?\DateTimeImmutable
    {
        $value = $this->raw($path);
        if (\is_int($value)) {
            return (new \DateTimeImmutable('@'.$value))->setTimezone($timezone ?? new \DateTimeZone('UTC'));
        }
        $string = $this->string($path);
        if (null === $string || str_starts_with($string, '0000-00-00')) {
            return null;
        }
        if (ctype_digit($string)) {
            return (new \DateTimeImmutable('@'.$string))->setTimezone($timezone ?? new \DateTimeZone('UTC'));
        }
        try {
            return new \DateTimeImmutable($string, $timezone);
        } catch (\Exception) {
            return null;
        }
    }

    /** The object at a path; an empty one when absent. */
    public function data(string $path): self
    {
        return self::of($this->raw($path));
    }

    /** @return list<self> the items of the list (or object) at a path */
    public function list(string $path): array
    {
        $value = $this->raw($path);

        return \is_array($value) ? array_values(array_map(self::of(...), $value)) : [];
    }

    /** @return list<self> the items of this value itself, when it is a list */
    public function items(): array
    {
        return array_values(array_map(self::of(...), $this->values));
    }
}
