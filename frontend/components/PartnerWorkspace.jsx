'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { fetchPartnerModule, fetchPartnerWorkspace, postPartnerAction } from '../lib/partner-api';
import { partnerConnectUrl } from '../lib/storefront-api';
import { BusinessOnboardingStatus } from './BusinessOnboardingStatus';
import { MeanlyConnectLink } from './MeanlyConnectLink';

const MODULES = [
  { key: 'overview', path: '', title: 'Overview', endpoint: null },
  { key: 'sales_channels', path: 'channels', title: 'Sales Channels', endpoint: null },
  { key: 'orders', path: 'orders', title: 'Orders', endpoint: 'orders' },
  { key: 'catalog', path: 'catalog', title: 'Catalog', endpoint: 'catalog' },
  { key: 'provider_storefront', path: 'storefront', title: 'Supply', endpoint: 'provider_storefront' },
  { key: 'warehouses', path: 'warehouses', title: 'Stock', endpoint: 'warehouses' },
  { key: 'activations', path: 'activations', title: 'Activations', endpoint: 'activations' },
  { key: 'vouchers', path: 'vouchers', title: 'Vouchers', endpoint: 'vouchers' },
  { key: 'finance', path: 'finance', title: 'Finance', endpoint: 'finance' },
  { key: 'support', path: 'support', title: 'Support', endpoint: 'tickets' },
  { key: 'settings', path: 'settings', title: 'Settings', endpoint: 'shops' },
];

const MODULE_BY_PATH = Object.fromEntries(MODULES.map((item) => [item.path, item]));
const LEGACY_PATH_MAP = {
  'dashboard': '',
  'dashboard/orders': 'orders',
  'dashboard/catalog': 'catalog',
  'dashboard/tickets': 'support',
  'dashboard/finance': 'finance',
  'dashboard/providers': 'storefront',
  'legacy': '',
};
const workspaceCacheKey = 'meanly:partner-workspace-cache';

function normalizeWorkspacePath(path = '') {
  const normalized = path.replace(/^\/+|\/+$/g, '');
  return LEGACY_PATH_MAP[normalized] ?? normalized;
}

function cachePartnerWorkspace(workspace) {
  try {
    window.sessionStorage.setItem(workspaceCacheKey, JSON.stringify(workspace));
  } catch {
    // Workspace cache is a UX optimization only.
  }
}

function formatCurrency(value) {
  const amount = Number(value || 0);

  return `${amount.toLocaleString('ru-RU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} ₽`;
}

function enabledCapabilities(capabilities = {}) {
  return Object.entries(capabilities)
    .filter(([, value]) => value === true)
    .map(([key]) => key);
}

function valuePreview(value) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }
  if (typeof value === 'boolean') {
    return value ? 'yes' : 'no';
  }
  if (Array.isArray(value)) {
    return `${value.length} items`;
  }
  if (typeof value === 'object') {
    return 'Details available';
  }

  return String(value);
}

function shouldShowOnboardingForEntity(entity) {
  return entity?.status === 'pending_moderation';
}

function firstCollection(payload = {}) {
  const preferred = [
    'orders',
    'products',
    'shops',
    'tickets',
    'warehouses',
    'activations',
    'vouchers',
    'transactions',
    'items',
  ];

  for (const key of preferred) {
    if (Array.isArray(payload[key])) {
      return { key, rows: payload[key] };
    }
  }

  const discovered = Object.entries(payload).find(([, value]) => Array.isArray(value));

  return discovered ? { key: discovered[0], rows: discovered[1] } : { key: 'rows', rows: [] };
}

function StatusPill({ children, tone = 'neutral' }) {
  return <span className={`seller-pill seller-pill--${tone}`}>{children}</span>;
}

function SummaryCard({ title, value, caption }) {
  return (
    <article className="seller-summary-card">
      <span>{title}</span>
      <strong>{value}</strong>
      {caption ? <p>{caption}</p> : null}
    </article>
  );
}

function AuthorityPanel({ workspace }) {
  return (
    <section className="seller-authority-panel">
      <div>
        <p className="eyebrow">Workspace permissions</p>
        <h2>Your enabled seller tools.</h2>
        <p>
          Meanly checks access, channel readiness, publishing, orders, and
          finance before protected actions continue.
        </p>
      </div>
      <div className="seller-authority-grid">
        {(workspace?.authority_invariant?.laravel_owns || []).map((item) => (
          <StatusPill key={item} tone="active">{item}</StatusPill>
        ))}
      </div>
    </section>
  );
}

