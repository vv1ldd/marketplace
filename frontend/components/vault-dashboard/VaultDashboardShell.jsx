'use client';

import { VaultExecutionLedger } from './VaultExecutionLedger';
import { VaultIdentitySidebar } from './VaultIdentitySidebar';
import { VaultInventoryGrid } from './VaultInventoryGrid';

export function VaultDashboardShell({ vaultData, loading = false }) {
  return (
    <div className="vault-dashboard-grid">
      <aside className="vault-dashboard-grid__sidebar">
        <VaultIdentitySidebar
          balances={vaultData?.balances}
          identity={vaultData?.identity}
          loading={loading}
        />
      </aside>

      <main className="vault-dashboard-grid__main">
        <VaultInventoryGrid items={vaultData?.inventory} loading={loading} />
        <VaultExecutionLedger executions={vaultData?.executions} loading={loading} />
      </main>
    </div>
  );
}
