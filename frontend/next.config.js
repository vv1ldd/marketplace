import path from 'node:path';

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone',
  allowedDevOrigins: ['meanly.test'],
  turbopack: {
    root: path.resolve(process.cwd()),
  },
  async rewrites() {
    const apiUrl = (process.env.NEXT_PUBLIC_MARKETPLACE_API_URL || 'https://api.meanly.test').replace(/\/+$/, '');

    return [
      {
        source: '/backend/:path*',
        destination: `${apiUrl}/:path*`,
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
        source: '/device-handoff/:path*',
        destination: `${apiUrl}/device-handoff/:path*`,
      },
      {
        source: '/device-pairing/:path*',
        destination: `${apiUrl}/device-pairing/:path*`,
      },
      {
        source: '/api/sl1e/:path*',
        destination: `${apiUrl}/api/sl1e/:path*`,
      },
    ];
  },
};

export default nextConfig;
