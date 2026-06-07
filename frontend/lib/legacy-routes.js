export function queryString(searchParams = {}) {
  const query = new URLSearchParams();

  Object.entries(searchParams || {}).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      value.forEach((item) => query.append(key, item));
      return;
    }

    if (value !== undefined) {
      query.set(key, value);
    }
  });

  return query.toString();
}

export function withQuery(path, searchParams = {}) {
  const query = queryString(searchParams);

  return `${path}${query ? `?${query}` : ''}`;
}
