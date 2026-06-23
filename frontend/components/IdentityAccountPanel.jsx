'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { buildEvmUsdcReceiveQrDataUrl } from '../lib/identity-wallet-qr';
import {
  createSettlementPaymentIntent,
  fetchIdentityStatement,
  fetchPaymentIntentDispute,
  fetchPaymentIntentTimeline,
  listSettlementPaymentIntents,
  listValueEntries,
  openPaymentDispute,
  resolveSettlementRecipient,
  submitUsdcTransferProof,
} from '../lib/storefront-api';
import {
  buildInstrumentCapabilityRows,
  hasOutgoingPaymentRouting,
  outgoingPaymentAssets,
} from '../lib/settlement-capabilities';
import { readStoredVaultToken } from '../lib/vault-authority';
import { IdentityProfileScreen } from './IdentityProfileScreen';
import { useLocale } from './LocaleProvider';
import {
  WalletActivityGroup,
  WalletActivityIcon,
  WalletActivityRow,
  WalletIdentityIcon,
  WalletNetworkIcon,
  WalletReceiveIcon,
  WalletSendIcon,
  WalletSettingsMenu,
  WalletSettingsScreen,
  WalletShellActions,
  WalletShellBalance,
  WalletShellHeader,
  WalletShellTabs,
  WalletStatementIcon,
  WalletTokenRow,
} from './WalletShell';
import { shortenIdentityAnchor, resolveIdentityAnchorAddress, buildValueEntryReceiveOptions } from '../lib/identity-wallets';

function identityLabel(identity = {}) {
  if (identity.username) return `@${identity.username}`;
  return identity.display_alias || identity.alias || identity.entity_l1_address || 'Vault identity';
}

function normalizeAlias(value) {
  const trimmed = String(value || '').trim();
  if (!trimmed) return '';
  return trimmed.startsWith('@') ? trimmed : `@${trimmed}`;
}

function paymentStatusLabel(status, t) {
  switch (status) {
    case 'executed':
      return t('identity_activity_status_confirmed');
    case 'executing':
      return t('identity_activity_status_processing');
    case 'routed':
      return t('identity_activity_status_planned');
    case 'failed':
      return t('identity_activity_status_failed');
    default:
      return status || t('identity_activity_status_unknown');
  }
}

function formatActivityWhen(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  const now = new Date();
  const sameDay = date.toDateString() === now.toDateString();
  if (sameDay) return 'Today';

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function primaryIdentityBalance(coins = []) {
  const priority = ['SL1', 'MCR', 'MLP'];
  for (const symbol of priority) {
    const coin = coins.find((entry) => String(entry.symbol || '').toUpperCase() === symbol);
    if (coin) {
      const raw = String(coin.display_amount || coin.amount || '0');
      const amount = raw.replace(new RegExp(`\\s*${symbol}$`, 'i'), '').trim() || '0';
      return { amount, symbol };
    }
  }

  return { amount: '0', symbol: 'SL1' };
}

const IDENTITY_LAYER_SYMBOLS = new Set(['SL1', 'MCR', 'MLP']);

function coinHasBalance(coin = {}) {
  const amount = Number(coin.amount ?? 0);
  return Number.isFinite(amount) && amount > 0;
}

function formatActivityDateHeader(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function activityStatusTone(status, item) {
  if (item?.activity_kind === 'value_entry') {
    return item?.value_entry?.credit_approved ? 'success' : 'error';
  }

  switch (status) {
    case 'executed':
      return 'success';
    case 'failed':
      return 'error';
    case 'executing':
      return 'pending';
    default:
      return 'default';
  }
}

function activityTitle(item, t) {
  if (item?.activity_kind === 'value_entry') {
    const entry = item.value_entry || {};
    return t('identity_activity_row_deposit', {
      asset: entry.asset || 'USDC',
      network: entry.network_label || entry.network || 'Polygon',
    });
  }

  const intent = item?.payment_intent || item?.intent || {};
  const direction = item?.activity_direction || 'outgoing';
  const counterparty = direction === 'incoming'
    ? (intent.from_alias || intent.from_identity)
    : (intent.to_alias || intent.to_identity);

  if (direction === 'incoming') {
    return t('identity_activity_row_received', {
      asset: intent.asset,
      from: counterparty,
    });
  }

  return t('identity_activity_row_sent', {
    asset: intent.asset,
    to: counterparty,
  });
}

function groupActivityByDate(items = []) {
  const groups = new Map();
  const fallbackLabel = new Date().toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });

  for (const item of items) {
    const intent = item?.payment_intent || item?.intent || {};
    const when = item?.activity_at || intent.routed_at || intent.executed_at || intent.created_at;
    const label = formatActivityDateHeader(when) || fallbackLabel;
    if (!groups.has(label)) {
      groups.set(label, []);
    }
    groups.get(label).push(item);
  }

  return [...groups.entries()];
}

function ActivityRow({ item, onSelect, t }) {
  if (item?.activity_kind === 'value_entry') {
    const entry = item.value_entry || {};
    const statusLabel = entry.credit_approved
      ? t('identity_activity_status_confirmed')
      : t('identity_activity_status_failed');

    return (
      <WalletActivityRow
        amount={`+${entry.amount} ${entry.asset}`}
        fiatAmount={entry.asset === 'USDC' ? `+$${entry.amount}` : null}
        icon={<WalletActivityIcon direction="incoming" />}
        onSelect={() => onSelect(item)}
        status={statusLabel}
        statusTone={activityStatusTone(null, item)}
        title={activityTitle(item, t)}
      />
    );
  }

  const intent = item?.payment_intent || item?.intent || {};
  const direction = item?.activity_direction || 'outgoing';
  const signedPrefix = direction === 'incoming' ? '+' : '-';

  return (
    <WalletActivityRow
      amount={`${signedPrefix}${intent.amount} ${intent.asset}`}
      fiatAmount={intent.asset === 'USDC' ? `${signedPrefix}$${intent.amount}` : null}
      icon={<WalletActivityIcon direction={direction} />}
      onSelect={() => onSelect(item)}
      status={paymentStatusLabel(intent.status, t)}
      statusTone={activityStatusTone(intent.status, item)}
      title={activityTitle(item, t)}
    />
  );
}

function timelineEventTitle(type, t) {
  const key = `identity_timeline_${type}`;
  const translated = t(key);
  return translated !== key ? translated : type.replaceAll('_', ' ');
}

