import path from 'node:path';

const storefrontDevHost = (() => {
  try {
    return new URL(process.env.NEXT_PUBLIC_STOREFRONT_URL || 'https://meanly.test').hostname;
  } catch {
    return 'meanly.test';
  }
})();

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  allowedDevOrigins: ['meanly.test', storefrontDevHost].filter((value, index, all) => all.indexOf(value) === index),
  turbopack: {
    root: path.resolve(process.cwd()),
  },
  async headers() {
    const permissionsPolicy = 'publickey-credentials-get=(self), publickey-credentials-create=(self)';

    return [
      {
        source: '/vault/connect',
        headers: [{ key: 'Permissions-Policy', value: permissionsPolicy }],
      },
      {
        source: '/authorize',
        headers: [{ key: 'Permissions-Policy', value: permissionsPolicy }],
      },
      {
        source: '/((?!_next/static|_next/image|favicon.ico|.*\\..*).*)',
        headers: [{ key: 'Cache-Control', value: 'no-store, max-age=0, must-revalidate' }],
      },
    ];
  },
  async rewrites() {
    const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');

    return [
      {
        source: '/backend/:path*',
        destination: `${apiUrl}/:path*`,
      },
      {
        source: '/identity',
        destination: `${apiUrl}/identity`,
      },
      {
        source: '/wallet',
        destination: `${apiUrl}/wallet`,
      },
      {
        source: '/simple-l1/:path*',
        destination: `${apiUrl}/simple-l1/:path*`,
      },
      {
        source: '/manifest.webmanifest',
        destination: `${apiUrl}/manifest.webmanifest`,
      },
      {
        source: '/identity-icon.svg',
        destination: `${apiUrl}/identity-icon.svg`,
      },
      {
        source: '/h/:path*',
        destination: `${apiUrl}/h/:path*`,
      },
      {
        source: '/device-handoff/:path*',
        destination: `${apiUrl}/device-handoff/:path*`,
      },
      {
        source: '/device-pairing/:path*',
        destination: `${apiUrl}/device-pairing/:path*`,
      },
    ];
  },
};

export default nextConfig;
