<?php

namespace App\Services\Settlement;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Services\MarketplaceIdentityResolver;
use App\Services\VaultIdentityService;
use App\Services\WalletBindingService;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecipientResolverService
{
    public const CONTRACT_NAME = 'resolve-recipient';

    public const CONTRACT_VERSION = 'v3a';

    public const CAPABILITY_RECEIVE = 'receive';

    public const STATUS_RECEIVE_ENABLED = 'receive_enabled';

    public const STATUS_RECEIVE_PENDING = 'receive_pending';

    public const STATUS_ROUTING_ENABLED = 'routing_enabled';

    public function __construct(
        private readonly MarketplaceIdentityResolver $identityResolver,
        private readonly VaultIdentityService $vaultIdentities,
        private readonly WalletBindingService $bindings,
        private readonly SettlementInstrumentCapabilityService $instrumentCapabilities,
    ) {}

    /**
     * Identity resolution boundary — capability graph, not wallet address lookup.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $alias): array
    {
        $normalizedAlias = User::normalizeUsername($alias);
        if ($normalizedAlias === null) {
            throw new NotFoundHttpException('Recipient alias is not valid.');
        }

        $user = $this->identityResolver->findExistingUserByUsernameCandidate($normalizedAlias);
        if (! $user instanceof User) {
            throw new NotFoundHttpException('Recipient identity was not found.');
        }

        $entityAddress = strtolower(trim((string) $user->entity_l1_address));
        if (! preg_match('/^sl1e_[a-f0-9]{39}$/', $entityAddress)) {
            throw new NotFoundHttpException('Recipient identity has no durable anchor.');
        }

        $identity = ['entity_l1_address' => $entityAddress];
        $vault = $this->vaultIdentities->resolveForStorefront($identity, $user);

        $walletBindings = $this->bindings
            ->listForVault($vault, IdentityBinding::TYPE_WALLET)
            ->filter(fn (IdentityBinding $binding) => $binding->isVerified())
            ->sortBy('binding_key')
            ->values();

        $capabilityBindings = $walletBindings
            ->map(fn (IdentityBinding $binding) => $this->formatReceivingCapability($binding))
            ->values()
            ->all();

        $paymentRoutingCapabilities = $walletBindings
            ->map(fn (IdentityBinding $binding) => $this->instrumentCapabilities->formatPaymentRoutingCapability($binding))
            ->filter()
            ->values()
            ->all();

        $displayAlias = '@'.$normalizedAlias;

        return [
            'contract' => [
                'name' => self::CONTRACT_NAME,
                'version' => self::CONTRACT_VERSION,
            ],
            'alias' => $displayAlias,
            'identity_id' => $entityAddress,
            'ownership' => [
                'vault_id' => $vault->id,
                'bindings' => $capabilityBindings,
            ],
            'receiving_capabilities' => $capabilityBindings,
            'payment_routing_capabilities' => $paymentRoutingCapabilities,
            'routing_candidates' => $this->routingCandidates($walletBindings),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReceivingCapability(IdentityBinding $binding): array
    {
        return [
            'binding_id' => $binding->id,
            'network' => $binding->binding_key,
            'asset' => $this->defaultReceiveAsset($binding),
            'capability' => self::CAPABILITY_RECEIVE,
            'status' => $binding->isVerified()
                ? self::STATUS_RECEIVE_ENABLED
                : self::STATUS_RECEIVE_PENDING,
        ];
    }

    /**
     * @param  Collection<int, IdentityBinding>  $bindings
     * @return list<array<string, mixed>>
     */
    private function routingCandidates(Collection $bindings): array
    {
        return $bindings
            ->map(fn (IdentityBinding $binding) => [
                'binding_id' => $binding->id,
                'network' => $binding->binding_key,
                'asset' => $this->defaultReceiveAsset($binding),
            ])
            ->values()
            ->all();
    }

    private function defaultReceiveAsset(IdentityBinding $binding): string
    {
        $protocol = (string) data_get($binding->metadata, 'protocol', '');

        return match ($protocol) {
            'evm' => 'USDC',
            'utxo' => 'BTC',
            'solana' => 'USDC',
            'ton' => 'USDC',
            default => 'USDC',
        };
    }
}
