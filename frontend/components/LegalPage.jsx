import Link from 'next/link';
import { companyDetails, legalPageEntries } from '../lib/legal-pages';

export function LegalPage({ pageKey, page, marketKey = 'global' }) {
  const details = companyDetails(marketKey);
  const labels = details.labels;
  const entries = legalPageEntries(marketKey);

  return (
    <main className="page legal-page">
      <section className="legal-hero">
        <span className="legal-eyebrow">{page.eyebrow}</span>
        <h1>{page.title}</h1>
        <p>{page.description}</p>
        <div className="legal-nav-shell">
          <nav aria-label={details.navLabel} className="legal-nav">
            {entries.map(([key, item]) => (
              <Link key={key} href={item.href} className={key === pageKey ? 'active' : ''}>
                {item.title}
              </Link>
            ))}
          </nav>
        </div>
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
          <h2>{details.requisitesTitle}</h2>
          <dl>
            <div><dt>{labels.brand}</dt><dd>{details.brand}</dd></div>
            <div><dt>{labels.legalName}</dt><dd>{details.legalName}</dd></div>
            <div><dt>{labels.country}</dt><dd>{details.country}</dd></div>
            <div><dt>{labels.legalAddress}</dt><dd>{details.legalAddress}</dd></div>
            <div><dt>{labels.actualAddress}</dt><dd>{details.actualAddress}</dd></div>
            <div><dt>{labels.phone}</dt><dd>{details.phone}</dd></div>
            <div><dt>{labels.email}</dt><dd>{details.email}</dd></div>
            {details.showTaxIds ? (
              <>
                <div><dt>{labels.inn}</dt><dd>{details.inn}</dd></div>
                <div><dt>{labels.kpp}</dt><dd>{details.kpp}</dd></div>
                <div><dt>{labels.ogrn}</dt><dd>{details.ogrn}</dd></div>
              </>
            ) : null}
            <div><dt>{labels.acquiringBank}</dt><dd>{details.acquiringBank}</dd></div>
            <div><dt>{labels.sslLevel}</dt><dd>{details.sslLevel}</dd></div>
            <div><dt>{labels.paymentSystems}</dt><dd>{details.paymentSystems.join(', ')}</dd></div>
          </dl>
          <p className="legal-note">{details.requisitesNote}</p>
        </aside>
      </div>
    </main>
  );
}
