'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import {
  approveOpsPartner,
  connectOpsZeroLayer,
  decideOpsRecommendation,
  fetchOpsCatalog,
  fetchOpsChannels,
  fetchOpsGrowth,
  fetchOpsInventory,
  fetchOpsLiquidity,
  fetchOpsOperations,
  fetchOpsOrders,
  fetchOpsPartners,
  fetchOpsProviders,
  fetchOpsSearchIntegrations,
  fetchOpsShops,
  fetchOpsTicketDetails,
  fetchOpsTickets,
  fetchOpsTreasury,
  replyOpsTicket,
  runOpsAiAudit,
  runOpsSearchSignalAction,
  sendOpsAiMessage,
  syncOpsInventoryWarehouses,
  syncOpsProvider,
  syncOpsZeroLayer,
  topUpOpsPartner,
  traceOpsSimpleLayer1,
  validateOpsTribunalChain,
} from '../lib/ops-api';

const MODULE_GROUPS = [
  {
    label: 'Analytics',
    modules: [
      ['command', 'Command Center', 'Live system picture'],
      ['decisions', 'Decision Console', 'Growth and authority'],
    ],
  },
  {
    label: 'Merchants',
    modules: [
      ['organizations', 'Organizations', 'Legal entity registry'],
      ['finance', 'Finance & Liquidity', 'Treasury and corridors'],
      ['channels', 'Channels', 'Sales channel matrix'],
      ['shops', 'Shops', 'Storefront registry'],
    ],
  },
  {
    label: 'Catalog',
    modules: [
      ['orders', 'Orders & Operations', 'Fulfillment history'],
      ['catalog', 'Catalog', 'Global product table'],
      ['inventory', 'Inventory', 'Warehouses and vouchers'],
      ['providers', 'Providers', 'EZPin supply runtime'],
      ['search', 'Search Integrations', 'Zero Layer signals'],
    ],
  },
  {
    label: 'System',
    modules: [
      ['support', 'Support', 'Incident queue'],
      ['audit', 'AI Audit', 'Ledger and SL1 validation'],
    ],
  },
];

const MODULE_COPY = {
  command: ['Operations command.', 'Global view of moderation, provider supply, liquidity, channel health, and ledger runtime.'],
  organizations: ['Company moderation.', 'Approve legal entities, inspect API authority, and settle partner balances.'],
  finance: ['Finance and liquidity.', 'Treasury requests, settlement events, currency readiness, rails, and corridors.'],
  channels: ['Channel matrix.', 'Meanly Storefront, Yandex, offline, CMS, and adapter health across shops.'],
  shops: ['Shop registry.', 'All stores, partners, sandbox flags, regions, categories, and creation state.'],
  orders: ['Orders and operations.', 'Marketplace orders plus unified Meanly API, ledger, and fulfillment history.'],
  catalog: ['Global catalog.', 'All products, stock, shop ownership, status, and provider/catalog errors.'],
  inventory: ['Warehouses and vouchers.', 'Master warehouses, low-stock rows, and voucher code registry.'],
  providers: ['Provider runtime.', 'EZPin/Meanly.one supply sync, provider credentials, terminals, and support plane.'],
  decisions: ['Decision Console.', 'Demand gaps, opportunity cases, search recommendations, and operational alerts.'],
  search: ['Search Integrations.', 'Zero Layer connectors, external demand signals, and search action pipelines.'],
  support: ['Support operations.', 'Ticket queue, details, admin replies, and incident closure.'],
  audit: ['AI and ledger audit.', 'Global audit, Ops AI chat, SL1 trace, and tribunal chain validation.'],
};

const TAB_ALIASES = {
  dashboard: 'command',
  partners: 'organizations',
  treasury: 'finance',
  liquidity: 'finance',
  'finance-liquidity': 'finance',
  'decision-console': 'decisions',
  operations: 'orders',
  'ai-audit': 'audit',
  tribunal: 'audit',
};

function moduleFromLocation() {
  if (typeof window === 'undefined') {
    return 'command';
  }

  const queryTab = new URLSearchParams(window.location.search).get('tab');
  const saved = window.localStorage.getItem('meanly:ops-module');
  const raw = queryTab || saved || 'command';
  return TAB_ALIASES[raw] || raw;
}

function valueText(value) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }
  if (typeof value === 'boolean') {
    return value ? 'yes' : 'no';
  }
  if (Array.isArray(value)) {
    return value.length ? value.join(', ') : '—';
  }
  if (typeof value === 'object') {
    return JSON.stringify(value);
  }
  return String(value);
}

function statusTone(status) {
  const normalized = String(status || '').toLowerCase();
  if (['active', 'approved', 'success', 'completed', 'ready', 'execution_ready', 'resolved', 'recorded'].includes(normalized)) {
    return 'active';
  }
  if (['pending', 'pending_moderation', 'processing', 'proposed', 'open', 'warning', 'paused', 'in_progress'].includes(normalized)) {
    return 'warn';
  }
  if (['rejected', 'failed', 'error', 'critical', 'inactive', 'cancelled'].includes(normalized)) {
    return 'danger';
  }
  return 'neutral';
}

function readColumn(row, key) {
  if (typeof key === 'function') {
    return key(row);
  }
  return String(key).split('.').reduce((current, part) => current?.[part], row);
}

function useOpsResource(loader, deps = [], enabled = true) {
  const [payload, setPayload] = useState(null);
  const [error, setError] = useState(null);
  const [isLoading, setIsLoading] = useState(Boolean(enabled));

  async function refresh() {
    if (!enabled) {
      return null;
    }
    setIsLoading(true);
    setError(null);
    try {
      const next = await loader();
      setPayload(next);
      return next;
    } catch (exception) {
      setError(exception);
      return null;
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    refresh();
  }, deps);

  return { payload, setPayload, error, isLoading, refresh };
}

function OpsStatusPill({ children, tone = 'neutral' }) {
  return <span className={`ops-pill ops-pill--${tone}`}>{children}</span>;
}

