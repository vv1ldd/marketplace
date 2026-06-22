'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  collectOpsPaymentDisputeEvidence,
  fetchOpsPaymentDispute,
  fetchOpsPaymentDisputes,
  requestOpsPaymentDisputeEvidence,
  resolveOpsPaymentDispute,
  reviewOpsPaymentDispute,
} from '../lib/ops-api';

function EvidenceViewer({ viewer = {} }) {
  return (
    <div className="ops-dispute-evidence">
      <p>Identity: {viewer.identity?.sender?.alias} → {viewer.identity?.receiver?.alias}</p>
      <p>Routing: {viewer.routing?.network} · {viewer.routing?.policy}</p>
      <p>Limits: {viewer.limits?.policy} · {viewer.limits?.approved ? 'approved' : 'denied'}</p>
      <p>Fees: {viewer.fees?.policy} · {viewer.fees?.amount} {viewer.fees?.asset}</p>
      <p>Settlement: <code>{viewer.settlement?.tx_reference}</code></p>
      <p>Reconciliation: {viewer.reconciliation?.status}</p>
    </div>
  );
}

export function OpsPaymentDisputesSection() {
  const [items, setItems] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [selectedId, setSelectedId] = useState('');
  const [detail, setDetail] = useState(null);
  const [output, setOutput] = useState(null);
  const [busy, setBusy] = useState('');

  const refresh = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const payload = await fetchOpsPaymentDisputes();
      setItems(payload?.items || []);
    } catch (caught) {
      setError(caught.message || 'Could not load disputes.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  async function openDetail(disputeId) {
    setSelectedId(disputeId);
    setDetail(await fetchOpsPaymentDispute(disputeId));
  }

  async function runAction(action) {
    if (!selectedId) return;
    setBusy(action);
    try {
      let result;
      if (action === 'request-evidence') result = await requestOpsPaymentDisputeEvidence(selectedId);
      if (action === 'collect-evidence') result = await collectOpsPaymentDisputeEvidence(selectedId);
      if (action === 'review') result = await reviewOpsPaymentDispute(selectedId);
      if (action === 'approve-refund') {
        result = await resolveOpsPaymentDispute(selectedId, {
          decision: 'approved',
          creates_compensation_intent: true,
          reason: 'duplicate_payment',
          resolved_by: 'ops',
        });
      }
      if (action === 'reject') {
        result = await resolveOpsPaymentDispute(selectedId, {
          decision: 'rejected',
          reason: 'insufficient_evidence',
          resolved_by: 'ops',
        });
      }
      setDetail(result);
      setOutput(result);
      await refresh();
    } catch (caught) {
      setOutput({ error: caught.message || 'Action failed.' });
    } finally {
      setBusy('');
    }
  }

  return (
    <section className="ops-finance-section">
      <div className="ops-finance-section__header">
        <div>
          <p className="eyebrow">Identity Payments</p>
          <h3>Payment disputes</h3>
        </div>
        <button className="ops-action-button" onClick={refresh} type="button">Refresh</button>
      </div>
      {loading ? <p className="ops-state-note">Loading disputes…</p> : null}
      {error ? <p className="ops-state-note">{error}</p> : null}
      {!loading && !items.length ? <p className="ops-state-note">No payment disputes yet.</p> : null}
      <div className="ops-dispute-list">
        {items.map((row) => (
          <button key={row.dispute?.id} className="ops-dispute-list__item" onClick={() => openDetail(row.dispute?.id)} type="button">
            <strong>{row.payment?.from_alias} → {row.payment?.to_alias}</strong>
            <span>{row.payment?.amount} {row.payment?.asset}</span>
            <small>{row.dispute?.status}</small>
          </button>
        ))}
      </div>
      {detail ? (
        <div className="ops-dispute-detail">
          <h4>{detail.dispute?.reason}</h4>
          <EvidenceViewer viewer={detail.evidence_viewer} />
          <div className="ops-row-actions">
            <button className="ops-action-button" disabled={busy !== ''} onClick={() => runAction('request-evidence')} type="button">Request evidence</button>
            <button className="ops-action-button" disabled={busy !== ''} onClick={() => runAction('collect-evidence')} type="button">Collect evidence</button>
            <button className="ops-action-button" disabled={busy !== ''} onClick={() => runAction('review')} type="button">Review</button>
            <button className="ops-action-button" disabled={busy !== ''} onClick={() => runAction('approve-refund')} type="button">Approve refund</button>
            <button className="ops-action-button ops-action-button--secondary" disabled={busy !== ''} onClick={() => runAction('reject')} type="button">Reject</button>
          </div>
        </div>
      ) : null}
      {output?.error ? <p className="ops-state-note">{output.error}</p> : null}
    </section>
  );
}
