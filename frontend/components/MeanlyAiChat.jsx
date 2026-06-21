'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { fetchStorefrontCatalog, submitStorefrontChat } from '../lib/storefront-api';
import { MeanlyLoadingMark } from './MeanlyLoadingMark';

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

function groupHref(group) {
  if (group.links?.self) {
    return localHref(group.links.self);
  }

  return group.slug ? `/products/${group.slug}` : '/';
}

function productHref(product) {
  return product.slug ? `/products/${product.slug}` : '/';
}

function collectResults(catalog = {}) {
  const groups = (catalog.product_groups || catalog.products?.groups || []).slice(0, 3);
  const products = [
    ...(catalog.products?.featured || []),
    ...(catalog.products?.browse || []),
  ].slice(0, 3);
  const categories = (catalog.categories || []).slice(0, 3);

  return { offers: [], groups, products, categories };
}

const EMPTY_RESULTS = Object.freeze({
  offers: [],
  groups: [],
  products: [],
  categories: [],
});

function normalizeResults(results) {
  if (!results || typeof results !== 'object') {
    return EMPTY_RESULTS;
  }

  return {
    offers: Array.isArray(results.offers) ? results.offers : [],
    groups: Array.isArray(results.groups) ? results.groups : [],
    products: Array.isArray(results.products) ? results.products : [],
    categories: Array.isArray(results.categories) ? results.categories : [],
  };
}

function collectChatResults(payload = {}) {
  const offers = (payload.products || []).slice(0, 6).map((product) => ({
    key: `offer-${product.url || product.name}`,
    href: localHref(product.url),
    title: product.name,
    meta: [
      product.region,
      product.price && product.price !== 'Coming soon' ? product.price : null,
    ].filter(Boolean).join(' · '),
  }));

  return { offers, groups: [], products: [], categories: [] };
}

function resultCount(results) {
  const normalized = normalizeResults(results);

  return normalized.offers.length
    + normalized.groups.length
    + normalized.products.length
    + normalized.categories.length;
}

function assistantCopy(question, results) {
  const count = resultCount(results);

  if (!count) {
    return `No matches for "${question}" yet. Try a brand, region, or product type.`;
  }

  return count === 1 ? 'One match in the catalog.' : `${count} matches in the catalog.`;
}

function assistantLeadText(results) {
  const count = resultCount(results);

  if (!count) {
    return null;
  }

  return count === 1 ? 'One match' : `${count} matches`;
}

function ResultCards({ results }) {
  const normalized = normalizeResults(results);
  const cards = [
    ...normalized.offers,
    ...normalized.groups.map((group) => ({
      key: `group-${group.id || group.name}`,
      href: groupHref(group),
      title: group.name,
      meta: group.region || null,
    })),
    ...normalized.products.map((product) => ({
      key: `product-${product.id || product.slug}`,
      href: productHref(product),
      title: product.name,
      meta: product.region || null,
    })),
    ...normalized.categories.map((category) => ({
      key: `category-${category.slug || category.name}`,
      href: category.slug ? `/catalog/${category.slug}` : '/',
      title: category.label || category.name,
      meta: null,
    })),
  ];

  if (!cards.length) {
    return null;
  }

  return (
    <div className="ai-result-grid">
      {cards.map((card) => (
        <Link className="ai-result-card" href={card.href || '/'} key={card.key}>
          <strong>{card.title}</strong>
          {card.meta ? <span>{card.meta}</span> : null}
        </Link>
      ))}
    </div>
  );
}

function displayAssistantText(text, results) {
  const normalized = normalizeResults(results);

  if (resultCount(normalized) > 0) {
    return assistantLeadText(normalized);
  }

  const cleaned = String(text || '')
    .split('\n')
    .filter((line) => !/^\s*-\s*\[/.test(line))
    .join('\n')
    .replace(/available in catalog; active offer will appear later/gi, '')
    .replace(/grouped product: choose region and denomination on the page/gi, '')
    .replace(/есть в каталоге, активный оффер появится позже/gi, '')
    .replace(/групповой товар: выберите регион и номинал на странице/gi, '')
    .replace(/\s*—\s*,/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();

  if (!cleaned) {
    return null;
  }

  const firstParagraph = cleaned.split('\n\n')[0].trim();

  return firstParagraph.length > 180 ? `${firstParagraph.slice(0, 177).trim()}…` : firstParagraph;
}

function resolveAssistantText(rawText, question, results) {
  return displayAssistantText(rawText, results)
    || assistantCopy(question, results);
}

function B2bNote({ email, message }) {
  if (!email || !message) {
    return null;
  }

  const parts = String(message).split(email);

  return (
    <div className="ai-b2b-note">
      <p>
        {parts[0]}
        <a href={`mailto:${email}?subject=${encodeURIComponent('Wholesale / B2B inquiry')}`}>{email}</a>
        {parts[1] || ''}
      </p>
    </div>
  );
}

function MessageText({ text }) {
  const lines = String(text || '').split('\n');

  return (
    <>
      {lines.map((line, lineIndex) => {
        const parts = [];
        const pattern = /\[([^\]]+)\]\(([^)]+)\)/g;
        let lastIndex = 0;
        let match;

        while ((match = pattern.exec(line)) !== null) {
          if (match.index > lastIndex) {
            parts.push(line.slice(lastIndex, match.index));
          }
          parts.push(
            <Link href={localHref(match[2])} key={`${lineIndex}-${match.index}`}>
              {match[1]}
            </Link>,
          );
          lastIndex = pattern.lastIndex;
        }

        if (lastIndex < line.length) {
          parts.push(line.slice(lastIndex));
        }

        if (!line.trim()) {
          return null;
        }

        return <p key={`${lineIndex}-${line}`}>{parts.length ? parts : line}</p>;
      })}
    </>
  );
}

