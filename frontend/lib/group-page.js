export function queryObject(searchParams = {}) {
  return Object.fromEntries(
    Object.entries(searchParams || {}).filter(([, value]) => value !== undefined && value !== null && value !== ''),
  );
}

function nominalParam(value = '') {
  const [faceValue = '', currency = ''] = String(value).split('|');

  return { face_value: faceValue, currency };
}

export function normalizeGroupQuery(query = {}) {
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

export function groupPageTitle(brandSlug, kindSlug) {
  const brand = String(brandSlug || '')
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
  const kind = String(kindSlug || '').replace(/-/g, ' ');

  return `${brand} ${kind}`.trim();
}
