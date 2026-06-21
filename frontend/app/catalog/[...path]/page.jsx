import Link from 'next/link';
import { CatalogGroupPanel } from '../../../components/CatalogGroupPanel';
import { ProjectionSurface } from '../../../components/ProjectionSurface';
import { ProductCard } from '../../../components/ProductCard';
import { fetchStorefrontCategory, fetchStorefrontGroup } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

function queryObject(searchParams = {}) {
  return Object.fromEntries(
    Object.entries(searchParams || {}).filter(([, value]) => value !== undefined && value !== null && value !== ''),
  );
}

function hrefWithQuery(pathname, query = {}) {
  const params = new URLSearchParams();
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.set(key, String(value));
    }
  });

  return `${pathname}${params.toString() ? `?${params.toString()}` : ''}`;
}

function localHref(href, fallback = '/') {
  if (!href) {
    return fallback;
  }

  try {
    const url = new URL(href);
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return href;
  }
}

function nominalParam(value = '') {
  const [faceValue = '', currency = ''] = String(value).split('|');

  return { face_value: faceValue, currency };
}

function normalizeGroupQuery(query = {}) {
  if (!query.nominal) {
    return query;
  }

  const { face_value: faceValue, currency } = nominalParam(query.nominal);
  const { nominal, ...rest } = query;

  return {
    ...rest,
    face_value: faceValue,
    currency,
  };
}

const NEED_ANSWERS_BY_CATEGORY = {
  gift_cards: {
    question: 'Gift cards for checkout',
    answer: 'Start with the brand card. Meanly resolves the region, currency, nominal, and checkout-ready supplier behind it.',
    examples: ['Retail and marketplace vouchers', 'Brand cards by region', 'Digital codes for checkout'],
  },
  subscriptions: {
    question: 'Subscription renewals',
    answer: 'Start with the service family. Meanly narrows it to the account region and renewal value that can actually be delivered.',
    examples: ['Music and video renewals', 'Cloud and app memberships', 'Account-region specific cards'],
  },
  console_payment_cards: {
    question: 'Console wallet credit',
    answer: 'Start with the console ecosystem. Meanly matches the wallet region before the buyer chooses a nominal.',
    examples: ['PlayStation wallet cards', 'Xbox and Nintendo credit', 'Regional console balance'],
  },
  travel_entertainment_vouchers: {
    question: 'Travel, rides, and tickets',
    answer: 'Start with the travel or entertainment network. Meanly narrows it by city, region, and redeemable voucher type.',
    examples: ['Transport and rides', 'Hotels and tickets', 'Local entertainment vouchers'],
  },
  payment_prepaid_cards: {
    question: 'Prepaid money',
    answer: 'Use this route for wallet-ready value, virtual payment codes, and stored-value vouchers.',
    examples: ['Virtual cards', 'Payment vouchers', 'Wallet-ready codes'],
  },
  mobile_app_store_cards: {
    question: 'App store credit',
    answer: 'Start with the mobile ecosystem. Meanly matches the storefront region and available value.',
    examples: ['Apple and iTunes credit', 'Google Play cards', 'Mobile ecosystem cards'],
  },
  software_licenses: {
    question: 'Software access',
    answer: 'Start with the software family. Meanly compares license type, activation region, and delivery source.',
    examples: ['VPN and antivirus', 'Productivity tools', 'License keys'],
  },
  game_wallet_topups: {
    question: 'Game balance top-ups',
    answer: 'Start with the game or wallet. Meanly matches region, currency, nominal, and available supplier stock.',
    examples: ['Steam and Razer Gold', 'Roblox, Riot, PUBG', 'In-game wallets'],
  },
  telecom_topups: {
    question: 'Mobile top-ups',
    answer: 'Start with the operator or top-up product. Meanly matches country, airtime/data type, and delivery path.',
    examples: ['Airtime', 'Data packs', 'Telecom balance'],
  },
};

