<?php

namespace App\Services;

use App\Models\SovereignLedger;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Meanly\Mdk\Kernel\Identity\CanonicalJsonEncoder;

class LedgerService
{
    /**
     * Record a deterministic event in the ledger, scoped by Shop or LegalEntity (Partner).
     */
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
            
            // 0. Sanitize payload to remove PII before hashing/storing
            $payload = $this->sanitizePayload($payload);

            // 0.5 Intelligent Deterministic Traceability (Source, Input, Output)
            if ($triggerSource === null) {
                if (app()->runningInConsole()) {
                    $args = $_SERVER['argv'] ?? [];
                    $cmdString = implode(' ', array_slice($args, 0, 5));
                    $did = substr(hash('sha256', "system:cli:" . implode('|', $args)), 0, 12);
                    $triggerSource = "DID:SYS:{$did} | CLI: {$cmdString}";
                } else {
                    $user = auth()->user();
                    if ($user) {
                        $guard = config('auth.defaults.guard', 'web');
                        // Get active roles at transaction time for authority context
                        $roles = [];
                        try {
                            if (method_exists($user, 'getRoleNames')) {
                                $roles = $user->getRoleNames()->sort()->values()->toArray();
                            }
                        } catch (\Throwable $e) {
                            // Graceful fallback if roles relation isn't ready
                        }
                        
                        $roleTag = count($roles) > 0 ? '[' . implode(',', $roles) . ']' : '[USER]';
                        
                        // Deterministic DID Seed: immutable ID + registration state + active roles context
                        $createdAt = $user->created_at ? (is_string($user->created_at) ? $user->created_at : $user->created_at->toIso8601String()) : 'legacy';
                        $didSeed = "user:{$user->id}:{$createdAt}:" . implode(',', $roles);
                        $did = substr(hash('sha256', $didSeed), 0, 12);
                        
                        $triggerSource = "DID:{$did} | {$roleTag}:#{$user->id} (" . ($user->email ?? $user->name ?? 'unnamed') . ")";
                    } else {
                        $method = request()->method();
                        $path = request()->path();
                        $ip = request()->ip() ?? '127.0.0.1';
                        $did = substr(hash('sha256', "system:http:{$method}:{$path}:{$ip}"), 0, 12);
                        $triggerSource = "DID:HTTP:{$did} | GUEST: {$method}:{$path}";
                    }
                }
            }

            if ($inputData === null) {
                if (!app()->runningInConsole()) {
                    $inputData = $this->sanitizePayload(request()->except(['password', '_token', 'credential', 'remember_token']));
                } else {
                    $inputData = ['args' => $_SERVER['argv'] ?? []];
                }
            }

            if ($outputState === null && $entity) {
                $outputState = $this->sanitizePayload($entity->toArray());
            }

            // 1. Get the last fingerprint for the PARENT scope (Legal Entity/Partner)
            $query = SovereignLedger::query();
            if ($legalEntityId) {
                $query->where('legal_entity_id', $legalEntityId);
            } elseif ($shop) {
                // Fallback case for legacy orphaned shops without legal entities
                $query->where('shop_id', $shop->id);
            } else {
                $query->whereNull('legal_entity_id')->whereNull('shop_id');
            }

            $lastEntry = $query->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $previousFingerprint = $lastEntry?->fingerprint;
            $createdAt = now()->toDateTimeString();

            // 2. Build the data for hashing using MDK Canonical Encoder
            $data = [
                'prev' => $previousFingerprint,
                'type' => $eventType,
                'entity_id' => (string) $entity?->getKey(),
                'entity_type' => $entity ? get_class($entity) : null,
                'payload' => $payload,
                'ts' => $createdAt,
                // Formally binding the deterministic I/O vector!
                'source' => $triggerSource,
                'in' => $inputData,
                'out' => $outputState,
            ];

            $canonicalJson = $encoder->encode($data);

            // 3. Generate Fingerprint (The Hash)
            $fingerprint = hash('sha256', $canonicalJson);

            // 4. Store in Ledger
            return SovereignLedger::create([
                'shop_id' => $shop?->id,
                'legal_entity_id' => $legalEntityId, // Store unified parent linkage!
                'event_type' => $eventType,
                'entity_type' => $entity ? get_class($entity) : null,
                'entity_id' => $entity?->getKey(),
                'payload' => $payload,
                'trigger_source' => $triggerSource,
                'input_data' => $inputData,
                'output_state' => $outputState,
                'fingerprint' => $fingerprint,
                'previous_fingerprint' => $previousFingerprint,
                'created_at' => $createdAt,
            ]);
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
            $data = [
                'prev' => $entry->previous_fingerprint,
                'type' => $entry->event_type,
                'entity_id' => (string) $entry->entity_id,
                'entity_type' => $entry->entity_type,
                'payload' => $entry->payload,
                'ts' => $entry->created_at->toDateTimeString(),
                'source' => $entry->trigger_source,
                'in' => $entry->input_data,
                'out' => $entry->output_state,
            ];

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

            $data = [
                'prev' => $entry->previous_fingerprint,
                'type' => $entry->event_type,
                'entity_id' => (string) $entry->entity_id,
                'entity_type' => $entry->entity_type,
                'payload' => $entry->payload,
                'ts' => $entry->created_at->toDateTimeString(),
                'source' => $entry->trigger_source,
                'in' => $entry->input_data,
                'out' => $entry->output_state,
            ];

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
}
