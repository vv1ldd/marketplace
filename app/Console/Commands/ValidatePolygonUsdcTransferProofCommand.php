<?php

namespace App\Console\Commands;

use App\Models\BindingProof;
use App\Models\User;
use App\Models\VerificationEvent;
use App\Services\BindingProofVerificationService;
use App\Services\VaultIdentityService;
use App\Support\EvmErc20TransferProofVerifier;
use App\Support\EvmRpcClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ValidatePolygonUsdcTransferProofCommand extends Command
{
    protected $signature = 'verification:validate-polygon-usdc-proof
        {--tx= : Polygon transaction hash}
        {--recipient= : Expected ERC-20 recipient}
        {--minimum-amount= : Minimum USDC amount in decimal units}
        {--sender= : Optional expected sender address}
        {--entity= : Vault entity_l1_address; generated if omitted}
        {--dry-run : Verify on RPC only; do not persist proof or events}
        {--artifact-dir= : Optional directory for validation artifact bundle}';

    protected $description = 'Run live Polygon USDC transfer proof validation against real RPC';

    public function handle(
        BindingProofVerificationService $proofs,
        VaultIdentityService $vaultIdentities,
        EvmErc20TransferProofVerifier $verifier,
        EvmRpcClient $rpcClient,
    ): int {
        $txHash = strtolower(trim((string) ($this->option('tx') ?: env('POLYGON_PROOF_E2E_TX_HASH', ''))));
        $recipient = strtolower(trim((string) ($this->option('recipient') ?: env('POLYGON_PROOF_E2E_RECIPIENT', ''))));
        $minimumAmount = trim((string) ($this->option('minimum-amount') ?: env('POLYGON_PROOF_E2E_MINIMUM_AMOUNT', '')));
        $sender = strtolower(trim((string) ($this->option('sender') ?: env('POLYGON_PROOF_E2E_SENDER', ''))));
        $entityAddress = trim((string) ($this->option('entity') ?: env('POLYGON_PROOF_E2E_ENTITY_ADDRESS', '')));
        $dryRun = (bool) $this->option('dry-run');
        $rpcUrl = trim((string) env('POLYGON_RPC_URL', ''));

        if ($txHash === '' || $recipient === '' || $minimumAmount === '' || $rpcUrl === '') {
            $this->error('Required: POLYGON_RPC_URL, tx hash, recipient, minimum amount.');
            $this->line('Use flags or env: POLYGON_PROOF_E2E_TX_HASH, POLYGON_PROOF_E2E_RECIPIENT, POLYGON_PROOF_E2E_MINIMUM_AMOUNT');

            return self::FAILURE;
        }

        config([
            'blockchain_networks.networks.polygon.rpc_url' => $rpcUrl,
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $tokenConfig = config('verification_proofs.usdc_transfer.polygon', []);
        $expectedChainId = (int) ($tokenConfig['chain_id'] ?? 137);
        $expectedTokenContract = strtolower((string) ($tokenConfig['token_contract'] ?? ''));
        $minimumAmountRaw = $this->toTokenBaseUnits($minimumAmount, (int) ($tokenConfig['decimals'] ?? 6));
        $proofReference = BindingProof::referenceFor(BindingProof::TYPE_USDC_TRANSFER, $txHash);
        $artifactDir = $this->resolveArtifactDirectory($txHash);

        $this->info('Polygon USDC transfer proof live gate');
        $this->table(['Input', 'Value'], [
            ['Gate', $dryRun ? 'DRY RUN' : 'PERSISTENCE'],
            ['RPC URL', $this->maskUrl($rpcUrl)],
            ['Transaction', $txHash],
            ['Recipient', $recipient],
            ['Minimum amount', $minimumAmount],
            ['Sender', $sender !== '' ? $sender : '(any / bound wallet)'],
            ['Proof reference', $proofReference],
            ['Artifact dir', $artifactDir],
        ]);

        if ($dryRun) {
            return $this->runDryRunGate(
                verifier: $verifier,
                rpcClient: $rpcClient,
                rpcUrl: $rpcUrl,
                txHash: $txHash,
                recipient: $recipient,
                sender: $sender,
                minimumAmount: $minimumAmount,
                minimumAmountRaw: $minimumAmountRaw,
                expectedChainId: $expectedChainId,
                expectedTokenContract: $expectedTokenContract,
                proofReference: $proofReference,
                artifactDir: $artifactDir,
            );
        }

        return $this->runPersistenceGate(
            proofs: $proofs,
            vaultIdentities: $vaultIdentities,
            verifier: $verifier,
            rpcClient: $rpcClient,
            rpcUrl: $rpcUrl,
            txHash: $txHash,
            recipient: $recipient,
            sender: $sender,
            minimumAmount: $minimumAmount,
            minimumAmountRaw: $minimumAmountRaw,
            entityAddress: $entityAddress,
            proofReference: $proofReference,
            expectedChainId: $expectedChainId,
            expectedTokenContract: $expectedTokenContract,
            artifactDir: $artifactDir,
        );
    }

    private function runDryRunGate(
        EvmErc20TransferProofVerifier $verifier,
        EvmRpcClient $rpcClient,
        string $rpcUrl,
        string $txHash,
        string $recipient,
        string $sender,
        string $minimumAmount,
        string $minimumAmountRaw,
        int $expectedChainId,
        string $expectedTokenContract,
        string $proofReference,
        string $artifactDir,
        bool $writeArtifacts = true,
    ): int {
        $proofCountBefore = BindingProof::query()->count();
        $verifiedEventCountBefore = VerificationEvent::query()
            ->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)
            ->count();
        $eventCountBefore = VerificationEvent::query()->count();

        $rpcReachable = false;
        $chainId = null;
        $receipt = null;

        try {
            Http::timeout(8)->acceptJson()->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'eth_chainId',
                'params' => [],
            ])->throw();
            $rpcReachable = true;
            $chainId = $rpcClient->getChainId($rpcUrl);
            $receipt = $rpcClient->getTransactionReceipt($rpcUrl, $txHash);
        } catch (\Throwable $exception) {
            $this->renderGateTable([
                ['RPC reachable', false],
                ['eth_chainId = '.$expectedChainId, false],
                ['Receipt found', false],
                ['status = 0x1', false],
                ['ERC-20 Transfer decoded', false],
                ['USDC contract matches', false],
                ['Recipient matches', false],
                ['Amount >= minimum', false],
                ['proof_created = NO', BindingProof::query()->count() === $proofCountBefore],
                ['proof_verified = NO', VerificationEvent::query()->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)->count() === $verifiedEventCountBefore],
                ['DB writes = NO', BindingProof::query()->count() === $proofCountBefore && VerificationEvent::query()->count() === $eventCountBefore],
            ]);
            $this->error('Dry run gate failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $result = $verifier->verify([
            'binding_key' => 'polygon',
            'transaction_hash' => $txHash,
            'token_contract' => $expectedTokenContract,
            'chain_id' => $expectedChainId,
            'expected_recipient' => $recipient,
            'minimum_amount' => $minimumAmountRaw,
            'expected_sender' => $sender !== '' ? $sender : null,
        ]);

        $proofData = (array) ($result['proof'] ?? []);
        $proofCountAfter = BindingProof::query()->count();
        $verifiedEventCountAfter = VerificationEvent::query()
            ->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)
            ->count();
        $eventCountAfter = VerificationEvent::query()->count();

        $checks = [
            ['RPC reachable', $rpcReachable],
            ['eth_chainId = '.$expectedChainId, $chainId === $expectedChainId],
            ['Receipt found', $receipt !== null],
            ['status = 0x1', strtolower((string) ($receipt['status'] ?? '')) === '0x1'],
            ['ERC-20 Transfer decoded', ($result['valid'] ?? false) === true],
            ['USDC contract matches', strtolower((string) ($proofData['token_contract'] ?? '')) === $expectedTokenContract],
            ['Recipient matches', strtolower((string) ($proofData['recipient'] ?? '')) === $recipient],
            ['Amount >= minimum', ($result['valid'] ?? false) === true && bccomp((string) ($proofData['amount'] ?? '0'), $minimumAmountRaw, 0) >= 0],
            ['proof_created = NO', $proofCountAfter === $proofCountBefore],
            ['proof_verified = NO', $verifiedEventCountAfter === $verifiedEventCountBefore],
            ['DB writes = NO', $proofCountAfter === $proofCountBefore && $eventCountAfter === $eventCountBefore],
        ];

        $this->renderGateTable($checks);

        if (! $this->allChecksPassed($checks)) {
            $this->error('Dry run gate failed: '.($result['error'] ?? 'One or more checks did not pass.'));

            return self::FAILURE;
        }

        if ($writeArtifacts) {
            $this->writeValidationBundle(
                artifactDir: $artifactDir,
                gate: 'dry_run',
                txHash: $txHash,
                recipient: $recipient,
                sender: $sender,
                minimumAmount: $minimumAmount,
                minimumAmountRaw: $minimumAmountRaw,
                expectedChainId: $expectedChainId,
                expectedTokenContract: $expectedTokenContract,
                proofReference: $proofReference,
                chainId: $chainId,
                receipt: $receipt,
                verificationResult: $result,
                proofData: $proofData,
                claimUpdate: [
                    'persisted' => false,
                    'reason' => 'dry_run_gate',
                    'expected_relation' => 'owns(Address)',
                    'expected_evidence_type' => BindingProof::TYPE_USDC_TRANSFER,
                ],
                events: [
                    'recorded' => false,
                    'reason' => 'dry_run_gate',
                    'db_writes' => false,
                ],
                checks: $checks,
            );
        }

        $this->info('Dry run gate: PASSED');
        if ($writeArtifacts) {
            $this->line('Artifact bundle: '.$artifactDir);
        }

        return self::SUCCESS;
    }

    private function runPersistenceGate(
        BindingProofVerificationService $proofs,
        VaultIdentityService $vaultIdentities,
        EvmErc20TransferProofVerifier $verifier,
        EvmRpcClient $rpcClient,
        string $rpcUrl,
        string $txHash,
        string $recipient,
        string $sender,
        string $minimumAmount,
        string $minimumAmountRaw,
        string $entityAddress,
        string $proofReference,
        int $expectedChainId,
        string $expectedTokenContract,
        string $artifactDir,
    ): int {
        $dryRunResult = $this->runDryRunGate(
            verifier: $verifier,
            rpcClient: $rpcClient,
            rpcUrl: $rpcUrl,
            txHash: $txHash,
            recipient: $recipient,
            sender: $sender,
            minimumAmount: $minimumAmount,
            minimumAmountRaw: $minimumAmountRaw,
            expectedChainId: $expectedChainId,
            expectedTokenContract: $expectedTokenContract,
            proofReference: $proofReference,
            artifactDir: $artifactDir,
            writeArtifacts: false,
        );

        if ($dryRunResult !== self::SUCCESS) {
            $this->error('Persistence gate blocked: dry-run checks must pass first.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Persistence gate');

        if ($entityAddress === '') {
            $entityAddress = 'sl1e_'.bin2hex(random_bytes(20));
            $entityAddress = substr($entityAddress, 0, 44);
        }

        $user = User::query()->where('entity_l1_address', $entityAddress)->first()
            ?? User::factory()->create(['entity_l1_address' => $entityAddress]);

        $vault = $vaultIdentities->resolveForStorefront([
            'entity_l1_address' => $entityAddress,
        ], $user);

        $input = [
            'binding_key' => 'polygon',
            'transaction_hash' => $txHash,
            'recipient' => $recipient,
            'minimum_amount' => $minimumAmount,
        ];

        if ($sender !== '') {
            $input['sender'] = $sender;
        }

        $existingProof = BindingProof::query()
            ->where('vault_id', $vault->id)
            ->where('proof_reference', $proofReference)
            ->first();

        if ($existingProof instanceof BindingProof) {
            $proof = $existingProof;
            $this->warn('Proof already exists for this vault/reference; validating counts only.');
        } else {
            try {
                $proof = $proofs->verifyUsdcTransfer($vault, $input);
            } catch (ValidationException $exception) {
                $this->error(collect($exception->errors())->flatten()->first() ?: 'Persistence gate failed.');

                return self::FAILURE;
            }
        }

        try {
            $proofs->verifyUsdcTransfer($vault, $input);
            $this->error('Persistence gate failed: duplicate request should be rejected.');

            return self::FAILURE;
        } catch (ValidationException) {
            // Expected for current API semantics.
        }

        $proofCount = BindingProof::query()
            ->where('vault_id', $vault->id)
            ->where('proof_reference', $proofReference)
            ->count();
        $verifiedEvent = VerificationEvent::query()
            ->where('vault_id', $vault->id)
            ->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)
            ->whereHas('bindingProof', fn ($query) => $query
                ->where('vault_id', $vault->id)
                ->where('proof_reference', $proofReference))
            ->first();

        $checks = [
            ['binding_proofs count = 1', $proofCount === 1],
            ['proof_verified count = 1', $verifiedEvent instanceof VerificationEvent],
            ['proof_payload populated', is_array($proof->proof_payload) && $proof->proof_payload !== []],
            ['duplicate request rejected', true],
        ];

        $this->renderGateTable($checks);

        if (! $this->allChecksPassed($checks)) {
            $this->error('Persistence gate failed.');

            return self::FAILURE;
        }

        $chainId = $rpcClient->getChainId($rpcUrl);
        $receipt = $rpcClient->getTransactionReceipt($rpcUrl, $txHash);
        $verificationResult = $verifier->verify([
            'binding_key' => 'polygon',
            'transaction_hash' => $txHash,
            'token_contract' => $expectedTokenContract,
            'chain_id' => $expectedChainId,
            'expected_recipient' => $recipient,
            'minimum_amount' => $minimumAmountRaw,
            'expected_sender' => $sender !== '' ? $sender : null,
        ]);
        $proofData = (array) ($verificationResult['proof'] ?? []);

        $this->writeValidationBundle(
            artifactDir: $artifactDir,
            gate: 'persistence',
            txHash: $txHash,
            recipient: $recipient,
            sender: $sender,
            minimumAmount: $minimumAmount,
            minimumAmountRaw: $minimumAmountRaw,
            expectedChainId: $expectedChainId,
            expectedTokenContract: $expectedTokenContract,
            proofReference: $proofReference,
            chainId: $chainId,
            receipt: $receipt,
            verificationResult: $verificationResult,
            proofData: $proofData,
            claimUpdate: [
                'persisted' => true,
                'relation' => 'owns(Address)',
                'vault_id' => $vault->id,
                'entity_l1_address' => $entityAddress,
                'proof' => $proofs->formatProof($proof->refresh()),
            ],
            events: [
                'recorded' => true,
                'verification_event' => $verifiedEvent ? app(\App\Services\VerificationEventRecorder::class)->formatEvent($verifiedEvent) : null,
            ],
            checks: $checks,
            status: [
                'phase_3_validation' => 'PASSED',
                'verification_layer' => 'VERIFIED AGAINST REAL NETWORK',
            ],
        );

        $this->info('Persistence gate: PASSED');
        $this->info('Phase 3 Validation: PASSED');
        $this->info('Verification Layer: VERIFIED AGAINST REAL NETWORK');
        $this->line('Artifact bundle: '.$artifactDir);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed>|null $receipt
     * @param array<string, mixed> $verificationResult
     * @param array<string, mixed> $proofData
     * @param array<string, mixed> $claimUpdate
     * @param array<string, mixed> $events
     * @param array<int, array{0: string, 1: bool}> $checks
     * @param array<string, string>|null $status
     */
    private function writeValidationBundle(
        string $artifactDir,
        string $gate,
        string $txHash,
        string $recipient,
        string $sender,
        string $minimumAmount,
        string $minimumAmountRaw,
        int $expectedChainId,
        string $expectedTokenContract,
        string $proofReference,
        ?int $chainId,
        ?array $receipt,
        array $verificationResult,
        array $proofData,
        array $claimUpdate,
        array $events,
        array $checks,
        ?array $status = null,
    ): void {
        File::ensureDirectoryExists($artifactDir);

        $this->writeJson($artifactDir.'/tx.json', [
            'transaction_hash' => $txHash,
            'binding_key' => 'polygon',
            'chain_id' => $expectedChainId,
            'expected_recipient' => $recipient,
            'expected_sender' => $sender !== '' ? $sender : null,
            'minimum_amount' => $minimumAmount,
            'minimum_amount_raw' => $minimumAmountRaw,
            'expected_token_contract' => $expectedTokenContract,
            'proof_reference' => $proofReference,
            'gate' => $gate,
            'captured_at' => now()->toIso8601String(),
        ]);

        $this->writeJson($artifactDir.'/receipt.json', [
            'chain_id' => $chainId,
            'receipt' => $receipt,
            'receipt_found' => $receipt !== null,
            'status' => $receipt['status'] ?? null,
            'block_number' => $receipt['blockNumber'] ?? null,
            'captured_at' => now()->toIso8601String(),
        ]);

        $this->writeJson($artifactDir.'/decoded-evidence.json', [
            'valid' => ($verificationResult['valid'] ?? false) === true,
            'error_code' => $verificationResult['error_code'] ?? null,
            'error' => $verificationResult['error'] ?? null,
            'evidence_type' => BindingProof::TYPE_USDC_TRANSFER,
            'decoded' => $proofData,
            'captured_at' => now()->toIso8601String(),
        ]);

        $this->writeJson($artifactDir.'/claim-update.json', $claimUpdate);
        $this->writeJson($artifactDir.'/events.json', $events);

        $this->writeJson($artifactDir.'/manifest.json', [
            'validation_contract' => $this->validationContract($gate, $expectedChainId),
            'passed_at' => now()->toIso8601String(),
            'command' => $this->buildCommandLine($gate === 'dry_run'),
            'provenance_chain' => [
                'external_signal' => 'tx.json',
                'rpc_observation' => 'receipt.json',
                'decoded_evidence' => 'decoded-evidence.json',
                'claim_update' => 'claim-update.json',
                'history_event' => 'events.json',
            ],
            'files' => [
                'tx.json',
                'receipt.json',
                'decoded-evidence.json',
                'claim-update.json',
                'events.json',
            ],
            'checks' => array_map(
                static fn (array $check): array => ['name' => $check[0], 'passed' => $check[1]],
                $checks,
            ),
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validationContract(string $gate, int $chainId): array
    {
        return [
            'validation_contract' => 'identity-claims-v1',
            'hypothesis' => 'Node → Edge → Evidence → History can describe identity relations across classes without core changes',
            'model' => 'Node-Edge-Evidence-History',
            'artifact' => 'live_polygon_usdc_transfer',
            'gate' => $gate,
            'relation' => 'owns',
            'relation_subject' => 'Address',
            'evidence_type' => BindingProof::TYPE_USDC_TRANSFER,
            'network' => 'polygon',
            'chain_id' => $chainId,
            'core_changes_required' => false,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    private function resolveArtifactDirectory(string $txHash): string
    {
        $customDir = trim((string) $this->option('artifact-dir'));
        if ($customDir !== '') {
            return $customDir;
        }

        $suffix = substr(str_replace('0x', '', $txHash), 0, 12);

        return base_path('scratch/verification-live-proofs/'.now()->format('Ymd-His').'-'.$suffix);
    }

    /**
     * @param array<int, array{0: string, 1: bool}> $checks
     */
    private function renderGateTable(array $checks): void
    {
        $this->table(
            ['Check', 'Status'],
            array_map(
                static fn (array $check): array => [$check[0], $check[1] ? 'PASS' : 'FAIL'],
                $checks,
            ),
        );
    }

    /**
     * @param array<int, array{0: string, 1: bool}> $checks
     */
    private function allChecksPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check[1] !== true) {
                return false;
            }
        }

        return true;
    }

    private function buildCommandLine(bool $dryRun): string
    {
        $parts = ['php artisan verification:validate-polygon-usdc-proof'];

        foreach (['tx', 'recipient', 'minimum-amount', 'sender', 'entity'] as $option) {
            $value = $this->option($option);
            if ($value !== null && trim((string) $value) !== '') {
                $parts[] = '--'.$option.'='.(string) $value;
            }
        }

        if ($dryRun) {
            $parts[] = '--dry-run';
        }

        return implode(' ', $parts);
    }

    private function maskUrl(string $url): string
    {
        return preg_replace('/(\/v2\/)([^\/\?]+)/', '$1***', $url) ?? $url;
    }

    private function toTokenBaseUnits(string $amount, int $decimals): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);

        return ltrim($whole.$fraction, '0') ?: '0';
    }
}
