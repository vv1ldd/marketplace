import Link from 'next/link';
import { permanentRedirect } from 'next/navigation';
import { ProjectionSurface } from '../../../components/ProjectionSurface';
import { ProductCard } from '../../../components/ProductCard';
import { groupCatalogPath } from '../../../lib/catalog-urls';
import { queryObject } from '../../../lib/group-page';
import { fetchStorefrontCategory } from '../../../lib/storefront-api';

export const dynamic = 'force-dynamic';

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

const NEED_ANSWERS_BY_CATEGORY = {
  play: {
    question: 'Play',
    answer: 'Start with the game or platform. Meanly matches region, currency, nominal, and supplier stock.',
    examples: ['Steam and console wallets', 'Roblox, Riot, PUBG', 'In-game currency'],
  },
  stream: {
    question: 'Watch & listen',
    answer: 'Start with the service family. Meanly narrows it to the account region and renewal value that can actually be delivered.',
    examples: ['Music and video renewals', 'Streaming memberships', 'Account-region specific cards'],
  },
  work: {
    question: 'Work & protect',
    answer: 'Start with the software family. Meanly compares license type, activation region, and delivery source.',
    examples: ['VPN and antivirus', 'Productivity tools', 'License keys'],
  },
  shop: {
    question: 'Gift & shop',
    answer: 'Start with the brand card. Meanly resolves the region, currency, nominal, and checkout-ready supplier behind it.',
    examples: ['Retail and marketplace vouchers', 'Brand cards by region', 'Digital codes for checkout'],
  },
  pay: {
    question: 'Pay without a card',
    answer: 'Use this route for wallet-ready value, virtual payment codes, and stored-value vouchers.',
    examples: ['Virtual cards', 'Payment vouchers', 'Wallet-ready codes'],
  },
  mobile: {
    question: 'On your phone',
    answer: 'Start with the mobile ecosystem or operator. Meanly matches the storefront region and available value.',
    examples: ['Apple and Google Play credit', 'Airtime and data packs', 'Mobile ecosystem cards'],
  },
  go: {
    question: 'Go & enjoy',
    answer: 'Start with the travel or entertainment network. Meanly narrows it by city, region, and redeemable voucher type.',
    examples: ['Transport and rides', 'Hotels and tickets', 'Local entertainment vouchers'],
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
        canonical: groupCatalogPath(path[1], path[2], path[3]),
      },
    };
  }

  if (path.length === 1) {
    const query = queryObject(await searchParams);
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
    permanentRedirect(groupCatalogPath(path[1], path[2], path[3], query));
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
