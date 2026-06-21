<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceStreamWriteResult
{
    public function __construct(
        public readonly IdentityGovernanceStreamAppendResult $append,
        public readonly IdentityGovernanceDualProjection $projection,
    ) {}
}
