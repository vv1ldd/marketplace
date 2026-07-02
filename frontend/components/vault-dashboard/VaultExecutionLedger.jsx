'use client';

import { useLocale } from '../LocaleProvider';

const STATE_LABELS = {
  issued: 'vault_dashboard_execution_state_issued',
  fulfilling: 'vault_dashboard_execution_state_fulfilling',
  failed: 'vault_dashboard_execution_state_failed',
  reserved: 'vault_dashboard_execution_state_reserved',
  manual: 'vault_dashboard_execution_state_manual',
};

function executionTone(state) {
  if (state === 'issued') return 'success';
  if (state === 'failed') return 'danger';
  if (state === 'fulfilling' || state === 'reserved') return 'warning';
  return 'neutral';
}

function formatWhen(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString();
}

export function VaultExecutionLedger({ executions = [], loading = false }) {
  const { t } = useLocale();

  return (
    <section className="vault-dashboard-section">
      <header className="vault-dashboard-section__header">
        <h2>{t('vault_dashboard_ledger_title')}</h2>
        <p>{t('vault_dashboard_ledger_hint')}</p>
      </header>

      <div className="vault-card vault-execution-ledger">
        {loading ? (
          <div className="vault-execution-ledger__row vault-execution-ledger__row--skeleton" aria-hidden="true" />
        ) : executions.length === 0 ? (
          <div className="vault-dashboard-empty vault-dashboard-empty--inline">
            <strong>{t('vault_dashboard_ledger_empty_title')}</strong>
            <p>{t('vault_dashboard_ledger_empty_body')}</p>
          </div>
        ) : (
          executions.map((entry) => {
            const tone = executionTone(entry.state);
            const labelKey = STATE_LABELS[entry.state] || 'vault_dashboard_execution_state_unknown';

            return (
              <div className="vault-execution-ledger__row" key={entry.id}>
                <div className="vault-execution-ledger__when">{formatWhen(entry.created_at)}</div>
                <div className="vault-execution-ledger__body">
                  <strong>{entry.title}</strong>
                  <span className={`vault-execution-ledger__state vault-execution-ledger__state--${tone}`}>
                    {t(labelKey)}
                  </span>
                </div>
                <code className="vault-execution-ledger__intent">{entry.intent_id || entry.id}</code>
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}