function timelineEventDetail(event, intent, t) {
  const evidence = event?.evidence || {};
  switch (event?.type) {
    case 'intent_created':
      return t('identity_timeline_detail_intent_created', {
        amount: evidence.amount || intent.amount,
        asset: evidence.asset || intent.asset,
        to: evidence.to_alias || intent.to_alias || intent.to_identity,
      });
    case 'routing_decided': {
      const parts = [evidence.network, evidence.policy_version || evidence.capability_policy_version]
        .filter(Boolean);
      return parts.length ? parts.join(' · ') : t('identity_timeline_detail_routing');
    }
    case 'limit_decided':
      return evidence.approved === false
        ? t('identity_timeline_detail_limit_denied')
        : t('identity_timeline_detail_limit_approved');
    case 'fee_quoted':
      return evidence.fee_amount
        ? `${evidence.fee_amount} ${evidence.asset || intent.asset}`
        : t('identity_timeline_detail_fee');
    case 'settlement_attempt_submitted':
      return t('identity_timeline_detail_attempt_submitted', { attempt: evidence.attempt_no || '1' });
    case 'settlement_attempt_failed':
      return evidence.failure_reason || t('identity_timeline_detail_attempt_failed');
    case 'settlement_confirmed':
      return evidence.tx_reference
        ? t('identity_timeline_detail_settlement_tx')
        : t('identity_timeline_detail_settlement_confirmed');
    case 'accounting_recorded':
      return (evidence.entries || []).length
        ? evidence.entries.join(', ')
        : t('identity_timeline_detail_accounting');
    case 'reconciliation_matched':
      return t('identity_timeline_detail_reconciliation_matched');
    case 'reconciliation_recorded':
      return evidence.status || t('identity_timeline_detail_reconciliation');
    case 'dispute_opened':
      return evidence.reason || t('identity_timeline_detail_dispute_opened');
    case 'dispute_resolved':
      return evidence.decision || evidence.reason || t('identity_timeline_detail_dispute_resolved');
    case 'compensation_intent_created':
      return t('identity_timeline_detail_compensation', {
        amount: evidence.amount,
        asset: evidence.asset,
      });
    default:
      return '';
  }
}

function currentMonthPeriod() {
  const now = new Date();
  const from = new Date(now.getFullYear(), now.getMonth(), 1);
  const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
    label: from.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }),
  };
}

function statementLineTitle(type, t) {
  const key = `identity_statement_line_${type}`;
  const translated = t(key);
  return translated !== key ? translated : type.replaceAll('_', ' ');
}

function formatSignedDisplay(value) {
  const raw = String(value || '0');
  if (raw.startsWith('+') || raw.startsWith('-')) {
    return raw;
  }

  const numeric = Number(raw);
  if (Number.isNaN(numeric) || numeric === 0) {
    return '0';
  }

  return numeric > 0 ? `+${raw}` : raw;
}

function PaymentTimeline({ events = [], intent, t }) {
  if (!events.length) {
    return null;
  }

  return (
    <section className="identity-payment-timeline">
      <h3>{t('identity_payment_timeline_title')}</h3>
      <ol className="identity-payment-timeline__list">
        {events.map((event, index) => {
          const detail = timelineEventDetail(event, intent, t);
          return (
            <li className="identity-payment-timeline__item is-ok" key={`${event.type}-${event.occurred_at}-${index}`}>
              <div className="identity-payment-timeline__head">
                <span className="identity-payment-timeline__marker" aria-hidden="true">✓</span>
                <strong>{timelineEventTitle(event.type, t)}</strong>
              </div>
              {detail ? <p className="identity-payment-timeline__detail">{detail}</p> : null}
              {event.source ? (
                <small className="identity-payment-timeline__source">{event.source}</small>
              ) : null}
            </li>
          );
        })}
      </ol>
    </section>
  );
}

function StatementLineDetail({ line, onBack, onViewPayment, t }) {
  const provenance = line?.provenance || {};

  return (
    <div className="identity-statement-line-detail">
      <button className="identity-account-back" onClick={onBack} type="button">
        {t('identity_statement_back')}
      </button>
      <header className="identity-statement-line-detail__header">
        <span>{t('identity_statement_line_detail_title')}</span>
        <strong>
          {formatSignedDisplay(line?.signed_amount)} {line?.asset}
        </strong>
        <small>{statementLineTitle(line?.type, t)}</small>
      </header>

      <dl className="identity-activity-detail__facts">
        {line?.narrative ? (
          <div>
            <dt>{t('identity_statement_line_narrative')}</dt>
            <dd>{line.narrative}</dd>
          </div>
        ) : null}
        {line?.occurred_at ? (
          <div>
            <dt>{t('identity_statement_line_when')}</dt>
            <dd>{formatActivityWhen(line.occurred_at) || line.occurred_at}</dd>
          </div>
        ) : null}
        <div>
          <dt>{t('identity_statement_line_source')}</dt>
          <dd>{provenance.source || 'accounting_event'}</dd>
        </div>
        {provenance.accounting_event_id ? (
          <div>
            <dt>{t('identity_statement_line_accounting_event')}</dt>
            <dd><code>{provenance.accounting_event_id}</code></dd>
          </div>
        ) : null}
      </dl>

      {line?.drilldown_available ? (
        <div className="identity-statement-line-detail__actions">
          <button className="identity-account-primary" onClick={onViewPayment} type="button">
            {t('identity_statement_view_payment')}
          </button>
          <p>{t('identity_statement_view_payment_hint')}</p>
        </div>
      ) : null}
    </div>
  );
}