function OverviewModule({ workspace }) {
  return (
    <div className="seller-module-stack">
      <section className="seller-summary-grid">
        <SummaryCard title="Orders in work" value={workspace.orders_summary?.active || 0} caption="Protected orders" />
        <SummaryCard title="30 day revenue" value={formatCurrency(workspace.orders_summary?.revenue_30_days)} caption="Completed orders only" />
        <SummaryCard title="Products" value={workspace.catalog_summary?.products || 0} caption={`${workspace.catalog_summary?.active_products || 0} active`} />
        <SummaryCard title="Balance" value={formatCurrency(workspace.finance_summary?.available)} caption="Available funds" />
      </section>

      <section className="seller-grid-two">
        <div className="panel">
          <div className="section-heading">
            <h2>Legal Entity</h2>
            <p>Identity is not recreated. Authority is granted to this entity.</p>
          </div>
          <div className="seller-kv">
            <span>Name</span><strong>{workspace.legal_entity?.short_name || workspace.legal_entity?.name}</strong>
            <span>INN</span><strong>{workspace.legal_entity?.inn || 'Required for marketplaces'}</strong>
            <span>Status</span><strong>{workspace.legal_entity?.status || 'unknown'}</strong>
          </div>
        </div>

        <div className="panel">
          <div className="section-heading">
            <h2>Alerts</h2>
            <p>Important workspace updates.</p>
          </div>
          {(workspace.alerts || []).length ? (
            <div className="seller-alert-list">
              {workspace.alerts.map((alert) => (
                <article key={`${alert.type}-${alert.title}`} className="seller-alert">
                  <StatusPill tone={alert.severity === 'high' ? 'danger' : 'warn'}>{alert.severity}</StatusPill>
                  <strong>{alert.title}</strong>
                  <p>{alert.description}</p>
                </article>
              ))}
            </div>
          ) : (
            <p className="product-card__muted">No blocking alerts.</p>
          )}
        </div>
      </section>

      <AuthorityPanel workspace={workspace} />
    </div>
  );
}

function SalesChannelsModule({ workspace }) {
  return (
    <section className="seller-module-stack">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Capability bounded context</p>
          <h2>Sales Channels</h2>
        </div>
        <span className="seller-secondary-link">React workspace</span>
      </div>

      <div className="grid">
        {(workspace.sales_channels || []).map((channel) => (
          <article className="product-card seller-channel-card" key={channel.type}>
            <div className="product-card__meta">
              <span>{channel.group || 'channel'}</span>
              <StatusPill tone={channel.ready ? 'active' : channel.implemented ? 'warn' : 'neutral'}>
                {channel.ready ? 'ready' : channel.implemented ? 'setup needed' : 'planned'}
              </StatusPill>
            </div>
            <h3>{channel.icon ? `${channel.icon} ` : ''}{channel.label}</h3>
            <p className="product-card__muted">{channel.next_action}</p>
            <div className="seller-channel-shops">
              {(channel.shops || []).slice(0, 4).map((shop) => (
                <div key={`${channel.type}-${shop.shop_id || shop.id}`} className="seller-channel-shop">
                  <strong>{shop.shop_name || shop.label || 'Shop'}</strong>
                  <span>{shop.state_label || shop.state || (shop.ready ? 'ready' : 'not ready')}</span>
                </div>
              ))}
            </div>
            {(channel.issues || []).length ? (
              <div className="seller-alert seller-alert--compact">
                <strong>{channel.issues.length} issue(s)</strong>
                <p>{channel.issues[0]?.message || channel.issues[0]?.code}</p>
              </div>
            ) : null}
          </article>
        ))}
      </div>
    </section>
  );
}

