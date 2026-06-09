'use client';

import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { fetchPartnerModule, fetchPartnerWorkspace, postPartnerAction } from '../lib/partner-api';
import { merchantConnectUrl } from '../lib/storefront-api';
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
const WORKSPACE_MODULE_ENDPOINTS = {
  orders: '/api/partner/workspace/orders',
  catalog: '/api/partner/workspace/catalog',
  provider_storefront: '/api/partner/workspace/supply',
  warehouses: '/api/partner/workspace/warehouses',
  activations: '/api/partner/workspace/activations',
  vouchers: '/api/partner/workspace/vouchers',
  finance: '/api/partner/workspace/finance',
  support: '/api/partner/workspace/tickets',
  settings: '/api/partner/workspace/shops',
};
const WORKSPACE_ACTION_ENDPOINTS = {
  orders_sync: '/api/partner/workspace/orders/sync',
  storefront_add_to_catalog: '/api/partner/workspace/storefront/add-to-catalog',
  storefront_buy_once: '/api/partner/workspace/storefront/buy-once',
  storefront_buy_options: '/api/partner/workspace/storefront/buy-options',
};

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

function base64UrlToBuffer(value = '') {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
  const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
  const binary = window.atob(padded);
  const bytes = new Uint8Array(binary.length);

  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return bytes.buffer;
}

function bufferToBase64Url(buffer) {
  const bytes = new Uint8Array(buffer || []);
  let binary = '';

  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });

  return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

async function requestPasskeyAssertion(options) {
  if (typeof window === 'undefined' || !window.PublicKeyCredential || !navigator.credentials?.get) {
    throw new Error('Signing is not available in this browser.');
  }

  const requestOptions = options.publicKey || options.public_key || options;
  const publicKey = {
    ...requestOptions,
    challenge: typeof requestOptions.challenge === 'string'
      ? base64UrlToBuffer(requestOptions.challenge)
      : requestOptions.challenge,
    allowCredentials: (requestOptions.allowCredentials || requestOptions.allow_credentials || []).map((credential) => ({
      ...credential,
      id: typeof credential.id === 'string' ? base64UrlToBuffer(credential.id) : credential.id,
    })),
  };
  const credential = await navigator.credentials.get({ publicKey });

  return {
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    response: {
      authenticatorData: bufferToBase64Url(credential.response.authenticatorData),
      clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
      signature: bufferToBase64Url(credential.response.signature),
      userHandle: credential.response.userHandle ? bufferToBase64Url(credential.response.userHandle) : null,
    },
  };
}

function shouldShowOnboardingForEntity(entity) {
  return entity?.status === 'pending_moderation';
}

function moduleEndpointFor(workspace, activeModule) {
  return WORKSPACE_MODULE_ENDPOINTS[activeModule.key] || workspace.module_endpoints?.[activeModule.endpoint];
}

