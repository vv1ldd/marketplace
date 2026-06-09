import { PremiumWalletPanel } from '../../components/PremiumWalletPanel';

export const dynamic = 'force-dynamic';

export const metadata = {
  title: 'Vault Wallet | Meanly',
  description: 'Preview SL1, MCR, and MLP coins in Vault Wallet.',
};

export default function WalletPage() {
  return <PremiumWalletPanel />;
}
