'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

async function loadOnboardingStatus() {
  const response = await fetch('/backend/business/register/onboarding', {
    credentials: 'include',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
    },
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(payload.error || payload.message || `Request failed: ${response.status}`);
    error.payload = payload;
    throw error;
  }

  return payload;
}

const DEFAULT_PENDING_PAYLOAD = {
  eyebrow: 'Заявка отправлена',
  headline: 'Мы проверяем компанию',
  body: 'Подпись получили, компанию взяли в работу. Обычно мы просто сверяем данные и открываем доступ. Если понадобится что-то уточнить, напишем на email компании.',
  notice: 'Все в порядке, сейчас ничего делать не нужно. Если нам понадобятся детали, мы напишем на подтвержденный email компании.',
  steps: [
    {
      title: 'Все подписано',
      body: 'Заявка связана с вашим профилем и компанией.',
    },
    {
      title: 'Смотрим компанию',
      body: 'Проверим ИНН, название и базовые данные, чтобы не открыть кабинет случайной организации.',
    },
    {
      title: 'Откроем кабинет',
      body: 'После одобрения здесь появится доступ к заказам, товарам и настройкам.',
    },
  ],
};

export function BusinessOnboardingStatus({ initialPayload = null }) {
  const [payload, setPayload] = useState(initialPayload);
  const [error, setError] = useState('');

  useEffect(() => {
    let isMounted = true;

    if (initialPayload) {
      setPayload(initialPayload);
      return undefined;
    }

    loadOnboardingStatus()
      .then((result) => {
        if (!isMounted) {
          return;
        }

        if (result.redirect) {
          window.location.href = result.redirect;
          return;
        }

        setPayload(result);
      })
      .catch((exception) => {
        const redirect = exception.payload?.redirect;
        if (redirect) {
          window.location.href = redirect;
          return;
        }

        if (isMounted) {
          setError(exception.message);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [initialPayload]);

  const displayPayload = payload || DEFAULT_PENDING_PAYLOAD;
  const entity = payload?.legal_entity || {};
  const steps = displayPayload.steps || [];

  return (
    <section className="business-onboarding-panel">
      <div className="business-register-card business-onboarding-card">
        <div className="business-onboarding-brand">
          <span className="brand-switcher__mark" aria-hidden="true" />
          <strong>MEANLY MERCHANT CENTER</strong>
        </div>

        <span>{displayPayload.eyebrow}</span>
        <h2>{displayPayload.headline}</h2>
        <p>
          {displayPayload.body}
        </p>

        {steps.length > 0 ? (
          <div className="business-onboarding-steps">
            {steps.map((step, index) => (
              <div className="business-onboarding-step" key={step.title}>
                <span className="business-onboarding-num">{index + 1}</span>
                <div>
                  <strong>{step.title}</strong>
                  <span>{step.body}</span>
                </div>
              </div>
            ))}
          </div>
        ) : null}

        {displayPayload.notice ? (
          <div className="business-onboarding-notice">
            {displayPayload.notice}
          </div>
        ) : null}

        {payload ? (
          <div className="business-onboarding-meta">
            Компания: {entity.name || 'профиль создается'}<br />
            Статус: {entity.status_label || entity.status || 'проверяем компанию'}<br />
            Email компании: {entity.email || 'не указан'}<br />
            Подано: {entity.submitted_at || 'сейчас'}
          </div>
        ) : null}

        {error ? <p className="product-card__reason">{error}</p> : null}

        <div className="business-onboarding-actions">
          <Link className="button-primary" href="/">Вернуться на витрину</Link>
          <Link href="/merchant">Merchant Center</Link>
        </div>
      </div>
    </section>
  );
}
