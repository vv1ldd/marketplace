const RU_ONLY_MODULES = new Set(['activations']);

export function currentMerchantMarketKey(fallback = 'global') {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname.toLowerCase();

    if (host === 'meanly.ru' || host.endsWith('.meanly.ru') || host.includes('ru.marketplace.')) {
      return 'ru';
    }

    if (host.endsWith('.meanly.ar') || host.includes('digitienda.ar') || host.includes('ar.marketplace.')) {
      return 'latam_ar';
    }

    if (host.endsWith('.tsipruli.ge') || host.includes('tsipruli.ge')) {
      return 'ge';
    }
  }

  return fallback;
}

export function marketScopedNavigation(workspace) {
  const marketKey = workspace?.market?.market || currentMerchantMarketKey();

  return (workspace?.navigation || []).filter((item) => {
    if (marketKey === 'ru') {
      return true;
    }

    return !RU_ONLY_MODULES.has(item.key);
  });
}

export function marketScopedSalesChannels(workspace) {
  const marketKey = workspace?.market?.market || currentMerchantMarketKey();

  return (workspace?.sales_channels || []).filter((channel) => {
    const scopedMarkets = Array.isArray(channel.scoped_markets) ? channel.scoped_markets : [];

    if (scopedMarkets.length === 0) {
      return true;
    }

    return scopedMarkets.includes(marketKey);
  });
}

export function merchantLedgerCurrency(market) {
  const marketKey = market?.market || currentMerchantMarketKey();

  if (marketKey === 'ru') {
    return 'RUB';
  }

  if (market?.currency && market.currency !== 'RUB') {
    return market.currency;
  }

  return 'USD';
}

export function normalizeFinanceSummary(financeSummary, market) {
  if (!financeSummary || typeof financeSummary !== 'object') {
    return financeSummary;
  }

  const currency = merchantLedgerCurrency(market);
  if (currency === 'RUB') {
    return financeSummary;
  }

  const fields = ['available', 'reserved', 'total'];
  const next = {
    ...financeSummary,
    currency,
  };

  fields.forEach((field) => {
    const formattedKey = `${field}_formatted`;
    const formatted = next[formattedKey];

    if (typeof formatted === 'string' && (formatted.includes('₽') || formatted.includes('RUB'))) {
      delete next[formattedKey];
    }
  });

  return next;
}

export function normalizePartnerWorkspace(workspace) {
  if (!workspace || typeof workspace !== 'object') {
    return workspace;
  }

  const marketKey = workspace.market?.market || currentMerchantMarketKey();
  const market = {
    ...(workspace.market || {}),
    market: marketKey,
    currency: merchantLedgerCurrency(workspace.market || { market: marketKey }),
  };

  return {
    ...workspace,
    market,
    finance_summary: normalizeFinanceSummary(workspace.finance_summary, market),
    navigation: marketScopedNavigation({ ...workspace, market }),
    sales_channels: marketScopedSalesChannels({ ...workspace, market }),
  };
}
