<?php

namespace App\Console\Commands;

use App\Services\Continuity\WriterAuthorityService;
use Illuminate\Console\Command;

class WriterAuthorityHeartbeatCommand extends Command
{
    protected $signature = 'marketplace:writer-authority:heartbeat
        {--scope= : Writer authority scope}
        {--region= : Region that currently holds writer authority}
        {--epoch= : Monotonic writer epoch}
        {--json : Output machine-readable JSON}';

    protected $description = 'Publish writer authority heartbeat for the current marketplace region and epoch.';

    public function handle(WriterAuthorityService $writers): int
    {
        $scope = (string) ($this->option('scope') ?: config('mutation.writer_scope', 'marketplace:global'));
        $region = (string) ($this->option('region') ?: config('mutation.region', 'local'));
        $epoch = (string) ($this->option('epoch') ?: config('mutation.writer_epoch', '1'));

        $row = $writers->heartbeat(
            scope: $scope,
            authorityHolder: $region,
            authorityEpoch: $epoch,
            metadata: [
                'source' => 'marketplace:writer-authority:heartbeat',
                'region' => $region,
                'epoch' => $epoch,
            ],
        );

        $payload = [
            'status' => $row ? 'OK' : 'SKIPPED',
            'scope' => $scope,
            'writer_region' => $region,
            'writer_epoch' => $epoch,
            'last_heartbeat_at' => $row?->last_heartbeat_at?->toJSON(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Writer authority heartbeat: {$region}@{$epoch} ({$scope})");
        }

        return $row ? self::SUCCESS : self::FAILURE;
    }
}
