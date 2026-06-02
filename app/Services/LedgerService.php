<?php

namespace App\Services;

use App\Models\SovereignLedger;
use App\Models\Shop;
use App\Services\Continuity\TransitionOutboxService;
use App\Services\Mutation\LedgerDedupGuard;
use App\Services\Mutation\MutationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Meanly\Mdk\Kernel\Core\EngineConfig;
use Meanly\Mdk\Kernel\Identity\CanonicalJsonEncoder;
use Meanly\Mdk\Kernel\Identity\ExecutionFingerprint;

class LedgerService
{
    private const CONSTITUTION_ID = 'consortium-sovereign-v1';

    public function __construct()
    {
        //
    }

    public function record(
        ?Shop $shop,
        string $eventType,
        ?Model $entity,
        array $payload,
        ?\App\Models\LegalEntity $legalEntity = null,
        ?string $triggerSource = null,
        ?array $inputData = null,
        ?array $outputState = null
    ): SovereignLedger {
        return DB::transaction(function () use ($shop, $eventType, $entity, $payload, $legalEntity, $triggerSource, $inputData, $outputState) {
            $encoder = new CanonicalJsonEncoder();

            // Auto-resolve legal entity from shop if not explicitly provided
            $legalEntityId = $legalEntity?->id ?? $shop?->legal_entity_id;
            
            // 0. Sanitize payload
            $payload = $this->sanitizePayload($payload);
            $mutationContext = MutationContext::all();
            if (filled($mutationContext['mutation_id'] ?? null)) {
                app(LedgerDedupGuard::class)->check(
                    mutationId: (string) $mutationContext['mutation_id'],
                    eventType: $eventType,
                    metadata: [
                        'entity_type' => $entity ? get_class($entity) : null,
                        'entity_id' => $entity?->getKey(),
                        'ledger_scope' => $legalEntityId ? 'legal_entity:'.$legalEntityId : ($shop ? 'shop:'.$shop->id : 'marketplace:global'),
                    ],
                );
            }

            // 1. Get previous state
            $query = SovereignLedger::query();
            if ($legalEntityId) {
                $query->where('legal_entity_id', $legalEntityId);
            } elseif ($shop) {
                $query->where('shop_id', $shop->id);
            } else {
                $query->whereNull('legal_entity_id')->whereNull('shop_id');
            }

            $lastEntry = $query->orderBy('id', 'desc')->lockForUpdate()->first();
            $previousFingerprint = $lastEntry?->fingerprint;
            $createdAt = now()->toDateTimeString();

            // 🛡️ Authority Context Capture
            if ($triggerSource === null) {
                $user = auth()->user();
                if ($user) {
                    $roles = [];
                    if (method_exists($user, 'getRoleNames')) {
                        $roles = $user->getRoleNames()->sort()->values()->toArray();
                    }
                    $roleTag = count($roles) > 0 ? '[' . implode(',', $roles) . ']' : '[USER]';
                    $triggerSource = "DID:SYS | {$roleTag}:#{$user->id}";
                } else {
                    $triggerSource = "DID:SYS | GUEST";
                }
            }

            // 2. Build the Document of Intent (Deterministic Packet)
            $enrichedPayload = array_merge($payload, [
                'kernel_fp' => $this->kernelFingerprint()->getHash(),
                'roles' => $roles ?? [],
                'mutation_context' => $mutationContext ?: null,
            ]);

            $data = [
                'prev' => $previousFingerprint,
                'type' => $eventType,
                'entity_id' => (string) $entity?->getKey(),
                'entity_type' => $entity ? get_class($entity) : null,
                'payload' => $enrichedPayload,
                'ts' => $createdAt,
                'source' => $triggerSource,
                'in' => $inputData,
                'out' => $outputState,
            ];

            $canonicalJson = $encoder->encode($data);
            $entryFingerprint = hash('sha256', $canonicalJson);

            // 3. Store with Kernel Proof
            $ledger = SovereignLedger::create([
                'shop_id' => $shop?->id,
                'legal_entity_id' => $legalEntityId,
                'event_type' => $eventType,
                'entity_type' => $entity ? get_class($entity) : null,
                'entity_id' => $entity?->getKey(),
                'payload' => $enrichedPayload,
                'trigger_source' => $triggerSource,
                'input_data' => $inputData,
                'output_state' => $outputState,
                'fingerprint' => $entryFingerprint,
                'previous_fingerprint' => $previousFingerprint,
                'created_at' => $createdAt,
            ]);

            app(TransitionOutboxService::class)->recordFromLedger($ledger);

            return $ledger;
        });
    }

