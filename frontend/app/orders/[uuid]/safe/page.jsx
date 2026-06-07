import { OrderSafePanel } from '../../../../components/OrderSafePanel';

export default async function OrderSafePage({ params }) {
  const { uuid } = await params;

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Order safe</p>
        <h1>Your order safe.</h1>
        <p>
          Sign in to view delivery status, reveal safe codes, or contact
          support for this order.
        </p>
      </section>
      <OrderSafePanel orderUuid={uuid} />
    </main>
  );
}
