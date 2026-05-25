<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Services\WildflowService;
use Illuminate\Console\Command;

class SyncPartnersToKernelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'partners:sync-kernel
                            {--only-with-shops : Only sync LegalEntities that have at least one shop (default)}
                            {--all : Sync all LegalEntities, including those without shops}
                            {--id= : Sync only a specific LegalEntity by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync seller balances to the Wildflow Kernel.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting sync of seller balances to the Wildflow Kernel...');
        $this->newLine();

        // Build query
        $query = LegalEntity::query();

        if ($specificId = $this->option('id')) {
            $query->where('id', $specificId);
        } elseif ($this->option('all')) {
            // Sync everything
            $this->warn('⚠️  --all flag set: syncing ALL LegalEntities, including those without shops.');
        } else {
            // Default: only with shops
            $query->has('shops');
        }

        $entities = $query->get();

        if ($entities->isEmpty()) {
            $this->warn('⚠️  No LegalEntities found matching the criteria. Nothing to sync.');
            return 0;
        }

        $this->info("📋 Found {$entities->count()} partner(s) to sync.");
        $this->newLine();

        $wfService = new WildflowService();

        $synced  = 0;
        $failed  = 0;
        $skipped = 0;

        $rows = [];

        foreach ($entities as $entity) {
            $label = "[ID:{$entity->id}] {$entity->name}";

            try {
                $credentials = $entity->vendor_credentials ?? [];
                $result = $wfService->syncPartner($entity, $credentials);

                $synced++;
                $rows[] = [
                    $entity->id,
                    mb_substr($entity->name ?? '—', 0, 35),
                    $entity->inn ?? '—',
                    number_format((float) ($entity->available_balance ?? 0), 2) . ' ' . ($entity->currency ?? 'RUB'),
                    '<fg=green>✅ OK</>',
                    $result['partner_id'] ?? '—',
                ];

                $this->line("  ✅ Synced: {$label}");
            } catch (\Exception $e) {
                $failed++;
                $rows[] = [
                    $entity->id,
                    mb_substr($entity->name ?? '—', 0, 35),
                    $entity->inn ?? '—',
                    number_format((float) ($entity->available_balance ?? 0), 2) . ' ' . ($entity->currency ?? 'RUB'),
                    '<fg=red>❌ FAIL</>',
                    mb_substr($e->getMessage(), 0, 40),
                ];

                $this->error("  ❌ Failed: {$label} — " . $e->getMessage());
            }
        }

        // 🧼 PRUNING Logic: Clean up any sub-partners in the Kernel that no longer exist in the local database matching the criteria
        $this->newLine();
        $this->info('🧼 Starting pruning of obsolete sub-partners in Wildflow Kernel...');
        try {
            $kernelPartners = $wfService->listPartners();
            $syncedExternalIds = $entities->pluck('id')->map(fn($id) => (string)$id)->toArray();
            $prunedCount = 0;

            foreach ($kernelPartners as $kp) {
                if (!in_array((string)$kp['external_id'], $syncedExternalIds)) {
                    $this->warn("  🧹 Removing obsolete sub-partner from Kernel: ID {$kp['id']} (External ID: {$kp['external_id']}, Name: {$kp['name']})");
                    $wfService->deletePartner((string)$kp['external_id']);
                    $prunedCount++;
                }
            }
            if ($prunedCount > 0) {
                $this->info("  ✨ Cleaned up {$prunedCount} obsolete partner(s) from Kernel.");
            } else {
                $this->info("  ✨ Kernel is already perfectly clean!");
            }
        } catch (\Exception $e) {
            $this->error("  ⚠️ Failed to prune obsolete partners: " . $e->getMessage());
        }

        $this->newLine();
        $this->table(
            ['ID', 'Name', 'INN', 'Balance', 'Status', 'Kernel Terminal / Error'],
            $rows
        );

        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🏆 Sync complete!");
        $this->info("   ✅ Synced : {$synced}");

        if ($failed > 0) {
            $this->error("   ❌ Failed : {$failed}");
        } else {
            $this->info("   ❌ Failed : {$failed}");
        }

        if ($skipped > 0) {
            $this->warn("   ⏭️  Skipped: {$skipped}");
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return $failed > 0 ? 1 : 0;
    }
}
