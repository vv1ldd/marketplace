'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { fetchStorefrontCatalog } from '../lib/storefront-api';

function groupHref(group) {
  if (group.links?.self) {
    try {
      const url = new URL(group.links.self);
      return `${url.pathname}${url.search}${url.hash}`;
    } catch {
      return group.links.self;
    }
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

  return { groups, products, categories };
}

function resultCount(results) {
  return results.groups.length + results.products.length + results.categories.length;
}

function assistantCopy(question, results) {
  const count = resultCount(results);

  if (!count) {
    return `I could not find exact catalog matches for "${question}" yet. Try a brand, product type, region, or denomination.`;
  }

  const lead = results.groups[0]?.name || results.products[0]?.name || results.categories[0]?.label;

  return `I found ${count} catalog ${count === 1 ? 'match' : 'matches'} for "${question}". Start with ${lead}, then open the result to choose region, value, and checkout options.`;
}

function ResultCards({ results }) {
  const cards = [
    ...results.groups.map((group) => ({
      key: `group-${group.id || group.name}`,
      href: groupHref(group),
      label: 'Product group',
      title: group.name,
      meta: [
        group.variant_group?.variant_count ? `${group.variant_group.variant_count} variants` : null,
        group.variant_group?.region_count ? `${group.variant_group.region_count} regions` : null,
      ].filter(Boolean).join(' · '),
    })),
    ...results.products.map((product) => ({
      key: `product-${product.id || product.slug}`,
      href: productHref(product),
      label: product.category?.label || 'Product',
      title: product.name,
      meta: product.region || 'global',
    })),
    ...results.categories.map((category) => ({
      key: `category-${category.slug || category.name}`,
      href: category.slug ? `/catalog/${category.slug}` : '/',
      label: 'Category',
      title: category.label || category.name,
      meta: category.count ? `${category.count} products` : 'Browse category',
    })),
  ];

  if (!cards.length) {
    return null;
  }

  return (
    <div className="ai-result-grid">
      {cards.map((card) => (
        <Link className="ai-result-card" href={card.href} key={card.key}>
          <span>{card.label}</span>
          <strong>{card.title}</strong>
          {card.meta ? <p>{card.meta}</p> : null}
        </Link>
      ))}
    </div>
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
      text: 'Hi. Ask me what to buy, where to find a brand, or which catalog group fits an intent.',
      results: null,
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
      const catalog = await fetchStorefrontCatalog(trimmed);
      const results = collectResults(catalog);
      const assistantMessage = {
        id: `assistant-${Date.now()}`,
        role: 'assistant',
        text: assistantCopy(trimmed, results),
        results,
      };

      setMessages((current) => [...current, assistantMessage]);
    } catch (error) {
      setMessages((current) => [
        ...current,
        {
          id: `assistant-error-${Date.now()}`,
          role: 'assistant',
          text: error.message || 'Meanly AI could not reach catalog retrieval right now.',
          results: null,
        },
      ]);
    } finally {
      setIsSending(false);
    }
  }

  function handleSubmit(event) {
    event.preventDefault();
    submitQuestion(input);
  }

  return (
    <section className="ai-chat-shell">
      <div className="ai-chat-panel">
        <div className="ai-chat-topbar">
          <div>
            <strong>Meanly AI</strong>
            <span>Catalog-aware marketplace chat</span>
          </div>
          <span className="ai-chat-status">Online</span>
        </div>

        <div className="ai-message-list" aria-live="polite" ref={messageListRef}>
          {messages.map((message) => (
            <article className={`ai-message ai-message--${message.role}`} key={message.id}>
              <span>{message.role === 'assistant' ? 'Meanly AI' : 'You'}</span>
              <p>{message.text}</p>
              {message.results ? <ResultCards results={message.results} /> : null}
            </article>
          ))}
          {isSending ? (
            <article className="ai-message ai-message--assistant">
              <span>Meanly AI</span>
              <p>Looking through the catalog...</p>
            </article>
          ) : null}
        </div>

        <form className="ai-chat-composer" onSubmit={handleSubmit}>
          <input
            aria-label="Ask Meanly AI"
            onChange={(event) => setInput(event.target.value)}
            placeholder="Ask for a product, brand, subscription, region..."
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
