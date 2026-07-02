'use client';

import { useLocale } from '../LocaleProvider';

function SkeletonLine({ className = '' }) {
  return <span className={`vault-dashboard-skeleton ${className}`.trim()} aria-hidden="true" />;
}

export function VaultIdentitySidebar({ identity, balances, loading = false }) {
  const { t } = useLocale();

  if (loading) {
    return (
      <section className="vault-card vault-identity-sidebar">
        <SkeletonLine className="vault-dashboard-skeleton--title" />
        <SkeletonLine className="vault-dashboard-skeleton--line" />
        <SkeletonLine className="vault-dashboard-skeleton--line" />
      </section>
    );
  }

  return (
    <section className="vault-card vault-identity-sidebar">
      <header className="vault-identity-sidebar__header">
        <span className="vault-identity-sidebar__kicker">{t('vault_dashboard_identity_kicker')}</span>
        <strong>{identity?.username || t('vault_dashboard_identity_fallback')}</strong>
        <span className="status-badge-issued">{t('vault_dashboard_identity_status')}</span>
      </header>

      <div className="vault-identity-sidebar__block">
        <span>{t('vault_dashboard_vault_key_ref')}</span>
        <code>{identity?.vault_key_ref || '—'}</code>
      </div>

      <div className="vault-identity-sidebar__block">
        <span>{t('vault_dashboard_partner_balance')}</span>
        <strong>
          {Number(balances?.partner_credit ?? 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}
          {' '}
          {balances?.currency || 'USD'}
        </strong>
      </div>
    </section>
  );
}
