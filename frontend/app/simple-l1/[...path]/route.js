const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');

function storefrontHost(request) {
  const host = request.headers.get('host') || 'meanly.test';
  return host.split(':')[0].toLowerCase();
}

async function proxySimpleL1Request(request, pathSegments) {
  const path = pathSegments.filter(Boolean).join('/');
  const sourceUrl = new URL(request.url);
  const target = `${apiUrl}/simple-l1/${path}${sourceUrl.search}`;
  const host = storefrontHost(request);
  const headers = new Headers();

  headers.set('Accept', request.headers.get('accept') || 'text/html');
  headers.set('X-Forwarded-Host', host);
  headers.set('X-Storefront-Host', host);
  headers.set('X-Forwarded-Proto', request.headers.get('x-forwarded-proto') || 'https');

  const contentType = request.headers.get('content-type');
  if (contentType) {
    headers.set('Content-Type', contentType);
  }

  const requestedWith = request.headers.get('x-requested-with');
  if (requestedWith) {
    headers.set('X-Requested-With', requestedWith);
  }

  const cookie = request.headers.get('cookie');
  if (cookie) {
    headers.set('Cookie', cookie);
  }

  const upstream = await fetch(target, {
    method: request.method,
    headers,
    body: request.method === 'GET' || request.method === 'HEAD' ? undefined : await request.text(),
    cache: 'no-store',
    redirect: 'manual',
  });

  const responseHeaders = new Headers();
  for (const key of ['content-type', 'location', 'set-cookie']) {
    const value = upstream.headers.get(key);
    if (value) {
      responseHeaders.set(key, value);
    }
  }

  return new Response(await upstream.text(), {
    status: upstream.status,
    headers: responseHeaders,
  });
}

export async function GET(request, context) {
  const params = await context.params;
  return proxySimpleL1Request(request, params.path || []);
}

export async function POST(request, context) {
  const params = await context.params;
  return proxySimpleL1Request(request, params.path || []);
}

export async function HEAD(request, context) {
  const params = await context.params;
  return proxySimpleL1Request(request, params.path || []);
}
