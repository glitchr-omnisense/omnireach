<?php

namespace Omnireach\Exception;

/** The provider refused the credentials (401, 403): a wrong or revoked key, a token past its time, a scope not granted. */
final class InvalidKeyException extends ProviderException
{
    public static function refused(string $provider, string $what, int $status, ?string $message = null): self
    {
        return new self(\sprintf('%s refused the credentials (%d) for %s%s', $provider, $status, $what, null !== $message ? ': '.$message : '.'), $status);
    }
}
