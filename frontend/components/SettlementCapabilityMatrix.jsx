'use client';

function CapabilityCell({ enabled, t }) {
  return (
    <span className={`identity-capability-cell${enabled ? ' is-enabled' : ''}`}>
      {enabled ? t('identity_capability_yes') : t('identity_capability_no')}
    </span>
  );
}

function instrumentKindLabel(row, t) {
  if (row.binding_source === 'managed') {
    return t('identity_instrument_managed');
  }

  return t('identity_instrument_connected');
}

export function SettlementCapabilityMatrix({ rows = [], t }) {
  if (!rows.length) {
    return null;
  }

  return (
    <section className="identity-capability-matrix">
      <header className="identity-capability-matrix__header">
        <span>{t('identity_capability_matrix_title')}</span>
        <small>{t('identity_capability_matrix_hint')}</small>
      </header>
      <div className="identity-capability-matrix__table" role="table">
        <div className="identity-capability-matrix__row identity-capability-matrix__row--head" role="row">
          <span role="columnheader">{t('identity_capability_col_instrument')}</span>
          <span role="columnheader">{t('identity_capability_col_receive')}</span>
          <span role="columnheader">{t('identity_capability_col_send')}</span>
          <span role="columnheader">{t('identity_capability_col_routing')}</span>
        </div>
        {rows.map((row) => (
          <div className="identity-capability-matrix__row" key={row.instrument} role="row">
            <span className="identity-capability-matrix__instrument" role="cell">
              <strong>{row.instrument_label || row.instrument}</strong>
              <small>{instrumentKindLabel(row, t)}</small>
            </span>
            <CapabilityCell enabled={row.receive?.enabled} t={t} />
            <CapabilityCell enabled={row.send?.enabled} t={t} />
            <CapabilityCell enabled={row.payment_routing?.enabled} t={t} />
          </div>
        ))}
      </div>
      <p className="identity-capability-matrix__footnote">{t('identity_capability_matrix_footnote')}</p>
    </section>
  );
}
