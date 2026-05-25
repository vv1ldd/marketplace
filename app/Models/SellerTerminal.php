<?php

namespace App\Models;

use App\Casts\VaultEncrypted;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SellerTerminal extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'terminal_id',
        'terminal_pin',
        'is_active',
        'daily_limit',
        'expires_at',
        'last_used_at',
        'last_ip',
    ];

    protected $casts = [
        'terminal_pin' => VaultEncrypted::class,  // XChaCha20 via VaultTransitService
        'is_active'    => 'boolean',
        'daily_limit'  => 'float',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'terminal_pin',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    // ─── Generators ───────────────────────────────────────────────────────────

    /**
     * Generate a readable, unique terminal ID.
     * Format: SL-YYYYMM-XXXXXX  (e.g. SL-202506-A3F9K1)
     */
    public static function generateTerminalId(): string
    {
        do {
            $id = 'SL-' . now()->format('Ym') . '-' . strtoupper(Str::random(6));
        } while (static::where('terminal_id', $id)->exists());

        return $id;
    }

    /**
     * Generate a secure numeric PIN (6 digits, padded).
     */
    public static function generatePin(): string
    {
        return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Check whether this terminal is currently valid (active + not expired).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Verify the supplied raw PIN against the stored encrypted value.
     */
    public function verifyPin(string $rawPin): bool
    {
        return $this->terminal_pin === $rawPin;
    }

    /**
     * Touch the audit fields after a successful authentication.
     */
    public function recordUsage(string $ip = ''): void
    {
        $this->updateQuietly([
            'last_used_at' => now(),
            'last_ip'      => $ip ?: null,
        ]);
    }

    /**
     * Check if the terminal has exceeded its daily spending limit.
     * Returns true when spending is allowed (within limit or no limit set).
     */
    public function hasRemainingDailyBudget(float $requiredAmount): bool
    {
        if ($this->daily_limit <= 0) {
            return true; // No limit configured
        }

        $spentToday = Order::query()
            ->whereHas('shop', fn ($q) => $q->where('legal_entity_id', $this->legal_entity_id))
            ->whereDate('created_at', today())
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['CANCELLED', 'FAILED']);
            })
            ->sum('total_amount') ?? 0;

        return ($spentToday + $requiredAmount) <= $this->daily_limit;
    }
}
