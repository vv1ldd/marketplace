import { CheckoutForm } from '../../components/CheckoutForm';

export default async function CheckoutPage({ searchParams }) {
  const params = await searchParams;
  const productId = params?.product_id;

  return (
    <main className="page">
      <section className="hero">
        <p className="eyebrow">Checkout</p>
        <h1>Review and place your order.</h1>
        <p>
          Check the purchase details first. Sign in with Meanly when you are
          ready to place the order.
        </p>
      </section>
      <CheckoutForm productId={productId} />
    </main>
  );
}
