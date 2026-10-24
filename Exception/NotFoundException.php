<?php

namespace Omnireach\Exception;

/** The provider has nothing under that identifier (404): a site or a property that does not exist or is not this account's. */
final class NotFoundException extends ProviderException
{
    public static function of(string $provider, string $what, ?string $message = null): self
    {
        return new self(\sprintf('%s has no %s%s', $provider, $what, null !== $message ? ': '.$message : '.'), 404);
    }
}
