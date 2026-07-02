'use client';

import { useLocale } from '../LocaleProvider';
import { VaultEntitlementCard } from './VaultEntitlementCard';

function SkeletonCard() {
  return <div className="vault-card vault-inventory-card vault-inventory-card--skeleton" aria-hidden="true" />;
}

export function VaultInventoryGrid({ items = [], loading = false, onEntitlementRevealed }) {
  const { t } = useLocale();

  return (
    <section className="vault-dashboard-section">
      <header className="vault-dashboard-section__header">
        <h2>{t('vault_dashboard_inventory_title')}</h2>
        <p>{t('vault_dashboard_inventory_hint')}</p>
      </header>

      {loading ? (
        <div className="vault-inventory-grid">
          <SkeletonCard />
          <SkeletonCard />
        </div>
      ) : items.length === 0 ? (
        <div className="vault-card vault-dashboard-empty">
          <strong>{t('vault_dashboard_inventory_empty_title')}</strong>
          <p>{t('vault_dashboard_inventory_empty_body')}</p>
        </div>
      ) : (
        <div className="vault-inventory-grid">
          {items.map((item) => (
            <VaultEntitlementCard item={item} key={item.id} onRevealed={onEntitlementRevealed} />
          ))}
        </div>
      )}
    </section>
  );
}
