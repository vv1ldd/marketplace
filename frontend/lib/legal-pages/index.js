import * as globalSurface from './global';
import * as ruSurface from './ru';

const surfaces = {
  global: globalSurface,
  ru: ruSurface,
  latam_ar: globalSurface,
  ge: globalSurface,
};

export function legalSurface(marketKey = 'global') {
  return surfaces[marketKey] || surfaces.global;
}

export function companyDetails(marketKey = 'global') {
  return legalSurface(marketKey).companyDetails;
}

export function legalPagesForMarket(marketKey = 'global') {
  return legalSurface(marketKey).legalPages;
}

export function legalPage(key, marketKey = 'global') {
  return legalPagesForMarket(marketKey)[key];
}

export function legalPageEntries(marketKey = 'global') {
  return Object.entries(legalPagesForMarket(marketKey));
}
