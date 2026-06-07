'use client';

import { useRouter } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';
import { BusinessOnboardingStatus } from './BusinessOnboardingStatus';

const taxRules = {
  RU: { label: 'INN', placeholder: '7700123456 or 123456789012', lengths: [10, 12], max: 12, hint: '10 digits for company, 12 for individual entrepreneur' },
  KZ: { label: 'BIN / IIN', placeholder: '123456789012', lengths: [12], max: 12, hint: '12 digits' },
  BY: { label: 'UNP', placeholder: '123456789', lengths: [9], max: 9, hint: '9 digits' },
  UZ: { label: 'INN / PINFL', placeholder: '123456789', lengths: [9, 14], max: 14, hint: '9 digits for company, 14 for person' },
  AM: { label: 'INN', placeholder: '12345678', lengths: [8], max: 8, hint: '8 digits' },
  KG: { label: 'INN', placeholder: '12345678901234', lengths: [14], max: 14, hint: '14 digits' },
  TM: { label: 'TIN', placeholder: '12345678', lengths: [8], max: 8, hint: '8 digits' },
};

function normalizeDigits(value, max = 14) {
  return String(value || '').replace(/\D+/g, '').slice(0, max);
}

function personNameFromOrg(org) {
  const management = typeof org?.management === 'string' ? org.management : org?.management?.name;
  return String(org?.is_ip ? (org.fio || org.name) : (management || org?.fio || '')).replace(/^ИП\s+/iu, '').replace(/\s+/g, ' ').trim();
}

