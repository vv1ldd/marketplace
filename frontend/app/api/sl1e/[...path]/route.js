const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');

const TECHNICAL_QR_HOSTS = new Set([
  'pass.simplelayer.one',
  'localhost',
  '127.0.0.1',
]);

function rewriteHandoffPayload(body, requestHost, requestProto) {
  if (!body) {
    return body;
  }

  let payload;
  try {
    payload = JSON.parse(body);
  } catch {
    return body;
  }

  if (!payload?.qrUrl || typeof payload.qrUrl !== 'string') {
    return body;
  }

  try {
    const qr = new URL(payload.qrUrl);
    const storeHost = (requestHost || 'meanly.test').split(':')[0].toLowerCase();
    const qrHost = qr.hostname.toLowerCase();

    if (!TECHNICAL_QR_HOSTS.has(qrHost) && qrHost === storeHost) {
      return body;
    }

    qr.protocol = `${requestProto || 'https'}:`;
    qr.host = requestHost || 'meanly.test';

    const fixedUrl = qr.toString();
    if (fixedUrl === payload.qrUrl) {
      return body;
    }

    payload.qrUrl = fixedUrl;
    delete payload.qrDataUrl;

    return JSON.stringify(payload);
  } catch {
    return body;
  }
}

async function proxySl1eRequest(request, pathSegments) {
  const path = pathSegments.filter(Boolean).join('/');
  const target = `${apiUrl}/api/sl1e/${path}${new URL(request.url).search}`;
  const headers = new Headers();

  headers.set('Accept', request.headers.get('accept') || 'application/json');
  headers.set('Content-Type', request.headers.get('content-type') || 'application/json');
  headers.set('X-Forwarded-Host', request.headers.get('host') || 'meanly.test');
  headers.set('X-Forwarded-Proto', request.headers.get('x-forwarded-proto') || 'https');

  const cookie = request.headers.get('cookie');
  if (cookie) {
    headers.set('Cookie', cookie);
  }

  const upstream = await fetch(target, {
    method: request.method,
    headers,
    body: request.method === 'GET' || request.method === 'HEAD' ? undefined : await request.text(),
    cache: 'no-store',
  });

  let body = await upstream.text();

  if (request.method === 'POST' && path === 'authorize/handoff') {
    body = rewriteHandoffPayload(
      body,
      request.headers.get('host') || 'meanly.test',
      request.headers.get('x-forwarded-proto') || 'https',
    );
  }

  return new Response(body, {
    status: upstream.status,
    headers: {
      'Content-Type': upstream.headers.get('content-type') || 'application/json',
    },
  });
}

export async function GET(request, context) {
  const params = await context.params;
  return proxySl1eRequest(request, params.path || []);
}

export async function POST(request, context) {
  const params = await context.params;
  return proxySl1eRequest(request, params.path || []);
}
