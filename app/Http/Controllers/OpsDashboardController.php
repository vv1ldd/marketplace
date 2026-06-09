<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Currency;
use App\Models\DemandGap;
use App\Models\ExternalSearchQuerySignal;
use App\Models\IntentLiquidityCorridor;
use App\Models\IntentLiquidityNode;
use App\Models\LiquidityCorridor;
use App\Models\LiquidityMethod;
use App\Models\MeanlyOperationalAlert;
use App\Models\OpportunityCase;
use App\Models\Shop;
use App\Models\LegalEntity;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\SearchDemandRecommendation;
use App\Models\SellerTerminal;
use App\Models\SovereignBalanceRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\SovereignLedger;
use App\Models\ApiApplication;
use App\Models\MeanlyApiReservation;
use App\Models\MerchantDepositIntent;
use App\Models\MeanlyApiOrder;
use App\Models\SettlementProof;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\ZeroLayerIntegration;
use App\Models\ZeroLayerSignal;
use App\Services\Ai\OpsAnalystService;
use App\Services\SearchSignals\ExternalSearchSignalIngestor;
use App\Services\ZeroLayer\ZeroLayerIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class OpsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            abort(403, 'Доступ в Центр Операций ограничен. Требуются права супер-администратора.');
        }

        // Global Platform Stats
        $stats = [
            'total_partners' => LegalEntity::count(),
            'pending_partners' => LegalEntity::where('status', 'pending_moderation')->count(),
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

        $decisionRecommendations = SearchDemandRecommendation::query()
            ->orderByRaw("CASE status WHEN 'proposed' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 WHEN 'applied' THEN 3 ELSE 4 END")
            ->orderByDesc('impact_score')
            ->orderByDesc('confidence')
            ->latest()
            ->limit(25)
            ->get();

        $decisionStatusCounts = SearchDemandRecommendation::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('ops.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'orders' => $orders,
            'catalog' => $catalog,
            'tickets' => $tickets,
            'shops' => $shops,
            'partners' => $partners,
            'ledgerTransactions' => $ledgerTransactions,
            'decisionRecommendations' => $decisionRecommendations,
            'decisionStatusCounts' => $decisionStatusCounts,
            'activeOpsTab' => $request->query('tab'),
        ]);
    }

    // 📋 AJAX — Глобальные Организации (Партнеры)
    public function getPartnersData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = LegalEntity::query()
            ->withCount(['shops', 'terminals']);

        if ($request->get('status') === 'pending_moderation') {
            $query->where('status', 'pending_moderation');
        }

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
                    'shops_count' => (int) ($entity->shops_count ?? 0),
                    'terminals_count' => (int) ($entity->terminals_count ?? 0),
                    'status' => $entity->status,
                    'status_label' => $this->legalEntityStatusLabel($entity),
                    'is_active' => (bool) $entity->is_active,
                    'api_identity' => $this->legalEntityApiIdentityPayload($entity),
                    'settlement' => $this->legalEntitySettlementPayload($entity),
                    'approve_url' => $entity->status === 'pending_moderation'
                        ? route('ops.dashboard.partners.approve', ['legalEntity' => $entity->id])
                        : null,
                    'action_urls' => [
                        'top_up' => route('ops.dashboard.partners.top-up', ['legalEntity' => $entity->id]),
                    ],
                    'created_at' => $entity->created_at->format('d.m.Y H:i'),
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function getTreasuryData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $pendingRequests = Schema::hasTable('sovereign_balance_requests')
            ? SovereignBalanceRequest::query()->where('status', 'pending')
            : null;

        $recentRequests = Schema::hasTable('sovereign_balance_requests')
            ? SovereignBalanceRequest::with(['legalEntity', 'approvedBy'])->latest()->limit(12)->get()
            : collect();

        $recentSettlementEvents = SovereignLedger::with('legalEntity')
            ->where(function ($query): void {
                $query->where('event_type', 'like', '%BALANCE%')
                    ->orWhere('event_type', 'like', '%SETTLEMENT%')
                    ->orWhere('event_type', 'like', '%RESERVED%')
                    ->orWhere('event_type', 'like', '%REFUNDED%')
                    ->orWhere('event_type', 'like', '%TOP_UP%');
            })
            ->latest('created_at')
            ->limit(12)
            ->get();

        $walletAccounts = Schema::hasTable('wallet_accounts')
            ? WalletAccount::query()
                ->selectRaw('asset, COUNT(*) as accounts_count, SUM(available_minor) as available_minor, SUM(reserved_minor) as reserved_minor')
                ->groupBy('asset')
                ->orderBy('asset')
                ->get()
            : collect();

        $recentWalletEvents = Schema::hasTable('wallet_ledger_entries')
            ? WalletLedgerEntry::with('user')
                ->latest()
                ->limit(12)
                ->get()
            : collect();

        $pendingDepositIntents = Schema::hasTable('merchant_deposit_intents')
            ? MerchantDepositIntent::query()
                ->with(['legalEntity', 'targetLegalEntity', 'proofs.authorityVerdicts', 'authorityVerdicts'])
                ->whereIn('status', ['waiting_payment', 'proof_received', 'waiting_authority', 'confirmed'])
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        $recentSettlementProofs = Schema::hasTable('settlement_proofs')
            ? SettlementProof::query()
                ->with(['intent', 'legalEntity', 'reviewedBy', 'authorityVerdicts'])
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        return response()->json([
            'summary' => [
                'partners' => LegalEntity::count(),
                'available_balance' => round((float) LegalEntity::sum('available_balance'), 2),
                'reserved_balance' => round((float) LegalEntity::sum('reserved_balance'), 2),
                'native_available' => round((float) LegalEntity::sum('native_token_balance'), 4),
                'native_reserved' => round((float) LegalEntity::sum('native_token_reserved'), 4),
                'pending_requests' => $pendingRequests ? (clone $pendingRequests)->count() : 0,
                'pending_amount' => $pendingRequests ? round((float) (clone $pendingRequests)->sum('amount'), 2) : 0.0,
                'pending_deposit_intents' => $pendingDepositIntents->count(),
                'pending_deposit_amount' => round((float) $pendingDepositIntents->sum('amount'), 2),
            ],
            'deposit_intents' => $pendingDepositIntents->map(fn (MerchantDepositIntent $intent): array => $this->formatOpsDepositIntent($intent))->values(),
            'settlement_proofs' => $recentSettlementProofs->map(fn (SettlementProof $proof): array => $this->formatOpsSettlementProof($proof))->values(),
            'requests' => $recentRequests->map(fn (SovereignBalanceRequest $request): array => [
                'id' => $request->id,
                'partner' => $request->legalEntity?->name ?? '—',
                'type' => $request->type,
                'amount' => round((float) $request->amount, 2),
                'currency' => $request->currency ?: 'RUB',
                'status' => $request->status,
                'comment' => $request->comment,
                'created_at' => optional($request->created_at)->format('d.m.Y H:i') ?: '—',
                'approved_by' => $request->approvedBy?->name,
            ])->values(),
            'settlement_events' => $recentSettlementEvents->map(fn (SovereignLedger $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'partner' => $event->legalEntity?->name ?? 'SYSTEM',
                'amount' => round((float) ($event->amount_base ?? data_get($event->payload, 'amount', 0)), 2),
                'currency' => $event->currency ?? $event->base_currency ?? data_get($event->payload, 'currency', '—'),
                'created_at' => optional($event->created_at)->format('d.m.Y H:i') ?: '—',
            ])->values(),
            'wallet' => [
                'summary' => $walletAccounts->map(fn ($account): array => [
                    'asset' => $account->asset,
                    'accounts_count' => (int) $account->accounts_count,
                    'available_minor' => (int) $account->available_minor,
                    'reserved_minor' => (int) $account->reserved_minor,
                ])->values(),
                'recent_events' => $recentWalletEvents->map(fn (WalletLedgerEntry $entry): array => [
                    'id' => $entry->id,
                    'asset' => $entry->asset,
                    'direction' => $entry->direction,
                    'entry_type' => $entry->entry_type,
                    'amount_minor' => (int) $entry->amount_minor,
                    'balance_after_minor' => (int) $entry->balance_after_minor,
                    'user' => $entry->user?->name ?? ('user #'.$entry->user_id),
                    'tx_hash' => $entry->tx_hash,
                    'created_at' => optional($entry->created_at)->format('d.m.Y H:i') ?: '—',
                ])->values(),
            ],
        ]);
    }

    public function getLiquidityData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $partners = LegalEntity::query()
            ->withCount('shops')
            ->orderByDesc('available_balance')
            ->limit(25)
            ->get();

        return response()->json([
            'summary' => [
                'currencies' => Schema::hasTable('currencies') ? Currency::count() : 0,
                'execution_ready_currencies' => Schema::hasTable('currencies') && Schema::hasColumn('currencies', 'execution_ready')
                    ? Currency::where('execution_ready', true)->count()
                    : 0,
                'liquidity_methods' => Schema::hasTable('liquidity_methods') ? LiquidityMethod::where('is_active', true)->count() : 0,
                'intent_nodes' => Schema::hasTable('intent_liquidity_nodes') ? IntentLiquidityNode::count() : 0,
                'intent_corridors_ready' => Schema::hasTable('intent_liquidity_corridors')
                    ? IntentLiquidityCorridor::where('execution_ready', true)->count()
                    : 0,
            ],
            'currencies' => $this->opsCurrencyLiquidityRows(),
            'methods' => $this->opsLiquidityMethodsRows(),
            'corridors' => $this->opsLiquidityCorridorRows(),
            'intent_corridors' => $this->opsIntentLiquidityCorridorRows(),
            'data' => $partners->map(function (LegalEntity $entity): array {
                $activeReservationAmount = Schema::hasTable('wildflow_credit_reservations')
                    ? (float) MeanlyApiReservation::query()
                        ->where('legal_entity_id', $entity->id)
                        ->where('status', 'active')
                        ->sum('amount')
                    : 0.0;

                return [
                    'id' => $entity->id,
                    'partner' => $entity->name,
                    'currency' => $entity->currency ?? 'RUB',
                    'available_balance' => round((float) $entity->available_balance, 2),
                    'reserved_balance' => round((float) $entity->reserved_balance, 2),
                    'native_available' => round((float) $entity->native_token_balance, 4),
                    'native_reserved' => round((float) $entity->native_token_reserved, 4),
                    'api_active_reservations' => round($activeReservationAmount, 2),
                    'shops_count' => (int) ($entity->shops_count ?? 0),
                    'status' => $entity->status ?: ($entity->is_active ? 'active' : 'inactive'),
                ];
            })->values(),
        ]);
    }

    public function getChannelsData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $configuredChannels = collect(config('sales_channels.channels', []));
        $channelRows = Schema::hasTable('product_sales_channels')
            ? ProductSalesChannel::query()
                ->select('channel')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN is_enabled = 1 THEN 1 ELSE 0 END) as enabled_total')
                ->selectRaw('SUM(CASE WHEN last_error IS NOT NULL AND last_error <> "" THEN 1 ELSE 0 END) as error_total')
                ->groupBy('channel')
                ->get()
                ->keyBy('channel')
            : collect();

        $shops = Shop::with('legalEntity')->latest()->limit(20)->get();
        $hasGlobalCatalogFlag = Schema::hasColumn('shops', 'is_global_catalog_enabled');

        return response()->json([
            'summary' => [
                'configured_channels' => $configuredChannels->count(),
                'implemented_channels' => $configuredChannels->where('implemented', true)->count(),
                'enabled_product_links' => (int) $channelRows->sum('enabled_total'),
                'channel_errors' => (int) $channelRows->sum('error_total'),
                'yandex_configured_shops' => $shops->filter(fn (Shop $shop): bool => $shop->isYandexMarketActive())->count(),
                'yandex_verified_shops' => $shops->filter(fn (Shop $shop): bool => $shop->isYandexMarketVerified())->count(),
            ],
            'channels' => $configuredChannels->map(function (array $config, string $key) use ($channelRows): array {
                $row = $channelRows->get($key);

                return [
                    'key' => $key,
                    'label' => $config['label'] ?? $key,
                    'group' => $config['group'] ?? 'other',
                    'implemented' => (bool) ($config['implemented'] ?? false),
                    'enabled' => (bool) ($config['enabled'] ?? false),
                    'product_links' => (int) ($row?->total ?? 0),
                    'enabled_links' => (int) ($row?->enabled_total ?? 0),
                    'errors' => (int) ($row?->error_total ?? 0),
                ];
            })->values(),
            'shops' => $shops->map(fn (Shop $shop): array => [
                'id' => $shop->id,
                'name' => $shop->name,
                'partner' => $shop->legalEntity?->name ?? '—',
                'is_active' => (bool) $shop->is_active,
                'meanly_storefront' => $hasGlobalCatalogFlag ? (bool) $shop->is_global_catalog_enabled : false,
                'yandex_configured' => $shop->isYandexMarketActive(),
                'yandex_verified' => $shop->isYandexMarketVerified(),
                'business_id' => $shop->business_id,
                'campaign_id' => $shop->campaign_id,
                'ym_warehouse_id' => $shop->ym_warehouse_id,
            ])->values(),
        ]);
    }

    public function getGrowthData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'summary' => [
                'demand_gaps' => Schema::hasTable('demand_gaps') ? DemandGap::count() : 0,
                'lost_gmv' => Schema::hasTable('demand_gaps') ? round((float) DemandGap::sum('estimated_lost_gmv'), 2) : 0,
                'open_cases' => Schema::hasTable('opportunity_cases')
                    ? OpportunityCase::whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])->count()
                    : 0,
                'overdue_cases' => Schema::hasTable('opportunity_cases')
                    ? OpportunityCase::whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])
                        ->whereNotNull('sla_due_at')
                        ->where('sla_due_at', '<', now())
                        ->count()
                    : 0,
                'proposed_recommendations' => Schema::hasTable('search_demand_recommendations')
                    ? SearchDemandRecommendation::where('status', SearchDemandRecommendation::STATUS_PROPOSED)->count()
                    : 0,
                'open_alerts' => Schema::hasTable('meanly_operational_alerts')
                    ? MeanlyOperationalAlert::where('status', 'open')->count()
                    : 0,
            ],
            'demand_gaps' => $this->opsDemandGapRows(),
            'opportunity_cases' => $this->opsOpportunityCaseRows(),
            'recommendations' => $this->opsSearchDemandRecommendationRows(),
            'alerts' => $this->opsOperationalAlertRows(),
        ]);
    }

    public function getSearchIntegrationsData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'summary' => [
                'zero_layer_integrations' => Schema::hasTable('zero_layer_integrations') ? ZeroLayerIntegration::count() : 0,
                'active_zero_layer_integrations' => Schema::hasTable('zero_layer_integrations')
                    ? ZeroLayerIntegration::where('status', 'active')->count()
                    : 0,
                'zero_layer_signals' => Schema::hasTable('zero_layer_signals') ? ZeroLayerSignal::count() : 0,
                'external_search_signals' => Schema::hasTable('external_search_query_signals') ? ExternalSearchQuerySignal::count() : 0,
                'recommendations_proposed' => Schema::hasTable('search_demand_recommendations')
                    ? SearchDemandRecommendation::where('status', SearchDemandRecommendation::STATUS_PROPOSED)->count()
                    : 0,
            ],
            'integrations' => $this->opsZeroLayerIntegrationRows(),
            'source_totals' => $this->opsSearchSourceTotals(),
            'zero_layer_signals' => $this->opsZeroLayerSignalRows(),
            'external_signals' => $this->opsExternalSearchSignalRows(),
            'recommendations' => $this->opsSearchDemandRecommendationRows(),
            'actions' => [
                'pull_providers' => ['google_search_console', 'yandex_webmaster', 'google_suggest', 'yandex_suggest'],
                'connect_url' => route('ops.dashboard.zero-layer.connect', [], false),
                'analyze_url' => route('ops.dashboard.search-signals.analyze', [], false),
                'recommend_url' => route('ops.dashboard.search-signals.recommend', [], false),
                'pull_url' => route('ops.dashboard.search-signals.pull', [], false),
                'promote_zero_layer_url' => route('ops.dashboard.search-signals.promote-zero-layer', [], false),
            ],
            'connectors' => $this->zeroLayerConnectorDefinitions(),
        ]);
    }

    public function saveZeroLayerIntegration(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $sources = implode(',', array_keys($this->zeroLayerConnectorDefinitions()));
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:zero_layer_integrations,id'],
            'name' => ['required', 'string', 'max:120'],
            'source' => ['required', 'string', 'in:'.$sources],
            'status' => ['nullable', 'string', 'in:active,inactive,paused'],
            'credentials' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $integration = filled($data['id'] ?? null)
            ? ZeroLayerIntegration::query()->findOrFail((int) $data['id'])
            : new ZeroLayerIntegration();

        $integration->fill([
            'name' => $data['name'],
            'source' => $data['source'],
            'status' => $data['status'] ?? 'active',
            'settings' => $data['settings'] ?? [],
        ]);

        if (array_key_exists('credentials', $data)) {
            $integration->credentials = $data['credentials'] ?? [];
        }

        $integration->save();

        return response()->json([
            'success' => true,
            'integration' => [
                'id' => $integration->id,
                'name' => $integration->name,
                'source' => $integration->source,
                'status' => $integration->status,
                'credential_keys' => array_keys((array) ($integration->credentials ?? [])),
                'settings_keys' => array_keys((array) ($integration->settings ?? [])),
            ],
        ]);
    }

    public function syncZeroLayerIntegration(ZeroLayerIntegration $integration, ZeroLayerIngestionService $ingestion)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            $result = $ingestion->syncIntegration($integration);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function pullSearchSignals(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'provider' => ['required', 'string', 'in:google_search_console,yandex_webmaster,google_suggest,yandex_suggest'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25000'],
            'query' => ['nullable', 'string', 'max:200'],
            'country' => ['nullable', 'string', 'max:16'],
            'locale' => ['nullable', 'string', 'max:16'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $arguments = [
            'provider' => $data['provider'],
            '--limit' => (int) ($data['limit'] ?? 500),
            '--json' => true,
        ];
        foreach (['from', 'to', 'query', 'country', 'locale'] as $field) {
            if (filled($data[$field] ?? null)) {
                $arguments['--'.$field] = (string) $data[$field];
            }
        }
        if ((bool) ($data['dry_run'] ?? false)) {
            $arguments['--dry-run'] = true;
        }

        return $this->opsArtisanJsonResponse('search-signals:pull', $arguments);
    }

    public function analyzeSearchSignals(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->opsArtisanJsonResponse('search-signals:analyze', [
            '--limit' => (int) ($data['limit'] ?? 25),
            '--days' => (int) ($data['days'] ?? 90),
            '--source' => (string) ($data['source'] ?? 'all'),
            '--json' => true,
        ]);
    }

    public function recommendSearchSignals(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'source' => ['nullable', 'string', 'max:80'],
            'min_score' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->opsArtisanJsonResponse('search-signals:recommend', [
            '--limit' => (int) ($data['limit'] ?? 25),
            '--days' => (int) ($data['days'] ?? 90),
            '--source' => (string) ($data['source'] ?? 'all'),
            '--min-score' => (float) ($data['min_score'] ?? 1),
            '--json' => true,
        ]);
    }

    public function promoteZeroLayerSignals(Request $request, ExternalSearchSignalIngestor $ingestor)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'source' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        if (! Schema::hasTable('zero_layer_signals')) {
            return response()->json(['success' => true, 'imported' => 0, 'skipped' => 0]);
        }

        $signals = ZeroLayerSignal::query()
            ->when(filled($data['source'] ?? null), fn ($query) => $query->where('source', (string) $data['source']))
            ->whereNotNull('query_text')
            ->latest()
            ->limit((int) ($data['limit'] ?? 250))
            ->get()
            ->map(fn (ZeroLayerSignal $signal): array => $this->zeroLayerSignalPromotionRecord($signal))
            ->all();

        $result = $ingestor->persist($signals, 'zero_layer');

        return response()->json([
            'success' => true,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function approvePartner(LegalEntity $legalEntity)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $metadata = $legalEntity->agreement_metadata ?? [];
        $metadata['moderated_at'] = now()->toIso8601String();
        $metadata['moderated_by_user_id'] = $user?->id;
        $metadata['moderation_decision'] = 'approved';

        $legalEntity->forceFill([
            'status' => 'active',
            'is_active' => true,
            'agreement_metadata' => $metadata,
        ])->save();

        app(\App\Services\SellerDistributionCenterService::class)
            ->ensureForLegalEntity($legalEntity->refresh());

        return response()->json([
            'success' => true,
            'status' => $legalEntity->status,
            'status_label' => $this->legalEntityStatusLabel($legalEntity),
        ]);
    }

    // 📋 AJAX — Глобальные Магазины
    public function getShopsData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
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
        if (! $this->canAccessOps($user)) {
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

    public function getOperationsData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $search = trim((string) $request->get('search', ''));

        $ledgerEvents = SovereignLedger::with(['legalEntity', 'shop.legalEntity'])
            ->latest('created_at')
            ->limit($search !== '' ? 100 : 20)
            ->get();

        if ($search !== '') {
            $needle = Str::lower($search);
            $ledgerEvents = $ledgerEvents
                ->filter(function (SovereignLedger $event) use ($needle): bool {
                    $haystack = Str::lower(json_encode([
                        $event->event_type,
                        $event->currency,
                        $event->trigger_source,
                        $event->payload,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    return Str::contains($haystack, $needle);
                })
                ->values();
        }

        $itemQuery = OrderItems::with(['order.shop.legalEntity'])->latest();
        if ($search !== '') {
            $itemQuery->where(function ($query) use ($search) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('purchase_status', 'like', "%{$search}%")
                    ->orWhere('purchase_error', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_id', 'like', "%{$search}%"));
            });
        }

        $events = collect()
            ->merge($ledgerEvents->take(20)->map(fn (SovereignLedger $event) => $this->ledgerOperationPayload($event)))
            ->merge($itemQuery->limit(20)->get()->map(fn (OrderItems $item) => $this->orderItemOperationPayload($item)));

        if (Schema::hasTable('wildflow_kernel_orders')) {
            $kernelQuery = MeanlyApiOrder::with('legalEntity')->latest();
            if ($search !== '') {
                $kernelQuery->where(function ($query) use ($search) {
                    $query->where('marketplace_reference', 'like', "%{$search}%")
                        ->orWhere('proxy_reference', 'like', "%{$search}%")
                        ->orWhere('vendor_reference', 'like', "%{$search}%")
                        ->orWhere('service_sku', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%");
                });
            }

            $events = $events->merge($kernelQuery->limit(20)->get()->map(fn (MeanlyApiOrder $order) => $this->kernelOrderOperationPayload($order)));
        }

        $events = $events
            ->sortByDesc('sort_at')
            ->values()
            ->take(30)
            ->map(fn (array $event) => collect($event)->except('sort_at')->all())
            ->values();

        return response()->json([
            'data' => $events,
            'total' => $events->count(),
        ]);
    }

    // 📋 AJAX — Глобальный Каталог
    public function getCatalogData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
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

    public function getInventoryData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'summary' => [
                'warehouses' => Schema::hasTable('warehouses') ? Warehouse::count() : 0,
                'active_warehouses' => Schema::hasTable('warehouses') ? Warehouse::where('is_active', true)->count() : 0,
                'stock_units' => Schema::hasTable('warehouse_stocks') ? (int) WarehouseStock::sum('count') : 0,
                'low_stock_rows' => Schema::hasTable('warehouse_stocks') ? WarehouseStock::where('count', '<', 5)->count() : 0,
                'vouchers' => Schema::hasTable('product_inventory') ? ProductInventory::count() : 0,
                'available_vouchers' => Schema::hasTable('product_inventory')
                    ? ProductInventory::where('is_used', false)->where('status', 'available')->count()
                    : 0,
            ],
            'warehouses' => $this->opsWarehouseRows(),
            'stock' => $this->opsWarehouseStockRows(),
            'vouchers' => $this->opsVoucherRows(),
        ]);
    }

    public function syncInventoryWarehouses(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (! Schema::hasTable('warehouses') || ! Schema::hasTable('warehouse_stocks')) {
            return response()->json([
                'success' => true,
                'message' => 'Warehouse tables are not available.',
                'synced_shops' => 0,
                'channel_warehouses' => 0,
            ]);
        }

        $shops = Shop::query()
            ->whereHas('warehouses', fn ($query) => $query->channelWarehouses()->where('is_active', true))
            ->get();

        $channelWarehouseCount = Warehouse::query()
            ->channelWarehouses()
            ->where('is_active', true)
            ->count();

        $synced = 0;
        foreach ($shops as $shop) {
            \App\Jobs\DistributeStockToChannels::dispatchSync($shop);
            $synced++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Marketplace warehouses synced from master warehouse.',
            'synced_shops' => $synced,
            'channel_warehouses' => $channelWarehouseCount,
        ]);
    }

    // 📋 AJAX — Поддержка и Тикеты
    public function getTicketsData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
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
        if (! $this->canAccessOps($user)) {
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

    public function getProvidersData(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = Provider::query()
            ->withCount([
                'providerProducts',
                'providerProducts as active_provider_products_count' => fn ($query) => $query->where('is_active', true),
            ]);

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $allProviders = $query->orderBy('type')->get();
        $authorityProviders = $allProviders
            ->filter(fn (Provider $provider): bool => $this->isSupplyAuthorityProvider($provider))
            ->values();
        $authorityProvidersByType = $authorityProviders
            ->sortBy(fn (Provider $provider): int => in_array($provider->type, ['wildflow', 'wildflow-sandbox'], true) ? 0 : 1)
            ->keyBy(fn (Provider $provider): string => $this->effectiveProviderType($provider));
        $expectedAuthorityTypes = $this->expectedSupplyAuthorityTypes();
        $authorityRows = collect($expectedAuthorityTypes)
            ->map(fn (string $type): array => $authorityProvidersByType->has($type)
                ? $this->providerOpsPayload($authorityProvidersByType->get($type))
                : $this->missingSupplyAuthorityPayload($type))
            ->merge(
                $authorityProvidersByType
                    ->reject(fn (Provider $provider, string $type): bool => in_array($type, $expectedAuthorityTypes, true))
                    ->map(fn (Provider $provider): array => $this->providerOpsPayload($provider))
                    ->values()
            )
            ->values();
        $catalogSources = $allProviders
            ->reject(fn (Provider $provider): bool => $this->isSupplyAuthorityProvider($provider))
            ->values();

        return response()->json([
            'data' => $authorityRows,
            'catalog_sources' => $catalogSources->map(fn (Provider $provider): array => $this->providerCatalogSourcePayload($provider))->values(),
            'kernel' => [
                'mode' => 'direct_supply_authority',
                'authority' => 'meanly.one',
                'upstream' => 'ezpin+fazercards',
                'upstream_label' => 'EZPin + Fazer Cards',
                'compatibility_host' => 'api.meanly.one',
                'ezpin_env_configured' => filled(config('services.ezpin.client_id')) && filled(config('services.ezpin.secret_key')),
                'compatibility_aliases' => ['legacy provider records resolve to ezpin'],
                'support_planes' => [
                    'docs' => [
                        'catalog' => '/api/v1/providers/{provider}/unified-catalog',
                        'availability' => '/api/v1/providers/{provider}/check-availability/{sku}',
                        'orders' => '/api/v1/providers/{provider}/order',
                        'balance' => '/api/v1/partners/{partner}',
                    ],
                    'devices' => [
                        'terminals_total' => SellerTerminal::count(),
                        'terminals_active' => SellerTerminal::where('is_active', true)->count(),
                    ],
                ],
            ],
        ]);
    }

    public function syncProvider(Request $request, Provider $provider)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'mode' => 'required|string|in:embedded,pull-upstream,http',
        ]);

        $args = [
            'provider' => $provider->id,
            '--force' => true,
        ];

        if ($data['mode'] === 'embedded') {
            $args['--embedded'] = true;
        }
        if ($data['mode'] === 'pull-upstream') {
            $args['--pull-upstream'] = true;
        }

        $provider->forceFill(['sync_status' => 'syncing'])->save();

        try {
            $exitCode = Artisan::call('app:sync-catalogs', $args);
            $provider->refresh()->forceFill([
                'sync_status' => 'idle',
                'last_sync_at' => $exitCode === 0 ? now() : $provider->last_sync_at,
            ])->save();

            return response()->json([
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'provider' => $this->providerOpsPayload($provider->refresh()),
                'output' => mb_substr(trim(Artisan::output()), -4000),
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Throwable $error) {
            $provider->refresh()->forceFill(['sync_status' => 'idle'])->save();

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    public function grantPartnerCredit(Request $request, LegalEntity $legalEntity)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'required|string|max:160',
        ]);

        $reservation = MeanlyApiReservation::query()->updateOrCreate(
            [
                'legal_entity_id' => $legalEntity->id,
                'reference' => $data['reference'],
            ],
            [
                'amount' => (float) $data['amount'],
                'status' => 'active',
                'expires_at' => now()->addHours(2),
            ],
        );

        return response()->json([
            'success' => true,
            'reservation_id' => 'MEANLY-HOLD-'.$reservation->id,
            'idempotent' => ! $reservation->wasRecentlyCreated,
            'partner' => [
                'id' => $legalEntity->id,
                'name' => $legalEntity->name,
                'available_balance' => round((float) $legalEntity->available_balance, 2),
            ],
        ]);
    }

    public function topUpPartnerBalance(Request $request, LegalEntity $legalEntity)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'required|string|max:160',
        ]);

        $intent = app(\App\Services\MerchantSettlementService::class)->issueIntent(
            legalEntity: $legalEntity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_OPS_MANUAL_CREDIT,
            amount: (float) $data['amount'],
            options: [
                'comment' => 'Ops manual credit',
                'idempotency_key' => 'ops-top-up|'.$legalEntity->id.'|'.$data['reference'].'|'.number_format((float) $data['amount'], 4, '.', ''),
            ],
        );
        $settlement = app(\App\Services\MerchantSettlementService::class);
        $proof = $settlement->recordProof(
            intent: $intent,
            externalReference: $data['reference'],
            confirmedAmount: (float) $data['amount'],
            source: 'ops_manual_credit',
            note: 'Ops submitted manual credit evidence.',
        );
        $settlement->attestProof(
            proof: $proof,
            signer: $user,
            type: \App\Models\ValidatorAttestation::TYPE_PROOF_OBSERVED,
            externalReference: $data['reference'],
            note: 'Ops attested manual credit evidence.',
        );
        $verdict = $settlement->evaluateAndCreditIfAllowed($proof->refresh());

        return response()->json([
            'success' => true,
            'intent' => $this->formatOpsDepositIntent($intent->refresh()->loadMissing(['legalEntity', 'targetLegalEntity', 'proofs'])),
            'proof' => $this->formatOpsSettlementProof($proof->loadMissing(['intent', 'legalEntity', 'reviewedBy'])),
            'authority_verdict' => $this->formatOpsAuthorityVerdict($verdict),
            'partner' => [
                'id' => $legalEntity->id,
                'name' => $legalEntity->name,
                'available_balance' => round((float) $legalEntity->refresh()->available_balance, 2),
            ],
        ]);
    }

    public function approveDepositIntent(Request $request, MerchantDepositIntent $merchantDepositIntent)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'external_reference' => 'required|string|max:160',
            'confirmed_amount' => 'nullable|numeric|min:0.01',
            'source' => 'nullable|string|max:40',
            'note' => 'nullable|string|max:1000',
        ]);

        $settlement = app(\App\Services\MerchantSettlementService::class);
        $proof = $settlement->recordProof(
            intent: $merchantDepositIntent,
            externalReference: $data['external_reference'],
            confirmedAmount: isset($data['confirmed_amount']) ? (float) $data['confirmed_amount'] : null,
            source: $data['source'] ?? 'ops_manual_review',
            note: $data['note'] ?? '',
            rawPayload: [
                'ops_user_id' => $user->id,
                'source' => $data['source'] ?? 'ops_manual_review',
                'note' => $data['note'] ?? '',
            ],
        );
        $settlement->attestProof(
            proof: $proof,
            signer: $user,
            type: \App\Models\ValidatorAttestation::TYPE_PROOF_OBSERVED,
            externalReference: $data['external_reference'],
            note: $data['note'] ?? '',
        );
        $verdict = $settlement->evaluateAndCreditIfAllowed($proof->refresh());

        return response()->json([
            'success' => true,
            'intent' => $this->formatOpsDepositIntent($merchantDepositIntent->refresh()->loadMissing(['legalEntity', 'targetLegalEntity', 'proofs', 'authorityVerdicts'])),
            'proof' => $this->formatOpsSettlementProof($proof->loadMissing(['intent', 'legalEntity', 'reviewedBy', 'authorityVerdicts'])),
            'authority_verdict' => $this->formatOpsAuthorityVerdict($verdict),
        ]);
    }

    public function rejectDepositIntent(Request $request, MerchantDepositIntent $merchantDepositIntent)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $latestProof = $merchantDepositIntent->proofs()->latest('id')->first();
        if ($latestProof) {
            $settlement = app(\App\Services\MerchantSettlementService::class);
            $settlement->attestProof(
                proof: $latestProof,
                signer: $user,
                type: \App\Models\ValidatorAttestation::TYPE_EVIDENCE_REJECTED,
                externalReference: $latestProof->external_reference,
                note: $data['note'] ?? '',
            );
            app(\App\Services\AuthorityPolicyService::class)->evaluateProof($latestProof->refresh());
        }

        $intent = app(\App\Services\MerchantSettlementService::class)->rejectIntent(
            intent: $merchantDepositIntent,
            reviewer: $user,
            note: $data['note'] ?? '',
        );

        return response()->json([
            'success' => true,
            'intent' => $this->formatOpsDepositIntent($intent->loadMissing(['legalEntity', 'targetLegalEntity', 'proofs'])),
        ]);
    }

    // 📋 AJAX — Детали тикета
    public function getTicketDetails($id)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
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
                    'sender' => $m->user->name ?? ($m->is_admin_reply ? 'Sovereign Validator' : 'Партнер'),
                    'message' => $m->message,
                    'is_admin' => (bool) $m->is_admin_reply,
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
        if (! $this->canAccessOps($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);
        $replyText = trim($validated['message']);
        if ($replyText === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'message' => 'Reply message is required.',
            ]);
        }

        $ticket = Ticket::findOrFail($id);

        $message = TicketMessage::create([
            'ticket_id' => $id,
            'user_id' => $user->id,
            'message' => $replyText,
            'is_admin_reply' => true,
        ]);

        $ticket->update([
            'status' => 'resolved',
            'last_reply_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ответ успешно добавлен!',
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
            ],
            'reply' => [
                'id' => $message->id,
                'is_admin' => true,
            ],
        ]);
    }

    // 📋 AJAX — Глобальный ИИ-аудит (Ledger Audit)
    public function runAiAudit(Request $request)
    {
        $user = Auth::user();
        if (! $this->canAccessOps($user)) {
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
        if (! $this->canAccessOps($user)) {
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
        if (! $this->canAccessOps($user)) {
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

    private function canAccessOps(?\App\Models\User $user): bool
    {
        return $user?->hasOpsSovereignAccess() === true;
    }

    private function formatOpsDepositIntent(MerchantDepositIntent $intent): array
    {
        $latestProof = ($intent->relationLoaded('proofs') ? $intent->proofs : collect())->sortByDesc('id')->first();
        $latestVerdict = ($intent->relationLoaded('authorityVerdicts') ? $intent->authorityVerdicts : collect())
            ->merge($latestProof?->authorityVerdicts ?? collect())
            ->sortByDesc('id')
            ->first();

        return [
            'id' => $intent->id,
            'reference' => $intent->reference,
            'partner' => $intent->legalEntity?->name ?? '—',
            'partner_id' => $intent->legal_entity_id,
            'target_partner' => $intent->targetLegalEntity?->name,
            'rail' => $intent->rail,
            'status' => $intent->status,
            'amount' => round((float) $intent->amount, 2),
            'currency' => $intent->currency ?: 'RUB',
            'proof_status' => $latestProof?->status,
            'proof_reference' => $latestProof?->external_reference,
            'authority' => $latestVerdict ? $this->formatOpsAuthorityVerdict($latestVerdict) : null,
            'created_at' => optional($intent->created_at)->format('d.m.Y H:i') ?: '—',
            'expires_at' => optional($intent->expires_at)->format('d.m.Y H:i') ?: '—',
            'action_urls' => [
                'approve' => route('ops.dashboard.deposit-intents.approve', ['merchantDepositIntent' => $intent->id]),
                'reject' => route('ops.dashboard.deposit-intents.reject', ['merchantDepositIntent' => $intent->id]),
            ],
        ];
    }

    private function formatOpsSettlementProof(SettlementProof $proof): array
    {
        $latestVerdict = ($proof->relationLoaded('authorityVerdicts') ? $proof->authorityVerdicts : collect())->sortByDesc('id')->first();

        return [
            'id' => $proof->id,
            'intent_id' => $proof->merchant_deposit_intent_id,
            'intent_reference' => $proof->intent?->reference,
            'partner' => $proof->legalEntity?->name ?? '—',
            'source' => $proof->source,
            'status' => $proof->status,
            'external_reference' => $proof->external_reference,
            'confirmed_amount' => round((float) $proof->confirmed_amount, 2),
            'confirmed_currency' => $proof->confirmed_currency ?: 'RUB',
            'reviewed_by' => $proof->reviewedBy?->name,
            'credited_ledger_id' => $proof->credited_ledger_id,
            'authority' => $latestVerdict ? $this->formatOpsAuthorityVerdict($latestVerdict) : null,
            'created_at' => optional($proof->created_at)->format('d.m.Y H:i') ?: '—',
        ];
    }

    private function formatOpsAuthorityVerdict(\App\Models\AuthorityVerdict $verdict): array
    {
        return [
            'id' => $verdict->id,
            'policy_key' => $verdict->policy_key,
            'status' => $verdict->status,
            'decision' => $verdict->decision,
            'reason_code' => $verdict->reason_code,
            'required_quorum' => (int) $verdict->required_quorum,
            'accepted_attestations' => (int) $verdict->accepted_attestations,
            'credited_ledger_id' => $verdict->credited_ledger_id,
            'decided_at' => optional($verdict->decided_at)->format('d.m.Y H:i') ?: null,
            'credited_at' => optional($verdict->credited_at)->format('d.m.Y H:i') ?: null,
        ];
    }

    private function legalEntityStatusLabel(LegalEntity $entity): string
    {
        return match ($entity->status) {
            'active' => 'Активна',
            'pending_moderation' => 'На модерации',
            'pending_signature' => 'Ждет подписи',
            'admin_console' => 'Админка',
            default => $entity->is_active ? 'Активна' : 'Не активна',
        };
    }

    private function opsCurrencyLiquidityRows()
    {
        if (! Schema::hasTable('currencies')) {
            return collect();
        }

        return Currency::query()
            ->orderByDesc('execution_ready')
            ->orderBy('code')
            ->limit(30)
            ->get()
            ->map(fn (Currency $currency): array => [
                'code' => $currency->code,
                'name' => $currency->name,
                'rate_to_rub' => round((float) $currency->effective_rate, 6),
                'base_asset' => $currency->base_asset,
                'quote_asset' => $currency->quote_asset,
                'market_regime' => $currency->market_regime,
                'execution_ready' => (bool) $currency->execution_ready,
                'confidence_score' => round((float) $currency->confidence_score, 4),
                'observability_score' => round((float) $currency->observability_score, 4),
                'stress_index' => round((float) $currency->liquidity_stress_index, 4),
                'max_executable_size' => round((float) $currency->max_executable_size, 2),
                'estimated_slippage' => round((float) $currency->estimated_slippage, 4),
                'settlement_time_hours' => round((float) $currency->settlement_time_hours, 2),
                'inbound_methods' => is_array($currency->inbound_methods) ? $currency->inbound_methods : [],
                'outbound_methods' => is_array($currency->outbound_methods) ? $currency->outbound_methods : [],
            ]);
    }

    private function opsLiquidityMethodsRows()
    {
        if (! Schema::hasTable('liquidity_methods')) {
            return collect();
        }

        return LiquidityMethod::query()
            ->withCount('currencies')
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->limit(25)
            ->get()
            ->map(fn (LiquidityMethod $method): array => [
                'name' => $method->name,
                'slug' => $method->slug,
                'type' => $method->type,
                'is_global' => (bool) $method->is_global,
                'is_active' => (bool) $method->is_active,
                'currencies_count' => (int) ($method->currencies_count ?? 0),
            ]);
    }

    private function opsLiquidityCorridorRows()
    {
        if (! Schema::hasTable('liquidity_corridors')) {
            return collect();
        }

        return LiquidityCorridor::query()
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (LiquidityCorridor $corridor): array => [
                'provider_node' => $corridor->provider_node,
                'currency_code' => $corridor->currency_code,
                'routing_asset' => $corridor->routing_asset,
                'direction' => $corridor->direction,
                'trust_tier' => (int) ($corridor->trust_tier ?? 0),
                'base_fee_percent' => round((float) ($corridor->base_fee_percent ?? 0), 2),
                'fixed_fee_amount' => round((float) ($corridor->fixed_fee_amount ?? 0), 4),
                'min_volume' => round((float) ($corridor->min_volume ?? 0), 4),
                'max_volume' => round((float) ($corridor->max_volume ?? 0), 4),
                'sla_minutes' => (int) ($corridor->sla_minutes ?? 0),
                'is_active' => (bool) $corridor->is_active,
                'metadata' => is_array($corridor->metadata) ? $corridor->metadata : [],
            ]);
    }

    private function opsIntentLiquidityCorridorRows()
    {
        if (! Schema::hasTable('intent_liquidity_corridors')) {
            return collect();
        }

        return IntentLiquidityCorridor::query()
            ->with('node')
            ->orderByDesc('execution_ready')
            ->orderByDesc('route_score')
            ->limit(30)
            ->get()
            ->map(fn (IntentLiquidityCorridor $corridor): array => [
                'intent_key' => $corridor->node?->intent_key,
                'intent_type' => $corridor->node?->intent_type,
                'entity_label' => $corridor->node?->entity_label,
                'corridor_type' => $corridor->corridor_type,
                'corridor_key' => $corridor->corridor_key,
                'source' => $corridor->source,
                'route_type' => $corridor->route_type,
                'route_score' => round((float) $corridor->route_score, 2),
                'capacity' => round((float) $corridor->capacity, 2),
                'friction_score' => round((float) $corridor->friction_score, 2),
                'execution_ready' => (bool) $corridor->execution_ready,
                'failure_modes' => is_array($corridor->failure_modes) ? $corridor->failure_modes : [],
            ]);
    }

    private function opsDemandGapRows()
    {
        if (! Schema::hasTable('demand_gaps')) {
            return collect();
        }

        return DemandGap::query()
            ->orderByDesc('opportunity_score')
            ->orderByDesc('estimated_lost_gmv')
            ->limit(25)
            ->get()
            ->map(fn (DemandGap $gap): array => [
                'query' => $gap->canonical_query,
                'brand' => $gap->brand_entity_key,
                'region' => $gap->region_entity_key,
                'category' => $gap->category_entity_key,
                'search_volume' => (int) $gap->search_volume,
                'zero_results_count' => (int) $gap->zero_results_count,
                'average_results_count' => round((float) $gap->average_results_count, 2),
                'orders' => round((float) $gap->attributed_orders_count, 2),
                'gmv' => round((float) $gap->attributed_gmv, 2),
                'lost_gmv' => round((float) $gap->estimated_lost_gmv, 2),
                'score' => round((float) $gap->opportunity_score, 2),
                'priority' => $gap->priority_label,
                'diagnosis' => $gap->opportunity_diagnosis,
                'confidence' => round((float) $gap->diagnosis_confidence, 2),
                'last_searched_at' => optional($gap->last_searched_at)->toIso8601String(),
            ]);
    }

    private function opsOpportunityCaseRows()
    {
        if (! Schema::hasTable('opportunity_cases')) {
            return collect();
        }

        return OpportunityCase::query()
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByDesc('before_opportunity_score')
            ->limit(25)
            ->get()
            ->map(fn (OpportunityCase $case): array => [
                'id' => $case->id,
                'query' => $case->canonical_query,
                'status' => $case->status,
                'owner_team' => $case->owner_team,
                'sla_due_at' => optional($case->sla_due_at)->toIso8601String(),
                'overdue' => $case->sla_due_at !== null
                    && $case->sla_due_at->isPast()
                    && in_array($case->status, [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS], true),
                'action_type' => $case->action_type,
                'before_score' => round((float) $case->before_opportunity_score, 2),
                'before_search_volume' => (int) $case->before_search_volume,
                'before_gmv' => round((float) $case->before_gmv, 2),
                'gmv_growth_percentage' => round((float) $case->gmv_growth_percentage, 2),
                'conversion_growth_percentage' => round((float) $case->conversion_growth_percentage, 2),
            ]);
    }

    private function opsSearchDemandRecommendationRows()
    {
        if (! Schema::hasTable('search_demand_recommendations')) {
            return collect();
        }

        return SearchDemandRecommendation::query()
            ->orderByRaw("CASE status WHEN 'proposed' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 WHEN 'applied' THEN 3 ELSE 4 END")
            ->orderByDesc('impact_score')
            ->limit(25)
            ->get()
            ->map(fn (SearchDemandRecommendation $recommendation): array => [
                'id' => $recommendation->id,
                'type' => $recommendation->type,
                'query' => $recommendation->query,
                'normalized_query' => $recommendation->normalized_query,
                'insight_type' => $recommendation->insight_type,
                'impact_score' => round((float) $recommendation->impact_score, 2),
                'confidence' => round((float) $recommendation->confidence, 2),
                'status' => $recommendation->status,
                'expected_entity' => is_array($recommendation->expected_entity) ? $recommendation->expected_entity : [],
                'updated_at' => optional($recommendation->updated_at)->toIso8601String(),
            ]);
    }

    private function opsOperationalAlertRows()
    {
        if (! Schema::hasTable('meanly_operational_alerts')) {
            return collect();
        }

        return MeanlyOperationalAlert::query()
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'warning' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderByDesc('last_seen_at')
            ->limit(25)
            ->get()
            ->map(fn (MeanlyOperationalAlert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'surface' => $alert->surface,
                'status' => $alert->status,
                'title' => $alert->title,
                'occurrence_count' => (int) $alert->occurrence_count,
                'last_seen_at' => optional($alert->last_seen_at)->toIso8601String(),
            ]);
    }

    private function opsWarehouseRows()
    {
        if (! Schema::hasTable('warehouses')) {
            return collect();
        }

        return Warehouse::query()
            ->with('shop.legalEntity')
            ->withCount('stocks')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'shop' => $warehouse->shop?->name ?? '—',
                'partner' => $warehouse->shop?->legalEntity?->name ?? '—',
                'channel' => $warehouse->channel_label,
                'is_main' => (bool) $warehouse->is_main,
                'is_active' => (bool) $warehouse->is_active,
                'stock_rows' => (int) ($warehouse->stocks_count ?? 0),
            ]);
    }

    private function opsWarehouseStockRows()
    {
        if (! Schema::hasTable('warehouse_stocks')) {
            return collect();
        }

        return WarehouseStock::query()
            ->with(['warehouse.shop.legalEntity', 'product'])
            ->orderBy('count')
            ->limit(30)
            ->get()
            ->map(fn (WarehouseStock $stock): array => [
                'id' => $stock->id,
                'product' => $stock->product?->name ?? '—',
                'sku' => $stock->product?->sku,
                'warehouse' => $stock->warehouse?->name ?? '—',
                'shop' => $stock->warehouse?->shop?->name ?? '—',
                'partner' => $stock->warehouse?->shop?->legalEntity?->name ?? '—',
                'count' => (int) $stock->count,
                'synced_at' => optional($stock->synced_at)->toIso8601String(),
            ]);
    }

    private function opsVoucherRows()
    {
        if (! Schema::hasTable('product_inventory')) {
            return collect();
        }

        return ProductInventory::query()
            ->with(['shop.legalEntity', 'warehouse'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (ProductInventory $voucher): array => [
                'id' => $voucher->id,
                'transaction_ref' => $voucher->transactionReference(),
                'sku' => $voucher->sku,
                'shop' => $voucher->shop?->name ?? '—',
                'partner' => $voucher->shop?->legalEntity?->name ?? '—',
                'warehouse' => $voucher->warehouse?->name ?? '—',
                'status' => $voucher->status,
                'is_used' => (bool) $voucher->is_used,
                'nominal' => round((float) $voucher->nominal_amount, 2),
                'currency' => $voucher->nominal_currency,
                'reserved_amount' => round((float) $voucher->reserved_amount, 2),
                'reserve_currency' => $voucher->reserve_currency,
            ]);
    }

    private function opsZeroLayerIntegrationRows()
    {
        if (! Schema::hasTable('zero_layer_integrations')) {
            return collect();
        }

        return ZeroLayerIntegration::query()
            ->withCount('signals')
            ->orderByDesc('status')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ZeroLayerIntegration $integration): array => [
                'id' => $integration->id,
                'name' => $integration->name,
                'source' => $integration->source,
                'status' => $integration->status,
                'credential_keys' => array_keys((array) ($integration->credentials ?? [])),
                'settings_keys' => array_keys((array) ($integration->settings ?? [])),
                'signals_count' => (int) ($integration->signals_count ?? 0),
                'last_synced_at' => optional($integration->last_synced_at)->toIso8601String(),
                'sync_url' => route('ops.dashboard.zero-layer.sync', $integration, false),
            ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function zeroLayerConnectorDefinitions(): array
    {
        return [
            'google_analytics' => [
                'label' => 'Google Analytics 4',
                'credentials' => ['access_token'],
                'settings' => ['property_id', 'row_limit'],
                'example_credentials' => ['access_token' => 'ya29...'],
                'example_settings' => ['property_id' => '123456789', 'row_limit' => 10000],
            ],
            'google_search_console' => [
                'label' => 'Google Search Console',
                'credentials' => ['access_token'],
                'settings' => ['site_url', 'row_limit'],
                'example_credentials' => ['access_token' => 'ya29...'],
                'example_settings' => ['site_url' => 'https://meanly.one/', 'row_limit' => 25000],
            ],
            'google_ads' => [
                'label' => 'Google Ads',
                'credentials' => ['access_token', 'developer_token'],
                'settings' => ['customer_id', 'login_customer_id', 'api_version'],
                'example_credentials' => ['access_token' => 'ya29...', 'developer_token' => '...'],
                'example_settings' => ['customer_id' => '1234567890', 'login_customer_id' => '1234567890'],
            ],
            'yandex_webmaster' => [
                'label' => 'Yandex Webmaster',
                'credentials' => ['oauth_token'],
                'settings' => ['user_id', 'host_id', 'limit'],
                'example_credentials' => ['oauth_token' => '...'],
                'example_settings' => ['user_id' => '123', 'host_id' => 'https:meanly.ru:443', 'limit' => 500],
            ],
            'indexnow' => [
                'label' => 'IndexNow',
                'credentials' => ['key'],
                'settings' => ['host', 'key_location', 'urls'],
                'example_credentials' => ['key' => 'indexnow-key'],
                'example_settings' => ['host' => 'meanly.one', 'urls' => ['https://meanly.one/sitemap.xml']],
            ],
            'bing_web_search' => [
                'label' => 'Bing Web Search',
                'credentials' => ['api_key'],
                'settings' => ['queries', 'market'],
                'example_credentials' => ['api_key' => '...'],
                'example_settings' => ['queries' => ['xbox gift card'], 'market' => 'en-US'],
            ],
            'yahoo_search' => [
                'label' => 'Yahoo Search',
                'credentials' => ['api_key'],
                'settings' => ['queries', 'location'],
                'example_credentials' => ['api_key' => 'searchapi-key'],
                'example_settings' => ['queries' => ['steam turkey 100'], 'location' => 'United States'],
            ],
            'duckduckgo_search' => [
                'label' => 'DuckDuckGo Search',
                'credentials' => ['api_key'],
                'settings' => ['queries', 'params'],
                'example_credentials' => ['api_key' => 'searchapi-key'],
                'example_settings' => ['queries' => ['playstation turkey'], 'params' => ['kl' => 'us-en']],
            ],
            'yandex_direct' => [
                'label' => 'Yandex Direct',
                'credentials' => ['oauth_token', 'client_login'],
                'settings' => ['campaign_ids'],
                'example_credentials' => ['oauth_token' => '...', 'client_login' => 'meanly'],
                'example_settings' => ['campaign_ids' => ['123456']],
            ],
            'meta_ads' => [
                'label' => 'Meta Ads',
                'credentials' => ['access_token', 'ad_account_id'],
                'settings' => ['fields'],
                'example_credentials' => ['access_token' => '...', 'ad_account_id' => 'act_123'],
                'example_settings' => [],
            ],
            'tiktok_ads' => [
                'label' => 'TikTok Ads',
                'credentials' => ['access_token', 'advertiser_id'],
                'settings' => [],
                'example_credentials' => ['access_token' => '...', 'advertiser_id' => '123456789'],
                'example_settings' => [],
            ],
        ];
    }

    private function opsSearchSourceTotals()
    {
        $zeroLayer = Schema::hasTable('zero_layer_signals')
            ? ZeroLayerSignal::query()
                ->select('source')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(COALESCE(impressions, 0)) as impressions')
                ->selectRaw('SUM(COALESCE(clicks, 0)) as clicks')
                ->groupBy('source')
                ->get()
                ->map(fn ($row): array => [
                    'pipeline' => 'zero_layer',
                    'source' => $row->source,
                    'total' => (int) $row->total,
                    'impressions' => round((float) $row->impressions, 2),
                    'clicks' => round((float) $row->clicks, 2),
                ])
            : collect();

        $external = Schema::hasTable('external_search_query_signals')
            ? ExternalSearchQuerySignal::query()
                ->select('source')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(impressions) as impressions')
                ->selectRaw('SUM(clicks) as clicks')
                ->groupBy('source')
                ->get()
                ->map(fn ($row): array => [
                    'pipeline' => 'external_demand',
                    'source' => $row->source,
                    'total' => (int) $row->total,
                    'impressions' => (int) $row->impressions,
                    'clicks' => (int) $row->clicks,
                ])
            : collect();

        return $zeroLayer->concat($external)->values();
    }

    private function opsZeroLayerSignalRows()
    {
        if (! Schema::hasTable('zero_layer_signals')) {
            return collect();
        }

        return ZeroLayerSignal::query()
            ->with('integration')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ZeroLayerSignal $signal): array => [
                'id' => $signal->id,
                'source' => $signal->source,
                'signal_type' => $signal->signal_type,
                'integration' => $signal->integration?->name,
                'query_text' => Str::limit((string) $signal->query_text, 120),
                'page_url' => Str::limit((string) $signal->page_url, 120),
                'position' => $signal->position !== null ? round((float) $signal->position, 2) : null,
                'impressions' => round((float) $signal->impressions, 2),
                'clicks' => round((float) $signal->clicks, 2),
                'cost' => round((float) $signal->cost, 2),
                'signal_date' => optional($signal->signal_date)->toDateString(),
            ]);
    }

    private function opsExternalSearchSignalRows()
    {
        if (! Schema::hasTable('external_search_query_signals')) {
            return collect();
        }

        return ExternalSearchQuerySignal::query()
            ->latest('observed_at')
            ->limit(30)
            ->get()
            ->map(fn (ExternalSearchQuerySignal $signal): array => [
                'id' => $signal->id,
                'source' => $signal->source,
                'query' => $signal->query,
                'normalized_query' => $signal->normalized_query,
                'country' => $signal->country,
                'locale' => $signal->locale,
                'impressions' => (int) $signal->impressions,
                'clicks' => (int) $signal->clicks,
                'volume' => (int) $signal->volume,
                'landing_url' => Str::limit((string) $signal->landing_url, 120),
                'observed_at' => optional($signal->observed_at)->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function opsArtisanJsonResponse(string $command, array $arguments)
    {
        try {
            $exitCode = Artisan::call($command, $arguments);
            $output = trim(Artisan::output());
            $payload = $output !== '' ? json_decode($output, true) : null;
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'payload' => is_array($payload) ? $payload : null,
            'output' => is_array($payload) ? null : $output,
        ], $exitCode === 0 ? 200 : 422);
    }

    private function zeroLayerSignalPromotionRecord(ZeroLayerSignal $signal): array
    {
        return [
            'query' => (string) $signal->query_text,
            'source' => $signal->source,
            'landing_url' => $signal->page_url,
            'observed_at' => optional($signal->signal_date)->toDateString() ?: optional($signal->created_at)->toDateString(),
            'impressions' => (int) round((float) $signal->impressions),
            'clicks' => (int) round((float) $signal->clicks),
            'metadata' => [
                'zero_layer_signal_id' => $signal->id,
                'signal_type' => $signal->signal_type,
                'position' => $signal->position !== null ? (float) $signal->position : null,
                'campaign' => $signal->campaign,
                'ad_group' => $signal->ad_group,
                'title' => $signal->title,
            ],
        ];
    }

    private function kernelOrderOperationPayload(MeanlyApiOrder $order): array
    {
        return [
            'source' => 'kernel_order',
            'type' => 'Aggregator order',
            'reference' => $order->proxy_reference ?: $order->marketplace_reference ?: 'MEANLY-API-'.$order->id,
            'partner' => $order->legalEntity?->name ?? '—',
            'provider' => $order->provider,
            'sku' => $order->service_sku,
            'amount' => round((float) $order->price, 2),
            'currency' => $order->currency ?? 'USD',
            'status' => $order->status,
            'failure_reason' => $order->error_message,
            'created_at' => optional($order->created_at)->format('d.m.Y H:i') ?: '—',
            'sort_at' => optional($order->created_at)->timestamp ?? 0,
        ];
    }

    private function ledgerOperationPayload(SovereignLedger $event): array
    {
        return [
            'source' => 'ledger',
            'type' => $event->event_type,
            'reference' => method_exists($event, 'transactionReference') ? $event->transactionReference() : 'LEDGER-'.$event->id,
            'partner' => $event->legalEntity?->name ?? $event->shop?->legalEntity?->name ?? '—',
            'provider' => data_get($event->payload, 'provider', data_get($event->payload, 'source', 'ledger')),
            'sku' => data_get($event->payload, 'sku', '—'),
            'amount' => round((float) ($event->amount_base ?? data_get($event->payload, 'amount', 0)), 2),
            'currency' => $event->currency ?? $event->base_currency ?? data_get($event->payload, 'currency', '—'),
            'status' => data_get($event->payload, 'status', 'recorded'),
            'failure_reason' => data_get($event->payload, 'error') ?: data_get($event->payload, 'failure_reason'),
            'created_at' => optional($event->created_at)->format('d.m.Y H:i') ?: '—',
            'sort_at' => optional($event->created_at)->timestamp ?? 0,
        ];
    }

    private function orderItemOperationPayload(OrderItems $item): array
    {
        $order = $item->order;

        return [
            'source' => 'marketplace_item',
            'type' => 'Marketplace fulfillment',
            'reference' => $item->provider_order_id ?: ($order?->order_id ?: 'ITEM-'.$item->id),
            'partner' => $order?->shop?->legalEntity?->name ?? '—',
            'provider' => (string) ($order?->provider_id ? 'provider#'.$order->provider_id : 'marketplace'),
            'sku' => $item->sku ?? '—',
            'amount' => round(((float) ($item->price_rub ?? 0)) / 100, 2),
            'currency' => 'RUB',
            'status' => $item->purchase_status ?: ($order?->progress_id === 4 ? 'completed' : 'processing'),
            'failure_reason' => $item->purchase_error,
            'created_at' => optional($item->created_at)->format('d.m.Y H:i') ?: '—',
            'sort_at' => optional($item->created_at)->timestamp ?? 0,
        ];
    }

    private function providerOpsPayload(Provider $provider): array
    {
        $rawCredentials = is_array($provider->credentials) ? $provider->credentials : [];
        $credentials = $this->providerAuthorityCredentials($provider, $rawCredentials);
        $settings = is_array($provider->settings) ? $provider->settings : [];
        $supportsUpstreamPull = in_array($provider->type, ['ezpin', 'ezpin-sandbox', 'wildflow', 'wildflow-sandbox', 'fazer'], true);
        $isLegacyAlias = in_array($provider->type, ['wildflow', 'wildflow-sandbox'], true);
        $effectiveType = $this->effectiveProviderType($provider);

        return [
            'id' => $provider->id,
            'name' => $this->providerDisplayName($provider),
            'type' => $effectiveType,
            'authority' => 'meanly.one',
            'upstream_provider' => $this->upstreamProviderSlug($effectiveType),
            'upstream_label' => $this->upstreamProviderLabel($effectiveType),
            'is_legacy_alias' => $isLegacyAlias,
            'is_active' => (bool) $provider->is_active,
            'sync_status' => $provider->sync_status ?: 'idle',
            'last_sync_at' => optional($provider->last_sync_at)->format('d.m.Y H:i') ?: '—',
            'catalog_source' => data_get($settings, 'catalog_source', 'http'),
            'provider_products_count' => (int) ($provider->provider_products_count ?? 0),
            'active_provider_products_count' => (int) ($provider->active_provider_products_count ?? 0),
            'credentials' => [
                'api_key' => filled($credentials['api_key'] ?? null),
                'client_id' => filled($credentials['client_id'] ?? null),
                'secret_key' => filled(($credentials['secret_key'] ?? null) ?: ($credentials['client_secret'] ?? null)),
                'terminal' => filled($credentials['terminal_id'] ?? null) || filled($credentials['terminal_pin'] ?? null),
                'financial_secret' => filled($credentials['financial_secret'] ?? null),
            ],
            'terminal' => [
                'id_configured' => filled($credentials['terminal_id'] ?? null),
                'pin_configured' => filled($credentials['terminal_pin'] ?? null),
                'id_masked' => $this->maskSecret((string) ($credentials['terminal_id'] ?? '')),
            ],
            'health' => [
                'catalog_ready' => (int) ($provider->active_provider_products_count ?? 0) > 0,
                'credentials_ready' => filled($credentials['api_key'] ?? null)
                    || (filled($credentials['client_id'] ?? null) && filled(($credentials['secret_key'] ?? null) ?: ($credentials['client_secret'] ?? null))),
                'terminal_ready' => filled($credentials['terminal_id'] ?? null) && filled($credentials['terminal_pin'] ?? null),
                'supports_upstream_pull' => $supportsUpstreamPull,
                'last_error' => data_get($settings, 'last_error'),
            ],
            'sync_url' => route('ops.dashboard.providers.sync', ['provider' => $provider->id]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function expectedSupplyAuthorityTypes(): array
    {
        return ['ezpin', 'fazer'];
    }

    private function missingSupplyAuthorityPayload(string $effectiveType): array
    {
        return [
            'id' => null,
            'name' => $this->providerDisplayNameForType($effectiveType),
            'type' => $effectiveType,
            'authority' => 'meanly.one',
            'upstream_provider' => $this->upstreamProviderSlug($effectiveType),
            'upstream_label' => $this->upstreamProviderLabel($effectiveType),
            'is_legacy_alias' => false,
            'is_active' => false,
            'sync_status' => 'not_configured',
            'last_sync_at' => '—',
            'catalog_source' => 'http',
            'provider_products_count' => 0,
            'active_provider_products_count' => 0,
            'credentials' => [
                'api_key' => false,
                'client_id' => false,
                'secret_key' => false,
                'terminal' => false,
                'financial_secret' => false,
            ],
            'terminal' => [
                'id_configured' => false,
                'pin_configured' => false,
                'id_masked' => 'not configured',
            ],
            'health' => [
                'catalog_ready' => false,
                'credentials_ready' => false,
                'terminal_ready' => false,
                'supports_upstream_pull' => false,
                'last_error' => 'Provider record is not configured yet.',
            ],
            'sync_url' => null,
        ];
    }

    private function isSupplyAuthorityProvider(Provider $provider): bool
    {
        return in_array((string) $provider->type, ['ezpin', 'ezpin-sandbox', 'wildflow', 'wildflow-sandbox', 'fazer'], true);
    }

    private function effectiveProviderType(Provider $provider): string
    {
        return match ((string) $provider->type) {
            'wildflow', 'ezpin' => 'ezpin',
            'wildflow-sandbox', 'ezpin-sandbox' => 'ezpin-sandbox',
            'fazer' => 'fazer',
            default => (string) $provider->type,
        };
    }

    private function upstreamProviderSlug(string $effectiveType): string
    {
        return match ($effectiveType) {
            'ezpin-sandbox' => 'ezpin-sandbox',
            'fazer' => 'fazer',
            default => 'ezpin',
        };
    }

    private function upstreamProviderLabel(string $effectiveType): string
    {
        return match ($effectiveType) {
            'ezpin-sandbox' => 'EZPin Sandbox',
            'fazer' => 'Fazer Cards',
            default => 'EZPin',
        };
    }

    private function providerDisplayName(Provider $provider): string
    {
        return $this->providerDisplayNameForType($this->effectiveProviderType($provider), (string) $provider->name);
    }

    private function providerDisplayNameForType(string $effectiveType, ?string $fallback = null): string
    {
        return match ($effectiveType) {
            'ezpin-sandbox' => 'EZPin Sandbox',
            'ezpin' => 'EZPin',
            'fazer' => 'Fazer Cards',
            default => (string) $fallback,
        };
    }

    private function providerCatalogSourcePayload(Provider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'type' => $provider->type,
            'source_kind' => 'parsed_catalog_source',
            'is_active' => (bool) $provider->is_active,
            'provider_products_count' => (int) ($provider->provider_products_count ?? 0),
            'active_provider_products_count' => (int) ($provider->active_provider_products_count ?? 0),
            'note' => 'Parsed catalog/source row, not a supply authority.',
        ];
    }

    private function providerAuthorityCredentials(Provider $provider, array $credentials): array
    {
        if (in_array($provider->type, ['ezpin', 'ezpin-sandbox', 'wildflow', 'wildflow-sandbox'], true)) {
            return array_filter([
                'api_key' => $credentials['api_key'] ?? null,
                'client_id' => $credentials['client_id'] ?? config('services.ezpin.client_id'),
                'secret_key' => ($credentials['secret_key'] ?? null) ?: ($credentials['client_secret'] ?? null) ?: config('services.ezpin.secret_key'),
                'client_secret' => $credentials['client_secret'] ?? null,
                'terminal_id' => $credentials['terminal_id'] ?? config('services.ezpin.terminal_id'),
                'terminal_pin' => $credentials['terminal_pin'] ?? config('services.ezpin.terminal_pin'),
                'financial_secret' => $credentials['financial_secret'] ?? null,
                'base_url' => $credentials['base_url'] ?? config('services.ezpin.base_url'),
            ], fn ($value) => filled($value));
        }

        return $credentials;
    }

    private function legalEntityApiIdentityPayload(LegalEntity $entity): array
    {
        $metadata = is_array($entity->agreement_metadata) ? $entity->agreement_metadata : [];
        $whitelist = array_values(array_filter($entity->meanlyIpWhitelist()));
        $token = $entity->meanlyApiToken();
        $financialSecret = $entity->meanlyFinancialSecret();

        return [
            'client_id' => (string) $entity->id,
            'kernel_external_id' => (string) (
                data_get($metadata, 'kernel_external_id')
                ?: data_get($metadata, 'meanly_api_client_id')
                ?: data_get($metadata, 'wildflow_client_id')
                ?: data_get($metadata, 'l1_address')
                ?: ''
            ),
            'token_configured' => $token !== '',
            'token_masked' => $this->maskSecret($token),
            'financial_secret_configured' => $financialSecret !== '',
            'financial_secret_masked' => $this->maskSecret($financialSecret),
            'ip_whitelist_count' => count($whitelist),
            'ip_whitelist' => $whitelist,
        ];
    }

    private function legalEntitySettlementPayload(LegalEntity $entity): array
    {
        if (! Schema::hasTable('wildflow_credit_reservations')) {
            return [
                'currency' => $entity->currency ?? 'RUB',
                'active_reservations_count' => 0,
                'active_reservations_amount' => 0.0,
            ];
        }

        $activeReservations = MeanlyApiReservation::query()
            ->where('legal_entity_id', $entity->id)
            ->where('status', 'active');

        return [
            'currency' => $entity->currency ?? 'RUB',
            'active_reservations_count' => (clone $activeReservations)->count(),
            'active_reservations_amount' => round((float) (clone $activeReservations)->sum('amount'), 2),
        ];
    }

    private function maskSecret(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'not configured';
        }

        if (mb_strlen($value) <= 10) {
            return mb_substr($value, 0, 2).'...'.mb_substr($value, -2);
        }

        return mb_substr($value, 0, 6).'...'.mb_substr($value, -4);
    }
}
