<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Shop;
use App\Models\LegalEntity;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\SovereignLedger;
use App\Models\ApiApplication;
use App\Services\Ai\OpsAnalystService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OpsDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            abort(403, 'Доступ в Центр Операций ограничен. Требуются права супер-администратора.');
        }

        // Global Platform Stats
        $stats = [
            'total_partners' => LegalEntity::count(),
            'total_shops' => Shop::count(),
            'total_orders' => Order::count(),
            'total_products' => Product::count(),
            'total_volume' => round(DB::table('order_items')->sum('price_rub') / 100, 2),
            'active_integrations' => ApiApplication::count(),
            'low_stock_count' => \App\Models\WarehouseStock::where('count', '<', 5)->count(),
            'critical_errors' => Product::whereNotNull('ym_errors')->count(),
        ];

        // 📋 Initial data sets for the SPA view
        $orders = Order::with(['items', 'shop'])->latest()->limit(50)->get();
        $catalog = Product::with(['shop'])->latest()->limit(50)->get();
        $tickets = Ticket::with(['shop'])->latest()->limit(50)->get();
        $shops = Shop::with(['legalEntity'])->latest()->limit(50)->get();
        $partners = LegalEntity::latest()->limit(50)->get();
        
        $ledgerTransactions = SovereignLedger::with(['shop', 'legalEntity'])
            ->latest()
            ->limit(50)
            ->get();

        return view('ops.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'orders' => $orders,
            'catalog' => $catalog,
            'tickets' => $tickets,
            'shops' => $shops,
            'partners' => $partners,
            'ledgerTransactions' => $ledgerTransactions,
        ]);
    }

    // 📋 AJAX — Глобальные Организации (Партнеры)
    public function getPartnersData(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = LegalEntity::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('inn', 'like', "%{$search}%")
                  ->orWhere('kpp', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate(10);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'inn' => $entity->inn,
                    'kpp' => $entity->kpp ?? '—',
                    'available_balance' => round($entity->available_balance, 2),
                    'reserved_balance' => round($entity->reserved_balance, 2),
                    'shops_count' => $entity->shops()->count(),
                    'migration_pill_issue_url' => (app()->isProduction() || config('app.env') === 'production')
                        ? null
                        : route('migration-pill.issue', ['legalEntity' => $entity->id]),
                    'created_at' => $entity->created_at->format('d.m.Y H:i'),
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    // 📋 AJAX — Глобальные Магазины
    public function getShopsData(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = Shop::with(['legalEntity']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('legalEntity', fn($qe) => $qe->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->latest()->paginate(10);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'legal_entity_name' => $shop->legalEntity->name ?? '—',
                    'is_active' => $shop->is_active,
                    'is_sandbox' => $shop->is_sandbox,
                    'allowed_regions' => $shop->allowed_regions ?? [],
                    'allowed_categories' => $shop->allowed_categories ?? [],
                    'created_at' => $shop->created_at ? $shop->created_at->format('d.m.Y H:i') : '—',
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    // 📋 AJAX — Глобальные Заказы
    public function getOrdersData(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = Order::with(['items', 'shop.legalEntity']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhereHas('items', fn($qi) => $qi->where('sku', 'like', "%{$search}%"))
                  ->orWhereHas('shop', fn($qs) => $qs->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->where('progress_id', '<>', 4)->where('progress_id', '<>', 5);
            } elseif ($status === 'completed') {
                $query->where('progress_id', 4);
            } elseif ($status === 'cancelled') {
                $query->where('progress_id', 5);
            } elseif ($status === 'sandbox') {
                $query->where('is_test', true);
            }
        }

        $paginator = $query->latest()->paginate(10);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($order) {
                $item = $order->items->first();
                $code = $item?->key ?: '—';
                if (str_starts_with((string)$code, 'vault:')) {
                    try {
                        $code = app(\App\Services\VaultTransitService::class)->decrypt($code);
                    } catch (\Exception $e) {
                        $code = '🔒 Зашифровано';
                    }
                }

                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'shop_name' => $order->shop->name ?? '—',
                    'partner_name' => $order->shop->legalEntity->name ?? '—',
                    'sku' => $item?->sku ?? '—',
                    'price_rub' => round(($item?->price_rub ?? 0) / 100, 2),
                    'code' => $code,
                    'status_id' => $order->progress_id,
                    'status_text' => $order->progress_id == 4 ? 'Выполнен' : ($order->progress_id == 5 ? 'Отменен' : 'В работе'),
                    'is_test' => $order->is_test,
                    'created_at' => $order->created_at ? $order->created_at->format('d.m.Y H:i') : '—',
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    // 📋 AJAX — Глобальный Каталог
    public function getCatalogData(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = Product::with(['shop.legalEntity']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhereHas('shop', fn($qs) => $qs->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->latest()->paginate(10);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price_rub' => round($product->price_rub / 100, 2),
                    'stock' => $product->stocks()->sum('count'),
                    'shop_name' => $product->shop->name ?? '—',
                    'partner_name' => $product->shop->legalEntity->name ?? '—',
                    'is_active' => $product->is_active,
                    'has_errors' => !empty($product->ym_errors),
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    // 📋 AJAX — Поддержка и Тикеты
    public function getTicketsData(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = Ticket::with(['shop.legalEntity']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('shop', fn($qs) => $qs->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->latest()->paginate(10);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'shop_name' => $ticket->shop->name ?? '—',
                    'partner_name' => $ticket->shop->legalEntity->name ?? '—',
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at ? $ticket->created_at->format('d.m.Y H:i') : '—',
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function traceSimpleLayer1(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'reference' => 'required|string|max:64',
        ]);

        $trace = app(\App\Services\SimpleLayer1TraceService::class)->trace($validated['reference']);

        if (! $trace) {
            return response()->json([
                'success' => false,
                'message' => 'Simple Layer 1 transaction reference not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'trace' => $trace,
        ]);
    }

    // 📋 AJAX — Детали тикета
    public function getTicketDetails($id)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $ticket = Ticket::with(['shop.legalEntity'])->findOrFail($id);
        $messages = TicketMessage::where('ticket_id', $id)
            ->with('user')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'sender' => $m->user->name ?? ($m->is_admin ? 'Супер-администратор' : 'Партнер'),
                    'message' => $m->message,
                    'is_admin' => $m->is_admin,
                    'created_at' => $m->created_at->format('d.m.Y H:i'),
                ];
            });

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'shop_name' => $ticket->shop->name ?? '—',
                'partner_name' => $ticket->shop->legalEntity->name ?? '—',
            ],
            'messages' => $messages,
        ]);
    }

    // 📋 AJAX — Ответить на тикет
    public function replyToTicket(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = Ticket::findOrFail($id);

        TicketMessage::create([
            'ticket_id' => $id,
            'user_id' => $user->id,
            'message' => $request->message,
            'is_admin' => true,
        ]);

        $ticket->update(['status' => 'resolved']);

        return response()->json([
            'success' => true,
            'message' => 'Ответ успешно добавлен!',
        ]);
    }

    // 📋 AJAX — Глобальный ИИ-аудит (Ledger Audit)
    public function runAiAudit(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $analyst = app(OpsAnalystService::class);
            $result = $analyst->analyzeGlobalSystem();

            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Сбой при запуске ИИ-анализа: ' . $e->getMessage()], 500);
        }
    }

    // 📋 AJAX — Глобальный чат с ИИ
    public function sendAiChatMessage(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $message = $request->input('message');

        try {
            $analyst = app(OpsAnalystService::class);
            $aiContent = $analyst->chatGlobal($user, $message);

            return response()->json([
                'success' => true,
                'response' => $aiContent,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Ошибка взаимодействия с ИИ: ' . $e->getMessage()], 500);
        }
    }

    // 🎨 AJAX — Сохранение темы оформления
    public function updateTheme(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'theme' => 'required|string|in:'.implode(',', config('app.supported_themes', ['partner', 'consortium', 'retro'])),
        ]);

        app(\App\Services\ThemeResolver::class)->persistUserTheme($user, $request->theme);

        return response()->json([
            'success' => true,
            'theme' => $user->refresh()->theme,
        ]);
    }
}
