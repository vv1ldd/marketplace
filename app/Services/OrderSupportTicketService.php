<?php

namespace App\Services;

use App\Filament\Partner\Resources\Tickets\TicketResource as PartnerTicketResource;
use App\Models\Order\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderSupportTicketService
{
    public function ticketForProblemSafe(Order $order): ?Ticket
    {
        if (! Schema::hasColumn('tickets', 'order_id')) {
            return null;
        }

        $order->loadMissing(['items.game', 'shop.legalEntity']);

        if (! $order->shop) {
            return null;
        }

        $ticket = Ticket::query()
            ->where('order_id', $order->id)
            ->first();

        if ($ticket) {
            return $ticket->loadMissing(['shop.legalEntity', 'order']);
        }

        return DB::transaction(function () use ($order) {
            $ticket = Ticket::query()
                ->lockForUpdate()
                ->where('order_id', $order->id)
                ->first();

            if ($ticket) {
                return $ticket->loadMissing(['shop.legalEntity', 'order']);
            }

            $shop = $order->shop;
            $legalEntity = $shop->legalEntity;
            $sellerId = $legalEntity?->seller_id;

            $ticket = Ticket::create([
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'user_id' => $this->buyerUserId($order),
                'seller_id' => $sellerId,
                'subject' => 'Проверка выдачи кода по заказу '.$order->order_id,
                'status' => 'open',
                'priority' => 'high',
                'last_reply_at' => now(),
            ]);

            $ticket->messages()->create([
                'seller_id' => $sellerId,
                'message' => $this->initialMessage($order),
                'is_admin_reply' => false,
            ]);

            $info = $order->info ?? [];
            data_set($info, 'order_safe.support_ticket_id', $ticket->id);
            data_set($info, 'order_safe.support_ticket_created_at', now()->toJSON());
            $order->forceFill(['info' => $info])->save();

            return $ticket->loadMissing(['shop.legalEntity', 'order']);
        });
    }

    public function ticketChatUrl(Ticket $ticket): string
    {
        $ticket->loadMissing('shop.legalEntity');

        try {
            return PartnerTicketResource::getUrl('view', [
                'tenant' => $ticket->shop?->legalEntity,
                'record' => $ticket,
            ]);
        } catch (\Throwable) {
            return route('partner.dashboard', ['ticket' => $ticket->id, 'tab' => 'support']);
        }
    }

    private function initialMessage(Order $order): string
    {
        $firstItem = $order->items->first();
        $productName = $firstItem?->game?->name ?: ($firstItem?->sku ?: 'Цифровой товар');
        $providerReferences = $order->items
            ->pluck('provider_order_id')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return implode("\n", array_filter([
            'Автоматически создано по проблемной выдаче кода.',
            'Заказ: '.$order->order_id,
            'Товар: '.$productName,
            'Сумма: '.number_format((float) $order->total_amount, 2, '.', ' ').' '.($order->currency ?: 'RUB'),
            'Статус сейфа: '.(data_get($order->info, 'order_safe.status') ?: $order->status),
            $providerReferences !== '' ? 'Provider order: '.$providerReferences : null,
            'Покупатель оплатил заказ, но код не был выдан автоматически. Проверьте цепочку выдачи или оформите возврат.',
        ]));
    }

    private function buyerUserId(Order $order): ?int
    {
        $userId = $order->user_id ?: data_get($order->client_info, 'buyer_user_id');

        return $userId ? (int) $userId : null;
    }
}
