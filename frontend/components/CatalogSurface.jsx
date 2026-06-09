'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { AskSearchBox } from './AskSearchBox';
import { GlossaryHint } from './GlossaryHint';
import { WelcomeTourCard } from './WelcomeTourCard';
import { fetchStorefrontCatalog, submitCatalogNeedRequest } from '../lib/storefront-api';
import { useLocale } from './LocaleProvider';

const DEFAULT_QUICK_CHIPS = [
  'Steam Turkey',
  'PlayStation US',
  'Spotify subscription',
  'Xbox gift card',
  '20 EUR card',
];

const NEED_GROUPS = [
  { key: 'games', title: 'Games', body: 'Top up games, wallets, and platforms.', query: 'games' },
  { key: 'travel', title: 'Travel', body: 'Hotels, tickets, eSIM, and transport.', query: 'travel hotels tickets' },
  { key: 'mobile', title: 'Mobile', body: 'Balance, internet, eSIM, and operators.', query: 'mobile internet esim' },
  { key: 'subscriptions', title: 'Subscriptions', body: 'Music, video, cloud, and software.', query: 'subscriptions' },
  { key: 'gift_cards', title: 'Gift Cards', body: 'Retail cards, vouchers, and prepaid codes.', query: 'gift cards' },
  { key: 'food', title: 'Food', body: 'Delivery, groceries, and local food credits.', query: 'food delivery' },
  { key: 'learning', title: 'Learning', body: 'Courses, exams, and language tools.', query: 'learning courses' },
  { key: 'finance', title: 'Prepaid', body: 'Wallet codes, rewards, and payment vouchers.', query: 'prepaid vouchers' },
];

const NEED_COPY_BY_CATEGORY = {
  gift_cards: {
    title: 'Gift cards for checkout',
    body: 'Retail cards, brand vouchers, and digital codes for checkout.',
  },
  subscriptions: {
    title: 'Subscription renewals',
    body: 'Music, video, cloud, apps, and membership renewals.',
  },
  console_payment_cards: {
    title: 'Console wallet credit',
    body: 'PlayStation, Xbox, Nintendo, and regional wallet cards.',
  },
  game_wallet_topups: {
    title: 'Game balance top-ups',
    body: 'Steam, Roblox, Riot, PUBG, Razer Gold, and in-game wallets.',
  },
  mobile_app_store_cards: {
    title: 'App store credit',
    body: 'Apple, iTunes, Google Play, and mobile ecosystem cards.',
  },
  software_licenses: {
    title: 'Software access',
    body: 'License keys, VPN, antivirus, productivity, and work tools.',
  },
  payment_prepaid_cards: {
    title: 'Prepaid money',
    body: 'Virtual cards, payment vouchers, and wallet-ready codes.',
  },
  telecom_topups: {
    title: 'Mobile top-ups',
    body: 'Airtime, data packs, internet balance, and telecom products.',
  },
  travel_entertainment_vouchers: {
    title: 'Travel, rides, and tickets',
    body: 'Transport, hotels, tickets, entertainment, and local vouchers.',
  },
  local_vouchers: {
    title: 'Local Meanly vouchers',
    body: 'Local inventory and voucher products managed by Meanly.',
  },
};

function categoryKey(category) {
  return category.slug || category.key || category.name || category.label || category.title || 'category';
}

function categoryTitle(category, t) {
  const key = categoryKey(category);
  const translatedTitle = t(`category_${key}_title`);
  if (translatedTitle !== `category_${key}_title`) {
    return translatedTitle;
  }
  return NEED_COPY_BY_CATEGORY[key]?.title || String(category.label || category.title || category.name || category.slug || t('Catalog option'));
}

function categoryDescription(category, t) {
  const key = categoryKey(category);
  const translatedBody = t(`category_${key}_body`);
  if (translatedBody !== `category_${key}_body`) {
    return translatedBody;
  }
  if (NEED_COPY_BY_CATEGORY[key]?.body) return NEED_COPY_BY_CATEGORY[key].body;
  if (category.description) return category.description;

  return t('category_fallback_body');
}

function categoryMeta(category, t) {
  if (category.count) {
    return `${category.count} ${t('category_products_count')}`;
  }
  if (category.seller_offer_count || category.provider_count) {
    return `${category.seller_offer_count || 0} ${t('category_offers')} · ${category.provider_count || 0} ${t('category_sources')}`;
  }

  return categoryKey(category).replaceAll('_', ' ');
}

