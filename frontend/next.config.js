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
    ];
  },
};

export default nextConfig;
