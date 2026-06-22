import assert from 'node:assert/strict';
import test from 'node:test';

import {
  buildProviderRegistryFromEntries,
  normalizeEip6963ProviderDetail,
  normalizeLegacyProviderDetail,
} from './evm-wallet-discovery.js';

test('merge keeps both metamask and rabby providers', () => {
  const metamaskProvider = { isMetaMask: true, request: () => {} };
  const rabbyProvider = { isRabby: true, request: () => {} };

  const providers = buildProviderRegistryFromEntries([
    normalizeEip6963ProviderDetail({
      info: { rdns: 'io.metamask', name: 'MetaMask', uuid: 'mm-1' },
      provider: metamaskProvider,
    }),
    normalizeEip6963ProviderDetail({
      info: { rdns: 'io.rabby', name: 'Rabby', uuid: 'rb-1' },
      provider: rabbyProvider,
    }),
  ]);

  assert.equal(providers.length, 2);
  assert.deepEqual(
    providers.map((entry) => entry.providerId).sort(),
    ['io.metamask', 'io.rabby'],
  );
});

test('legacy duplicate is ignored when eip6963 provider already exists', () => {
  const metamaskProvider = { isMetaMask: true, request: () => {} };

  const providers = buildProviderRegistryFromEntries([
    normalizeEip6963ProviderDetail({
      info: { rdns: 'io.metamask', name: 'MetaMask', uuid: 'mm-1' },
      provider: metamaskProvider,
    }),
    normalizeLegacyProviderDetail(metamaskProvider),
  ]);

  assert.equal(providers.length, 1);
  assert.equal(providers[0].source, 'eip6963');
});

test('rabby-only legacy provider resolves without metamask branding requirement', () => {
  const rabbyProvider = { isRabby: true, request: () => {} };
  const entry = normalizeLegacyProviderDetail(rabbyProvider);

  assert.equal(entry.providerId, 'io.rabby');
  assert.equal(entry.label, 'Rabby');
});