async function jsonRequest(path, { body, method = 'POST', csrfToken } = {}) {
  const response = await fetch(`/backend${path}`, {
    method,
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = payload.message || payload.error || Object.values(payload.errors || {})?.[0]?.[0] || `Request failed: ${response.status}`;
    throw new Error(message);
  }

  return payload;
}

export function BusinessRegistrationForm({ initialApplicationChecked = false }) {
  const router = useRouter();
  const [csrfToken, setCsrfToken] = useState('');
  const [applicationChecked, setApplicationChecked] = useState(initialApplicationChecked);
  const [hasExistingApplication, setHasExistingApplication] = useState(false);
  const [email, setEmail] = useState('');
  const [emailCode, setEmailCode] = useState('');
  const [emailVerified, setEmailVerified] = useState(false);
  const [jurisdiction, setJurisdiction] = useState('RU');
  const [inn, setInn] = useState('');
  const [org, setOrg] = useState(null);
  const [orgStatus, setOrgStatus] = useState('');
  const [isOrgConfirmed, setIsOrgConfirmed] = useState(false);
  const [signerRole, setSignerRole] = useState('ceo');
  const [signerName, setSignerName] = useState('');
  const [taxSystem, setTaxSystem] = useState('OSN');
  const [manualAddress, setManualAddress] = useState('');
  const [mode, setMode] = useState('business');
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const [isBusy, setIsBusy] = useState(false);
  const rule = taxRules[jurisdiction] || taxRules.RU;
  const normalizedInn = useMemo(() => normalizeDigits(inn, rule.max), [inn, rule.max]);
  const canSearch = rule.lengths.includes(normalizedInn.length);
  const directorName = personNameFromOrg(org);
  const isIp = Boolean(org?.is_ip || org?.raw_type === 'INDIVIDUAL');
  const activeStep = !emailVerified ? 'email' : (!isOrgConfirmed ? 'company' : 'signer');

  useEffect(() => {
    jsonRequest('/csrf-token', { method: 'GET' })
      .then((payload) => setCsrfToken(payload.csrf_token || ''))
      .catch(() => setError('Could not prepare protected form. Refresh and try again.'));

    if (!initialApplicationChecked) {
      fetch('/backend/business/register/onboarding', {
        credentials: 'include',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      })
        .then((response) => response.ok ? response.json() : null)
        .then((payload) => {
          if (payload?.redirect === '/partner') {
            router.push('/partner');
            return;
          }

          if (payload?.legal_entity) {
            setHasExistingApplication(true);
          }
        })
        .catch(() => null)
        .finally(() => setApplicationChecked(true));
    }
  }, [initialApplicationChecked, router]);

  function resetOrg() {
    setOrg(null);
    setOrgStatus('');
    setIsOrgConfirmed(false);
    setManualAddress('');
    setMode('business');
  }

  async function sendEmailCode() {
    setError('');
    setStatus('');
    setIsBusy(true);

    try {
      await jsonRequest('/business/register/email/send', {
        csrfToken,
        body: { email: email.trim().toLowerCase() },
      });
      setStatus('Code sent. Enter it below.');
    } catch (exception) {
      setError(exception.message);
    } finally {
      setIsBusy(false);
    }
  }

  async function verifyEmailCode() {
    setError('');
    setStatus('');
    setIsBusy(true);

    try {
      const payload = await jsonRequest('/business/register/email/verify', {
        csrfToken,
        body: { email: email.trim().toLowerCase(), code: emailCode.trim() },
      });
      setEmailVerified(true);
      setEmail(payload.email || email.trim().toLowerCase());
      setStatus(payload.message || 'Email verified. Now check INN.');
    } catch (exception) {
      setError(exception.message);
    } finally {
      setIsBusy(false);
    }
  }

  async function searchInn() {
    setError('');
    setStatus('');
    resetOrg();

    if (!canSearch) {
      setOrgStatus(rule.hint);
      return;
    }

    if (jurisdiction !== 'RU') {
      setOrgStatus('Foreign tax ID accepted. Add company details and continue.');
      setIsOrgConfirmed(true);
      return;
    }

    setIsBusy(true);
    setOrgStatus('Checking INN...');

    try {
      const payload = await jsonRequest('/api/b2b/search', {
        csrfToken,
        body: { inn: normalizedInn },
      });
      const suggestion = payload.suggestions?.[0];

      if (suggestion && String(suggestion.inn || '') === normalizedInn) {
        setOrg(suggestion);
        setManualAddress(suggestion.address || '');
        setTaxSystem(suggestion.tax_system || 'OSN');
        setOrgStatus('Company found. Confirm it to continue.');
        return;
      }

      if (payload.npd?.status === true) {
        setMode('self_employed');
        setOrgStatus('Self-employed status confirmed. You can continue as NPD.');
        setIsOrgConfirmed(true);
        setTaxSystem('NPD');
        return;
      }

      if (normalizedInn.length === 12) {
        setMode('profile');
        setOrgStatus('No active company or IP was found for this INN. You can continue as an individual profile.');
        return;
      }

      setOrgStatus('INN was not found in DaData.');
    } catch (exception) {
      setError(exception.message || 'INN check failed.');
      setOrgStatus('INN check failed.');
    } finally {
      setIsBusy(false);
    }
  }

  async function submitRegistration(event) {
    event.preventDefault();
    setError('');
    setStatus('');

    if (!emailVerified) {
      setError('Verify business email first.');
      return;
    }

    if (!isOrgConfirmed && mode === 'business') {
      setError('Confirm the company before continuing.');
      return;
    }

    if (signerRole === 'representative' && !signerName.trim()) {
      setError('Add representative full name.');
      return;
    }

    const payload = {
      registration_target: 'legal_entity',
      registration_mode: mode,
      business_email: email.trim().toLowerCase(),
      inn: normalizedInn,
      jurisdiction,
      signer_role: signerRole,
      signer_name: signerRole === 'representative' ? signerName.trim() : '',
      tax_system: taxSystem,
      dadata_verified: isOrgConfirmed ? '1' : '0',
      dadata_party_type: mode === 'self_employed' ? 'NPD' : (org?.raw_type || (isIp ? 'INDIVIDUAL' : 'LEGAL')),
      legal_name: org?.name || (mode === 'self_employed' ? `Самозанятый ${normalizedInn}` : ''),
      ogrn: org?.ogrn || '',
      kpp: org?.kpp || '',
      address: manualAddress || org?.address || '',
      director_name: signerRole === 'representative' ? signerName.trim() : directorName,
    };

    setIsBusy(true);

    try {
      const result = await jsonRequest('/business/register', {
        csrfToken,
        body: payload,
      });

      if (result.redirect) {
        router.push(String(result.redirect).includes('/partner/register/offer')
          ? '/business/register/offer'
          : result.redirect);
        return;
      }

      setStatus('Company details saved.');
    } catch (exception) {
      setError(exception.message);
    } finally {
      setIsBusy(false);
    }
  }

  if (!applicationChecked) {
    return (
      <section className="business-register-panel">
        <div className="business-register-card">
          <span>Checking status</span>
          <h2>Preparing Merchant Center...</h2>
          <p>Meanly is checking whether this identity already has a merchant application.</p>
        </div>
      </section>
    );
  }

  if (hasExistingApplication) {
    return <BusinessOnboardingStatus />;
  }

  return (
    <form className="business-register-panel" onSubmit={submitRegistration}>
      {activeStep === 'email' ? (
        <section className="business-register-card">
          <span>Step 1 of 3</span>
          <h2>Confirm business email.</h2>
          <p>We use this email for moderation, seller access, and company requests.</p>
          <div className="business-register-row">
            <input
              disabled={emailVerified}
              onChange={(event) => setEmail(event.target.value)}
              placeholder="founder@company.com"
              type="email"
              value={email}
            />
            <button disabled={isBusy || emailVerified || !email.trim()} onClick={sendEmailCode} type="button">
              Get code
            </button>
          </div>
          <div className="business-register-row">
            <input
              onChange={(event) => setEmailCode(event.target.value)}
              placeholder="Code from email"
              value={emailCode}
            />
            <button disabled={isBusy || !email.trim() || !emailCode.trim()} onClick={verifyEmailCode} type="button">
              Verify
            </button>
          </div>
        </section>
      ) : null}

      {activeStep === 'company' ? (
        <section className="business-register-card">
          <span>Step 2 of 3</span>
          <h2>Add company by INN.</h2>
          <p>Find your legal entity or IP by tax ID, then confirm it before continuing.</p>
          <label>
            Jurisdiction
            <select disabled={isBusy} onChange={(event) => { setJurisdiction(event.target.value); setInn(''); resetOrg(); }} value={jurisdiction}>
              <option value="RU">Russia</option>
              <option value="KZ">Kazakhstan</option>
              <option value="BY">Belarus</option>
              <option value="UZ">Uzbekistan</option>
              <option value="AM">Armenia</option>
              <option value="KG">Kyrgyzstan</option>
              <option value="TM">Turkmenistan</option>
            </select>
          </label>
          <label>
            {rule.label}
            <div className="business-register-row">
              <input
                disabled={isBusy}
                inputMode="numeric"
                maxLength={rule.max}
                onChange={(event) => { setInn(normalizeDigits(event.target.value, rule.max)); resetOrg(); }}
                placeholder={rule.placeholder}
                value={inn}
              />
              <button disabled={isBusy || !normalizedInn} onClick={searchInn} type="button">
                Check
              </button>
            </div>
          </label>
          <p className="product-card__muted">{rule.hint}</p>
          {orgStatus ? <p className="checkout-note">{orgStatus}</p> : null}
          {org ? (
            <div className="business-org-card">
              <span>Found company</span>
              <strong>{org.name}</strong>
              <p>INN {org.inn}{org.ogrn ? ` · OGRN ${org.ogrn}` : ''}</p>
              <button disabled={isBusy} onClick={() => setIsOrgConfirmed(true)} type="button">
                Yes, this is my company
              </button>
            </div>
          ) : null}
          {mode === 'profile' ? (
            <div className="business-org-card">
              <span>Individual profile</span>
              <strong>Company was not found.</strong>
              <p>You can continue as an individual profile, but selling tools require IP or legal entity.</p>
              <button disabled={isBusy} onClick={() => setIsOrgConfirmed(true)} type="button">
                Continue as individual
              </button>
            </div>
          ) : null}
        </section>
      ) : null}

      {activeStep === 'signer' ? (
        <section className="business-register-card">
        <span>Step 3 of 3</span>
        <h2>Signer details.</h2>
        <label>
          Tax system
          <select disabled={isBusy} onChange={(event) => setTaxSystem(event.target.value)} value={taxSystem}>
            <option value="OSN">OSN</option>
            <option value="USN">USN</option>
            <option value="AUSN">AUSN</option>
            <option value="USN_INCOME">USN Income</option>
            <option value="NPD">NPD</option>
          </select>
        </label>
        {(isIp || mode === 'self_employed') ? (
          <label>
            Registration address
            <textarea disabled={isBusy} onChange={(event) => setManualAddress(event.target.value)} value={manualAddress} />
          </label>
        ) : null}
        <div className="business-radio-grid">
          <label>
            <input checked={signerRole === 'ceo'} disabled={isBusy} name="signer_role" onChange={() => setSignerRole('ceo')} type="radio" />
            I am the company director
          </label>
          <label>
            <input checked={signerRole === 'representative'} disabled={isBusy} name="signer_role" onChange={() => setSignerRole('representative')} type="radio" />
            I act by power of attorney
          </label>
        </div>
        {signerRole === 'representative' ? (
          <label>
            Representative full name
            <input disabled={isBusy} onChange={(event) => setSignerName(event.target.value)} placeholder="Ivanov Ivan Ivanovich" value={signerName} />
          </label>
        ) : null}
        <button disabled={isBusy || !isOrgConfirmed || !csrfToken} type="submit">
          Continue onboarding
        </button>
      </section>
      ) : null}

      {status ? <p className="checkout-note">{status}</p> : null}
      {error ? <p className="product-card__reason">{error}</p> : null}
    </form>
  );
}