export function MeanlyAiChat({ initialQuery = '' }) {
  const [input, setInput] = useState('');
  const messageListRef = useRef(null);
  const initialQuerySubmitted = useRef(false);
  const [messages, setMessages] = useState([
    {
      id: 'welcome',
      role: 'assistant',
      text: 'What are you looking for?',
    },
  ]);
  const [isSending, setIsSending] = useState(false);

  useEffect(() => {
    const messageList = messageListRef.current;

    if (messageList) {
      messageList.scrollTop = messageList.scrollHeight;
    }
  }, [messages, isSending]);

  useEffect(() => {
    if (initialQuerySubmitted.current || !initialQuery.trim()) {
      return;
    }

    initialQuerySubmitted.current = true;
    submitQuestion(initialQuery);
  }, [initialQuery]);

  async function submitQuestion(question) {
    const trimmed = question.trim();

    if (!trimmed || isSending) {
      return;
    }

    const userMessage = {
      id: `user-${Date.now()}`,
      role: 'user',
      text: trimmed,
    };

    setInput('');
    setIsSending(true);
    setMessages((current) => [...current, userMessage]);

    try {
      const history = messages
        .filter((message) => message.role === 'user' || message.role === 'assistant')
        .map((message) => ({
          role: message.role,
          content: message.text,
        }));
      const payload = await submitStorefrontChat(trimmed, history);
      const results = collectChatResults(payload);
      const hasResults = resultCount(results) > 0;
      const rawText = payload.response?.trim() || '';

      setMessages((current) => [
        ...current,
        {
          id: `assistant-${Date.now()}`,
          role: 'assistant',
          text: resolveAssistantText(rawText, trimmed, hasResults ? results : null),
          results: hasResults ? results : null,
          b2b: payload.b2b?.active ? payload.b2b : null,
        },
      ]);
    } catch (error) {
      try {
        const catalog = await fetchStorefrontCatalog(trimmed);
        const results = collectResults(catalog);
        const hasResults = resultCount(results) > 0;
        setMessages((current) => [
          ...current,
          {
            id: `assistant-fallback-${Date.now()}`,
            role: 'assistant',
            text: resolveAssistantText(assistantCopy(trimmed, results), trimmed, hasResults ? results : null),
            results: hasResults ? results : null,
          },
        ]);
      } catch {
        setMessages((current) => [
          ...current,
          {
            id: `assistant-error-${Date.now()}`,
            role: 'assistant',
            text: error.message || 'Meanly AI could not reach catalog retrieval right now.',
            results: null,
          },
        ]);
      }
    } finally {
      setIsSending(false);
    }
  }

  function handleSubmit(event) {
    event.preventDefault();
    submitQuestion(input);
  }

  return (
    <section aria-label="Meanly AI chat" className="ai-chat-shell">
      <div className="ai-chat-panel">
        <div className="ai-message-list" aria-live="polite" ref={messageListRef}>
          {messages.map((message) => {
            const normalizedResults = normalizeResults(message.results);
            const hasResultCards = resultCount(normalizedResults) > 0;
            const visibleText = message.text?.trim();

            if (!visibleText && !hasResultCards && !message.b2b?.active) {
              return null;
            }

            return (
              <article
                aria-label={message.role === 'assistant' ? 'Meanly AI' : 'Your message'}
                className={`ai-message ai-message--${message.role}${hasResultCards ? ' ai-message--cards' : ''}${message.b2b?.active ? ' ai-message--b2b' : ''}`}
                key={message.id}
              >
                {visibleText ? <MessageText text={visibleText} /> : null}
                {message.b2b?.active ? <B2bNote email={message.b2b.email} message={message.b2b.message} /> : null}
                {hasResultCards ? <ResultCards results={normalizedResults} /> : null}
              </article>
            );
          })}
          {isSending ? (
            <div aria-live="polite" className="ai-chat-pending">
              <MeanlyLoadingMark className="meanly-loading-mark--inline" label="Thinking…" size="xs" />
            </div>
          ) : null}
        </div>

        <form className="ai-chat-composer" onSubmit={handleSubmit}>
          <input
            aria-label="Ask Meanly AI"
            onChange={(event) => setInput(event.target.value)}
            placeholder="Ask about a product…"
            value={input}
          />
          <button disabled={isSending || !input.trim()} type="submit">
            Send
          </button>
        </form>
      </div>
    </section>
  );
}
