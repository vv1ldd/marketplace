<?php

namespace App\Services;

use App\Models\Provider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProviderCatalogSyncState
{
    public function reconcile(Provider $provider): Provider
    {
        if (($provider->sync_status ?: 'idle') !== 'syncing') {
            return $provider;
        }

        if ($this->isActive($provider->id)) {
            return $provider;
        }

        $provider->forceFill(['sync_status' => 'idle'])->save();

        return $provider->refresh();
    }

    public function isActive(int $providerId): bool
    {
        return $this->hasQueuedJob($providerId) || $this->overlapLockHeld($providerId);
    }

    public function hasQueuedJob(int $providerId): bool
    {
        $needle = 's:10:"providerId";i:'.$providerId.';';

        return DB::table('jobs')
            ->where('payload', 'like', '%SyncProviderCatalogJob%')
            ->where('payload', 'like', '%'.$needle.'%')
            ->exists();
    }

    public function overlapLockHeld(int $providerId): bool
    {
        $lock = Cache::lock($this->overlapLockKey($providerId), 1);

        if ($lock->get()) {
            $lock->release();

            return false;
        }

        return true;
    }

    private function overlapLockKey(int $providerId): string
    {
        return 'laravel-queue-overlap:sync-provider-'.$providerId;
    }
}
