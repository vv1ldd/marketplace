<?php

namespace App\Support;

final class SettlementAdapterHealthCodes
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const RPC_ERROR = 'rpc_error';

    public const BALANCE_UNAVAILABLE = 'balance_unavailable';

    public const STALE_OBSERVATION = 'stale_observation';

    public const CRYPTO_RAILS_DISABLED = 'crypto_rails_disabled';

    public const ADAPTER_DISABLED = 'adapter_disabled';

    public const CHAIN_ID_MISMATCH = 'chain_id_mismatch';
}
