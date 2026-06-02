<?php

namespace App\Console\Commands;

use App\Models\WriterAuthorityReadiness;
use App\Services\Continuity\WriterAuthorityService;
use Illuminate\Console\Command;

class WriterAuthorityPromoteCommand extends Command
{
    protected $signature = 'marketplace:writer-authority:promote
        {region : Region to promote as writer}
        {epoch : New monotonic writer epoch}
        {--scope= : Writer authority scope}
        {--json : Output machine-readable JSON}';

    protected $description = 'Promote a region to writer authority for a monotonic epoch.';

    public function handle(WriterAuthorityService $writers): int
    {
        $scope = (string) ($this->option('scope') ?: config('mutation.writer_scope', 'marketplace:global'));
        $region = (string) $this->argument('region');
        $epoch = (string) $this->argument('epoch');

        $row = $writers->heartbeat(
            scope: $scope,
            authorityHolder: $region,
            authorityEpoch: $epoch,
            fencingStatus: WriterAuthorityReadiness::FENCING_FENCED_PREVIOUS,
            metadata: [
                'source' => 'marketplace:writer-authority:promote',
                'promoted_region' => $region,
                'promoted_epoch' => $epoch,
                'promoted_at' => now()->toJSON(),
            ],
        );

        $payload = [
            'status' => $row ? 'OK' : 'FAILED',
            'scope' => $scope,
            'writer_region' => $region,
            'writer_epoch' => $epoch,
            'fencing_status' => $row?->fencing_status,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Promoted writer authority: {$region}@{$epoch} ({$scope})");
        }

        return $row ? self::SUCCESS : self::FAILURE;
    }
}
