<?php

namespace Omnireach\Exception;

/** A provider asked for what it cannot do: a dimension it does not report, search queries from an analytics tool. */
final class NotSupportedException extends \LogicException implements OmnireachException
{
    public static function capability(object $provider, string $name, string $capability): self
    {
        return new self(\sprintf('The "%s" provider (%s) does not %s.', $name, $provider::class, $capability));
    }
}