function OpsPanel({ eyebrow, title, description, badge, children, actions }) {
  return (
    <section className="ops-panel">
      <div className="section-heading ops-section-heading">
        <div>
          {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
          <h2>{title}</h2>
          {description ? <p>{description}</p> : null}
        </div>
        <div className="ops-heading-actions">
          {badge}
          {actions}
        </div>
      </div>
      {children}
    </section>
  );
}

function OpsMetrics({ metrics }) {
  return (
    <div className="ops-metric-grid" aria-label="Ops runtime metrics">
      {metrics.map((metric) => (
        <article key={metric.label}>
          <span>{metric.label}</span>
          <strong>{valueText(metric.value)}</strong>
          <small>{metric.detail}</small>
        </article>
      ))}
    </div>
  );
}

function OpsState({ error, isLoading, empty, emptyText = 'No rows for this surface.' }) {
  if (error) {
    return <p className="ops-state-note ops-state-note--error">{error.message}</p>;
  }
  if (isLoading) {
    return <p className="ops-state-note">Loading ops surface...</p>;
  }
  if (empty) {
    return <p className="ops-state-note">{emptyText}</p>;
  }
  return null;
}

function OpsTable({ columns, rows, emptyText = 'No data.', actions }) {
  if (!rows?.length) {
    return <p className="ops-state-note">{emptyText}</p>;
  }

  return (
    <div className="ops-table-wrap">
      <table className="ops-table">
        <thead>
          <tr>
            {columns.map((column) => <th key={column.label}>{column.label}</th>)}
            {actions ? <th>Actions</th> : null}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={row.id || row.reference || row.sku || row.query || `${index}`}>
              {columns.map((column) => (
                <td key={column.label}>
                  {column.render ? column.render(row) : valueText(readColumn(row, column.key))}
                </td>
              ))}
              {actions ? <td>{actions(row)}</td> : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function OpsToolbar({ children, onSubmit, search, setSearch, placeholder = 'Search...' }) {
  return (
    <div className="ops-toolbar">
      {children}
      {setSearch ? (
        <form onSubmit={(event) => {
          event.preventDefault();
          onSubmit?.();
        }}>
          <input onChange={(event) => setSearch(event.target.value)} placeholder={placeholder} value={search} />
          <button type="submit">Search</button>
        </form>
      ) : null}
    </div>
  );
}

function OpsPagination({ page, lastPage, isLoading, onPage }) {
  if (!lastPage || lastPage <= 1) {
    return null;
  }
  return (
    <div className="ops-pagination">
      <button disabled={page <= 1 || isLoading} onClick={() => onPage(page - 1)} type="button">Previous</button>
      <span>Page {page} / {lastPage}</span>
      <button disabled={page >= lastPage || isLoading} onClick={() => onPage(page + 1)} type="button">Next</button>
    </div>
  );
}

function OpsOutput({ output }) {
  return output ? <pre className="ops-provider-output">{typeof output === 'string' ? output : JSON.stringify(output, null, 2)}</pre> : null;
}

function ActionButton({ children, disabled, onClick, tone = 'primary' }) {
  return (
    <button className={`ops-action-button ops-action-button--${tone}`} disabled={disabled} onClick={onClick} type="button">
      {children}
    </button>
  );
}

function AccessPanel({ error }) {
  return (
    <main className="page">
      <section className="ops-access-panel">
        <p className="eyebrow">Meanly Ops</p>
        <h1>Ops access required.</h1>
        <p>
          {error?.status === 403
            ? 'Your current identity is signed in, but it does not have ops rights for moderation.'
            : 'Sign in with an ops-enabled Meanly identity to review company applications.'}
        </p>
        <div className="product-card__actions">
          <Link href="/login">Check login</Link>
          <Link href="/business">Merchant Center</Link>
        </div>
      </section>
    </main>
  );
}

function CommandCenterModule() {
  const { payload, error, isLoading, refresh } = useOpsResource(async () => {
    const [partners, orders, catalog, providers, treasury, growth, operations] = await Promise.all([
      fetchOpsPartners({ status: 'pending_moderation' }),
      fetchOpsOrders({ status: 'active' }),
      fetchOpsCatalog(),
      fetchOpsProviders(),
      fetchOpsTreasury(),
      fetchOpsGrowth(),
      fetchOpsOperations(),
    ]);
    return { partners, orders, catalog, providers, treasury, growth, operations };
  }, []);

  const metrics = [
    { label: 'Pending orgs', value: payload?.partners?.total ?? 0, detail: 'moderation queue' },
    { label: 'Active orders', value: payload?.orders?.total ?? 0, detail: 'in fulfillment' },
    { label: 'Catalog SKUs', value: payload?.catalog?.total ?? 0, detail: 'global products' },
    { label: 'Open alerts', value: payload?.growth?.summary?.open_alerts ?? 0, detail: 'growth and ops' },
  ];

  return (
    <OpsPanel
      actions={<ActionButton onClick={refresh}>Refresh command center</ActionButton>}
      badge={<OpsStatusPill tone="active">SL1 live</OpsStatusPill>}
      description="A compact top-level view across moderation, supply, treasury, growth, and ledger history."
      eyebrow="Command Center"
      title="Meanly Systems Operations"
    >
      <OpsMetrics metrics={metrics} />
      <OpsState error={error} isLoading={isLoading} />
      <div className="ops-grid-two">
        <div>
          <h3>System ledger log</h3>
          <OpsTable
            columns={[
              { label: 'Source', key: 'source', render: (row) => <OpsStatusPill tone={statusTone(row.source)}>{row.source}</OpsStatusPill> },
              { label: 'Type', key: 'type' },
              { label: 'Reference', key: 'reference' },
              { label: 'Status', key: 'status' },
            ]}
            rows={payload?.operations?.data || []}
          />
        </div>
        <div>
          <h3>Provider support plane</h3>
          <OpsTable
            columns={[
              { label: 'Provider', key: 'name' },
              { label: 'Type', key: 'type' },
              { label: 'Active SKUs', key: 'active_provider_products_count' },
              { label: 'Sync', key: 'sync_status' },
            ]}
            rows={payload?.providers?.data || []}
          />
        </div>
      </div>
    </OpsPanel>
  );
}

function OrganizationsModule() {
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [confirmId, setConfirmId] = useState(null);
  const [busyId, setBusyId] = useState(null);
  const { payload, error, isLoading, refresh } = useOpsResource(
    () => fetchOpsPartners({ status: statusFilter, search, page }),
    [statusFilter, page],
  );
  const partners = payload?.data || [];

  async function approve(partner) {
    if (!partner.approve_url) return;
    if (confirmId !== partner.id) {
      setConfirmId(partner.id);
      window.setTimeout(() => setConfirmId((current) => (current === partner.id ? null : current)), 3500);
      return;
    }
    setBusyId(partner.id);
    try {
      await approveOpsPartner(partner.approve_url);
      setConfirmId(null);
      await refresh();
    } finally {
      setBusyId(null);
    }
  }

  async function topUp(partner) {
    const url = partner.action_urls?.top_up;
    if (!url) return;
    const amount = window.prompt(`Top up amount for ${partner.name}:`, '10');
    if (!amount) return;
    const reference = window.prompt('Reference key:', `OPS-TOPUP-${partner.id}-${Date.now()}`);
    if (!reference) return;
    setBusyId(partner.id);
    try {
      await topUpOpsPartner(url, { amount: Number(amount), reference });
      await refresh();
    } finally {
      setBusyId(null);
    }
  }

  return (
    <OpsPanel
      badge={<OpsStatusPill tone="warn">{payload?.total ?? 0} shown</OpsStatusPill>}
      description="Legal entity authority, Merchant Center access, API identity, reservations, and settlement controls."
      eyebrow="Organizations"
      title="Moderation and partner authority"
    >
      <OpsToolbar onSubmit={() => { setPage(1); refresh(); }} placeholder="Search by company or INN" search={search} setSearch={setSearch}>
        <button className={statusFilter === '' ? 'is-active' : ''} onClick={() => { setPage(1); setStatusFilter(''); }} type="button">All</button>
        <button className={statusFilter === 'pending_moderation' ? 'is-active' : ''} onClick={() => { setPage(1); setStatusFilter('pending_moderation'); }} type="button">In moderation</button>
      </OpsToolbar>
      <OpsState empty={!partners.length && !isLoading} error={error} isLoading={isLoading} />
      <OpsTable
        columns={[
          { label: 'Legal entity', key: 'name', render: (row) => <strong>{row.name}</strong> },
          { label: 'INN/KPP', key: (row) => `${row.inn || '—'} / ${row.kpp || '—'}` },
          { label: 'Status', key: 'status', render: (row) => <OpsStatusPill tone={statusTone(row.status)}>{row.status_label || row.status}</OpsStatusPill> },
          { label: 'Balance', key: (row) => `${row.available_balance} / reserved ${row.reserved_balance}` },
          { label: 'API', key: (row) => row.api_identity?.token_configured ? `client ${row.api_identity.kernel_external_id || row.id}` : 'not configured' },
          { label: 'Reservations', key: (row) => `${row.settlement?.active_reservations_count || 0} / ${row.settlement?.active_reservations_amount || 0}` },
        ]}
        rows={partners}
        actions={(row) => (
          <div className="ops-row-actions">
            {row.approve_url ? <ActionButton disabled={busyId === row.id} onClick={() => approve(row)}>{confirmId === row.id ? 'Confirm approve' : 'Approve'}</ActionButton> : null}
            {row.action_urls?.top_up ? <ActionButton disabled={busyId === row.id} onClick={() => topUp(row)} tone="secondary">Top up</ActionButton> : null}
          </div>
        )}
      />
      <OpsPagination isLoading={isLoading} lastPage={payload?.last_page || 1} onPage={setPage} page={page} />
    </OpsPanel>
  );
}

function FinanceLiquidityModule() {
  const { payload, error, isLoading, refresh } = useOpsResource(async () => {
    const [treasury, liquidity] = await Promise.all([fetchOpsTreasury(), fetchOpsLiquidity()]);
    return { treasury, liquidity };
  }, []);

  return (
    <OpsPanel
      actions={<ActionButton onClick={refresh}>Refresh finance</ActionButton>}
      description="Partner balances, pending requests, settlement events, currency readiness, rails, and execution corridors."
      eyebrow="Finance & Liquidity"
      title="Treasury and liquidity"
    >
      <OpsMetrics metrics={[
        { label: 'Partners', value: payload?.treasury?.summary?.partners ?? 0, detail: 'legal entities' },
        { label: 'Available', value: payload?.treasury?.summary?.available_balance ?? 0, detail: 'partner balance' },
        { label: 'Reserved', value: payload?.treasury?.summary?.reserved_balance ?? 0, detail: 'partner reserves' },
        { label: 'Ready FX', value: payload?.liquidity?.summary?.execution_ready_currencies ?? 0, detail: 'execution currencies' },
      ]} />
      <OpsState error={error} isLoading={isLoading} />
      <div className="ops-grid-two">
        <div>
          <h3>Balance requests</h3>
          <OpsTable columns={[
            { label: 'Partner', key: 'partner' },
            { label: 'Type', key: 'type' },
            { label: 'Amount', key: (row) => `${row.amount} ${row.currency}` },
            { label: 'Status', key: 'status', render: (row) => <OpsStatusPill tone={statusTone(row.status)}>{row.status}</OpsStatusPill> },
          ]} rows={payload?.treasury?.requests || []} />
        </div>
        <div>
          <h3>Settlement events</h3>
          <OpsTable columns={[
            { label: 'Event', key: 'event_type' },
            { label: 'Partner', key: 'partner' },
            { label: 'Amount', key: (row) => `${row.amount} ${row.currency}` },
            { label: 'Created', key: 'created_at' },
          ]} rows={payload?.treasury?.settlement_events || []} />
        </div>
      </div>
      <h3>Liquidity readiness</h3>
      <OpsTable columns={[
        { label: 'Currency', key: 'code' },
        { label: 'Route', key: (row) => `${row.base_asset || '—'} / ${row.quote_asset || '—'}` },
        { label: 'Rate', key: 'rate_to_rub' },
        { label: 'Ready', key: 'execution_ready', render: (row) => <OpsStatusPill tone={row.execution_ready ? 'active' : 'warn'}>{row.execution_ready ? 'ready' : 'watch'}</OpsStatusPill> },
        { label: 'Capacity', key: 'max_executable_size' },
      ]} rows={payload?.liquidity?.currencies || []} />
      <div className="ops-grid-two">
        <OpsTable columns={[
          { label: 'Method', key: 'name' },
          { label: 'Type', key: 'type' },
          { label: 'Currencies', key: 'currencies_count' },
          { label: 'Active', key: 'is_active' },
        ]} rows={payload?.liquidity?.methods || []} />
        <OpsTable columns={[
          { label: 'Intent', key: 'intent_key' },
          { label: 'Corridor', key: 'corridor_key' },
          { label: 'Score', key: 'route_score' },
          { label: 'Friction', key: 'friction_score' },
        ]} rows={payload?.liquidity?.intent_corridors || []} />
      </div>
      <h3>Partner balance liquidity</h3>
      <OpsTable columns={[
        { label: 'Partner', key: 'partner' },
        { label: 'Currency', key: 'currency' },
        { label: 'Available', key: 'available_balance' },
        { label: 'Reserved', key: 'reserved_balance' },
        { label: 'API holds', key: 'api_active_reservations' },
      ]} rows={payload?.liquidity?.data || []} />
    </OpsPanel>
  );
}

function ChannelsModule() {
  const { payload, error, isLoading, refresh } = useOpsResource(fetchOpsChannels, []);
  return (
    <OpsPanel actions={<ActionButton onClick={refresh}>Refresh channels</ActionButton>} description="Sales-channel matrix and shop channel health." eyebrow="Channels" title="Sales channel plane">
      <OpsMetrics metrics={[
        { label: 'Configured', value: payload?.summary?.configured_channels ?? 0, detail: 'channels' },
        { label: 'Implemented', value: payload?.summary?.implemented_channels ?? 0, detail: 'runtime adapters' },
        { label: 'Enabled links', value: payload?.summary?.enabled_product_links ?? 0, detail: 'product links' },
        { label: 'Errors', value: payload?.summary?.channel_errors ?? 0, detail: 'channel failures' },
      ]} />
      <OpsState error={error} isLoading={isLoading} />
      <div className="ops-grid-two">
        <div>
          <h3>Channel matrix</h3>
          <OpsTable columns={[
            { label: 'Channel', key: 'label' },
            { label: 'Group', key: 'group' },
            { label: 'Links', key: (row) => `${row.enabled_links}/${row.product_links}` },
            { label: 'Errors', key: 'errors' },
          ]} rows={payload?.channels || []} />
        </div>
        <div>
          <h3>Shop channel health</h3>
          <OpsTable columns={[
            { label: 'Shop', key: 'name' },
            { label: 'Partner', key: 'partner' },
            { label: 'Meanly', key: 'meanly_storefront' },
            { label: 'Yandex', key: (row) => `${row.yandex_configured ? 'configured' : 'missing'} / ${row.yandex_verified ? 'verified' : 'unverified'}` },
          ]} rows={payload?.shops || []} />
        </div>
      </div>
    </OpsPanel>
  );
}

function PagedTableModule({ title, eyebrow, description, fetcher, columns, searchPlaceholder, filters = [] }) {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const { payload, error, isLoading, refresh } = useOpsResource(
    () => fetcher({ search, status, page }),
    [status, page],
  );
  const rows = payload?.data || [];

  return (
    <OpsPanel badge={<OpsStatusPill>{payload?.total ?? 0} rows</OpsStatusPill>} description={description} eyebrow={eyebrow} title={title}>
      <OpsToolbar onSubmit={() => { setPage(1); refresh(); }} placeholder={searchPlaceholder} search={search} setSearch={setSearch}>
        {filters.map((filter) => (
          <button className={status === filter.value ? 'is-active' : ''} key={filter.label} onClick={() => { setPage(1); setStatus(filter.value); }} type="button">
            {filter.label}
          </button>
        ))}
      </OpsToolbar>
      <OpsState empty={!rows.length && !isLoading} error={error} isLoading={isLoading} />
      <OpsTable columns={columns} rows={rows} />
      <OpsPagination isLoading={isLoading} lastPage={payload?.last_page || 1} onPage={setPage} page={page} />
    </OpsPanel>
  );
}

function OrdersOperationsModule() {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const orders = useOpsResource(() => fetchOpsOrders({ search, status, page }), [status, page]);
  const operations = useOpsResource(() => fetchOpsOperations({ search }), []);

  async function runSearch() {
    setPage(1);
    await orders.refresh();
    await operations.refresh();
  }

  return (
    <OpsPanel description="Global orders plus merged Meanly API, ledger, and marketplace fulfillment history." eyebrow="Orders" title="Orders and unified operations feed">
      <OpsToolbar onSubmit={runSearch} placeholder="Search order, SKU, provider reference..." search={search} setSearch={setSearch}>
        {[
          ['All', ''],
          ['Active', 'active'],
          ['Completed', 'completed'],
          ['Cancelled', 'cancelled'],
          ['Sandbox', 'sandbox'],
        ].map(([label, value]) => (
          <button className={status === value ? 'is-active' : ''} key={label} onClick={() => { setPage(1); setStatus(value); }} type="button">{label}</button>
        ))}
      </OpsToolbar>
      <OpsState error={orders.error || operations.error} isLoading={orders.isLoading || operations.isLoading} />
      <h3>Orders</h3>
      <OpsTable columns={[
        { label: 'Order', key: 'order_id' },
        { label: 'Shop', key: 'shop_name' },
        { label: 'Partner', key: 'partner_name' },
        { label: 'SKU', key: 'sku' },
        { label: 'Status', key: 'status_text', render: (row) => <OpsStatusPill tone={statusTone(row.status_text)}>{row.status_text}</OpsStatusPill> },
      ]} rows={orders.payload?.data || []} />
      <OpsPagination isLoading={orders.isLoading} lastPage={orders.payload?.last_page || 1} onPage={setPage} page={page} />
      <h3>Unified operations feed</h3>
      <OpsTable columns={[
        { label: 'Source', key: 'source', render: (row) => <OpsStatusPill tone={statusTone(row.source)}>{row.source}</OpsStatusPill> },
        { label: 'Type', key: 'type' },
        { label: 'Reference', key: 'reference' },
        { label: 'Partner', key: 'partner' },
        { label: 'Provider', key: 'provider' },
        { label: 'SKU', key: 'sku' },
        { label: 'Status', key: 'status' },
      ]} rows={operations.payload?.data || []} />
    </OpsPanel>
  );
}

function InventoryModule() {
  const { payload, error, isLoading, refresh } = useOpsResource(fetchOpsInventory, []);
  const [output, setOutput] = useState('');
  const [isSyncing, setIsSyncing] = useState(false);

  async function syncWarehouses() {
    setIsSyncing(true);
    setOutput('Syncing marketplace warehouses from master warehouse...');
    try {
      const result = await syncOpsInventoryWarehouses();
      setOutput(result);
      await refresh();
    } catch (exception) {
      setOutput(exception.message || 'Warehouse sync failed.');
    } finally {
      setIsSyncing(false);
    }
  }

  return (
    <OpsPanel
      actions={(
        <>
          <ActionButton disabled={isSyncing} onClick={syncWarehouses}>Sync marketplace warehouses</ActionButton>
          <ActionButton disabled={isSyncing} onClick={refresh} tone="secondary">Refresh inventory</ActionButton>
        </>
      )}
      description="Warehouse readiness, marketplace projections, low-stock rows, and voucher inventory."
      eyebrow="Inventory"
      title="Warehouses and vouchers"
    >
      <OpsMetrics metrics={[
        { label: 'Warehouses', value: payload?.summary?.warehouses ?? 0, detail: 'total locations' },
        { label: 'Active', value: payload?.summary?.active_warehouses ?? 0, detail: 'active warehouses' },
        { label: 'Low stock', value: payload?.summary?.low_stock_rows ?? 0, detail: 'rows under threshold' },
        { label: 'Vouchers', value: payload?.summary?.available_vouchers ?? 0, detail: 'available codes' },
      ]} />
      <OpsState error={error} isLoading={isLoading} />
      <h3>Warehouses</h3>
      <OpsTable columns={[
        { label: 'Warehouse', key: 'name' },
        { label: 'Shop', key: 'shop' },
        { label: 'Partner', key: 'partner' },
        { label: 'Stock rows', key: 'stock_rows' },
        { label: 'Active', key: 'is_active' },
      ]} rows={payload?.warehouses || []} />
      <h3>Low-first stock</h3>
      <OpsTable columns={[
        { label: 'Product', key: 'product' },
        { label: 'SKU', key: 'sku' },
        { label: 'Warehouse', key: 'warehouse' },
        { label: 'Count', key: 'count' },
        { label: 'Synced', key: 'synced_at' },
      ]} rows={payload?.stock || []} />
      <h3>Voucher registry</h3>
      <OpsTable columns={[
        { label: 'Reference', key: 'transaction_ref' },
        { label: 'SKU', key: 'sku' },
        { label: 'Shop', key: 'shop' },
        { label: 'Status', key: 'status', render: (row) => <OpsStatusPill tone={statusTone(row.status)}>{row.status}</OpsStatusPill> },
        { label: 'Nominal', key: (row) => `${row.nominal} ${row.currency || ''}` },
      ]} rows={payload?.vouchers || []} />
      <OpsOutput output={output} />
    </OpsPanel>
  );
}

function ProvidersModule() {
  const { payload, setPayload, error, isLoading, refresh } = useOpsResource(fetchOpsProviders, []);
  const [output, setOutput] = useState('');
  const [busyId, setBusyId] = useState(null);
  const providers = payload?.data || [];

  async function syncProvider(provider, mode) {
    if (!provider.sync_url) return;
    if (mode === 'pull-upstream' && !window.confirm('Pull EZPin will refresh Meanly.one supply from the upstream vendor. Continue?')) return;
    setBusyId(provider.id);
    setOutput(`Running ${mode} sync for ${provider.name}...`);
    try {
      const result = await syncOpsProvider(provider.sync_url, mode);
      setPayload((current) => ({
        ...(current || {}),
        data: (current?.data || providers).map((item) => (item.id === provider.id ? result.provider || item : item)),
      }));
      setOutput(result.output || result.message || `Provider sync finished with exit code ${result.exit_code ?? 0}.`);
      await refresh();
    } catch (exception) {
      setOutput(exception.message || 'Provider sync failed.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <OpsPanel
      actions={<ActionButton onClick={refresh}>Refresh providers</ActionButton>}
      badge={<OpsStatusPill tone="active">{payload?.kernel?.authority || 'meanly.one'}</OpsStatusPill>}
      description="Meanly.one provider authority, EZPin supply synchronization, credentials, terminals, and support plane."
      eyebrow="Providers"
      title="Provider runtime"
    >
      <OpsMetrics metrics={[
        { label: 'Providers', value: providers.length, detail: payload?.kernel?.authority || 'meanly.one' },
        { label: 'Terminals', value: `${payload?.kernel?.support_planes?.devices?.terminals_active || 0}/${payload?.kernel?.support_planes?.devices?.terminals_total || 0}`, detail: 'active / total' },
        { label: 'Docs', value: Object.keys(payload?.kernel?.support_planes?.docs || {}).length, detail: 'support endpoints' },
        { label: 'Upstream', value: payload?.kernel?.upstream || 'ezpin', detail: payload?.kernel?.ezpin_env_configured ? 'env ready' : 'provider credentials' },
      ]} />
      <OpsState error={error} isLoading={isLoading} />
      <OpsTable columns={[
        { label: 'Provider', key: 'name', render: (row) => <strong>{row.name}<br /><small>{row.is_legacy_alias ? 'legacy alias -> ' : ''}{row.upstream_provider || row.type}</small></strong> },
        { label: 'Catalog', key: (row) => `${row.active_provider_products_count}/${row.provider_products_count}` },
        { label: 'Credentials', key: (row) => Object.entries(row.credentials || {}).filter(([, ok]) => ok).map(([key]) => key).join(', ') || 'missing' },
        { label: 'Terminal', key: (row) => row.terminal?.id_masked || 'not configured' },
        { label: 'Health', key: (row) => row.sync_status, render: (row) => <OpsStatusPill tone={statusTone(row.sync_status)}>{row.sync_status || 'idle'}</OpsStatusPill> },
      ]} rows={providers} actions={(row) => (
        <div className="ops-row-actions">
          <ActionButton disabled={busyId === row.id} onClick={() => syncProvider(row, 'embedded')} tone="secondary">Embedded sync</ActionButton>
          <ActionButton disabled={busyId === row.id || !row.health?.supports_upstream_pull} onClick={() => syncProvider(row, 'pull-upstream')}>Pull EZPin</ActionButton>
        </div>
      )} />
      <OpsOutput output={output} />
    </OpsPanel>
  );
}

function DecisionsModule() {
  const { payload, error, isLoading, refresh } = useOpsResource(fetchOpsGrowth, []);
  const [output, setOutput] = useState('');

  async function decide(row, decision) {
    if (!window.confirm(`${decision} recommendation #${row.id}?`)) return;
    try {
      await decideOpsRecommendation(row.id, decision);
      setOutput(`Recommendation #${row.id} ${decision} requested.`);
      await refresh();
    } catch (exception) {
      setOutput(exception.message);
    }
  }

  return (
    <OpsPanel actions={<ActionButton onClick={refresh}>Refresh graph</ActionButton>} description="Demand graph, opportunity cases, recommendations, and operational alerts." eyebrow="Decision Console" title="Growth authority">
      <OpsMetrics metrics={[
        { label: 'Demand gaps', value: payload?.summary?.demand_gaps ?? 0, detail: 'catalog gaps' },
        { label: 'Lost GMV', value: payload?.summary?.lost_gmv ?? 0, detail: 'estimated' },
        { label: 'Open cases', value: payload?.summary?.open_cases ?? 0, detail: `${payload?.summary?.overdue_cases ?? 0} overdue` },
        { label: 'Alerts', value: payload?.summary?.open_alerts ?? 0, detail: 'open ops alerts' },
      ]} />
      <OpsState error={error} isLoading={isLoading} />
      <div className="ops-grid-two">
        <div>
          <h3>Demand gaps</h3>
          <OpsTable columns={[
            { label: 'Query', key: 'query' },
            { label: 'Brand', key: 'brand' },
            { label: 'Region', key: 'region' },
            { label: 'Score', key: 'score' },
          ]} rows={payload?.demand_gaps || []} />
        </div>
        <div>
          <h3>Operational alerts</h3>
          <OpsTable columns={[
            { label: 'Title', key: 'title' },
            { label: 'Severity', key: 'severity', render: (row) => <OpsStatusPill tone={statusTone(row.severity)}>{row.severity}</OpsStatusPill> },
            { label: 'Surface', key: 'surface' },
            { label: 'Count', key: 'occurrence_count' },
          ]} rows={payload?.alerts || []} />
        </div>
      </div>
      <h3>Recommendations</h3>
      <OpsTable columns={[
        { label: 'Query', key: 'query' },
        { label: 'Insight', key: 'insight_type' },
        { label: 'Impact', key: 'impact_score' },
        { label: 'Confidence', key: 'confidence' },
        { label: 'Status', key: 'status', render: (row) => <OpsStatusPill tone={statusTone(row.status)}>{row.status}</OpsStatusPill> },
      ]} rows={payload?.recommendations || []} actions={(row) => (
        <div className="ops-row-actions">
          <ActionButton disabled={row.status !== 'proposed'} onClick={() => decide(row, 'approve')}>Approve</ActionButton>
          <ActionButton disabled={row.status !== 'proposed'} onClick={() => decide(row, 'reject')} tone="secondary">Reject</ActionButton>
        </div>
      )} />
      <h3>Opportunity cases</h3>
      <OpsTable columns={[
        { label: 'Query', key: 'query' },
        { label: 'Owner', key: 'owner_team' },
        { label: 'Status', key: 'status' },
        { label: 'Overdue', key: 'overdue' },
      ]} rows={payload?.opportunity_cases || []} />
      <OpsOutput output={output} />
    </OpsPanel>
  );
}

function SearchIntegrationsModule() {
  const { payload, error, isLoading, refresh } = useOpsResource(fetchOpsSearchIntegrations, []);
  const [output, setOutput] = useState('');

  async function action(url, body, label) {
    if (!url) return;
    setOutput(`Running ${label}...`);
    try {
      const result = await runOpsSearchSignalAction(url, body);
      setOutput(result.payload || result.output || result);
      await refresh();
    } catch (exception) {
      setOutput(exception.message);
    }
  }

  async function connectSource() {
    const connectors = Object.keys(payload?.connectors || {});
    const source = window.prompt(`Source (${connectors.join(', ')}):`, connectors[0] || 'google_analytics');
    if (!source) return;
    const name = window.prompt('Integration name:', payload?.connectors?.[source]?.label || source);
    if (!name) return;
    try {
      const result = await connectOpsZeroLayer({ name, source, status: 'active', credentials: {}, settings: {} });
      setOutput(result);
      await refresh();
    } catch (exception) {
      setOutput(exception.message);
    }
  }

  async function syncIntegration(row) {
    if (!row.sync_url) return;
    try {
      setOutput(await syncOpsZeroLayer(row.sync_url));
      await refresh();
    } catch (exception) {
      setOutput(exception.message);
    }
  }

  const actions = payload?.actions || {};
  return (
    <OpsPanel actions={<ActionButton onClick={refresh}>Refresh search</ActionButton>} description="External search sources, Zero Layer signals, and recommendation pipelines." eyebrow="Search Integrations" title="Zero Layer demand graph">
      <OpsMetrics metrics={[
        { label: 'Integrations', value: payload?.summary?.zero_layer_integrations ?? 0, detail: `${payload?.summary?.active_zero_layer_integrations ?? 0} active` },
        { label: 'Zero signals', value: payload?.summary?.zero_layer_signals ?? 0, detail: 'raw signals' },
        { label: 'External', value: payload?.summary?.external_search_signals ?? 0, detail: 'persisted demand' },
        { label: 'Recommendations', value: payload?.summary?.recommendations_proposed ?? 0, detail: 'proposed' },
      ]} />
      <OpsToolbar>
        <ActionButton onClick={connectSource}>Connect Source</ActionButton>
        <ActionButton onClick={() => action(actions.promote_zero_layer_url, { limit: 250 }, 'promote zero layer')}>Promote ZeroLayer</ActionButton>
        <ActionButton onClick={() => action(actions.analyze_url, { limit: 25, days: 90, source: 'all' }, 'analyze')}>Analyze</ActionButton>
        <ActionButton onClick={() => action(actions.recommend_url, { limit: 25, days: 90, source: 'all', min_score: 1 }, 'recommend')}>Recommend</ActionButton>
      </OpsToolbar>
      <OpsState error={error} isLoading={isLoading} />
      <h3>Integrations</h3>
      <OpsTable columns={[
        { label: 'Name', key: 'name' },
        { label: 'Source', key: 'source' },
        { label: 'Status', key: 'status' },
        { label: 'Credentials', key: (row) => row.credential_keys?.join(', ') },
        { label: 'Signals', key: 'signals_count' },
      ]} rows={payload?.integrations || []} actions={(row) => <ActionButton onClick={() => syncIntegration(row)} tone="secondary">Sync</ActionButton>} />
      <div className="ops-grid-two">
        <div>
          <h3>Zero Layer signals</h3>
          <OpsTable columns={[
            { label: 'Source', key: 'source' },
            { label: 'Query', key: 'query_text' },
            { label: 'Position', key: 'position' },
            { label: 'Clicks', key: 'clicks' },
          ]} rows={payload?.zero_layer_signals || []} />
        </div>
        <div>
          <h3>External demand</h3>
          <OpsTable columns={[
            { label: 'Source', key: 'source' },
            { label: 'Query', key: 'query' },
            { label: 'Country', key: 'country' },
            { label: 'Volume', key: 'volume' },
          ]} rows={payload?.external_signals || []} />
        </div>
      </div>
      <OpsOutput output={output} />
    </OpsPanel>
  );
}

function SupportModule() {
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState(null);
  const [replyMessage, setReplyMessage] = useState('');
  const [output, setOutput] = useState('');
  const [openingId, setOpeningId] = useState(null);
  const [isReplying, setIsReplying] = useState(false);
  const { payload, error, isLoading, refresh } = useOpsResource(() => fetchOpsTickets({ search, page }), [page]);
  const tickets = payload?.data || [];

  async function openTicket(row) {
    setOpeningId(row.id);
    try {
      const details = await fetchOpsTicketDetails(row.id);
      setSelected(details);
      setReplyMessage('');
      setOutput('');
    } catch (exception) {
      setOutput(exception.message || 'Could not open ticket.');
    } finally {
      setOpeningId(null);
    }
  }

  async function reply() {
    if (!selected?.ticket?.id) return;
    const message = replyMessage.trim();
    if (!message) {
      setOutput('Reply message is required.');
      return;
    }
    setIsReplying(true);
    try {
      const result = await replyOpsTicket(selected.ticket.id, message);
      setOutput(result.message || 'Reply sent.');
      setReplyMessage('');
      setSelected(await fetchOpsTicketDetails(selected.ticket.id));
      await refresh();
    } catch (exception) {
      setOutput(exception.message || 'Reply failed.');
    } finally {
      setIsReplying(false);
    }
  }

  return (
    <OpsPanel description="Support ticket queue, ticket details, and admin reply flow." eyebrow="Support" title="Incident queue">
      <OpsToolbar onSubmit={() => { setPage(1); refresh(); }} placeholder="Search tickets or shops" search={search} setSearch={setSearch} />
      <OpsState error={error} isLoading={isLoading} />
      <OpsTable columns={[
        { label: 'Subject', key: 'subject' },
        { label: 'Shop', key: 'shop_name' },
        { label: 'Partner', key: 'partner_name' },
        { label: 'Status', key: 'status', render: (row) => <OpsStatusPill tone={statusTone(row.status)}>{row.status}</OpsStatusPill> },
        { label: 'Created', key: 'created_at' },
      ]} rows={tickets} actions={(row) => <ActionButton disabled={openingId === row.id} onClick={() => openTicket(row)}>{openingId === row.id ? 'Opening...' : 'Open'}</ActionButton>} />
      <OpsPagination isLoading={isLoading} lastPage={payload?.last_page || 1} onPage={setPage} page={page} />
      {selected ? (
        <div className="ops-detail-card">
          <div className="ops-ticket-detail__header">
            <div>
              <h3>{selected.ticket.subject}</h3>
              <p>{selected.ticket.shop_name} / {selected.ticket.partner_name}</p>
            </div>
            <OpsStatusPill tone={statusTone(selected.ticket.status)}>{selected.ticket.status}</OpsStatusPill>
          </div>
          <OpsTable columns={[
            { label: 'Sender', key: 'sender', render: (row) => row.is_admin ? <OpsStatusPill tone="active">{row.sender}</OpsStatusPill> : row.sender },
            { label: 'Message', key: 'message' },
            { label: 'Created', key: 'created_at' },
          ]} rows={selected.messages || []} />
          <div className="ops-form-card ops-reply-composer">
            <h3>Admin reply</h3>
            <textarea
              onChange={(event) => setReplyMessage(event.target.value)}
              placeholder="Write the operator response. Sending will resolve this ticket."
              value={replyMessage}
            />
            <div className="ops-row-actions">
              <ActionButton disabled={!replyMessage.trim() || isReplying} onClick={reply}>{isReplying ? 'Sending...' : 'Reply and resolve'}</ActionButton>
              <ActionButton disabled={isReplying} onClick={() => { setSelected(null); setReplyMessage(''); }} tone="secondary">Close</ActionButton>
            </div>
          </div>
        </div>
      ) : null}
      <OpsOutput output={output} />
    </OpsPanel>
  );
}

function AuditModule() {
  const [message, setMessage] = useState('');
  const [reference, setReference] = useState('');
  const [output, setOutput] = useState('');
  const [isBusy, setIsBusy] = useState(false);

  async function run(label, loader) {
    setIsBusy(true);
    setOutput(`Running ${label}...`);
    try {
      setOutput(await loader());
    } catch (exception) {
      setOutput(exception.message);
    } finally {
      setIsBusy(false);
    }
  }

  return (
    <OpsPanel description="Global ledger audit, Ops AI chat, SL1 reference tracing, and tribunal validation." eyebrow="AI Audit" title="Ledger and SL1 audit">
      <OpsToolbar>
        <ActionButton disabled={isBusy} onClick={() => run('global audit', runOpsAiAudit)}>Global Ledger Audit</ActionButton>
        <ActionButton disabled={isBusy} onClick={() => run('chain validation', validateOpsTribunalChain)}>Validate Chain</ActionButton>
      </OpsToolbar>
      <div className="ops-grid-two">
        <div className="ops-form-card">
          <h3>Ops AI chat</h3>
          <textarea onChange={(event) => setMessage(event.target.value)} placeholder="Ask the Ops analyst..." value={message} />
          <ActionButton disabled={!message || isBusy} onClick={() => run('AI chat', () => sendOpsAiMessage(message))}>Send message</ActionButton>
        </div>
        <div className="ops-form-card">
          <h3>Simple Layer 1 trace</h3>
          <input onChange={(event) => setReference(event.target.value)} placeholder="Transaction/reference key" value={reference} />
          <ActionButton disabled={!reference || isBusy} onClick={() => run('SL1 trace', () => traceOpsSimpleLayer1(reference))}>Trace reference</ActionButton>
        </div>
      </div>
      <OpsOutput output={output} />
    </OpsPanel>
  );
}

export function OpsWorkspace() {
  const [activeModule, setActiveModule] = useState(moduleFromLocation);
  const selectedCopy = MODULE_COPY[activeModule] || MODULE_COPY.command;

  useEffect(() => {
    if (typeof window !== 'undefined') {
      window.localStorage.setItem('meanly:ops-module', activeModule);
      const url = new URL(window.location.href);
      url.searchParams.set('tab', activeModule);
      window.history.replaceState({}, '', url);
      document.querySelector('.ops-command-main')?.scrollTo({ top: 0 });
    }
  }, [activeModule]);

  function renderModule() {
    switch (activeModule) {
      case 'command':
        return <CommandCenterModule />;
      case 'organizations':
        return <OrganizationsModule />;
      case 'finance':
        return <FinanceLiquidityModule />;
      case 'channels':
        return <ChannelsModule />;
      case 'shops':
        return (
          <PagedTableModule
            columns={[
              { label: 'Shop', key: 'name' },
              { label: 'Partner', key: 'legal_entity_name' },
              { label: 'Active', key: 'is_active' },
              { label: 'Sandbox', key: 'is_sandbox' },
              { label: 'Created', key: 'created_at' },
            ]}
            description="Searchable registry of merchant storefronts and channel containers."
            eyebrow="Shops"
            fetcher={fetchOpsShops}
            searchPlaceholder="Search shops or partners"
            title="Storefront registry"
          />
        );
      case 'orders':
        return <OrdersOperationsModule />;
      case 'catalog':
        return (
          <PagedTableModule
            columns={[
              { label: 'Product', key: 'name' },
              { label: 'SKU', key: 'sku' },
              { label: 'Price', key: 'price_rub' },
              { label: 'Stock', key: 'stock' },
              { label: 'Shop', key: 'shop_name' },
              { label: 'Status', key: 'is_active', render: (row) => <OpsStatusPill tone={row.is_active ? 'active' : 'danger'}>{row.is_active ? 'active' : 'inactive'}</OpsStatusPill> },
            ]}
            description="Global marketplace product catalog and product health."
            eyebrow="Catalog"
            fetcher={fetchOpsCatalog}
            searchPlaceholder="Search products or SKU"
            title="Global catalog"
          />
        );
      case 'inventory':
        return <InventoryModule />;
      case 'providers':
        return <ProvidersModule />;
      case 'decisions':
        return <DecisionsModule />;
      case 'search':
        return <SearchIntegrationsModule />;
      case 'support':
        return <SupportModule />;
      case 'audit':
        return <AuditModule />;
      default:
        return <CommandCenterModule />;
    }
  }

  return (
    <main className="ops-command-shell">
      <aside className="ops-sidebar" aria-label="Meanly Ops modules">
        <div className="ops-sidebar__brand">
          <span className="ops-sidebar__dot" />
          <div>
            <strong>Meanly Ops</strong>
            <span>Sovereign Validator</span>
          </div>
        </div>
        <nav className="ops-sidebar__nav">
          {MODULE_GROUPS.map((group) => (
            <div className="ops-sidebar__group" key={group.label}>
              <span className="ops-sidebar__group-title">{group.label}</span>
              {group.modules.map(([key, title, subtitle]) => (
                <button className={key === activeModule ? 'is-active' : ''} key={key} onClick={() => setActiveModule(key)} type="button">
                  <span>{title}</span>
                  <small>{subtitle}</small>
                </button>
              ))}
            </div>
          ))}
        </nav>
        <div className="ops-sidebar__footer">
          <span>SL1 authority</span>
          <strong>ops.access</strong>
          <Link href="/">Return to storefront</Link>
        </div>
      </aside>

      <section className="ops-command-main">
        <section className="ops-hero">
          <div>
            <p className="eyebrow">Meanly Operations Command Center</p>
            <h1>{selectedCopy[0]}</h1>
            <p>{selectedCopy[1]}</p>
          </div>
          <div className="ops-hero__stats">
            <strong>SL1</strong>
            <span>authority plane</span>
          </div>
        </section>
        {renderModule()}
      </section>
    </main>
  );
}
