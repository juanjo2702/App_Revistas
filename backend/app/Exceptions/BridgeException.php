<?php

namespace App\Exceptions;

use RuntimeException;

class BridgeException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 502, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}
