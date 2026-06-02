<?php

namespace App\Exceptions;

use RuntimeException;

class WriterAuthorityException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $scope,
        string $message = 'Writer authority cannot be proven.'
    ) {
        parent::__construct($message);
    }
}