function categoryAnswer(categorySlug, fallbackTitle, fallbackDescription) {
  return NEED_ANSWERS_BY_CATEGORY[categorySlug] || {
    question: fallbackTitle || 'Catalog answer',
    answer: fallbackDescription || 'Choose the group that matches the buyer outcome, then narrow by region, value, and supplier availability.',
    examples: ['Choose a group', 'Match region and value', 'Continue to checkout'],
  };
}

function CategoryGroupCard({ group }) {
  const variantGroup = group.variant_group || {};
  const href = localHref(group.links?.self, group.slug ? `/products/${group.slug}` : '/');
  const meta = [
    `${variantGroup.variant_count || 1} variants`,
    variantGroup.region_count ? `${variantGroup.region_count} regions` : null,
    variantGroup.nominal_count ? `${variantGroup.nominal_count} nominals` : null,
  ].filter(Boolean).join(' · ');

  return (
    <Link className="category-card" href={href}>
      <strong>{group.name}</strong>
      <span>{meta}</span>
    </Link>
  );
}

function NeedAnswer({ answer }) {
  return (
    <section className="need-answer-panel">
      <span>Matched outcome</span>
      <div>
        <h1>{answer.question}</h1>
        <p>{answer.answer}</p>
      </div>
      <div className="need-answer-grid">
        {(answer.examples || []).map((example) => (
          <strong key={example}>{example}</strong>
        ))}
      </div>
    </section>
  );
}

function CatalogResults({ answer, products = [], pagination }) {
  const grouped = products.some((product) => product.variant_group?.is_grouped);

  return (
    <main className="page">
      <NeedAnswer answer={answer} />

      <section className="catalog-section">
        <div className="section-heading">
          <h2>{grouped ? 'Choose the route' : 'Products for this outcome'}</h2>
          <p>{pagination?.total || products.length} {grouped ? 'groups' : 'matches'}</p>
        </div>
        {grouped ? (
          <div className="category-grid">
            {products.map((group) => (
              <CategoryGroupCard key={`${group.type}-${group.id || group.slug}`} group={group} />
            ))}
          </div>
        ) : (
          <div className="grid">
            {products.map((product) => (
              <ProductCard key={`${product.type}-${product.id || product.slug}`} product={product} />
            ))}
          </div>
        )}
      </section>
    </main>
  );
}


export async function generateMetadata({ params, searchParams }) {
  const { path = [] } = await params;
  const query = queryObject(await searchParams);

  if (path[0] === 'groups' && path.length >= 4) {
    const brand = path[2]
      .split('-')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
    const kind = path[3].replace(/-/g, ' ');

    return {
      title: `${brand} ${kind} | Meanly`,
      description: 'Choose region and nominal for this product group.',
      alternates: {
        canonical: `/catalog/groups/${path[1]}/${path[2]}/${path[3]}`,
      },
    };
  }

  if (path.length === 1) {
    const category = await fetchStorefrontCategory(path[0], query);
    const surface = category.surface || {};

    return {
      title: `${surface.title || path[0]} | Meanly catalog`,
      description: surface.description || 'Meanly catalog category.',
      alternates: {
        canonical: `/catalog/${path[0]}`,
      },
    };
  }

  return {
    title: 'Meanly catalog',
  };
}

export default async function CatalogProjectionPage({ params, searchParams }) {
  const { path = [] } = await params;
  const query = queryObject(await searchParams);

  if (path[0] === 'groups' && path.length >= 4) {
    const group = await fetchStorefrontGroup(path[1], path[2], path[3], normalizeGroupQuery(query));

    return <CatalogGroupPanel group={group} />;
  }

  if (path.length === 1) {
    const category = await fetchStorefrontCategory(path[0], query);
    const surface = category.surface || {};
    const answer = categoryAnswer(path[0], surface.title, surface.description);

    return (
      <CatalogResults
        answer={answer}
        products={category.products?.browse || []}
        pagination={category.pagination}
      />
    );
  }

  return (
    <main className="page">
      <ProjectionSurface surface="catalog" path={path} searchParams={query} />
    </main>
  );
}