    /**
     * Recursively mask PII in payload
     */
    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'email', 'phone', 'first_name', 'last_name', 'middle_name',
            'inn', 'kpp', 'ogrn', 'bank_account', 'bank_correspondent_account',
            'legal_address', 'postal_address', 'director_name',
            'info', 'client_info', 'password', 'remember_token'
        ];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            } elseif (in_array(strtolower($key), $sensitiveKeys)) {
                if (is_string($value) && !empty($value)) {
                    // Simple masking: first 2 chars ... last 2 chars
                    $len = mb_strlen($value);
                    if ($len > 6) {
                        $payload[$key] = mb_substr($value, 0, 2) . '***' . mb_substr($value, -2);
                    } else {
                        $payload[$key] = '***';
                    }
                } else {
                    $payload[$key] = '***';
                }
            }
        }

        return $payload;
    }

    public function recordGlobal(string $eventType, ?Model $entity, array $payload): SovereignLedger
    {
        return $this->record(null, $eventType, $entity, $payload);
    }

    /**
     * Verify the integrity of the ledger for a shop.
     * Returns true if all hashes match.
     */
    public function verifyIntegrity(Shop $shop, ?int $limit = null): array
    {
        $query = SovereignLedger::where('shop_id', $shop->id);
        
        if ($limit) {
            // 💡 SMART TAIL SCAN: Take latest N, then reverse to read forward in time.
            $entries = $query->orderBy('id', 'desc')->take($limit)->get()->reverse();
        } else {
            $entries = $query->orderBy('id', 'asc')->get();
        }

        $errors = [];
        // Initialize expectedPrev from the FIRST record in our subset to allow segmented chain verification!
        $expectedPrev = $entries->first()?->previous_fingerprint ?? null; 
        $encoder = new CanonicalJsonEncoder();

        foreach ($entries as $entry) {
            // Check chaining
            if ($entry->previous_fingerprint !== $expectedPrev) {
                $errors[] = "Chain broken at ID {$entry->id}: Previous hash mismatch.";
            }

            // Verify current hash using MDK Canonical Encoder
            $data = $this->canonicalLedgerData($entry);

            $calculated = hash('sha256', $encoder->encode($data));

            if ($entry->fingerprint !== $calculated) {
                $errors[] = "Data corruption at ID {$entry->id}: Fingerprint mismatch.";
            }

            $expectedPrev = $entry->fingerprint;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'count' => $entries->count()
        ];
    }

    /**
     * Verify the integrity of the ledger for an entire Legal Entity (Partner).
     * Returns true if all hashes across ALL its shops match in a contiguous chain.
     */
    public function verifyLegalEntityIntegrity(\App\Models\LegalEntity $legalEntity, ?int $limit = null): array
    {
        $query = SovereignLedger::where('legal_entity_id', $legalEntity->id);

        if ($limit) {
             $entries = $query->orderBy('id', 'desc')->take($limit)->get()->reverse();
        } else {
             $entries = $query->orderBy('id', 'asc')->get();
        }

        $errors = [];
        $expectedPrev = $entries->first()?->previous_fingerprint ?? null; 
        $encoder = new CanonicalJsonEncoder();

        foreach ($entries as $entry) {
            if ($entry->previous_fingerprint !== $expectedPrev) {
                $errors[] = "Chain broken at ID {$entry->id}: Previous hash mismatch.";
            }

            $data = $this->canonicalLedgerData($entry);

            $calculated = hash('sha256', $encoder->encode($data));

            if ($entry->fingerprint !== $calculated) {
                $errors[] = "Data corruption at ID {$entry->id}: Fingerprint mismatch.";
            }

            $expectedPrev = $entry->fingerprint;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'count' => $entries->count()
        ];
    }

    private function kernelFingerprint(): ExecutionFingerprint
    {
        return new ExecutionFingerprint(
            constitutionId: self::CONSTITUTION_ID,
            config: new EngineConfig(
                constitutionId: self::CONSTITUTION_ID,
                mathMode: 'atto',
                strictIdentity: true,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalLedgerData(SovereignLedger $entry): array
    {
        return [
            'prev' => $entry->previous_fingerprint,
            'type' => $entry->event_type,
            'entity_id' => (string) $entry->entity_id,
            'entity_type' => $entry->entity_type,
            'payload' => $entry->payload,
            'ts' => $entry->created_at->toDateTimeString(),
            'source' => $entry->trigger_source,
            'in' => $this->emptyArrayAsNull($entry->input_data),
            'out' => $this->emptyArrayAsNull($entry->output_state),
        ];
    }

    private function emptyArrayAsNull(mixed $value): mixed
    {
        return $value === [] ? null : $value;
    }
}
