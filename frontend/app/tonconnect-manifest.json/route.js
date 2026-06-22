import { NextResponse } from 'next/server';

function resolveOrigin(request) {
  const forwardedHost = request.headers.get('x-forwarded-host');
  const host = forwardedHost || request.headers.get('host');
  if (!host) {
    return new URL(request.url).origin;
  }

  const forwardedProto = request.headers.get('x-forwarded-proto');
  const protocol = forwardedProto || (host.includes('localhost') ? 'http' : 'https');

  return `${protocol}://${host}`.replace(/\/$/, '');
}

export function GET(request) {
  const origin = resolveOrigin(request);

  return NextResponse.json({
    url: origin,
    name: 'Meanly Vault',
    iconUrl: `${origin}/identity-icon.svg`,
    termsOfUseUrl: `${origin}/terms`,
    privacyPolicyUrl: `${origin}/privacy`,
  }, {
    headers: {
      'Cache-Control': 'public, max-age=300',
      'Access-Control-Allow-Origin': '*',
    },
  });
}
