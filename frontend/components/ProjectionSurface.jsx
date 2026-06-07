import Link from 'next/link';
import { fetchUiProjection } from '../lib/storefront-api';

const SURFACE_PROFILES = {
  business: {
    eyebrow: 'Merchant Center',
    title: 'Meanly Merchant Center',
    lead: 'Start seller onboarding, manage merchant access, and continue only when a protected step needs identity.',
    meta: 'Merchant',
    cta: 'Continue',
  },
  services: {
    eyebrow: 'Services',
    title: 'Meanly services',
    lead: 'Explore Merchant Center services and open the path that fits your goal.',
    meta: 'Service',
    cta: 'View service',
  },
  store: {
    eyebrow: 'Store',
    title: 'Meanly storefront',
    lead: 'Browse products, open your Vault, and continue shopping from one place.',
    meta: 'Store',
    cta: 'Open',
  },
  redeem: {
    eyebrow: 'Redeem',
    title: 'Redeem a code',
    lead: 'Follow the activation steps and continue when a code or delivery check is needed.',
    meta: 'Step',
    cta: 'Continue',
  },
  reader: {
    eyebrow: 'Reader',
    title: 'Artifact reader',
    lead: 'Check trusted artifacts and receipts from a guided reader screen.',
    meta: 'Reader',
    cta: 'Open reader',
  },
  terminal: {
    eyebrow: 'Terminal',
    title: 'Meanly terminal',
    lead: 'Open terminal tools for trust, runtime, and commerce status.',
    meta: 'Tool',
    cta: 'Open tool',
  },
  'catalog-network': {
    eyebrow: 'Provider network',
    title: 'Provider network catalog',
    lead: 'Browse provider supply by category and open the catalog path that matches your intent.',
    meta: 'Network',
    cta: 'Browse',
  },
  'products-search': {
    eyebrow: 'Search',
    title: 'Product search',
    lead: 'Search the catalog and continue to matching products or groups.',
    meta: 'Search',
    cta: 'Search catalog',
  },
  catalog: {
    eyebrow: 'Catalog',
    title: 'Catalog',
    lead: 'Browse products, groups, and provider paths.',
    meta: 'Catalog',
    cta: 'Browse',
  },
};

const SECTION_TITLES = {
  'available actions': 'What you can do',
  'search handoff': 'Search catalog',
  'flow state': 'Activation steps',
  'workspace modules': 'Workspace areas',
  'ops modules': 'Operations areas',
  'runtime panels': 'Terminal tools',
  'trust profiles': 'Reader modes',
  'network categories': 'Network categories',
  'storefront projections': 'Storefront paths',
  'catalog path': 'Catalog paths',
  'projection status': 'Status',
};

function projectionQuery(searchParams = {}) {
  return Object.fromEntries(
    Object.entries(searchParams || {}).filter(([, value]) => value !== undefined && value !== null),
  );
}

function profileFor(surface) {
  return SURFACE_PROFILES[surface] || {
    eyebrow: 'Meanly',
    title: 'Meanly surface',
    lead: 'Open the next available path.',
    meta: 'Path',
    cta: 'Open',
  };
}

function sanitizeText(value = '') {
  return String(value)
    .replace(/\bbackend-defined\b/gi, '')
    .replace(/\bbackend contracts?\b/gi, 'checkout rules')
    .replace(/\bLaravel projection contracts?\b/gi, 'Meanly data')
    .replace(/\bLaravel projection rules?\b/gi, 'Meanly rules')
    .replace(/\bprojection contracts?\b/gi, 'data')
    .replace(/\bprojection targets?\b/gi, 'paths')
    .replace(/\bprojections?\b/gi, 'views')
    .replace(/\bDTOs?\b/g, 'data')
    .replace(/\bReact renders?\b/gi, 'This page shows')
    .replace(/\bBlade\b/g, 'classic')
    .replace(/\bbackend\b/gi, 'Meanly')
    .replace(/\s+/g, ' ')
    .trim();
}

function displayTitle(value, fallback) {
  const sanitized = sanitizeText(value || '');

  if (!sanitized || /^meanly view$/i.test(sanitized) || /^view$/i.test(sanitized)) {
    return fallback;
  }

  return sanitized;
}

function displaySectionTitle(title) {
  const normalized = String(title || '').toLowerCase();

  return SECTION_TITLES[normalized] || displayTitle(title, 'Available paths');
}

export async function ProjectionSurface({ surface, path = '', searchParams = {} }) {
  const payload = await fetchUiProjection(surface, path, projectionQuery(searchParams));
  const projection = payload.projection || {};
  const sections = projection.sections || [];
  const profile = profileFor(payload.surface || surface);

  return (
    <>
      <section className="hero">
        <p className="eyebrow">{profile.eyebrow}</p>
        <h1>{displayTitle(projection.title, profile.title)}</h1>
        <p>{profile.lead}</p>
      </section>

      {sections.map((section) => (
        <section className="catalog-section panel" key={section.title}>
          <div className="section-heading">
            <h2>{displaySectionTitle(section.title)}</h2>
            {section.description ? <p>{sanitizeText(section.description)}</p> : null}
          </div>
          {(section.cards || []).length ? (
            <div className="grid">
            {(section.cards || []).map((card) => (
              <article className="product-card" key={`${section.title}-${card.title}`}>
                <div className="product-card__body">
                  <div className="product-card__meta">
                    <span>{profile.meta}</span>
                  </div>
                  <h3>{displayTitle(card.title, 'Open path')}</h3>
                  {card.description ? <p className="product-card__muted">{sanitizeText(card.description)}</p> : null}
                  {card.href ? (
                    <div className="product-card__actions">
                      <Link href={card.href}>{profile.cta}</Link>
                    </div>
                  ) : null}
                </div>
              </article>
            ))}
            </div>
          ) : (
            <p className="checkout-note">Nothing to show here yet.</p>
          )}
        </section>
      ))}
    </>
  );
}