function categoryHref(category) {
  const href = category.links?.self || category.href || category.url;
  if (href) {
    try {
      const url = new URL(href);
      return `${url.pathname}${url.search}${url.hash}`;
    } catch {
      return href;
    }
  }

  const slug = category.slug || category.key;
  return slug ? `/catalog/${slug}` : '/catalog';
}

function chipLabel(chip) {
  return typeof chip === 'string' ? chip : chip.label || chip.query || 'Catalog';
}

function chipQuery(chip) {
  return typeof chip === 'string' ? chip : chip.query || chip.label || '';
}

function catalogCacheKey(query, surface) {
  return `meanly:catalog:${surface}:${query || ''}`;
}

function cachedCatalog(query, surface) {
  try {
    return JSON.parse(window.sessionStorage.getItem(catalogCacheKey(query, surface)) || 'null');
  } catch {
    return null;
  }
}

function cacheCatalog(query, surface, catalog) {
  try {
    window.sessionStorage.setItem(catalogCacheKey(query, surface), JSON.stringify(catalog));
  } catch {
    // Catalog cache is a navigation smoothness optimization only.
  }
}

export function CatalogSurface({ query = '', surface = 'catalog', initialCatalog = null }) {
  const [catalog, setCatalog] = useState(initialCatalog);
  const [error, setError] = useState('');
  const [needRequest, setNeedRequest] = useState({ description: '', contact: '', needKey: '', needTitle: '' });
  const [needScreenshot, setNeedScreenshot] = useState(null);
  const [needStatus, setNeedStatus] = useState('');
  const [isSubmittingNeed, setIsSubmittingNeed] = useState(false);
  const [isNeedRequestOpen, setIsNeedRequestOpen] = useState(false);
  const { t } = useLocale();

  useEffect(() => {
    let cancelled = false;
    setError('');

    if (initialCatalog) {
      cacheCatalog(query, surface, initialCatalog);
      setCatalog(initialCatalog);
      return undefined;
    }

    setCatalog(cachedCatalog(query, surface));

    fetchStorefrontCatalog(query)
      .then((payload) => {
        if (cancelled) return;
        cacheCatalog(query, surface, payload);
        setCatalog(payload);
      })
      .catch((exception) => {
        if (!cancelled) {
          setError(exception.message || t('Catalog is temporarily unavailable.'));
        }
      });

    return () => {
      cancelled = true;
    };
  }, [initialCatalog, query, surface]);

  const quickChips = catalog?.quick_chips?.length ? catalog.quick_chips : DEFAULT_QUICK_CHIPS;
  const isHome = surface === 'home';
  const categoryNeeds = catalog?.categories?.length
    ? catalog.categories.map((category) => ({
      body: categoryDescription(category, t),
      href: categoryHref(category),
      key: categoryKey(category),
      meta: categoryMeta(category, t),
      title: categoryTitle(category, t),
    }))
    : NEED_GROUPS.map((group) => ({
      ...group,
      title: t(`need_${group.key}`),
      body: t(`need_${group.key}_body`),
    }));
  const isUsingCatalogCategories = Boolean(catalog?.categories?.length);

  function selectNeed(need) {
    if (need.href) {
      return;
    }

    setNeedRequest((current) => ({
      ...current,
      needKey: need.key,
      needTitle: need.title,
      description: current.description || `Request outcome: ${need.title}. `,
    }));
    setIsNeedRequestOpen(true);
    setNeedStatus('');
  }

  async function submitNeed(event) {
    event.preventDefault();
    const description = needRequest.description.trim();
    if (!description) {
      setNeedStatus(t('demand_status_describe'));
      return;
    }

    const formData = new FormData();
    formData.set('description', description);
    if (needRequest.needKey) formData.set('need_key', needRequest.needKey);
    if (needRequest.needTitle) formData.set('need_title', needRequest.needTitle);
    if (needRequest.contact.trim()) formData.set('contact', needRequest.contact.trim());
    if (needScreenshot) formData.set('screenshot', needScreenshot);

    setIsSubmittingNeed(true);
    setNeedStatus(t('demand_status_saving'));
    try {
      await submitCatalogNeedRequest(formData);
      setNeedStatus(t('demand_status_saved'));
      setNeedRequest({ description: '', contact: '', needKey: '', needTitle: '' });
      setNeedScreenshot(null);
      setIsNeedRequestOpen(false);
      event.currentTarget.reset();
    } catch (exception) {
      setNeedStatus(exception.message || t('demand_status_failed'));
    } finally {
      setIsSubmittingNeed(false);
    }
  }

  return (
    <>
      {isHome ? (
        <section className="hero hero--search-home">
          <div className="home-wordmark" aria-label="maestrooo">
            maestrooo
            <GlossaryHint>{t('wordmark_hint')}</GlossaryHint>
          </div>
          <AskSearchBox initialQuery={query} />
          {error ? <p className="product-card__reason">{error}</p> : null}
          {quickChips.length > 0 ? (
            <div className="hero-chips" aria-label="Popular searches">
              {quickChips.map((chip) => (
                <Link key={chipQuery(chip)} href={`/?q=${encodeURIComponent(chipQuery(chip))}`}>
                  {chipLabel(chip)}
                </Link>
              ))}
            </div>
          ) : null}
          <WelcomeTourCard />
        </section>
      ) : null}

      {!isHome ? (
        <section className="need-graph-section" aria-labelledby="need-graph-title">
          <div className="need-graph-heading">
            <h2 id="need-graph-title">
              {t('outcome_title')}
              <GlossaryHint>{t('outcome_hint')}</GlossaryHint>
            </h2>
            <p>
              {t('outcome_desc')}
            </p>
          </div>
          <div className="need-grid">
            {categoryNeeds.map((need) => (
              need.href ? (
                <Link className="need-card" href={need.href} key={need.key}>
                  <span>{need.meta || need.key.replaceAll('_', ' ')}</span>
                  <strong>{need.title}</strong>
                  <p>{need.body}</p>
                </Link>
              ) : (
                <button className="need-card" key={need.key} onClick={() => selectNeed(need)} type="button">
                  <span>{need.meta || need.key.replaceAll('_', ' ')}</span>
                  <strong>{need.title}</strong>
                  <p>{need.body}</p>
                </button>
              )
            ))}
          </div>
          <form className={`need-request-card ${isNeedRequestOpen ? 'is-open' : ''}`} onSubmit={submitNeed}>
            <div className="need-request-summary">
              <span>
                {t('demand_title')}
                <GlossaryHint>{t('demand_hint')}</GlossaryHint>
              </span>
              <strong>{t('demand_missing')}</strong>
              <p>{isUsingCatalogCategories ? t('demand_desc_categories') : t('demand_desc_screenshot')}</p>
            </div>
            {!isNeedRequestOpen ? (
              <button className="need-request-toggle" onClick={() => setIsNeedRequestOpen(true)} type="button">
                {t('demand_btn_request')}
              </button>
            ) : (
              <div className="need-request-form">
                {needRequest.needTitle ? <small>{t('selected_need', { title: needRequest.needTitle })}</small> : <small>{t('selected_general')}</small>}
                <textarea
                  disabled={isSubmittingNeed}
                  onChange={(event) => setNeedRequest((current) => ({ ...current, description: event.target.value }))}
                  placeholder={t('demand_textarea_placeholder')}
                  value={needRequest.description}
                />
                <div className="need-request-row">
                  <input
                    disabled={isSubmittingNeed}
                    onChange={(event) => setNeedRequest((current) => ({ ...current, contact: event.target.value }))}
                    placeholder={t('demand_input_contact')}
                    value={needRequest.contact}
                  />
                  <input
                    accept="image/*"
                    disabled={isSubmittingNeed}
                    onChange={(event) => setNeedScreenshot(event.target.files?.[0] || null)}
                    type="file"
                  />
                </div>
                <div className="need-request-actions">
                  <button disabled={isSubmittingNeed || !needRequest.description.trim()} type="submit">
                    {isSubmittingNeed ? t('demand_btn_sending') : t('demand_btn_send')}
                  </button>
                  <button disabled={isSubmittingNeed} onClick={() => setIsNeedRequestOpen(false)} type="button">
                    {t('demand_btn_close')}
                  </button>
                </div>
                {needStatus ? <p className="need-request-status">{needStatus}</p> : null}
              </div>
            )}
          </form>
        </section>
      ) : null}
    </>
  );
}
