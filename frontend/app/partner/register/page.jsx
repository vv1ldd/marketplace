import { redirect } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function PartnerRegisterPage({ searchParams }) {
  const params = await searchParams;
  const query = new URLSearchParams();

  Object.entries(params || {}).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      value.forEach((item) => query.append(key, item));
      return;
    }

    if (value !== undefined && value !== null) {
      query.set(key, value);
    }
  });

  const queryString = query.toString();

  redirect(`/merchant/register${queryString ? `?${queryString}` : ''}`);
}
