const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');

function storefrontHost(request) {
  const host = request.headers.get('host') || 'meanly.test';
  return host.split(':')[0].toLowerCase();
}

async function proxyBackendRequest(request, pathSegments) {
  const path = pathSegments.filter(Boolean).join('/');
  const sourceUrl = new URL(request.url);
  const target = `${apiUrl}/${path}${sourceUrl.search}`;
  const headers = new Headers();
  const host = storefrontHost(request);

  headers.set('Accept', request.headers.get('accept') || 'application/json');
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
  const contentTypeResponse = upstream.headers.get('content-type');
  if (contentTypeResponse) {
    responseHeaders.set('Content-Type', contentTypeResponse);
  }
  const location = upstream.headers.get('location');
  if (location) {
    responseHeaders.set('Location', location);
  }
  const setCookie = upstream.headers.get('set-cookie');
  if (setCookie) {
    responseHeaders.set('Set-Cookie', setCookie);
  }

  return new Response(await upstream.text(), {
    status: upstream.status,
    headers: responseHeaders,
  });
}

export async function GET(request, context) {
  const params = await context.params;
  return proxyBackendRequest(request, params.path || []);
}

export async function POST(request, context) {
  const params = await context.params;
  return proxyBackendRequest(request, params.path || []);
}

export async function HEAD(request, context) {
  const params = await context.params;
  return proxyBackendRequest(request, params.path || []);
}
