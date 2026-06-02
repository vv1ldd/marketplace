<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateMutationException extends RuntimeException
{
    public function __construct(
        public readonly string $mutationId,
        public readonly string $mutationPath,
        public readonly string $guardKey,
        string $message = 'Duplicate mutation rejected.'
    ) {
        parent::__construct($message);
    }
}