function actionEndpointFor(workspace, actionKey) {
  return WORKSPACE_ACTION_ENDPOINTS[actionKey] || workspace?.actions?.[actionKey];
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

function OverviewModule({ workspace }) {
  return (
    <div className="seller-module-stack">
      <section className="seller-summary-grid">
        <SummaryCard title="Orders in work" value={workspace.orders_summary?.active || 0} caption="Active orders" />
        <SummaryCard title="Catalog available" value={workspace.catalog_summary?.catalog_available || 0} caption="Platform products seller can take" />
        <SummaryCard title="Supply" value={workspace.catalog_summary?.supply_products || 0} caption={`${workspace.catalog_summary?.active_supply_products || 0} active seller products`} />
        <SummaryCard title="Stock" value={workspace.catalog_summary?.stock_units || 0} caption="Available units in warehouses" />
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

    </div>
  );
}

function SalesChannelsModule({ workspace }) {
  return (
    <section className="seller-module-stack">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Channels</p>
          <h2>Sales Channels</h2>
        </div>
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
  const isSellerSupply = module.key === 'provider_storefront';

  return (
    <section className="panel seller-data-panel">
      <div className="section-heading">
        <div>
          <p className="eyebrow">{key}</p>
          <h2>{module.title}</h2>
          {isSellerSupply ? (
            <p>Products the seller has already taken from the platform catalog into their own assortment.</p>
          ) : null}
        </div>
        <StatusPill>{payload.total ?? rows.length} total</StatusPill>
      </div>

      {rows.length ? (
        <>
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
          <div className="seller-mobile-rows">
            {rows.slice(0, 12).map((row, index) => (
              <article className="seller-mobile-row" key={row.id || row.transaction_ref || row.sku || index}>
                {columns.map((column) => (
                  <div key={`${row.id || index}-${column}`}>
                    <span>{column.replaceAll('_', ' ')}</span>
                    <strong>{valuePreview(row[column])}</strong>
                  </div>
                ))}
              </article>
            ))}
          </div>
        </>
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
          <p>Shops and distribution centers connected to this merchant account.</p>
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

function FinanceModule({
  workspace,
  payload,
  actionState,
  actionFeedback,
  onFinanceAction,
}) {
  const rails = payload?.deposit_rails || [];
  const intents = payload?.deposit_intents || [];
  const balances = payload?.balances || workspace.finance_summary || {};
  const [rail, setRail] = useState(rails[0]?.key || 'invoice_manual');
  const [amount, setAmount] = useState('');
  const [comment, setComment] = useState('');
  const [targetLegalEntityId, setTargetLegalEntityId] = useState('');

  function submitIntent(event) {
    event.preventDefault();
    onFinanceAction('create', {
      rail,
      amount: Number(amount),
      comment,
      target_legal_entity_id: rail === 'merchant_transfer' ? Number(targetLegalEntityId) || null : null,
    }).then((result) => {
      if (result?.success) {
        setAmount('');
        setComment('');
        setTargetLegalEntityId('');
      }
    });
  }

  return (
    <div className="seller-module-stack">
      <section className="seller-summary-grid">
        <SummaryCard title="Available" value={formatCurrency(balances.available)} caption="Ready for stock procurement" />
        <SummaryCard title="Reserved" value={formatCurrency(balances.reserved)} caption="Held for open operations" />
        <SummaryCard title="Total" value={formatCurrency(balances.total ?? balances.available)} caption="Merchant RUB ledger" />
      </section>

      <section className="seller-grid-two">
        <form className="panel seller-data-panel" onSubmit={submitIntent}>
          <div className="section-heading">
            <div>
              <p className="eyebrow">Top up balance</p>
              <h2>Create settlement intent</h2>
              <p>Balance changes only after proof, authority policy, and validator quorum pass.</p>
            </div>
          </div>
          <div className="seller-kv seller-form-stack">
            <span>Rail</span>
            <select onChange={(event) => setRail(event.target.value)} value={rail}>
              {rails.map((item) => (
                <option key={item.key} value={item.key}>{item.title}</option>
              ))}
            </select>
            <span>Amount RUB</span>
            <input min="0.01" onChange={(event) => setAmount(event.target.value)} placeholder="10000" step="0.01" type="number" value={amount} />
            {rail === 'merchant_transfer' ? (
              <>
                <span>Target legal entity ID</span>
                <input onChange={(event) => setTargetLegalEntityId(event.target.value)} placeholder="Merchant legal entity id" value={targetLegalEntityId} />
              </>
            ) : null}
            <span>Comment</span>
            <input onChange={(event) => setComment(event.target.value)} placeholder="Invoice, crypto tx, provider reference..." value={comment} />
          </div>
          <div className="product-card__actions seller-module-actions">
            <button disabled={actionState === 'finance:create' || !amount} type="submit">
              {actionState === 'finance:create' ? 'Creating...' : 'Create intent'}
            </button>
          </div>
          {actionFeedback ? <p className="checkout-note">{actionFeedback}</p> : null}
        </form>

        <section className="panel seller-data-panel">
          <div className="section-heading">
            <div>
              <p className="eyebrow">Rails</p>
              <h2>Available funding methods</h2>
            </div>
          </div>
          <div className="seller-card-list">
            {rails.map((item) => (
              <article className="seller-mini-card" key={item.key}>
                <strong>{item.title}</strong>
                <span>{item.description}</span>
              </article>
            ))}
          </div>
        </section>
      </section>

      <section className="panel seller-data-panel">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Settlement queue</p>
            <h2>Deposit intents</h2>
          </div>
          <StatusPill>{intents.length} shown</StatusPill>
        </div>
        {intents.length ? (
          <div className="seller-card-list">
            {intents.map((intent) => (
              <article className="seller-mini-card" key={intent.id}>
                <strong>{intent.reference} · {intent.amount_formatted}</strong>
                <span>{intent.rail_title} · {intent.status} · {intent.next_action}</span>
                {intent.authority ? (
                  <span>
                    Authority {intent.authority.decision} · quorum {intent.authority.accepted_attestations}/{intent.authority.required_quorum}
                  </span>
                ) : null}
                {intent.proof?.credited_ledger_id ? <span>Ledger #{intent.proof.credited_ledger_id}</span> : null}
                {['waiting_payment', 'proof_received', 'waiting_authority'].includes(intent.status) ? (
                  <button
                    disabled={actionState === `finance:cancel:${intent.id}`}
                    onClick={() => onFinanceAction('cancel', { id: intent.id })}
                    type="button"
                  >
                    {actionState === `finance:cancel:${intent.id}` ? 'Cancelling...' : 'Cancel'}
                  </button>
                ) : null}
              </article>
            ))}
          </div>
        ) : (
          <p className="checkout-note">No deposit intents yet.</p>
        )}
      </section>

      <GenericRows payload={{ transactions: payload?.transactions || [] }} module={{ title: 'Ledger events' }} />
    </div>
  );
}

function catalogSearchKey(value = '') {
  return String(value).trim().toLowerCase().replace(/[_-]+/g, ' ').replace(/\s+/g, ' ');
}

function catalogSuggestionMatches(suggestion, query) {
  const needle = catalogSearchKey(query);
  const haystack = catalogSearchKey([
    suggestion.label,
    suggestion.type,
    suggestion.meta,
    suggestion.code,
  ].filter(Boolean).join(' '));

  return !needle || haystack.includes(needle);
}

function SupplyCatalogModule({
  workspace,
  payload,
  filters,
  onFilter,
  onOpenFinance,
  onProviderAction,
  actionState,
  actionFeedback,
}) {
  const products = payload?.products || [];
  const shop = (workspace.shops || [])[0];
  const [searchDraft, setSearchDraft] = useState(filters.search || '');
  const [showSearchSuggestions, setShowSearchSuggestions] = useState(false);
  const [catalogSurface, setCatalogSurface] = useState('search');
  const hasActiveCatalogQuery = Boolean(
    filters.search
    || filters.catalog_group_id
    || filters.brand_id
    || filters.region_id
  );
  const readyChannels = (workspace.sales_channels || [])
    .filter((channel) => channel.ready || channel.configured)
    .map((channel) => channel.type);
  const signingReady = workspace.identity_security?.signing_ready !== false;
  const appConnectUrl = workspace.identity_security?.app_connect_url || '/simple-l1/connect?mode=login&return_to=/merchant/catalog';
  const groupedProducts = products.reduce((groups, product) => {
    const key = product.category_label || product.catalog_group_name || 'Catalog';
    groups.set(key, [...(groups.get(key) || []), product]);
    return groups;
  }, new Map());

  useEffect(() => {
    setSearchDraft(filters.search || '');
  }, [filters.search]);

  const searchSuggestions = useMemo(() => {
    const categories = (payload?.category_cards || []).map((category) => ({
      key: `category-${category.filter_key || category.slug}`,
      type: 'Category',
      label: category.name,
      meta: `${category.count || 0} products`,
      action: { catalog_group_id: category.filter_key === '__all' ? '' : category.filter_key },
    }));
    const brands = (payload?.brands || []).map((brand) => ({
      key: `brand-${brand.id}`,
      type: 'Brand',
      label: brand.name,
      meta: 'Brand catalog',
      action: { brand_id: brand.id, search: '' },
    }));
    const regions = (payload?.regions || []).map((region) => ({
      key: `region-${region.id}`,
      type: 'Region',
      label: region.name_ru || region.code,
      code: region.code,
      meta: region.code ? `Region ${region.code}` : 'Region',
      action: { region_id: region.id },
    }));
    const productMatches = products.map((product) => ({
      key: `product-${product.id}`,
      type: product.is_variable ? 'Product group' : 'Product',
      label: product.name,
      meta: [
        product.brand_name,
        product.category_label,
        product.region_code,
        product.nominal_price_formatted,
      ].filter(Boolean).join(' · '),
      action: { search: product.name },
    }));

    return [...categories, ...brands, ...regions, ...productMatches]
      .filter((suggestion) => catalogSuggestionMatches(suggestion, searchDraft))
      .slice(0, 10);
  }, [payload, products, searchDraft]);

  function applyFilter(patch) {
    onFilter('catalog', { ...filters, ...patch });
  }

  function submitSearch(event) {
    event.preventDefault();
    applyFilter({ search: searchDraft.trim() });
  }

  function chooseSuggestion(suggestion) {
    setShowSearchSuggestions(false);
    setSearchDraft(suggestion.action?.search || suggestion.label || '');
    applyFilter(suggestion.action || { search: suggestion.label });
  }

  return (
    <section className="panel seller-supply-panel">
      <div className="seller-catalog-taxonomy">
        <div className="seller-catalog-toolbar">
          <div className="seller-catalog-tabs" aria-label="Merchant catalog mode">
            <button
              className={catalogSurface === 'search' ? 'is-active' : ''}
              onClick={() => setCatalogSurface('search')}
              type="button"
            >
              Search / Ask
            </button>
            <button
              className={catalogSurface === 'categories' ? 'is-active' : ''}
              onClick={() => setCatalogSurface('categories')}
              type="button"
            >
              Categories
            </button>
          </div>
          <StatusPill>{payload?.total ?? products.length} available</StatusPill>
        </div>

        {catalogSurface === 'search' ? (
          <section className="seller-catalog-search-surface">
            <form className="seller-catalog-search seller-catalog-search--hero" onSubmit={submitSearch}>
              <label>
                Merchant resolver search
                <span>Search brands, categories, regions, nominal values, and product identities.</span>
                <input
                  onBlur={() => window.setTimeout(() => setShowSearchSuggestions(false), 120)}
                  onChange={(event) => {
                    setSearchDraft(event.target.value);
                    setShowSearchSuggestions(true);
                  }}
                  onFocus={() => setShowSearchSuggestions(true)}
                  placeholder="PlayStation Turkey 10 USD, Steam, gift cards, US..."
                  value={searchDraft}
                />
              </label>
              <button type="submit">Search</button>
              {showSearchSuggestions && searchDraft.trim() ? (
                <div className="seller-catalog-suggestions" onMouseDown={(event) => event.preventDefault()}>
                  {searchSuggestions.length ? searchSuggestions.map((suggestion) => (
                    <button
                      className="seller-catalog-suggestion"
                      key={suggestion.key}
                      onClick={() => chooseSuggestion(suggestion)}
                      type="button"
                    >
                      <span className="seller-catalog-suggestion__mark">{suggestion.type.slice(0, 1)}</span>
                      <span className="seller-catalog-suggestion__body">
                        <span>{suggestion.type}</span>
                        <strong>{suggestion.label}</strong>
                        {suggestion.meta ? <p>{suggestion.meta}</p> : null}
                      </span>
                    </button>
                  )) : (
                    <p>No exact resolver hit yet. Press Enter for catalog search.</p>
                  )}
                </div>
              ) : null}
            </form>
            <div className="seller-catalog-search-prompts">
              {['PlayStation TR', 'Steam USD', 'Gift cards', 'App Store US'].map((prompt) => (
                <button key={prompt} onClick={() => applyFilter({ search: prompt })} type="button">
                  {prompt}
                </button>
              ))}
            </div>
          </section>
        ) : (
          <>
            <div className="seller-catalog-taxonomy__section">
              <span>Categories</span>
              <div className="seller-catalog-chip-row">
                {(payload?.category_cards || []).slice(0, 10).map((category) => {
                  const value = category.filter_key === '__all' ? '' : category.filter_key;
                  const active = (filters.catalog_group_id || '') === value || (!filters.catalog_group_id && value === '');

                  return (
                    <button
                      className={active ? 'is-active' : ''}
                      key={category.filter_key || category.slug || category.name}
                      onClick={() => applyFilter({ catalog_group_id: value })}
                      type="button"
                    >
                      <strong>{category.name}</strong>
                      <small>{category.count}</small>
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="seller-catalog-filter-grid">
              <div className="seller-catalog-taxonomy__section">
                <span>Brands</span>
                <div className="seller-catalog-mini-row">
                  {(payload?.brands || []).slice(0, 12).map((brand) => (
                    <button
                      className={Number(filters.brand_id || 0) === Number(brand.id) ? 'is-active' : ''}
                      key={brand.id}
                      onClick={() => applyFilter({ brand_id: brand.id })}
                      type="button"
                    >
                      {brand.name}
                    </button>
                  ))}
                </div>
              </div>

              <div className="seller-catalog-taxonomy__section">
                <span>Regions</span>
                <div className="seller-catalog-mini-row">
                  {(payload?.regions || []).slice(0, 12).map((region) => (
                    <button
                      className={Number(filters.region_id || 0) === Number(region.id) ? 'is-active' : ''}
                      key={region.id}
                      onClick={() => applyFilter({ region_id: region.id })}
                      type="button"
                    >
                      {region.code || region.name_ru}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </>
        )}

        {filters.catalog_group_id || filters.brand_id || filters.region_id || filters.search ? (
          <button className="seller-catalog-clear" onClick={() => onFilter('catalog', {})} type="button">
            Clear catalog filters
          </button>
        ) : null}
      </div>

      {actionFeedback ? (
        <div className="seller-alert seller-alert--compact">
          <strong>{actionFeedback}</strong>
          {/balance|баланс|недостаточно|RUB/i.test(actionFeedback) ? (
            <button onClick={onOpenFinance} type="button">Top up RUB balance</button>
          ) : null}
        </div>
      ) : null}

      {!shop ? (
        <p className="checkout-note">Create or approve a seller shop before buying supply.</p>
      ) : null}

      {products.length && hasActiveCatalogQuery ? (
        <div className="seller-catalog-groups">
          {Array.from(groupedProducts.entries()).map(([groupName, groupProducts]) => (
            <section className="seller-catalog-group" key={groupName}>
              <div className="seller-catalog-group__heading">
                <div>
                  <span>Category</span>
                  <h3>{groupName}</h3>
                </div>
                <StatusPill>{groupProducts.length} shown</StatusPill>
              </div>
              <div className="seller-supply-grid">
                {groupProducts.map((product) => {
                  const availability = product.seller_offer_availability || {};
                  const busyStock = actionState === `stock:${product.id}`;
                  const busyOnce = actionState === `once:${product.id}`;
                  const disabled = !shop || !signingReady || product.action?.enabled === false || busyStock || busyOnce;
                  const quantity = Math.max(1, Number(product.min_purchase_quantity || 1));

                  return (
                    <article className="seller-supply-card" key={product.id}>
                      <div className="product-card__meta">
                        <span>{product.supply_label || 'Meanly Supply'}</span>
                        <span>{product.brand_name || 'Meanly'}</span>
                        <span>{product.region_code || 'GLOBAL'}</span>
                      </div>
                      <h3>{product.name}</h3>
                      <div className="seller-supply-card__price">
                        <strong>{product.purchase_price_formatted || formatCurrency(product.purchase_price)}</strong>
                        <span>Nominal {product.nominal_price_formatted || valuePreview(product.retail_price)}</span>
                      </div>
                      <div className="seller-supply-card__status">
                        <StatusPill tone={availability.in_seller_catalog ? 'active' : 'neutral'}>
                          {availability.in_seller_catalog ? 'in Supply' : 'not listed'}
                        </StatusPill>
                        <StatusPill tone={availability.stock_count > 0 ? 'active' : 'warn'}>
                          stock {availability.stock_count || 0}
                        </StatusPill>
                      </div>
                      <div className="seller-supply-card__meta">
                        <span>Brand</span><strong>{product.brand_name || 'Meanly'}</strong>
                        <span>Identity</span><strong>{product.canonical_identity?.confidence || 'pending'} confidence</strong>
                        <span>Mode</span><strong>{product.is_variable ? 'variable nominal' : 'fixed nominal'}</strong>
                        <span>Channels</span><strong>{readyChannels.length ? readyChannels.join(', ') : 'direct only'}</strong>
                      </div>
                      <div className="product-card__actions">
                        {!signingReady ? (
                          <Link href={appConnectUrl}>Open Meanly.one</Link>
                        ) : (
                          <>
                            <button
                              disabled={disabled}
                              onClick={() => onProviderAction('stock', product, {
                                count: quantity,
                                sales_channels: readyChannels,
                                shop_id: shop.id,
                              })}
                              type="button"
                            >
                              {busyStock ? 'Signing...' : `Take + stock ${quantity}`}
                            </button>
                            <button
                              disabled={disabled}
                              onClick={() => onProviderAction('once', product, {
                                quantity: 1,
                                shop_id: shop.id,
                              })}
                              type="button"
                            >
                              {busyOnce ? 'Signing...' : 'Buy once'}
                            </button>
                          </>
                        )}
                      </div>
                      {!signingReady ? (
                        <p className="product-card__muted">Open the installed Meanly.one app on this device to sign purchases.</p>
                      ) : null}
                      {product.action?.enabled === false ? (
                        <p className="product-card__muted">Identity review is required before this product can be sold.</p>
                      ) : null}
                    </article>
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      ) : hasActiveCatalogQuery ? (
        <p className="checkout-note">No catalog products reached this merchant account yet. Refresh the provider sync.</p>
      ) : (
        <section className="seller-catalog-empty-search">
          <span>Search first</span>
          <strong>Ask for what you want to sell or buy.</strong>
          <p>
            Try a brand, region, nominal, product type, or category. Results appear only after
            you search or choose a category.
          </p>
        </section>
      )}
    </section>
  );
}

function ModuleDetail({
  activeModule,
  workspace,
  payload,
  loading,
  error,
  onAction,
  onFilter,
  onFinanceAction,
  onOpenFinance,
  onProviderAction,
  filters,
  actionState,
  actionFeedback,
}) {
  if (activeModule.key === 'overview') {
    return <OverviewModule workspace={workspace} />;
  }
  if (activeModule.key === 'sales_channels') {
    return <SalesChannelsModule workspace={workspace} />;
  }
  if (activeModule.key === 'settings') {
    return <SettingsModule workspace={workspace} payload={payload} />;
  }
  if (activeModule.key === 'finance') {
    return (
      <div className="seller-module-stack">
        {error ? <p className="product-card__reason">{error}</p> : null}
        {loading ? <p className="checkout-note">Loading finance...</p> : null}
        {payload ? (
          <FinanceModule
            actionFeedback={actionFeedback}
            actionState={actionState}
            onFinanceAction={onFinanceAction}
            payload={payload}
            workspace={workspace}
          />
        ) : null}
      </div>
    );
  }
  if (activeModule.key === 'catalog') {
    return (
      <div className="seller-module-stack">
        {error ? <p className="product-card__reason">{error}</p> : null}
        {loading ? <p className="checkout-note">Loading {activeModule.title.toLowerCase()}...</p> : null}
        {payload ? (
          <SupplyCatalogModule
            actionFeedback={actionFeedback}
            actionState={actionState}
            filters={filters}
            onFilter={onFilter}
            onOpenFinance={onOpenFinance}
            onProviderAction={onProviderAction}
            payload={payload}
            workspace={workspace}
          />
        ) : null}
      </div>
    );
  }

  return (
    <div className="seller-module-stack">
      {activeModule.key === 'orders' ? (
        <div className="product-card__actions seller-module-actions">
          <button type="button" onClick={() => onAction('orders_sync')}>
            {actionState === 'orders_sync' ? 'Syncing...' : 'Sync orders'}
          </button>
        </div>
      ) : null}
      {error ? <p className="product-card__reason">{error}</p> : null}
      {loading ? <p className="checkout-note">Loading {activeModule.title.toLowerCase()}...</p> : null}
      {!loading && payload ? <GenericRows payload={payload} module={activeModule} /> : null}
    </div>
  );
}

function AuthRequired({ error }) {
  const connectUrl = merchantConnectUrl();

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
  const [moduleQueries, setModuleQueries] = useState({});
  const [moduleLoading, setModuleLoading] = useState(false);
  const [moduleError, setModuleError] = useState('');
  const [actionState, setActionState] = useState('');
  const [actionFeedback, setActionFeedback] = useState('');

  const activeModule = useMemo(
    () => MODULES.find((item) => item.key === activeKey) || MODULES[0],
    [activeKey],
  );
  const activeModuleQuery = useMemo(
    () => moduleQueries[activeModule.key] || {},
    [activeModule.key, moduleQueries],
  );
  const activeModuleQueryKey = JSON.stringify(activeModuleQuery);

  function activateModule(item) {
    setActiveKey(item.key);
    const href = item.path ? `/merchant/${item.path}` : '/merchant';
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

    const endpoint = moduleEndpointFor(workspace, activeModule);
    if (!endpoint) {
      return;
    }

    let cancelled = false;
    setModuleLoading(true);
    setModuleError('');
    fetchPartnerModule(endpoint, activeModuleQuery)
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
  }, [activeModule, activeModuleQuery, activeModuleQueryKey, modulePayloads, workspace]);

  function handleModuleFilter(moduleKey, patch) {
    const nextQuery = Object.fromEntries(
      Object.entries(patch).filter(([, value]) => value !== undefined && value !== null && value !== ''),
    );

    setModuleQueries((current) => ({
      ...current,
      [moduleKey]: nextQuery,
    }));
    setModulePayloads((current) => ({
      ...current,
      [moduleKey]: undefined,
    }));
    setActionFeedback('');
  }

  async function refreshWorkspace() {
    const payload = await fetchPartnerWorkspace();
    cachePartnerWorkspace(payload);
    setWorkspace(payload);
  }

  async function handleAction(actionKey) {
    const endpoint = actionEndpointFor(workspace, actionKey);
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

  function openFinance() {
    const financeModule = MODULES.find((item) => item.key === 'finance');
    if (financeModule) {
      activateModule(financeModule);
      setActionFeedback('');
    }
  }

  async function handleFinanceAction(action, body = {}) {
    const endpoint = action === 'cancel'
      ? `/api/partner/workspace/finance/deposit-intents/${body.id}/cancel`
      : '/api/partner/workspace/finance/deposit-intents';
    const actionKey = action === 'cancel' ? `finance:cancel:${body.id}` : 'finance:create';

    setActionState(actionKey);
    setActionFeedback('');
    try {
      const result = await postPartnerAction(endpoint, action === 'cancel' ? {} : body);
      setActionFeedback(action === 'cancel' ? 'Deposit intent cancelled.' : result.intent?.next_action || 'Deposit intent created.');
      setModulePayloads((current) => ({ ...current, finance: undefined }));
      await refreshWorkspace();
      return result;
    } catch (error) {
      setActionFeedback(error.message || 'Finance action failed.');
      return null;
    } finally {
      setActionState('');
    }
  }

  async function handleProviderAction(mode, product, overrides = {}) {
    const shopId = overrides.shop_id || workspace?.shops?.[0]?.id;
    const optionsEndpoint = actionEndpointFor(workspace, 'storefront_buy_options');
    const actionEndpoint = mode === 'once'
      ? actionEndpointFor(workspace, 'storefront_buy_once')
      : actionEndpointFor(workspace, 'storefront_add_to_catalog');

    if (!shopId || !optionsEndpoint || !actionEndpoint) {
      setActionFeedback('Seller shop or protected action endpoint is not ready yet.');
      return;
    }
    if (workspace?.identity_security?.signing_ready === false) {
      setActionFeedback('Open the installed Meanly.one app on this device before buying stock or making a one-time purchase.');
      return;
    }

    const amount = product.is_variable
      ? Number(product.min_price || product.purchase_price || product.retail_price || 0)
      : null;
    const count = Math.max(1, Number(overrides.count || overrides.quantity || product.min_purchase_quantity || 1));
    const transaction = {
      action: mode === 'once' ? 'buy_once' : 'stock_procurement',
      provider_product_id: product.id,
      shop_id: shopId,
      count,
      amount,
      payment_method: 'rub',
      sales_channels: mode === 'once' ? [] : (overrides.sales_channels || []),
    };

    setActionState(`${mode}:${product.id}`);
    setActionFeedback('');

    try {
      if (workspace?.identity_security?.signing_method === 'simple_l1_app') {
        const body = mode === 'once'
          ? {
              provider_product_id: product.id,
              shop_id: shopId,
              quantity: count,
              amount,
              payment_method: 'rub',
              simple_l1_sign: true,
            }
          : {
              provider_product_id: product.id,
              shop_id: shopId,
              sales_channels: transaction.sales_channels,
              count,
              amount,
              payment_method: 'rub',
              simple_l1_sign: true,
            };
        const result = await postPartnerAction(actionEndpoint, body);
        setActionFeedback(result.message || (mode === 'once' ? 'Purchase completed.' : 'Stock procurement completed.'));
        setModulePayloads((current) => ({ ...current, [activeModule.key]: undefined }));
        await refreshWorkspace();
        return;
      }

      const options = await postPartnerAction(optionsEndpoint, { transaction });
      const assertion = await requestPasskeyAssertion(options);
      const body = mode === 'once'
        ? {
            provider_product_id: product.id,
            shop_id: shopId,
            quantity: count,
            amount,
            payment_method: 'rub',
            assertion,
          }
        : {
            provider_product_id: product.id,
            shop_id: shopId,
            sales_channels: transaction.sales_channels,
            count,
            amount,
            payment_method: 'rub',
            assertion,
          };
      const result = await postPartnerAction(actionEndpoint, body);
      setActionFeedback(result.message || (mode === 'once' ? 'Purchase completed.' : 'Stock procurement completed.'));
      setModulePayloads((current) => ({ ...current, [activeModule.key]: undefined }));
      await refreshWorkspace();
    } catch (error) {
      setActionFeedback(error.message || 'Supply action failed.');
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

  return (
    <main className="seller-workspace">
      <aside className="seller-sidebar">
        <Link href="/merchant" className="seller-sidebar__brand">
          <span className="brand__mark" />
          <strong>Merchant Center</strong>
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
      </aside>

      <section className="seller-main">
        <nav aria-label="Seller modules" className="seller-mobile-tabs">
          {(workspace.navigation || []).map((item) => (
            <button
              className={item.key === activeKey ? 'is-active' : ''}
              disabled={!item.enabled}
              key={item.key}
              onClick={() => activateModule(MODULES.find((module) => module.key === item.key) || MODULES[0])}
              type="button"
            >
              {item.label}
            </button>
          ))}
        </nav>
        <ModuleDetail
          activeModule={activeModule}
          workspace={workspace}
          payload={modulePayloads[activeModule.key]}
          loading={moduleLoading}
          error={moduleError}
          onAction={handleAction}
          onFinanceAction={handleFinanceAction}
          onFilter={handleModuleFilter}
          onOpenFinance={openFinance}
          onProviderAction={handleProviderAction}
          filters={activeModuleQuery}
          actionState={actionState}
          actionFeedback={actionFeedback}
        />
      </section>
    </main>
  );
}
