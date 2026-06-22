<?php

namespace App\Services\Settlement;

use App\Contracts\IdentityPaymentExecutor;
use App\Models\VaultManagedWalletKey;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ManagedEvmIdentityPaymentExecutor implements IdentityPaymentExecutor
{
    /**
     * @return array{transaction_hash: string, network: string}
     */
    public function executeUsdcTransfer(
        int $senderBindingId,
        string $recipientAddressNormalized,
        string $amountWei,
        string $networkKey,
    ): array {
        $managedKey = VaultManagedWalletKey::query()
            ->where('identity_binding_id', $senderBindingId)
            ->where('network_key', $networkKey)
            ->first();

        if (! $managedKey instanceof VaultManagedWalletKey) {
            throw ValidationException::withMessages([
                'execute' => 'Managed settlement key is unavailable for the sender binding.',
            ]);
        }

        $tokenContract = (string) data_get(config('verification_proofs.usdc_transfer.'.$networkKey), 'token_contract', '');
        $chainId = (int) data_get(config('verification_proofs.usdc_transfer.'.$networkKey), 'chain_id', 0);
        $rpcUrl = $this->rpcUrlForNetwork($networkKey);

        if ($tokenContract === '' || $chainId <= 0 || $rpcUrl === '') {
            throw ValidationException::withMessages([
                'execute' => 'Settlement rail configuration is incomplete for '.$networkKey.'.',
            ]);
        }

        if (! $this->castAvailable()) {
            throw ValidationException::withMessages([
                'execute' => 'On-chain execution requires the Foundry cast binary.',
            ]);
        }

        $privateKeyHex = Crypt::decryptString((string) $managedKey->encrypted_secret);

        $process = new Process([
            'cast', 'send', $tokenContract,
            'transfer(address,uint256)(bool)',
            $recipientAddressNormalized,
            $amountWei,
            '--private-key', '0x'.$privateKeyHex,
            '--rpc-url', $rpcUrl,
            '--chain', (string) $chainId,
            '--json',
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages([
                'execute' => trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Settlement execution failed.',
            ]);
        }

        $payload = json_decode($process->getOutput(), true);
        $transactionHash = is_array($payload) ? strtolower((string) ($payload['transactionHash'] ?? '')) : '';

        if ($transactionHash === '') {
            throw ValidationException::withMessages([
                'execute' => 'Settlement execution did not return a transaction hash.',
            ]);
        }

        return [
            'transaction_hash' => $transactionHash,
            'network' => $networkKey,
        ];
    }

    private function castAvailable(): bool
    {
        $process = new Process(['cast', '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    private function rpcUrlForNetwork(string $networkKey): string
    {
        return match ($networkKey) {
            'polygon' => (string) env('POLYGON_RPC_URL', 'https://polygon-bor-rpc.publicnode.com'),
            'ethereum' => (string) env('ETHEREUM_RPC_URL', 'https://ethereum-rpc.publicnode.com'),
            'base' => (string) env('BASE_RPC_URL', 'https://base-rpc.publicnode.com'),
            default => '',
        };
    }
}