function StatementScreen({
  statement,
  loading,
  error,
  periodLabel,
  onRefresh,
  onSelectLine,
  t,
}) {
  const totals = statement?.totals || {};

  return (
    <div className="identity-statement-screen">
      <div className="identity-account-recent__header">
        <span>{t('identity_statement_title')}</span>
        <button disabled={loading} onClick={onRefresh} type="button">
          {loading ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
        </button>
      </div>
      <p className="identity-statement-period">{periodLabel}</p>

      {error ? <p className="identity-send-error">{error}</p> : null}

      {statement ? (
        <>
          <section className="identity-statement-summary">
            <div>
              <span>{t('identity_statement_opening_balance')}</span>
              <strong>{formatSignedDisplay(statement.opening_balance)} {statement.asset}</strong>
            </div>
            <div>
              <span>{t('identity_statement_closing_balance')}</span>
              <strong>{formatSignedDisplay(statement.closing_balance)} {statement.asset}</strong>
            </div>
            <div>
              <span>{t('identity_statement_net_change')}</span>
              <strong>{formatSignedDisplay(totals.net_change)} {statement.asset}</strong>
            </div>
          </section>

          <section className="identity-statement-totals">
            <div>
              <span>{t('identity_statement_total_outbound')}</span>
              <strong>{formatSignedDisplay(totals.outbound)}</strong>
            </div>
            <div>
              <span>{t('identity_statement_total_inbound')}</span>
              <strong>{formatSignedDisplay(totals.inbound)}</strong>
            </div>
            <div>
              <span>{t('identity_statement_total_compensations')}</span>
              <strong>{formatSignedDisplay(totals.compensations)}</strong>
            </div>
            <div>
              <span>{t('identity_statement_total_fees')}</span>
              <strong>{formatSignedDisplay(totals.fees)}</strong>
            </div>
          </section>

          <ol className="identity-statement-lines">
            {(statement.lines || []).map((line) => (
              <li key={line.line_id || `${line.type}-${line.occurred_at}`}>
                <button
                  className="identity-statement-line"
                  onClick={() => onSelectLine(line)}
                  type="button"
                >
                  <div className="identity-statement-line__main">
                    <strong>{statementLineTitle(line.type, t)}</strong>
                    <span>
                      {formatSignedDisplay(line.signed_amount)} {line.asset}
                    </span>
                  </div>
                  {line.narrative ? <small>{line.narrative}</small> : null}
                </button>
              </li>
            ))}
          </ol>

          {!statement.lines?.length && !loading ? (
            <p className="premium-wallet-balances-empty">{t('identity_statement_empty')}</p>
          ) : null}
        </>
      ) : null}
    </div>
  );
}

function ValueEntryDetail({ item, onBack, t }) {
  const entry = item?.value_entry || {};

  return (
    <div className="identity-activity-detail">
      <button className="identity-account-back" onClick={onBack} type="button">
        {t('identity_activity_back')}
      </button>
      <header className="identity-activity-detail__header">
        <span>{t('identity_value_entry_detail_title')}</span>
        <strong>
          +{entry.amount} {entry.asset}
        </strong>
        <small>
          {entry.credit_approved
            ? t('identity_activity_status_confirmed')
            : t('identity_activity_status_failed')}
        </small>
      </header>

      <dl className="identity-activity-detail__facts">
        <div>
          <dt>{t('identity_activity_amount')}</dt>
          <dd>
            +{entry.amount} {entry.asset}
          </dd>
        </div>
        <div>
          <dt>{t('identity_activity_settlement')}</dt>
          <dd>{entry.network_label || entry.network}</dd>
        </div>
        {entry.transaction_hash ? (
          <div>
            <dt>{t('identity_value_entry_proof')}</dt>
            <dd><code>{entry.transaction_hash}</code></dd>
          </div>
        ) : null}
        {entry.credit_status ? (
          <div>
            <dt>{t('identity_value_entry_credit')}</dt>
            <dd>
              {entry.credit_approved
                ? t('identity_value_entry_credit_approved')
                : t('identity_value_entry_credit_rejected')}
            </dd>
          </div>
        ) : null}
      </dl>
    </div>
  );
}

function PaymentDetail({ item, onBack, t, disputesEnabled = false, onIssueReported }) {
  const [advancedOpen, setAdvancedOpen] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const [reportReason, setReportReason] = useState('unauthorized');
  const [reportBusy, setReportBusy] = useState(false);
  const [reportError, setReportError] = useState('');
  const [issueState, setIssueState] = useState(null);
  const [timelineEvents, setTimelineEvents] = useState([]);
  const intent = item?.payment_intent || item?.intent || {};
  const accounting = item?.accounting_event;
  const reconciliation = item?.reconciliation_record;
  const settlement = item?.settlement_execution;
  const routing = item?.routing_decision || {};
  const feeDecision = item?.fee_decision || {};
  const limitDecision = item?.limit_decision || {};
  const canReportProblem = disputesEnabled
    && intent.status === 'executed'
    && accounting;

  useEffect(() => {
    if (!intent.id) {
      setTimelineEvents([]);
      return undefined;
    }

    let cancelled = false;
    const token = readStoredVaultToken();
    if (!token) return undefined;

    fetchPaymentIntentTimeline(intent.id, token)
      .then((payload) => {
        if (!cancelled) setTimelineEvents(Array.isArray(payload?.events) ? payload.events : []);
      })
      .catch(() => {
        if (!cancelled) setTimelineEvents([]);
      });

    return () => {
      cancelled = true;
    };
  }, [intent.id]);

  useEffect(() => {
    if (!canReportProblem || !intent.id) {
      setIssueState(null);
      return undefined;
    }

    let cancelled = false;
    const token = readStoredVaultToken();
    if (!token) return undefined;

    fetchPaymentIntentDispute(intent.id, token)
      .then((payload) => {
        if (!cancelled) setIssueState(payload?.dispute || null);
      })
      .catch(() => {
        if (!cancelled) setIssueState(null);
      });

    return () => {
      cancelled = true;
    };
  }, [canReportProblem, intent.id]);

  async function handleReportProblem() {
    setReportError('');
    const token = readStoredVaultToken();
    if (!token) {
      setReportError(t('wallet_connect_session_expired'));
      return;
    }

    setReportBusy(true);
    try {
      const payload = await openPaymentDispute(intent.id, { reason: reportReason }, token);
      setIssueState(payload);
      setReportOpen(false);
      onIssueReported?.(payload);
    } catch (error) {
      setReportError(error?.message || t('identity_issue_report_error'));
    } finally {
      setReportBusy(false);
    }
  }

  const paymentVerified = intent.status === 'executed';
  const settlementConfirmed = Boolean(settlement?.tx_reference);
  const accountingRecorded = Boolean(accounting?.id || accounting?.narrative);

  return (
    <div className="identity-activity-detail">
      <button className="identity-account-back" onClick={onBack} type="button">
        {t('identity_activity_back')}
      </button>
      <header className="identity-activity-detail__header">
        <span>{t('identity_activity_detail_title')}</span>
        <strong>
          {intent.amount} {intent.asset}
        </strong>
        <small>{paymentStatusLabel(intent.status, t)}</small>
      </header>

      {timelineEvents.length ? (
        <PaymentTimeline events={timelineEvents} intent={intent} t={t} />
      ) : (
        <ul className="identity-payment-trust">
          <li className={paymentVerified ? 'is-ok' : ''}>{t('identity_payment_verified')}</li>
          <li className={settlementConfirmed ? 'is-ok' : ''}>{t('identity_settlement_confirmed')}</li>
          <li className={accountingRecorded ? 'is-ok' : ''}>{t('identity_accounting_recorded')}</li>
        </ul>
      )}

      <dl className="identity-activity-detail__facts">
        <div>
          <dt>{t('identity_activity_from')}</dt>
          <dd>{intent.from_alias || intent.from_identity}</dd>
        </div>
        <div>
          <dt>{t('identity_activity_to')}</dt>
          <dd>{intent.to_alias || intent.to_identity}</dd>
        </div>
        <div>
          <dt>{t('identity_activity_amount')}</dt>
          <dd>
            {intent.amount} {intent.asset}
          </dd>
        </div>
        <div>
          <dt>{t('identity_activity_status')}</dt>
          <dd>{paymentStatusLabel(intent.status, t)}</dd>
        </div>
        {accounting?.narrative ? (
          <div>
            <dt>{t('identity_activity_accounting')}</dt>
            <dd>{accounting.narrative}</dd>
          </div>
        ) : null}
        {settlement?.network ? (
          <div>
            <dt>{t('identity_activity_settlement')}</dt>
            <dd>
              <span>{settlement.network}</span>
              {settlement.tx_reference ? <code>{settlement.tx_reference}</code> : null}
            </dd>
          </div>
        ) : null}
        {reconciliation?.status ? (
          <div>
            <dt>{t('identity_activity_reconciliation')}</dt>
            <dd>
              {reconciliation.status === 'matched'
                ? t('identity_activity_reconciliation_matched')
                : reconciliation.status}
            </dd>
          </div>
        ) : null}
        {feeDecision?.fee_amount ? (
          <div>
            <dt>{t('identity_activity_fee')}</dt>
            <dd>{feeDecision.fee_amount} {feeDecision.asset || intent.asset}</dd>
          </div>
        ) : null}
      </dl>

      {issueState?.dispute ? (
        <div className="identity-issue-status">
          <span>{t('identity_issue_reported_title')}</span>
          <strong>{issueState.dispute.reason}</strong>
          <small>{issueState.dispute.status}</small>
        </div>
      ) : null}

      {canReportProblem && !issueState?.dispute ? (
        <div className="identity-issue-actions">
          <button className="identity-account-secondary" onClick={() => setReportOpen(true)} type="button">
            {t('identity_issue_report_cta')}
          </button>
        </div>
      ) : null}

      {reportOpen ? (
        <div className="identity-issue-report">
          <h3>{t('identity_issue_report_title')}</h3>
          <p>{t('identity_issue_report_body')}</p>
          <label>
            <span>{t('identity_issue_report_reason')}</span>
            <select onChange={(event) => setReportReason(event.target.value)} value={reportReason}>
              <option value="unauthorized">{t('identity_issue_reason_unauthorized')}</option>
              <option value="duplicate_payment">{t('identity_issue_reason_duplicate')}</option>
              <option value="wrong_amount">{t('identity_issue_reason_wrong_amount')}</option>
              <option value="not_received">{t('identity_issue_reason_not_received')}</option>
            </select>
          </label>
          {reportError ? <p className="identity-send-error">{reportError}</p> : null}
          <div className="identity-send-actions">
            <button disabled={reportBusy} onClick={() => setReportOpen(false)} type="button">
              {t('identity_send_cancel')}
            </button>
            <button className="identity-account-primary" disabled={reportBusy} onClick={handleReportProblem} type="button">
              {reportBusy ? t('identity_issue_report_submitting') : t('identity_issue_report_submit')}
            </button>
          </div>
        </div>
      ) : null}

      <button
        className="identity-account-advanced-toggle"
        onClick={() => setAdvancedOpen((current) => !current)}
        type="button"
      >
        {advancedOpen ? t('identity_send_advanced_hide') : t('identity_send_advanced_show')}
      </button>

      {advancedOpen ? (
        <div className="identity-account-advanced">
          <div>
            <span>{t('identity_send_routing_policy')}</span>
            <code>{routing.policy_version || routing.policy}</code>
          </div>
          <div>
            <span>{t('identity_send_selected_network')}</span>
            <strong>{routing.selected?.network || '—'}</strong>
          </div>
          <div>
            <span>{t('identity_send_bindings')}</span>
            <code>
              {routing.selected?.sender_binding_id ?? '—'} → {routing.selected?.receiver_binding_id ?? '—'}
            </code>
          </div>
          {routing.decision_context?.ruleset_hash ? (
            <div>
              <span>{t('identity_timeline_ruleset_hash')}</span>
              <code>{routing.decision_context.ruleset_hash}</code>
            </div>
          ) : null}
          {limitDecision?.ruleset_hash ? (
            <div>
              <span>{t('identity_timeline_limit_ruleset')}</span>
              <code>{limitDecision.ruleset_hash}</code>
            </div>
          ) : null}
          {feeDecision?.ruleset_hash ? (
            <div>
              <span>{t('identity_timeline_fee_ruleset')}</span>
              <code>{feeDecision.ruleset_hash}</code>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

function SendModal({
  onClose,
  onSuccess,
  paymentAssets = ['USDC'],
  t,
}) {
  const [step, setStep] = useState('compose');
  const [toAlias, setToAlias] = useState('');
  const [amount, setAmount] = useState('');
  const [asset] = useState(paymentAssets[0] || 'USDC');
  const [resolvePreview, setResolvePreview] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const normalizedTo = useMemo(() => normalizeAlias(toAlias), [toAlias]);

  const handleContinue = useCallback(async () => {
    setError('');
    if (!normalizedTo || !amount.trim()) {
      setError(t('identity_send_validation'));
      return;
    }

    const token = readStoredVaultToken();
    if (!token) {
      setError(t('wallet_connect_session_expired'));
      return;
    }

    setBusy(true);
    try {
      const preview = await resolveSettlementRecipient(normalizedTo, token);
      setResolvePreview(preview);
      setStep('review');
    } catch (exception) {
      setError(exception?.message || t('identity_send_error'));
    } finally {
      setBusy(false);
    }
  }, [amount, normalizedTo, t]);

  const handleConfirm = useCallback(async () => {
    setError('');
    const token = readStoredVaultToken();
    if (!token) {
      setError(t('wallet_connect_session_expired'));
      return;
    }

    setBusy(true);
    try {
      const result = await createSettlementPaymentIntent({
        to_alias: normalizedTo,
        asset,
        amount: amount.trim(),
        execute: true,
      }, token);
      onSuccess(result);
      onClose();
    } catch (exception) {
      setError(exception?.message || t('identity_send_error'));
    } finally {
      setBusy(false);
    }
  }, [amount, asset, normalizedTo, onClose, onSuccess, t]);

  const capabilityCount = resolvePreview?.payment_routing_capabilities?.length
    || resolvePreview?.receiving_capabilities?.length
    || 0;
  const routingAvailable = (resolvePreview?.payment_routing_capabilities?.length || 0) > 0;

  return (
    <div className="identity-send-modal" role="presentation">
      <button aria-label={t('identity_send_cancel')} className="identity-send-modal__backdrop" onClick={onClose} type="button" />
      <section aria-modal="true" className="identity-send-modal__panel" role="dialog">
        <header className="identity-send-modal__header">
          <span>{t('identity_send_title')}</span>
          <button onClick={onClose} type="button">{t('identity_send_cancel')}</button>
        </header>
        <p className="identity-send-scope">{t('identity_send_scope_hint')}</p>

        {step === 'compose' ? (
          <div className="identity-send-modal__body">
            <label className="identity-send-field">
              <span>{t('identity_send_to')}</span>
              <input
                autoComplete="off"
                onChange={(event) => setToAlias(event.target.value)}
                placeholder="@alice"
                value={toAlias}
              />
            </label>
            <label className="identity-send-field">
              <span>{t('identity_send_amount')}</span>
              <div className="identity-send-amount-row">
                <input
                  inputMode="decimal"
                  onChange={(event) => setAmount(event.target.value)}
                  placeholder="10"
                  value={amount}
                />
                <span>{asset}</span>
              </div>
            </label>
            {error ? <p className="identity-send-error">{error}</p> : null}
            <button className="identity-account-primary" disabled={busy} onClick={handleContinue} type="button">
              {busy ? t('identity_send_resolving') : t('identity_send_continue')}
            </button>
          </div>
        ) : (
          <div className="identity-send-modal__body">
            <h3>{t('identity_send_review_title')}</h3>
            <dl className="identity-send-review">
              <div>
                <dt>{t('identity_send_review_you_send')}</dt>
                <dd>{amount} {asset}</dd>
              </div>
              <div>
                <dt>{t('identity_send_review_recipient')}</dt>
                <dd>{normalizedTo}</dd>
              </div>
              <div>
                <dt>{t('identity_send_review_delivery')}</dt>
                <dd>{t('identity_send_review_automatic')}</dd>
              </div>
            </dl>
            <ul className="identity-send-trust">
              <li>{capabilityCount > 0 ? t('identity_send_recipient_verified') : t('identity_send_recipient_unverified')}</li>
              <li>{routingAvailable ? t('identity_send_routing_available') : t('identity_send_settlement_unavailable')}</li>
            </ul>
            {error ? <p className="identity-send-error">{error}</p> : null}
            <div className="identity-send-actions">
              <button disabled={busy} onClick={() => setStep('compose')} type="button">
                {t('identity_send_back')}
              </button>
              <button className="identity-account-primary" disabled={busy} onClick={handleConfirm} type="button">
                {busy ? t('identity_send_confirming') : t('identity_send_confirm')}
              </button>
            </div>
          </div>
        )}
      </section>
    </div>
  );
}

function ValueEntryModal({
  receiveOptions = [],
  bootstrapping = false,
  onClose,
  onSuccess,
  onRetryBootstrap,
  t,
}) {
  const [step, setStep] = useState('address');
  const [selectedKey, setSelectedKey] = useState(() => receiveOptions[0]?.key || '');
  const [qrDataUrl, setQrDataUrl] = useState('');
  const [qrLoading, setQrLoading] = useState(false);
  const [copied, setCopied] = useState(false);
  const [txHash, setTxHash] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  const selectedOption = useMemo(
    () => receiveOptions.find((option) => option.key === selectedKey) || receiveOptions[0] || null,
    [receiveOptions, selectedKey],
  );

  const networkKey = selectedOption?.key || '';
  const address = selectedOption?.address || '';
  const networkLabel = selectedOption?.label || networkKey;
  const asset = selectedOption?.asset || 'USDC';

  useEffect(() => {
    if (!receiveOptions.length) {
      setSelectedKey('');
      return;
    }

    if (!receiveOptions.some((option) => option.key === selectedKey)) {
      setSelectedKey(receiveOptions[0].key);
    }
  }, [receiveOptions, selectedKey]);

  useEffect(() => {
    if (!address || !networkKey) {
      setQrDataUrl('');
      return undefined;
    }

    let cancelled = false;
    setQrLoading(true);
    buildEvmUsdcReceiveQrDataUrl(address, networkKey)
      .then((dataUrl) => {
        if (!cancelled) setQrDataUrl(dataUrl || '');
      })
      .finally(() => {
        if (!cancelled) setQrLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [address, networkKey]);

  function handleSelectNetwork(nextKey) {
    if (nextKey === selectedKey) {
      return;
    }

    setSelectedKey(nextKey);
    setStep('address');
    setTxHash('');
    setError('');
    setResult(null);
  }

  async function handleCopyAddress() {
    if (!address || typeof navigator === 'undefined' || !navigator.clipboard) {
      return;
    }

    try {
      await navigator.clipboard.writeText(address);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  }

  async function handleSubmitProof() {
    setError('');
    const token = readStoredVaultToken();
    if (!token) {
      setError(t('wallet_connect_session_expired'));
      return;
    }

    const normalizedHash = String(txHash || '').trim();
    if (!/^0x[a-fA-F0-9]{64}$/.test(normalizedHash)) {
      setError(t('identity_value_entry_error'));
      return;
    }

    setBusy(true);
    try {
      const payload = await submitUsdcTransferProof({
        binding_key: networkKey,
        transaction_hash: normalizedHash,
        recipient: address,
        minimum_amount: '0.01',
      }, token);
      setResult(payload);
      setStep('success');
    } catch (exception) {
      setError(exception?.message || t('identity_value_entry_error'));
    } finally {
      setBusy(false);
    }
  }

  function handleDone() {
    onSuccess?.(result);
    onClose();
  }

  if (!receiveOptions.length || !selectedOption?.address) {
    return (
      <div className="identity-send-modal" role="presentation">
        <button aria-label={t('identity_send_cancel')} className="identity-send-modal__backdrop" onClick={onClose} type="button" />
        <section aria-modal="true" className="identity-send-modal__panel" role="dialog">
          <header className="identity-send-modal__header">
            <span>{t('identity_value_entry_title')}</span>
            <button onClick={onClose} type="button">{t('identity_send_cancel')}</button>
          </header>
          <div className="identity-send-modal__body identity-receive-body">
            {bootstrapping ? (
              <p>{t('identity_value_entry_bootstrapping')}</p>
            ) : (
              <>
                <p>{t('identity_value_entry_no_instrument')}</p>
                {onRetryBootstrap ? (
                  <button className="identity-send-primary" onClick={onRetryBootstrap} type="button">
                    {t('wallet_balances_refresh')}
                  </button>
                ) : null}
              </>
            )}
          </div>
        </section>
      </div>
    );
  }

  return (
    <div className="identity-send-modal" role="presentation">
      <button aria-label={t('identity_send_cancel')} className="identity-send-modal__backdrop" onClick={onClose} type="button" />
      <section aria-modal="true" className="identity-send-modal__panel" role="dialog">
        <header className="identity-send-modal__header">
          <span>
            {step === 'confirm'
              ? t('identity_value_entry_confirm_title')
              : t('identity_value_entry_title')}
          </span>
          <button onClick={onClose} type="button">{t('identity_send_cancel')}</button>
        </header>

        <div className="identity-send-modal__body identity-receive-body">
          {step === 'address' ? (
            <>
              {receiveOptions.length > 1 ? (
                <div className="identity-value-entry-networks">
                  <span className="identity-value-entry-networks__label">{t('identity_value_entry_choose_network')}</span>
                  <div className="identity-value-entry-networks__list" role="tablist">
                    {receiveOptions.map((option) => (
                      <button
                        aria-selected={option.key === selectedKey}
                        className={`identity-value-entry-network${option.key === selectedKey ? ' is-active' : ''}`}
                        key={option.key}
                        onClick={() => handleSelectNetwork(option.key)}
                        role="tab"
                        type="button"
                      >
                        {option.label}
                      </button>
                    ))}
                  </div>
                </div>
              ) : null}
              <p>{t('identity_value_entry_subtitle', { network: networkLabel })}</p>
              <p className="identity-value-entry-asset">{asset} · {networkLabel}</p>
              {qrLoading ? <p>{t('wallet_balances_refreshing')}</p> : null}
              {qrDataUrl ? <img alt={t('wallet_identity_qr_alt')} className="identity-receive-qr" src={qrDataUrl} /> : null}
              <div className="identity-value-entry-address">
                <span className="identity-value-entry-address__label">{t('identity_value_entry_address_label')}</span>
                <code className="identity-value-entry-address__value">{address}</code>
                <button onClick={handleCopyAddress} type="button">
                  {copied ? t('identity_value_entry_copied') : t('identity_value_entry_copy')}
                </button>
              </div>
              <button className="identity-send-primary" onClick={() => setStep('confirm')} type="button">
                {t('identity_value_entry_sent_cta')}
              </button>
            </>
          ) : null}

          {step === 'confirm' ? (
            <>
              <p>{t('identity_value_entry_confirm_body')}</p>
              <label className="identity-send-field">
                <span>{t('identity_value_entry_tx_hash')}</span>
                <input
                  autoComplete="off"
                  onChange={(event) => setTxHash(event.target.value)}
                  placeholder="0x…"
                  spellCheck={false}
                  value={txHash}
                />
              </label>
              {error ? <p className="identity-send-error">{error}</p> : null}
              <div className="identity-send-actions">
                <button onClick={() => setStep('address')} type="button">
                  {t('identity_activity_back')}
                </button>
                <button
                  className="identity-send-primary"
                  disabled={busy}
                  onClick={handleSubmitProof}
                  type="button"
                >
                  {busy ? t('identity_value_entry_submitting') : t('identity_value_entry_submit')}
                </button>
              </div>
            </>
          ) : null}

          {step === 'success' ? (
            <>
              <p className="identity-value-entry-success">{t('identity_value_entry_success')}</p>
              <p>
                {t('identity_value_entry_success_body', {
                  amount: result?.value_entry?.value_entry?.amount || '—',
                  asset,
                })}
              </p>
              <button className="identity-send-primary" onClick={handleDone} type="button">
                {t('identity_value_entry_done')}
              </button>
            </>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export function IdentityAccountPanel({
  identity,
  summaryCoins = [],
  polygonWallet,
  visibleWallets = [],
  futureWallets = [],
  walletBindings = [],
  identityPaymentFlags = {},
  connectNotice = null,
  connectPhase = '',
  connectingKey = null,
  connectError = null,
  onConnect,
  onCreateManaged,
  onImportManaged,
  onRevokeInstrument,
  onReplaceInstrument,
  instrumentActionError = null,
  instrumentActingKey = null,
  legacyConnectEnabled = false,
  autoProvisionOnVault = false,
  onRefreshWallet,
  refreshingWallet = false,
  observationState = 'none',
}) {
  const { t } = useLocale();
  const [tab, setTab] = useState('tokens');
  const [showZeroBalances, setShowZeroBalances] = useState(false);
  const [overlay, setOverlay] = useState(null);
  const [sendOpen, setSendOpen] = useState(false);
  const [receiveOpen, setReceiveOpen] = useState(false);
  const [receiveBootstrapping, setReceiveBootstrapping] = useState(false);
  const [activityItems, setActivityItems] = useState([]);
  const [activityLoading, setActivityLoading] = useState(false);
  const [activityError, setActivityError] = useState('');
  const [selectedActivity, setSelectedActivity] = useState(null);
  const [statementData, setStatementData] = useState(null);
  const [statementLoading, setStatementLoading] = useState(false);
  const [statementError, setStatementError] = useState('');
  const [selectedStatementLine, setSelectedStatementLine] = useState(null);
  const [statementPaymentItem, setStatementPaymentItem] = useState(null);
  const receiveBootstrapAttempts = useRef(0);
  const statementPeriod = useMemo(() => currentMonthPeriod(), []);

  const coins = summaryCoins.length
    ? summaryCoins
    : (polygonWallet?.preview?.coins || []);
  const identityBalance = primaryIdentityBalance(coins);
  const displayCoins = useMemo(() => {
    if (showZeroBalances) {
      return coins;
    }

    const nonZero = coins.filter((coin) => coinHasBalance(coin));
    if (nonZero.length) {
      return nonZero;
    }

    return coins.filter((coin) => IDENTITY_LAYER_SYMBOLS.has(String(coin.symbol || '').toUpperCase()));
  }, [coins, showZeroBalances]);
  const hiddenTokenCount = Math.max(0, coins.length - displayCoins.length);
  const identityAnchor = resolveIdentityAnchorAddress(identity);
  const receiveOptions = useMemo(
    () => buildValueEntryReceiveOptions(visibleWallets),
    [visibleWallets],
  );

  const ensureReceiveInstruments = useCallback(async () => {
    if (!autoProvisionOnVault || receiveOptions.length || !onRefreshWallet) {
      return receiveOptions.length > 0;
    }

    if (receiveBootstrapAttempts.current >= 2) {
      return false;
    }

    receiveBootstrapAttempts.current += 1;
    setReceiveBootstrapping(true);
    try {
      await onRefreshWallet();
      return true;
    } catch {
      return false;
    } finally {
      setReceiveBootstrapping(false);
    }
  }, [autoProvisionOnVault, onRefreshWallet, receiveOptions.length]);

  useEffect(() => {
    if (receiveOptions.length > 0) {
      receiveBootstrapAttempts.current = 0;
    }
  }, [receiveOptions.length]);

  const handleOpenReceive = useCallback(async () => {
    setReceiveOpen(true);
    if (!receiveOptions.length) {
      await ensureReceiveInstruments();
    }
  }, [ensureReceiveInstruments, receiveOptions.length]);

  const bindingByKey = useMemo(
    () => new Map(
      walletBindings
        .filter((entry) => entry?.binding_key)
        .map((entry) => [entry.binding_key, entry]),
    ),
    [walletBindings],
  );

  const capabilityRows = useMemo(
    () => buildInstrumentCapabilityRows(visibleWallets, {
      ...identityPaymentFlags,
      bindingByKey,
    }),
    [bindingByKey, identityPaymentFlags, visibleWallets],
  );

  const outgoingAssets = useMemo(
    () => outgoingPaymentAssets(capabilityRows),
    [capabilityRows],
  );

  const canSendPayments = hasOutgoingPaymentRouting(capabilityRows) && outgoingAssets.length > 0;
  const shortAddress = identityAnchor ? shortenIdentityAnchor(identityAnchor) : '';

  const loadActivity = useCallback(async () => {
    const token = readStoredVaultToken();
    if (!token) {
      setActivityItems([]);
      return;
    }

    setActivityLoading(true);
    setActivityError('');
    try {
      const [paymentsPayload, valueEntriesPayload] = await Promise.all([
        listSettlementPaymentIntents(token, { limit: 25 }).catch(() => ({ items: [] })),
        listValueEntries(token, { limit: 25 }),
      ]);

      const merged = [
        ...(paymentsPayload?.items || []),
        ...(valueEntriesPayload?.items || []),
      ].sort((left, right) => {
        const leftAt = new Date(left?.activity_at
          || left?.payment_intent?.routed_at
          || left?.intent?.created_at
          || 0).getTime();
        const rightAt = new Date(right?.activity_at
          || right?.payment_intent?.routed_at
          || right?.intent?.created_at
          || 0).getTime();

        return rightAt - leftAt;
      });

      setActivityItems(merged);
    } catch (exception) {
      setActivityError(exception?.message || t('identity_activity_error'));
      setActivityItems([]);
    } finally {
      setActivityLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadActivity();
  }, [loadActivity]);

  const loadStatement = useCallback(async () => {
    const token = readStoredVaultToken();
    if (!token) {
      setStatementData(null);
      return;
    }

    setStatementLoading(true);
    setStatementError('');
    try {
      const payload = await fetchIdentityStatement(token, {
        from: statementPeriod.from,
        to: statementPeriod.to,
        asset: 'USDC',
      });
      setStatementData(payload);
    } catch (exception) {
      setStatementError(exception?.message || t('identity_statement_error'));
      setStatementData(null);
    } finally {
      setStatementLoading(false);
    }
  }, [statementPeriod.from, statementPeriod.to, t]);

  useEffect(() => {
    if (overlay === 'statement') {
      loadStatement();
    }
  }, [loadStatement, overlay]);

  const resolvePaymentActivityItem = useCallback(async (paymentIntentId) => {
    if (!paymentIntentId) {
      return null;
    }

    const existing = activityItems.find(
      (item) => (item?.payment_intent?.id || item?.intent?.id) === paymentIntentId,
    );
    if (existing) {
      return existing;
    }

    const token = readStoredVaultToken();
    if (!token) {
      return null;
    }

    const payload = await listSettlementPaymentIntents(token, { limit: 50 });
    return (payload?.items || []).find(
      (item) => (item?.payment_intent?.id || item?.intent?.id) === paymentIntentId,
    ) || null;
  }, [activityItems]);

  const handleStatementViewPayment = useCallback(async () => {
    const paymentIntentId = selectedStatementLine?.provenance?.payment_intent_id;
    if (!paymentIntentId) {
      return;
    }

    try {
      const item = await resolvePaymentActivityItem(paymentIntentId);
      if (item) {
        setStatementPaymentItem(item);
      }
    } catch {
      setStatementError(t('identity_statement_payment_unavailable'));
    }
  }, [resolvePaymentActivityItem, selectedStatementLine, t]);

  const handleSendSuccess = useCallback(() => {
    loadActivity();
    loadStatement();
    setTab('activity');
    setOverlay(null);
  }, [loadActivity, loadStatement]);

  const handleReceiveSuccess = useCallback(() => {
    onRefreshWallet?.();
    loadActivity();
    loadStatement();
    setTab('activity');
  }, [loadActivity, loadStatement, onRefreshWallet]);

  const groupedActivity = useMemo(() => groupActivityByDate(activityItems), [activityItems]);

  const menuSections = [
    {
      key: 'manage',
      label: t('wallet_shell_menu_manage'),
      items: [
        {
          key: 'networks',
          label: t('wallet_safe_networks_title'),
          icon: <WalletNetworkIcon />,
        },
        {
          key: 'statement',
          label: t('identity_account_nav_statement'),
          icon: <WalletStatementIcon />,
        },
      ],
    },
    {
      key: 'identity',
      label: t('wallet_shell_menu_identity'),
      items: [
        {
          key: 'identity',
          label: t('identity_profile_title'),
          icon: <WalletIdentityIcon />,
        },
      ],
    },
  ];

  return (
    <section className="identity-account-panel wallet-shell">
      {overlay === 'menu' ? (
        <WalletSettingsMenu
          backLabel={t('wallet_shell_back')}
          onBack={() => setOverlay(null)}
          onSelect={(key) => setOverlay(key)}
          sections={menuSections}
          title={t('wallet_shell_menu_title')}
        />
      ) : overlay === 'networks' ? (
        <WalletSettingsScreen
          backLabel={t('wallet_shell_back')}
          onBack={() => setOverlay('menu')}
          title={t('wallet_safe_networks_title')}
        >
          <IdentityProfileScreen
            connectError={connectError}
            connectNotice={connectNotice}
            connectPhase={connectPhase}
            connectingKey={connectingKey}
            futureWallets={futureWallets}
            identity={identity}
            identityPaymentFlags={identityPaymentFlags}
            instrumentActionError={instrumentActionError}
            instrumentActingKey={instrumentActingKey}
            legacyConnectEnabled={legacyConnectEnabled}
            onConnect={onConnect}
            onCreateManaged={onCreateManaged}
            onImportManaged={onImportManaged}
            onReplaceInstrument={onReplaceInstrument}
            onRevokeInstrument={onRevokeInstrument}
            variant="networks"
            visibleWallets={visibleWallets}
            walletBindings={walletBindings}
          />
        </WalletSettingsScreen>
      ) : overlay === 'statement' ? (
        <div className="identity-statement-flow">
          {statementPaymentItem ? (
            <PaymentDetail
              disputesEnabled={identityPaymentFlags.identityPaymentDisputesEnabled === true}
              item={statementPaymentItem}
              onBack={() => setStatementPaymentItem(null)}
              t={t}
            />
          ) : selectedStatementLine ? (
            <StatementLineDetail
              line={selectedStatementLine}
              onBack={() => setSelectedStatementLine(null)}
              onViewPayment={handleStatementViewPayment}
              t={t}
            />
          ) : (
            <WalletSettingsScreen
              backLabel={t('wallet_shell_back')}
              onBack={() => setOverlay('menu')}
              title={t('identity_account_nav_statement')}
            >
              <StatementScreen
                error={statementError}
                loading={statementLoading}
                onRefresh={loadStatement}
                onSelectLine={setSelectedStatementLine}
                periodLabel={statementPeriod.label}
                statement={statementData}
                t={t}
              />
            </WalletSettingsScreen>
          )}
        </div>
      ) : overlay === 'identity' ? (
        <WalletSettingsScreen
          backLabel={t('wallet_shell_back')}
          onBack={() => setOverlay('menu')}
          title={t('identity_profile_title')}
        >
          <IdentityProfileScreen
            connectError={connectError}
            connectNotice={connectNotice}
            connectPhase={connectPhase}
            connectingKey={connectingKey}
            futureWallets={futureWallets}
            identity={identity}
            identityPaymentFlags={identityPaymentFlags}
            instrumentActionError={instrumentActionError}
            instrumentActingKey={instrumentActingKey}
            legacyConnectEnabled={legacyConnectEnabled}
            onConnect={onConnect}
            onCreateManaged={onCreateManaged}
            onImportManaged={onImportManaged}
            onReplaceInstrument={onReplaceInstrument}
            onRevokeInstrument={onRevokeInstrument}
            variant="profile"
            visibleWallets={visibleWallets}
            walletBindings={walletBindings}
          />
        </WalletSettingsScreen>
      ) : (
        <>
          <WalletShellHeader
            accountName={identityLabel(identity)}
            address={shortAddress}
            addressKind="sl1"
            copiedLabel={t('wallet_shell_address_copied')}
            fullAddress={identityAnchor || ''}
            menuLabel={t('wallet_shell_menu_title')}
            onMenu={() => setOverlay('menu')}
          />

          <WalletShellBalance
            amount={identityBalance.amount}
            label={t('wallet_shell_balance_identity')}
            symbol={identityBalance.symbol}
          />

          <WalletShellActions
            actions={[
              {
                key: 'send',
                label: t('identity_account_send'),
                icon: <WalletSendIcon />,
                disabled: !canSendPayments,
                onClick: () => setSendOpen(true),
              },
              {
                key: 'receive',
                label: t('identity_account_receive'),
                icon: <WalletReceiveIcon />,
                onClick: handleOpenReceive,
              },
            ]}
          />

          <WalletShellTabs
            active={tab}
            ariaLabel={t('identity_account_nav')}
            onChange={(nextTab) => {
              setTab(nextTab);
              setSelectedActivity(null);
            }}
            tabs={[
              { key: 'tokens', label: t('wallet_shell_tab_tokens') },
              { key: 'activity', label: t('identity_account_nav_activity') },
            ]}
          />

          {tab === 'tokens' ? (
            <div className="wallet-shell-panel wallet-shell-panel--tokens">
              {displayCoins.length ? (
                <div className="wallet-shell-token-list wallet-shell-token-list--compact" role="list">
                  {displayCoins.map((coin) => (
                    <WalletTokenRow
                      amount={coin.display_amount}
                      fiatAmount={coin.symbol === 'USDC' ? `$${coin.display_amount}` : null}
                      key={coin.key || coin.symbol}
                      symbol={coin.symbol}
                    />
                  ))}
                </div>
              ) : (
                <p className="wallet-shell-empty">{t('wallet_vault_balances_zero')}</p>
              )}
              {hiddenTokenCount > 0 ? (
                <button
                  className="wallet-shell-token-toggle"
                  onClick={() => setShowZeroBalances((current) => !current)}
                  type="button"
                >
                  {showZeroBalances
                    ? t('wallet_tokens_hide_zero', { count: hiddenTokenCount })
                    : t('wallet_tokens_show_zero', { count: hiddenTokenCount })}
                </button>
              ) : null}
              {onRefreshWallet ? (
                <button
                  className="wallet-shell-refresh"
                  disabled={refreshingWallet}
                  onClick={onRefreshWallet}
                  type="button"
                >
                  {refreshingWallet ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
                </button>
              ) : null}
              {observationState === 'unavailable' ? (
                <p className="wallet-shell-hint">{t('wallet_vault_balances_unavailable_hint')}</p>
              ) : null}
              {!canSendPayments ? (
                <p className="wallet-shell-hint">{t('identity_send_unavailable_hint')}</p>
              ) : null}
            </div>
          ) : null}

          {tab === 'activity' ? (
            <div className="wallet-shell-panel identity-activity-screen">
              {selectedActivity ? (
                selectedActivity?.activity_kind === 'value_entry' ? (
                  <ValueEntryDetail
                    item={selectedActivity}
                    onBack={() => setSelectedActivity(null)}
                    t={t}
                  />
                ) : (
                  <PaymentDetail
                    disputesEnabled={identityPaymentFlags.identityPaymentDisputesEnabled === true}
                    item={selectedActivity}
                    onBack={() => setSelectedActivity(null)}
                    t={t}
                  />
                )
              ) : (
                <>
                  <div className="wallet-shell-panel__toolbar">
                    <button disabled={activityLoading} onClick={loadActivity} type="button">
                      {activityLoading ? t('wallet_balances_refreshing') : t('wallet_balances_refresh')}
                    </button>
                  </div>
                  {activityError ? <p className="identity-send-error">{activityError}</p> : null}
                  {groupedActivity.length ? (
                    groupedActivity.map(([dateLabel, items]) => (
                      <WalletActivityGroup dateLabel={dateLabel} key={dateLabel}>
                        {items.map((item) => (
                          <ActivityRow
                            item={item}
                            key={item?.activity_kind === 'value_entry'
                              ? `value-entry-${item?.value_entry?.proof_id}`
                              : (item?.payment_intent?.id || item?.intent?.id)}
                            onSelect={setSelectedActivity}
                            t={t}
                          />
                        ))}
                      </WalletActivityGroup>
                    ))
                  ) : (
                    !activityLoading && <p className="wallet-shell-empty">{t('identity_activity_empty')}</p>
                  )}
                </>
              )}
            </div>
          ) : null}
        </>
      )}

      {sendOpen ? (
        <SendModal
          onClose={() => setSendOpen(false)}
          onSuccess={handleSendSuccess}
          paymentAssets={outgoingAssets}
          t={t}
        />
      ) : null}

      {receiveOpen ? (
        <ValueEntryModal
          bootstrapping={receiveBootstrapping}
          onClose={() => setReceiveOpen(false)}
          onRetryBootstrap={ensureReceiveInstruments}
          onSuccess={handleReceiveSuccess}
          receiveOptions={receiveOptions}
          t={t}
        />
      ) : null}
    </section>
  );
}
