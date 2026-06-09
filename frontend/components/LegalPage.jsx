import Link from 'next/link';
import { companyDetails, legalPageEntries } from '../lib/legal-pages';

export function LegalPage({ pageKey, page }) {
  return (
    <main className="page legal-page">
      <section className="legal-hero">
        <span className="legal-eyebrow">{page.eyebrow}</span>
        <h1>{page.title}</h1>
        <p>{page.description}</p>
        <nav aria-label="Юридические документы" className="legal-nav">
          {legalPageEntries().map(([key, item]) => (
            <Link key={key} href={item.href} className={key === pageKey ? 'active' : ''}>
              {item.title}
            </Link>
          ))}
        </nav>
      </section>

      <div className="legal-grid">
        <div className="legal-stack">
          {page.sections.map((section) => (
            <article className="legal-card" key={section.title}>
              <h2>{section.title}</h2>
              <ul>
                {section.items.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </article>
          ))}
        </div>

        <aside className="legal-card legal-requisites">
          <h2>Реквизиты и оплата</h2>
          <dl>
            <div><dt>Бренд</dt><dd>{companyDetails.brand}</dd></div>
            <div><dt>Юр. лицо</dt><dd>{companyDetails.legalName}</dd></div>
            <div><dt>Страна</dt><dd>{companyDetails.country}</dd></div>
            <div><dt>Юр. адрес</dt><dd>{companyDetails.legalAddress}</dd></div>
            <div><dt>Факт. адрес</dt><dd>{companyDetails.actualAddress}</dd></div>
            <div><dt>Телефон</dt><dd>{companyDetails.phone}</dd></div>
            <div><dt>Email</dt><dd>{companyDetails.email}</dd></div>
            <div><dt>ИНН</dt><dd>{companyDetails.inn}</dd></div>
            <div><dt>КПП</dt><dd>{companyDetails.kpp}</dd></div>
            <div><dt>ОГРН</dt><dd>{companyDetails.ogrn}</dd></div>
            <div><dt>Эквайринг</dt><dd>{companyDetails.acquiringBank}</dd></div>
            <div><dt>HTTPS</dt><dd>{companyDetails.sslLevel}</dd></div>
            <div><dt>Карты</dt><dd>{companyDetails.paymentSystems.join(', ')}</dd></div>
          </dl>
          <p className="legal-note">
            Перед заявкой в банк замените NEXT_PUBLIC_ACQUIRING_COMPANY_* и NEXT_PUBLIC_ACQUIRING_BANK_NAME
            на согласованные юридические данные.
          </p>
        </aside>
      </div>
    </main>
  );
}