function GenericRows({ payload, module }) {
  const { key, rows } = firstCollection(payload);
  const columns = rows.length
    ? Object.keys(rows[0]).filter((column) => !['id'].includes(column)).slice(0, 6)
    : [];

  return (
    <section className="panel seller-data-panel">
      <div className="section-heading">
        <div>
          <p className="eyebrow">{key}</p>
          <h2>{module.title}</h2>
        </div>
        <StatusPill>{payload.total ?? rows.length} total</StatusPill>
      </div>

      {rows.length ? (
        <div className="seller-table-wrap">
          <table className="seller-table">
            <thead>
              <tr>
                {columns.map((column) => <th key={column}>{column.replaceAll('_', ' ')}</th>)}
              </tr>
            </thead>
            <tbody>
              {rows.slice(0, 12).map((row, index) => (
                <tr key={row.id || row.transaction_ref || row.sku || index}>
                  {columns.map((column) => <td key={`${row.id || index}-${column}`}>{valuePreview(row[column])}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="checkout-note">No records yet.</p>
      )}
    </section>
  );
}

function SettingsModule({ workspace, payload }) {
  return (
    <section className="seller-grid-two">
      <div className="panel">
        <div className="section-heading">
          <h2>Shops</h2>
          <p>Shops and distribution centers are created from protected seller actions.</p>
        </div>
        <div className="seller-card-list">
          {(workspace.shops || []).map((shop) => (
            <article className="seller-mini-card" key={shop.id}>
              <strong>{shop.name}</strong>
              <span>{shop.domain || 'No domain'} · {shop.region}</span>
              <StatusPill tone={shop.is_active ? 'active' : 'neutral'}>{shop.is_active ? 'active' : 'paused'}</StatusPill>
            </article>
          ))}
        </div>
      </div>
      <GenericRows payload={payload || { shops: workspace.shops || [] }} module={{ title: 'API Apps and Shops' }} />
    </section>
  );
}

function ModuleDetail({ activeModule, workspace, payload, loading, error, onAction, actionState }) {
  if (activeModule.key === 'overview') {
    return <OverviewModule workspace={workspace} />;
  }
  if (activeModule.key === 'sales_channels') {
    return <SalesChannelsModule workspace={workspace} />;
  }
  if (activeModule.key === 'settings') {
    return <SettingsModule workspace={workspace} payload={payload} />;
  }

  return (
    <div className="seller-module-stack">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Workspace data</p>
          <h2>{activeModule.title}</h2>
        </div>
        <div className="product-card__actions">
          {activeModule.key === 'orders' ? (
            <button type="button" onClick={() => onAction('orders_sync')}>
              {actionState === 'orders_sync' ? 'Syncing...' : 'Sync orders'}
            </button>
          ) : null}
        </div>
      </div>
      {error ? <p className="product-card__reason">{error}</p> : null}
      {loading ? <p className="checkout-note">Loading {activeModule.title.toLowerCase()}...</p> : null}
      {!loading && payload ? <GenericRows payload={payload} module={activeModule} /> : null}
    </div>
  );
}

function AuthRequired({ error }) {
  const connectUrl = partnerConnectUrl();

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Seller workspace</p>
        <h1>Continue with Meanly</h1>
        <p>{error || 'Meanly needs to verify seller access before opening the workspace.'}</p>
        <div className="product-card__actions">
          <MeanlyConnectLink href={connectUrl}>Open seller workspace</MeanlyConnectLink>
          <Link href="/">Browse marketplace</Link>
        </div>
      </section>
    </main>
  );
}

export function PartnerWorkspace({ initialPath = '', initialWorkspace = null }) {
  const normalizedPath = normalizeWorkspacePath(initialPath);
  const initialModule = MODULE_BY_PATH[normalizedPath] || MODULES[0];
  const [workspace, setWorkspace] = useState(initialWorkspace);
  const [workspaceError, setWorkspaceError] = useState('');
  const [activeKey, setActiveKey] = useState(initialModule.key);
  const [modulePayloads, setModulePayloads] = useState({});
  const [moduleLoading, setModuleLoading] = useState(false);
  const [moduleError, setModuleError] = useState('');
  const [actionState, setActionState] = useState('');

  const activeModule = useMemo(
    () => MODULES.find((item) => item.key === activeKey) || MODULES[0],
    [activeKey],
  );

  function activateModule(item) {
    setActiveKey(item.key);
    const href = item.path ? `/partner/${item.path}` : '/partner';
    window.history.pushState({}, '', href);
  }

  useEffect(() => {
    if (initialWorkspace) {
      cachePartnerWorkspace(initialWorkspace);
      return undefined;
    }

    let cancelled = false;
    fetchPartnerWorkspace()
      .then((payload) => {
        if (!cancelled) {
          cachePartnerWorkspace(payload);
          setWorkspace(payload);
          setWorkspaceError('');
        }
      })
      .catch((error) => {
        if (!cancelled) {
          setWorkspaceError(error.message || 'Could not load seller workspace.');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [initialWorkspace]);

  useEffect(() => {
    if (
      !workspace
      || shouldShowOnboardingForEntity(workspace.legal_entity)
      || !activeModule.endpoint
      || modulePayloads[activeModule.key]
    ) {
      return;
    }

    const endpoint = workspace.module_endpoints?.[activeModule.endpoint];
    if (!endpoint) {
      return;
    }

    let cancelled = false;
    setModuleLoading(true);
    setModuleError('');
    fetchPartnerModule(endpoint)
      .then((payload) => {
        if (!cancelled) {
          setModulePayloads((current) => ({ ...current, [activeModule.key]: payload }));
        }
      })
      .catch((error) => {
        if (!cancelled) {
          setModuleError(error.message || `Could not load ${activeModule.title}.`);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setModuleLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [activeModule, modulePayloads, workspace]);

  async function refreshWorkspace() {
    const payload = await fetchPartnerWorkspace();
    cachePartnerWorkspace(payload);
    setWorkspace(payload);
  }

  async function handleAction(actionKey) {
    const endpoint = workspace?.actions?.[actionKey];
    if (!endpoint) {
      return;
    }

    setActionState(actionKey);
    try {
      await postPartnerAction(endpoint);
      setModulePayloads((current) => ({ ...current, [activeModule.key]: undefined }));
      await refreshWorkspace();
    } catch (error) {
      setModuleError(error.message || 'Action failed.');
    } finally {
      setActionState('');
    }
  }

  if (workspaceError) {
    return <AuthRequired error={workspaceError} />;
  }

  if (!workspace) {
    return (
      <main className="page">
        <section className="hero">
          <p className="eyebrow">Seller workspace</p>
          <h1>Opening seller workspace...</h1>
          <p>Checking seller access. If this takes more than a moment, continue with Meanly.</p>
        </section>
      </main>
    );
  }

  if (shouldShowOnboardingForEntity(workspace.legal_entity)) {
    return (
      <main className="page">
        <BusinessOnboardingStatus />
      </main>
    );
  }

  const capabilities = enabledCapabilities(workspace.capabilities);

  return (
    <main className="seller-workspace">
      <aside className="seller-sidebar">
        <Link href="/partner" className="seller-sidebar__brand">
          <span className="brand__mark" />
          <strong>Seller Workspace</strong>
          <small>{workspace.legal_entity?.short_name || 'Meanly partner'}</small>
        </Link>
        <nav>
          {(workspace.navigation || []).map((item) => (
            <button
              key={item.key}
              type="button"
              disabled={!item.enabled}
              className={item.key === activeKey ? 'is-active' : ''}
              title={item.disabled_reason || ''}
              onClick={() => activateModule(MODULES.find((module) => module.key === item.key) || MODULES[0])}
            >
              {item.label}
            </button>
          ))}
        </nav>
        <div className="seller-sidebar__footer">
          <span>Capabilities: {capabilities.length}</span>
          <span>React frontend</span>
        </div>
      </aside>

      <section className="seller-main">
        <section className="seller-hero">
          <div>
            <p className="eyebrow">Seller workspace</p>
            <h1>{activeModule.title}</h1>
            <p>
              Manage seller operations, channels, orders, catalog, finance,
              and support from one workspace.
            </p>
          </div>
          <div className="seller-hero__stats">
            <SummaryCard title="Shops" value={(workspace.shops || []).length} />
            <SummaryCard title="Channels ready" value={(workspace.sales_channels || []).filter((item) => item.ready).length} />
          </div>
        </section>

        <ModuleDetail
          activeModule={activeModule}
          workspace={workspace}
          payload={modulePayloads[activeModule.key]}
          loading={moduleLoading}
          error={moduleError}
          onAction={handleAction}
          actionState={actionState}
        />
      </section>
    </main>
  );
}
