<?php

namespace Omnireach\Exception;

/** A query no report can answer: a period that ends before it starts, a limit below 1. */
final class InvalidArgumentException extends \InvalidArgumentException implements OmnireachException
{
}
