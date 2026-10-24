<?php

namespace Omnireach\Exception;

/** The provider answered, and not with what was asked: an error status, an answer that is not JSON. */
class ProviderException extends \RuntimeException implements OmnireachException
{
    public static function answered(string $provider, string $what, int $status, ?string $message = null): self
    {
        return new self(\sprintf('%s answered %d for %s%s', $provider, $status, $what, null !== $message ? ': '.$message : '.'), $status);
    }
}
