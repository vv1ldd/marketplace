<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('filament.partner.auth.login');
        }

        $legalEntity = $user->legalEntities()->first();

        // If no legal entity exists, direct to registration page
        if (!$legalEntity) {
            return redirect()->route('partner.register');
        }

        // Dynamically reconstruct balance using MDK Sovereign L1 Ledger
        $l1State = app(\App\Services\L1StateService::class)->reconstructBalance($legalEntity);

        // Fetch stats if entity is active
        $stats = [
            'balance' => $l1State['available_balance'],
            'reserved_balance' => $l1State['reserved_balance'],
            'total_balance' => $l1State['total_balance'],
            'native_balance' => $l1State['native_available_balance'],
            'native_reserved_balance' => $l1State['native_reserved_balance'],
            'native_total_balance' => $l1State['native_total_balance'],
            'integrity_secured' => $l1State['integrity_secured'],
            'channels_count' => $legalEntity->shops()->count(),
            'active_orders' => Order::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
                ->where('progress_id', '<>', 4)
                ->count(),
            'completed_orders_30_days' => Order::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->where('progress_id', 4)
                ->count(),
            'revenue_30_days' => (float) (DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->join('shops', 'orders.shop_id', '=', 'shops.id')
                ->where('shops.legal_entity_id', $legalEntity->id)
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->where('orders.progress_id', 4)
                ->sum('order_items.price_rub') / 100),
            'market_errors_count' => \App\Models\Product::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
                ->whereNotNull('ym_errors')
                ->count(),
        ];

        $shops = $legalEntity->shops()->get();

        $testOrders = Order::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->where('is_test', true)
            ->with(['items'])
            ->latest()
            ->limit(5)
            ->get();

        // 📋 Fetch all B2B panel resources for integrated SPA view
        $orders = Order::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with(['items', 'shop'])
            ->latest()
            ->limit(50)
            ->get();

        $catalog = \App\Models\Product::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with(['shop'])
            ->latest()
            ->limit(50)
            ->get();

        $tickets = \App\Models\Ticket::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->latest()
            ->limit(50)
            ->get();

        $warehouses = \App\Models\Warehouse::where('is_main', true)
            ->whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->latest()
            ->limit(50)
            ->get();

        // Scope provider products according to allowed regions/categories of the legal entity's shops
        $shops = $legalEntity->shops;
        $providerProductsQuery = \App\Models\ProviderProduct::where('is_active', true);
        
        $allRegions = $shops->flatMap->allowed_regions->unique()->filter()->toArray();
        if (!empty($allRegions)) {
            $providerProductsQuery->whereIn('region_id', function ($q) use ($allRegions) {
                $q->select('id')
                    ->from('mapping_countries')
                    ->whereIn('code', $allRegions);
            });
        }

        $allCategories = $shops->flatMap->allowed_categories->unique()->filter()->toArray();
        if (!empty($allCategories)) {
            $providerProductsQuery->where(function ($q) use ($allCategories) {
                foreach ($allCategories as $category) {
                    $q->orWhere('category', 'like', "%{$category}%");
                }
            });
        }

        $providerProducts = $providerProductsQuery->latest()
            ->limit(50)
            ->get();

        $vouchers = \App\Models\ProductInventory::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with('orderItem')
            ->latest()
            ->limit(50)
            ->get();

        $apiApplications = \App\Models\ApiApplication::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->latest()
            ->get();

        $ledgerTransactions = DB::table('sovereign_ledger')
            ->where('legal_entity_id', $legalEntity->id)
            ->latest()
            ->limit(50)
            ->get();

        $activations = \App\Models\Procurement::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with(['product', 'warehouse', 'shop'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'date' => $p->completed_at ? $p->completed_at->format('d.m.Y H:i') : ($p->created_at ? $p->created_at->format('d.m.Y H:i') : '—'),
                    'product_name' => $p->product->name ?? '—',
                    'sku' => $p->product->sku ?? '—',
                    'warehouse_name' => $p->warehouse->name ?? '—',
                    'count' => $p->count,
                    'total_price_rub' => round($p->total_price / 100, 2),
                    'status' => $p->status,
                ];
            });

        $sovereignRequests = \App\Models\SovereignBalanceRequest::where('legal_entity_id', $legalEntity->id)
            ->latest()
            ->get();

        $agreement = \App\Models\Agreement::where('is_active', true)->latest('published_at')->first();
        $agreementText = $agreement ? $agreement->content : "Текст оферты не найден.";

        return view('partner.dashboard', [
            'user' => $user,
            'legalEntity' => $legalEntity,
            'agreementText' => $agreementText,
            'stats' => $stats,
            'shops' => $shops,
            'testOrders' => $testOrders,
            'orders' => $orders,
            'catalog' => $catalog,
            'tickets' => $tickets,
            'warehouses' => $warehouses,
            'providerProducts' => $providerProducts,
            'vouchers' => $vouchers,
            'apiApplications' => $apiApplications,
            'ledgerTransactions' => $ledgerTransactions,
            'activations' => $activations,
            'sovereignRequests' => $sovereignRequests,
        ]);
    }

    public function signAgreement(Request $request)
    {
        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        $legalEntity->update([
            'agreement_signed_at' => now(),
            'agreement_signature' => 'SGN:' . bin2hex(random_bytes(32)),
        ]);

        return response()->json(['success' => true]);
    }

    public function updateBank(Request $request)
    {
        $request->validate([
            'bic' => 'required|string|size:9',
            'account' => 'required|string|size:20',
        ]);

        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        $legalEntity->update([
            'bank_bic' => $request->bic,
            'bank_account' => $request->account,
        ]);

        return response()->json(['success' => true]);
    }

    public function createSandboxOrder(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'price_rub' => 'required|integer',
            'code' => 'required|string',
            'mode' => 'nullable|string|in:legacy,wildflow_sandbox',
            'service_sku' => 'nullable|string',
            'nominal_amount' => 'nullable|numeric|min:0.01',
            'nominal_currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0.0001',
        ]);

        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();
        $shop = $legalEntity?->shops?->first();

        if (!$shop) {
            return response()->json(['error' => 'Нет подключенных магазинов/каналов.'], 400);
        }

        if ($request->input('mode') === 'wildflow_sandbox') {
            return $this->createWildflowSandboxOrder($request, $legalEntity, $shop);
        }

        try {
            DB::beginTransaction();

            $sandboxId = 'SANDBOX-' . strtoupper(Str::random(8));

            $orderId = DB::table('orders')->insertGetId([
                'order_id'    => $sandboxId,
                'uuid'        => Str::uuid()->toString(),
                'status'      => 'PROCESSING',
                'sub_status'  => 'SANDBOX',
                'shop_id'     => $shop->id,
                'is_test'     => 1,
                'progress_id' => 2,
                'info'        => json_encode([]),
                'client_info' => json_encode([
                    'firstName' => 'Sandbox',
                    'lastName'  => 'Client',
                    'email'     => 'sandbox@example.com',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $typeFormId = \App\Models\Product::queryByOfferSku($request->sku)?->value('type_form_id');

            $voucherCode = $request->code;
            if ($voucherCode === 'SANDBOX-TEST-CODE-0000') {
                $voucherCode = \App\Services\VoucherEngine::issue(
                    issuerPrefix: $shop->name ?? 'SND',
                    sku: $request->sku
                );
            }

            $vault = app(\App\Services\VaultTransitService::class);
            $encryptedCode = $vault->encrypt($voucherCode);
            $blindIndex = $vault->computeBlindIndex($voucherCode);

            DB::table('order_items')->insert([
                'uuid'            => Str::uuid()->toString(),
                'order_id'        => $orderId,
                'sku'             => $request->sku,
                'count'           => 1,
                'price_rub'       => (int) $request->price_rub,
                'purchase_status' => 'sandbox',
                'original_code'   => $encryptedCode,
                'key'             => $encryptedCode,
                'key_bidx'        => $blindIndex,
                'is_activated'    => 0,
                'is_redeemed'     => 0,
                'type_form_id'    => $typeFormId,
                'activate_till'   => now()->addYear()->format('Y-m-d'),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            DB::table('order_comments')->insert([
                'order_id'   => $orderId,
                'user_id'    => null,
                'user_type'  => null,
                'comment'    => '🧪 Тестовый заказ (Sandbox) создан вручную из B2B консоли.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            $orderItem = \App\Models\Order\OrderItems::where('order_id', $orderId)->first();

            return response()->json([
                'success' => true,
                'transaction_ref' => $orderItem?->transactionReference(),
                'source_order_id' => $sandboxId,
                'voucher_code' => $voucherCode,
                'redeem_url' => route('redeem.code', ['code' => $voucherCode]),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function createWildflowSandboxOrder(Request $request, $legalEntity, Shop $shop)
    {
        try {
            DB::beginTransaction();

            $provider = \App\Models\Provider::updateOrCreate(
                ['type' => 'wildflow-sandbox'],
                [
                    'name' => 'Wildflow EZPin Sandbox',
                    'is_active' => true,
                    'settings' => ['upstream_provider' => 'ezpin-sandbox'],
                ]
            );

            $sku = trim((string) $request->sku);
            $serviceSku = trim((string) ($request->input('service_sku') ?: $sku));
            $nominalCurrency = strtoupper((string) ($request->input('nominal_currency') ?: 'USD'));
            $nominalAmount = round((float) ($request->input('nominal_amount') ?: max(0.01, ((int) $request->price_rub) / 100)), 2);
            $exchangeRate = round((float) ($request->input('exchange_rate') ?: 85.0), 4);
            $costRub = round($nominalAmount * $exchangeRate, 2);
            $priceRubMinor = (int) round($costRub * 100);
            $marginRub = 0.0;

            $catalog = \App\Models\WildflowCatalog::firstOrCreate(
                ['sku' => $sku],
                [
                    'provider_id' => $provider->id,
                    'service_sku' => $serviceSku,
                    'type' => 'sandbox_e2e',
                    'retail_price' => $nominalAmount,
                    'purchase_price' => $nominalAmount,
                    'min_price' => $nominalAmount,
                    'max_price' => $nominalAmount,
                    'is_active' => true,
                    'data' => [
                        'display_name' => "EZPin Sandbox {$serviceSku}",
                        'currency' => $nominalCurrency,
                        'service_sku' => $serviceSku,
                    ],
                ]
            );

            $catalog->forceFill([
                'provider_id' => $provider->id,
                'retail_price' => $nominalAmount,
                'purchase_price' => $nominalAmount,
                'min_price' => $nominalAmount,
                'max_price' => $nominalAmount,
                'is_active' => true,
            ])->save();

            \App\Models\ProviderProduct::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'market_sku' => $sku,
                ],
                [
                    'sku' => $serviceSku,
                    'name' => $catalog->title,
                    'category' => 'Sandbox Gift Card',
                    'reward_type' => 'Gift-Card',
                    'purchase_price' => $nominalAmount,
                    'retail_price' => $nominalAmount,
                    'min_price' => $nominalAmount,
                    'max_price' => $nominalAmount,
                    'currency' => $nominalCurrency,
                    'is_active' => true,
                    'data' => ['upstream_provider' => 'ezpin-sandbox'],
                ]
            );

            $voucherCode = $request->code;
            if ($voucherCode === 'SANDBOX-TEST-CODE-0000') {
                $voucherCode = \App\Services\VoucherEngine::issue(
                    issuerPrefix: $shop->voucher_prefix ?: ($shop->name ?? 'SND'),
                    sku: $sku
                );
            }

            $vault = app(\App\Services\VaultTransitService::class);
            $encryptedCode = $vault->encrypt($voucherCode);
            $blindIndex = $vault->computeBlindIndex($voucherCode);
            $sandboxId = 'SBX-E2E-' . strtoupper(Str::random(8));

            $orderId = DB::table('orders')->insertGetId([
                'order_id'    => $sandboxId,
                'uuid'        => Str::uuid()->toString(),
                'status'      => 'PROCESSING',
                'sub_status'  => 'SANDBOX_WILDFLOW',
                'shop_id'     => $shop->id,
                'is_test'     => 1,
                'progress_id' => 2,
                'info'        => json_encode([
                    'wildflow_sandbox_e2e' => true,
                    'provider' => 'ezpin-sandbox',
                    'service_sku' => $serviceSku,
                    'calculation' => [
                        'nominal_amount' => $nominalAmount,
                        'nominal_currency' => $nominalCurrency,
                        'exchange_rate' => $exchangeRate,
                        'cost_rub' => $costRub,
                        'price_rub_minor' => $priceRubMinor,
                        'margin_rub' => $marginRub,
                    ],
                ]),
                'client_info' => json_encode([
                    'firstName' => 'Sandbox',
                    'lastName'  => 'Client',
                    'email'     => 'sandbox@example.com',
                ]),
                'total_amount' => $costRub,
                'currency' => 'RUB',
                'total_amount_base' => $costRub,
                'exchange_rate' => $exchangeRate,
                'cost_amount' => $nominalAmount,
                'cost_currency' => $nominalCurrency,
                'cost_amount_base' => $costRub,
                'margin_base' => $marginRub,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $itemId = DB::table('order_items')->insertGetId([
                'uuid'            => Str::uuid()->toString(),
                'order_id'        => $orderId,
                'sku'             => $sku,
                'nominal_amount'  => $nominalAmount,
                'nominal_currency'=> $nominalCurrency,
                'count'           => 1,
                'price_rub'       => $priceRubMinor,
                'purchase_status' => 'none',
                'key'             => $encryptedCode,
                'key_bidx'        => $blindIndex,
                'is_activated'    => 0,
                'is_redeemed'     => 0,
                'type_form_id'    => null,
                'activate_till'   => now()->addYear()->format('Y-m-d'),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            \App\Models\ProductInventory::create([
                'shop_id' => $shop->id,
                'sku' => $sku,
                'nominal_amount' => $nominalAmount,
                'nominal_currency' => $nominalCurrency,
                'voucher' => $voucherCode,
                'is_used' => true,
                'status' => 'reserved',
                'order_item_id' => $itemId,
                'expires_at' => now()->addYear(),
            ]);

            $legalEntity->decrement('available_balance', $costRub);
            $legalEntity->increment('reserved_balance', $costRub);

            DB::table('order_comments')->insert([
                'order_id'   => $orderId,
                'user_id'    => null,
                'user_type'  => null,
                'comment'    => "🧪 Sandbox E2E: voucher создан, {$costRub} RUB зарезервировано, redeem пойдет через ezpin-sandbox.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            $orderItem = \App\Models\Order\OrderItems::find($itemId);

            return response()->json([
                'success' => true,
                'transaction_ref' => $orderItem?->transactionReference(),
                'source_order_id' => $sandboxId,
                'voucher_code' => $voucherCode,
                'redeem_url' => route('redeem.code', ['code' => $voucherCode]),
                'calculation' => [
                    'nominal_amount' => $nominalAmount,
                    'nominal_currency' => $nominalCurrency,
                    'exchange_rate' => $exchangeRate,
                    'cost_rub' => $costRub,
                    'price_rub_minor' => $priceRubMinor,
                    'margin_rub' => $marginRub,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createDepositIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        if (!$legalEntity) {
            return response()->json(['error' => 'Юридическое лицо не привязано.'], 400);
        }

        $token = 'DEP-' . strtoupper(Str::random(12));
        $amount = (float) $request->amount;

        // Store intent in cache for 30 minutes
        \Illuminate\Support\Facades\Cache::put("deposit_intent:{$token}", [
            'legal_entity_id' => $legalEntity->id,
            'amount' => $amount,
            'status' => 'pending',
            'created_at' => now()->toIso8601String()
        ], 1800);

        // Generate SBP mock link
        $sbpLink = "sbp://pay?merchant=MeanlySystems&amount={$amount}&intent={$token}";

        return response()->json([
            'success' => true,
            'token' => $token,
            'amount' => $amount,
            'sbp_link' => $sbpLink,
        ]);
    }

    public function clearDepositIntent(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->token;
        $intent = \Illuminate\Support\Facades\Cache::get("deposit_intent:{$token}");

        if (!$intent) {
            return response()->json(['error' => 'Интент пополнения не найден или срок его действия истек.'], 404);
        }

        $user = Auth::user();
        $legalEntity = \App\Models\LegalEntity::find($intent['legal_entity_id']);

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        try {
            DB::beginTransaction();

            $amount = $intent['amount'];

            // 1. Update balance atomically
            $legalEntity->increment('available_balance', $amount);

            // 2. Clear intent
            \Illuminate\Support\Facades\Cache::forget("deposit_intent:{$token}");

            // 3. Record in Sovereign Ledger
            app(\App\Services\LedgerService::class)->record(
                shop: null,
                eventType: 'DEPOSIT_INTENT_CLEARED',
                entity: $legalEntity,
                payload: [
                    'intent_token' => $token,
                    'amount' => $amount,
                    'currency' => 'RUB',
                    'clearing_type' => 'SBP_AUTOMATED',
                    'new_balance' => (float)$legalEntity->available_balance,
                ],
                legalEntity: $legalEntity,
                triggerSource: "SYSTEM:CLEARING_ENGINE",
                inputData: [
                    'token' => $token,
                    'amount' => $amount,
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'new_balance' => number_format($legalEntity->available_balance, 2, '.', ' ')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createInviteIntent(Request $request)
    {
        $request->validate([
            'role'  => 'required|string|in:admin,manager,viewer,support',
            'email' => 'nullable|email|max:255',
            'name'  => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        $role         = $request->role;
        $inviteeEmail = $request->input('email');
        $inviteeName  = $request->input('name');
        $token        = 'INV-' . strtoupper(Str::random(32));

        $roleLabel = match($role) {
            'admin'   => 'Администратор',
            'manager' => 'Менеджер',
            'viewer'  => 'Наблюдатель',
            'support' => 'Поддержка',
            default   => ucfirst($role),
        };

        // Use the new dedicated invite acceptance page
        $inviteLink = route('invite.accept', ['token' => $token]);

        // Store invite intent in cache for 7 days
        \Illuminate\Support\Facades\Cache::put("intent:{$token}", [
            'type'          => 'workspace_invite',
            'source_type'   => 'legal_entity',
            'partner_id'    => $legalEntity->id,
            'partner_name'  => $legalEntity->name,
            'role'          => $role,
            'invitee_email' => $inviteeEmail,
            'invitee_name'  => $inviteeName,
            'is_b2b'        => true,
            'created_at'    => now()->toIso8601String(),
        ], 604800); // 7 days

        // Send invitation email if email was provided
        $emailSent = false;
        if ($inviteeEmail) {
            try {
                \Illuminate\Support\Facades\Mail::to($inviteeEmail)->send(
                    new \App\Mail\StaffInviteEmail(
                        partnerName: $legalEntity->name,
                        roleLabel: $roleLabel,
                        inviteLink: $inviteLink,
                        inviteeName: $inviteeName,
                    )
                );
                $emailSent = true;
            } catch (\Exception $e) {
                \Log::warning('Staff invite email failed: ' . $e->getMessage(), [
                    'to' => $inviteeEmail,
                    'token' => $token,
                ]);
            }
        }

        // Record invite creation in ledger
        try {
            app(\App\Services\LedgerService::class)->record(
                shop: null,
                eventType: 'STAFF_INVITE_CREATED',
                entity: $legalEntity,
                payload: [
                    'token'         => $token,
                    'role'          => $role,
                    'invitee_email' => $inviteeEmail,
                    'email_sent'    => $emailSent,
                ],
                legalEntity: $legalEntity,
                triggerSource: "USER:{$user->id}",
                inputData: $request->only(['role', 'email', 'name'])
            );
        } catch (\Exception $e) {
            \Log::warning('Ledger record failed for invite creation: ' . $e->getMessage());
        }

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'role'       => $role,
            'role_label' => $roleLabel,
            'invite_link' => $inviteLink,
            'email_sent' => $emailSent,
        ]);
    }

    /**
     * Create a new B2B API Application integration.
     */
    public function storeApiApp(Request $request)
    {
        $user = Auth::user();
        $seller = \App\Models\Seller::findByEmail($user->email);
        $legalEntity = $seller?->legalEntity;

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'shop_id' => 'required|exists:shops,id',
            'domain' => 'nullable|string|max:255',
        ]);

        // Verify shop belongs to the seller's legal entity
        $shop = $legalEntity->shops()->find($request->shop_id);
        if (!$shop) {
            return response()->json(['error' => 'Доступ запрещен.'], 403);
        }

        $token = \App\Models\ApiApplication::generateToken();

        $app = \App\Models\ApiApplication::create([
            'shop_id' => $shop->id,
            'type' => \App\Models\ApiApplication::TYPE_SHOP,
            'name' => $request->name,
            'domain' => $request->domain,
            'token' => $token,
            'is_active' => true,
        ]);

        // Record integration event in ledger
        app(\App\Services\LedgerService::class)->record($shop, 'API_APPLICATION_CREATED', $app, [
            'name' => $app->name,
            'domain' => $app->domain,
        ]);

        return response()->json([
            'success' => true,
            'app' => [
                'id' => $app->id,
                'name' => $app->name,
                'domain' => $app->domain,
                'token' => $token,
                'shop_name' => $shop->name,
                'is_active' => true,
                'created_at' => $app->created_at->format('d.m.Y H:i'),
            ]
        ]);
    }

    /**
     * Toggle the status of a B2B API Application.
     */
    public function toggleApiApp(Request $request, $id)
    {
        $user = Auth::user();
        $seller = \App\Models\Seller::findByEmail($user->email);
        $legalEntity = $seller?->legalEntity;

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        $app = \App\Models\ApiApplication::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->find($id);

        if (!$app) {
            return response()->json(['error' => 'Интеграция не найдена.'], 404);
        }

        $app->is_active = !$app->is_active;
        $app->save();

        // Record in ledger
        app(\App\Services\LedgerService::class)->record($app->shop, 'API_APPLICATION_TOGGLED', $app, [
            'is_active' => $app->is_active,
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $app->is_active,
        ]);
    }

    /**
     * Delete a B2B API Application.
     */
    public function deleteApiApp(Request $request, $id)
    {
        $user = Auth::user();
        $seller = \App\Models\Seller::findByEmail($user->email);
        $legalEntity = $seller?->legalEntity;

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        $app = \App\Models\ApiApplication::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->find($id);

        if (!$app) {
            return response()->json(['error' => 'Интеграция не найдена.'], 404);
        }

        $shop = $app->shop;
        
        // Record in ledger before deleting
        app(\App\Services\LedgerService::class)->record($shop, 'API_APPLICATION_DELETED', $app, [
            'name' => $app->name,
        ]);

        $app->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Create a new B2B Partner Shop / Sales Channel.
     */
    public function createShop(Request $request)
    {
        $user = Auth::user();
        $legalEntity = $user ? $user->legalEntities()->first() : null;

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'shop_region' => 'required|string|max:10',
        ]);

        $shop = \App\Models\Shop::create([
            'name' => $request->name,
            'domain' => $request->domain,
            'shop_region' => $request->shop_region,
            'legal_entity_id' => $legalEntity->id,
            'allowed_regions' => [$request->shop_region],
            'allowed_categories' => ['Vouchers', 'Games'],
            'is_active' => true,
            'is_sandbox' => false,
            'voucher_prefix' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $request->name), 0, 3)),
            'notification_token' => Str::random(24),
        ]);

        // Record in ledger
        app(\App\Services\LedgerService::class)->record($shop, 'SHOP_CREATED', $shop, [
            'name' => $shop->name,
            'domain' => $shop->domain,
            'shop_region' => $shop->shop_region,
        ]);

        return response()->json([
            'success' => true,
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
            ]
        ]);
    }

    /**
     * Update Yandex Market credentials for a specific tenant shop.
     */
    public function updateYandexMarket(Request $request, $id)
    {
        $user = Auth::user();
        $legalEntity = $user ? $user->legalEntities()->first() : null;

        if (!$legalEntity) {
            return response()->json(['error' => 'Организация не найдена.'], 404);
        }

        $shop = $legalEntity->shops()->find($id);
        if (!$shop) {
            return response()->json(['error' => 'Магазин не найден.'], 404);
        }

        $request->validate([
            'business_id' => 'nullable|integer',
            'campaign_id' => 'nullable|integer',
            'api_key' => 'nullable|string',
        ]);

        $shop->business_id = $request->business_id;
        $shop->campaign_id = $request->campaign_id;

        if (blank($shop->notification_token)) {
            $shop->notification_token = Str::random(24);
        }

        if ($request->has('api_key')) {
            $shop->api_key = $request->api_key;
        }

        $shop->save();

        // Record integration audit event in ledger
        app(\App\Services\LedgerService::class)->record($shop, 'YANDEX_MARKET_CONFIGURED', $shop, [
            'business_id' => $shop->business_id,
            'campaign_id' => $shop->campaign_id,
            'is_active' => filled($shop->campaign_id) && filled($shop->api_key),
        ]);

        return response()->json([
            'success' => true,
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'business_id' => $shop->business_id,
                'campaign_id' => $shop->campaign_id,
                'notification_url' => url('/api/ym/'.$shop->notification_token.'/notification'),
                'is_configured' => filled($shop->campaign_id) && filled($shop->api_key),
            ]
        ]);
    }

    /**
     * Get B2B Notifications for the authenticated user and their linked seller profile.
     */
    public function getNotifications()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $seller = \App\Models\Seller::findByEmail($user->email);

        $notifications = collect();
        if ($user) {
            $notifications = $notifications->merge($user->notifications);
        }
        if ($seller) {
            $notifications = $notifications->merge($seller->notifications);
        }

        \Illuminate\Support\Carbon::setLocale('ru');

        $formatted = $notifications->sortByDesc('created_at')->values()->map(function ($n) {
            $data = is_string($n->data) ? json_decode($n->data, true) : $n->data;
            
            $status = $data['status'] ?? 'info';
            $icon = 'ph-bold ph-info';
            if ($status === 'success') $icon = 'ph-bold ph-check-circle';
            elseif ($status === 'warning') $icon = 'ph-bold ph-warning-circle';
            elseif ($status === 'danger' || $status === 'error') $icon = 'ph-bold ph-x-circle';

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? 'Уведомление',
                'body' => $data['body'] ?? '',
                'status' => $status,
                'icon' => $icon,
                'iconColor' => $data['iconColor'] ?? 'info',
                'read' => !is_null($n->read_at),
                'time' => $n->created_at->diffForHumans(),
                'date' => $n->created_at->format('d.m.Y H:i'),
            ];
        });

        $unreadCount = $notifications->whereNull('read_at')->count();

        return response()->json([
            'notifications' => $formatted,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark all notifications for user and seller as read.
     */
    public function readAllNotifications()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $seller = \App\Models\Seller::findByEmail($user->email);

        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
        if ($seller) {
            $seller->unreadNotifications->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark a single notification as read.
     */
    public function readNotification($id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $seller = \App\Models\Seller::findByEmail($user->email);

        $notification = null;
        if ($user) {
            $notification = $user->notifications()->find($id);
        }
        if (!$notification && $seller) {
            $notification = $seller->notifications()->find($id);
        }

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Notification not found'], 404);
    }

    // 🛒 B2B Provider Showcase Controller Methods
    public function getStorefrontProducts(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = \App\Models\LegalEntity::where('user_id', $user->id)->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $shops = $legalEntity->shops;
        $query = \App\Models\ProviderProduct::with(['brand', 'region'])->where('is_active', true);

        // 1. Scoped Filter by Allowed Regions across all shops
        $allRegions = $shops->flatMap->allowed_regions->unique()->filter()->toArray();
        if (!empty($allRegions)) {
            $query->whereIn('region_id', function ($q) use ($allRegions) {
                $q->select('id')
                    ->from('mapping_countries')
                    ->whereIn('code', $allRegions);
            });
        }

        // 2. Scoped Filter by Allowed Categories across all shops
        $allCategories = $shops->flatMap->allowed_categories->unique()->filter()->toArray();
        if (!empty($allCategories)) {
            $query->where(function ($q) use ($allCategories) {
                foreach ($allCategories as $category) {
                    $q->orWhere('category', 'like', "%{$category}%");
                }
            });
        }

        // 3. User Filter: Brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // 4. User Filter: Region (Mapping Country)
        if ($request->filled('region_id')) {
            $query->where('region_id', $request->region_id);
        }

        // 5. User Filter: Search Query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('market_sku', 'like', "%{$search}%")
                  ->orWhereHas('brand', function($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Clone query for filter options
        $brandIds = (clone $query)->distinct()->pluck('brand_id')->filter()->toArray();
        $brands = \App\Models\Brand::whereIn('id', $brandIds)->orderBy('name')->get(['id', 'name']);

        $regionIds = (clone $query)->distinct()->pluck('region_id')->filter()->toArray();
        $regions = \App\Models\MappingCountry::whereIn('id', $regionIds)->orderBy('name_ru')->get(['id', 'name_ru', 'flag']);

        // Paginate
        $paginator = $query->orderBy('name')->paginate(12);

        // Map items
        $vault = app(\App\Services\VaultTransitService::class);
        $items = collect($paginator->items())->map(function($record) use ($shops, $vault) {
            $shopHasProduct = false;
            $skuBidx = $vault->computeBlindIndex($record->sku);
            $marketSkuBidx = !empty($record->market_sku) ? $vault->computeBlindIndex($record->market_sku) : null;

            $hasProduct = \App\Models\Product::whereIn('shop_id', $shops->pluck('id'))
                ->where(function ($q) use ($record, $skuBidx, $marketSkuBidx) {
                    $q->where('products.sku', $record->sku)
                      ->orWhere('products.wildflow_catalog_sku_bidx', $skuBidx);
                    
                    if ($marketSkuBidx) {
                        $q->orWhere('products.sku', $record->market_sku)
                          ->orWhere('products.wildflow_catalog_sku_bidx', $marketSkuBidx);
                    }
                })
                ->exists();

            $purchasePriceFormatted = '';
            $nominalPriceFormatted = '';

            $wf = \App\Models\WildflowCatalog::where('sku', $record->market_sku ?? $record->sku)->first();

            if (!$wf) {
                $purchasePriceFormatted = number_format((float) $record->purchase_price, 2).' '.$record->currency;
                $nominalPriceFormatted = number_format((float) $record->retail_price, 2).' '.$record->currency;
            } else {
                if ($wf->is_variable_price) {
                    $min = number_format($wf->min_purchase_price, 2);
                    $max = number_format($wf->max_purchase_price, 2);
                    $purchasePriceFormatted = $min.'–'.$max.' '.$record->currency;

                    $minNominal = number_format($record->min_price, 2);
                    $maxNominal = number_format($record->max_price, 2);
                    $nominalPriceFormatted = $minNominal.'–'.$maxNominal.' '.$record->currency;
                } else {
                    $firstShop = $shops->first();
                    $purchasePriceFormatted = number_format($firstShop ? $wf->getPurchasePriceForShop($firstShop) : $wf->purchase_price, 2).' '.$record->currency;
                    $nominalPriceFormatted = number_format((float) $record->retail_price, 2).' '.$record->currency;
                }
            }

            return [
                'id' => $record->id,
                'name' => $record->name,
                'sku' => $record->sku,
                'market_sku' => $record->market_sku,
                'brand_name' => $record->brand?->name ?? '—',
                'brand_logo' => $record->brand?->logo ? asset($record->brand->logo) : ($record->brand?->logo_png ? asset($record->brand->logo_png) : null),
                'region_name' => $record->region?->name_ru ?? '—',
                'region_flag' => $record->region?->flag ?? '',
                'purchase_price_formatted' => $purchasePriceFormatted,
                'nominal_price_formatted' => $nominalPriceFormatted,
                'has_product' => $hasProduct,
                'min_price' => $record->min_price,
                'max_price' => $record->max_price,
                'currency' => $record->currency,
                'is_variable' => $wf ? (bool) $wf->is_variable_price : false,
                'redemption_instructions' => $record->redemption_instructions ? preg_replace([
                    '/[a-zA-Z0-9._%+-]+@(wildflow|ezpaypin)\.[a-z]{2,}/i',
                    '/https?:\/\/(portal|api|www)\.(wildflow|ezpaypin)\.[a-z]{2,}[^\s]*/i'
                ], ['[support]', '[link]'], $record->redemption_instructions) : '',
                'activation_url' => $record->activation_url,
                'reward_type' => $record->reward_type,
            ];
        });

        return response()->json([
            'products' => $items,
            'brands' => $brands,
            'regions' => $regions,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'balance' => $legalEntity->available_balance,
        ]);
    }

    public function addStorefrontToCatalog(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = \App\Models\LegalEntity::where('user_id', $user->id)->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $request->validate([
            'provider_product_id' => 'required|exists:provider_products,id',
            'shop_id' => 'required|exists:shops,id',
            'sales_channels' => 'required|array',
            'count' => 'required|integer|min:0',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $record = \App\Models\ProviderProduct::find($request->provider_product_id);
        $shop = $legalEntity->shops()->find($request->shop_id);

        if (!$shop) return response()->json(['error' => 'Shop not found or not owned by legal entity'], 403);

        $selectedChannels = \App\Support\SalesChannels::normalizeSelection($request->sales_channels);
        $count = (int) $request->count;
        $amount = $request->amount ? (float) $request->amount : null;

        try {
            $job = new \App\Jobs\AddCatalogItemToShop(
                $record->id,
                $shop->id,
                $user->id,
                $selectedChannels,
                $count,
                $amount
            );
            dispatch($job);

            return response()->json([
                'success' => true,
                'message' => 'Задача успешно запущена. Генерация карточки товара и отправка на каналы продаж выполняются в фоновом режиме.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function buyStorefrontOptions(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $json = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction::class)->execute($user);
        $optionsArray = json_decode($json, true);
        
        // Ensure RP ID stability for local dev
        $optionsArray['rpId'] = $request->getHost();
        
        $signingOptions = json_encode($optionsArray);
        session(['storefront_signing_options' => $signingOptions]);

        return response()->json($optionsArray);
    }

    public function buyStorefrontOnce(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = \App\Models\LegalEntity::where('user_id', $user->id)->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $request->validate([
            'provider_product_id' => 'required|exists:provider_products,id',
            'shop_id' => 'required|exists:shops,id',
            'quantity' => 'required|integer|min:1|max:20',
            'amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:rub,native_token',
            'assertion' => 'nullable|array',
        ]);

        $paymentMethod = $request->input('payment_method', 'rub');
        $passkey = null;

        if ($paymentMethod === 'native_token') {
            if (!$request->filled('assertion')) {
                return response()->json(['error' => 'Для оплаты нативными токенами требуется подпись Passkey.'], 422);
            }

            $signingOptions = session('storefront_signing_options');
            if (!$signingOptions) {
                return response()->json(['error' => 'Контекст подписи утерян. Пожалуйста, обновите страницу.'], 422);
            }

            try {
                $assertion = json_encode($request->input('assertion'));
                $passkey = app(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class)->execute(
                    $assertion,
                    $signingOptions
                );

                if (!$passkey || $passkey->authenticatable_id !== $user->id) {
                    return response()->json(['error' => 'Недействительная или неавторизованная подпись транзакции.'], 422);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => 'Криптографическая проверка подписи не удалась: ' . $e->getMessage()], 422);
            }
        }

        $record = \App\Models\ProviderProduct::find($request->provider_product_id);
        $shop = $legalEntity->shops()->find($request->shop_id);

        if (!$shop) return response()->json(['error' => 'Shop not found or not owned by legal entity'], 403);

        $wf = \App\Models\WildflowCatalog::where('sku', $record->market_sku ?? $record->sku)->first();
        if (!$wf) return response()->json(['error' => 'Товар не найден в каталоге Wildflow.'], 404);

        $isVariable = (bool) $wf->is_variable_price;
        $nominalAmount = $isVariable ? (float) $request->amount : (float) $wf->retail_price;

        if ($isVariable) {
            $min = (float) $record->min_price;
            $max = (float) $record->max_price;
            if ($nominalAmount < $min || $nominalAmount > $max) {
                return response()->json(['error' => "Сумма номинала должна быть от {$min} до {$max} {$record->currency}."], 422);
            }
        }

        $percentageAdjustment = (float) (data_get($wf->data, 'data.percentage_of_buying_price', data_get($wf->data, 'percentage_of_buying_price', -2)));
        $buyingPrice = $isVariable
            ? (float) ($nominalAmount * (1 + ($percentageAdjustment / 100)))
            : (float) $wf->purchase_price;

        $currency = $wf->currency_code;
        $financeService = app(\App\Services\FinanceService::class);
        $rate = $financeService->getRate($currency);

        $buyingPriceRub = $buyingPrice * $rate;
        $nominalPriceRub = $nominalAmount * $rate;

        $standardizer = app(\App\Services\StandardizationService::class);
        $tariffPriceRub = $standardizer->getPurchasePriceForShop($buyingPriceRub, $nominalPriceRub, $shop);

        $quantity = (int) $request->quantity;
        $totalCostRub = $tariffPriceRub * $quantity;

        // Converted amounts for native tokens (rate: 1 SL1 = 100 RUB)
        $gasFeeSl1 = 0.0015;
        $costSl1 = $totalCostRub / 100.0;
        $totalCostSl1 = $costSl1 + $gasFeeSl1;

        if ($paymentMethod === 'native_token') {
            $l1State = app(\App\Services\L1StateService::class);
            $balances = $l1State->reconstructBalance($legalEntity);
            $availableSl1 = $balances['native_available_balance'] ?? 0.0;
            if ($availableSl1 < $totalCostSl1) {
                return response()->json([
                    'error' => "Недостаточно средств в нативных токенах. Требуется " . number_format($totalCostSl1, 4) . " SL1, доступно " . number_format($availableSl1, 4) . " SL1."
                ], 422);
            }
        }

        // Check if this catalog item is managed by our Premium Sovereign Warehouse / Local Provider
        $providerType = $wf->provider?->type ?? 'wildflow';

        if ($providerType === 'sovereign' || $providerType === 'local') {
            try {
                $vault = app(\App\Services\VaultTransitService::class);
                $serviceSku = $vault->decrypt($wf->service_sku);

                $l1Clearing = app(\App\Services\L1ClearingService::class);
                $orderReference = 'SL1-' . strtoupper(\Illuminate\Support\Str::random(10));

                // 1. Dispatch hold block to L1 Ledger
                $l1Clearing->dispatchOrderRequest(
                    $legalEntity,
                    $serviceSku,
                    $quantity,
                    $totalCostRub,
                    $orderReference,
                    $paymentMethod,
                    $passkey ? $passkey->credential_id : null,
                    $paymentMethod === 'native_token' ? $gasFeeSl1 : 0.0,
                    $paymentMethod === 'native_token' ? $costSl1 : 0.0
                );

                // 2. Process validator queue instantly (Step-by-step cryptographic settlement)
                $l1Clearing->processClearingQueue();

                // 3. Verify success in the ledger stream
                $replenishBlock = \App\Models\SovereignLedger::where('event_type', 'STOCK_REPLENISH')
                    ->where('payload->reference_code', $orderReference)
                    ->first();

                if (!$replenishBlock) {
                    $failBlock = \App\Models\SovereignLedger::where('event_type', 'FINANCE_RELEASE_HOLD')
                        ->where('payload->reference_code', $orderReference)
                        ->first();
                    $reason = $failBlock ? data_get($failBlock->payload, 'reason', 'Clearing failed') : 'Clearing failed';

                    if ($reason === 'OUT_OF_STOCK') {
                        return response()->json(['error' => 'Товара временно нет в наличии у суверенного поставщика.'], 422);
                    }
                    return response()->json(['error' => 'Ошибка Simple Layer 1 клиринга: ' . $reason], 422);
                }

                // 4. Create Order & Items locally for tracking and return codes to UI
                \Illuminate\Support\Facades\DB::beginTransaction();

                // Deduct legal entity balance (sync database with L1 Ledger state)
                if ($paymentMethod === 'native_token') {
                    $legalEntity->deductNativeBalance($totalCostSl1);
                } else {
                    $legalEntity->deductRubBalance($totalCostRub);
                }

                $order = \App\Models\Order\Order::create([
                    'order_id'     => $orderReference,
                    'uuid'         => \Illuminate\Support\Str::uuid()->toString(),
                    'status'       => 'COMPLETED',
                    'sub_status'   => 'DIRECT_PURCHASE',
                    'shop_id'      => $shop->id,
                    'progress_id'  => 4, // COMPLETED
                    'sales_channel'=> 'manual',
                    'comment'      => $paymentMethod === 'native_token'
                        ? 'Прямая суверенная B2B закупка через Simple Layer 1 Ledger с нативным токеном SL1.'
                        : 'Прямая суверенная B2B закупка через Simple Layer 1 Ledger.',
                ]);

                app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_CAPTURE', $order, [
                    'amount_rub'  => $totalCostRub,
                    'reference'   => $orderReference,
                    'description' => $paymentMethod === 'native_token'
                        ? 'Simple Layer 1 Ledger списание за суверенную закупку товара ×' . $quantity . ' в SL1'
                        : 'Simple Layer 1 Ledger списание за суверенную закупку товара ×' . $quantity,
                    'payment_method' => $paymentMethod,
                    'assertion_id' => $passkey ? $passkey->credential_id : null,
                    'gas_fee' => $paymentMethod === 'native_token' ? $gasFeeSl1 : 0.0,
                    'sl1_amount' => $paymentMethod === 'native_token' ? $costSl1 : 0.0,
                ]);

                $replenishedVouchers = data_get($replenishBlock->payload, 'vouchers', []);
                $voucherKeys = [];
                $masterWarehouse = \App\Models\Warehouse::where('shop_id', $shop->id)->where('is_main', true)->first()
                    ?? \App\Models\Warehouse::where('shop_id', $shop->id)->first();

                for ($i = 0; $i < $quantity; $i++) {
                    $voucherToken = \App\Helpers\GenerateSecureCode::generate($shop->voucher_prefix);
                    $ledgerVoucher = $replenishedVouchers[$i] ?? null;
                    $decryptedVoucherCode = $ledgerVoucher ? $vault->decrypt($ledgerVoucher['code']) : 'MOCK-SL1-' . strtoupper(\Illuminate\Support\Str::random(8));

                    $item = \App\Models\Order\OrderItems::create([
                        'key' => $voucherToken,
                        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                        'order_id' => $order->id,
                        'activate_till' => now()->addYear()->format('Y-m-d'),
                        'sku' => $wf->sku,
                        'nominal_amount' => $nominalAmount,
                        'nominal_currency' => $currency,
                        'count' => 1,
                        'price_rub' => $tariffPriceRub * 100,
                        'price_try' => 0,
                        'type_form_id' => 2,
                        'purchase_status' => 'success',
                        'original_code' => $decryptedVoucherCode,
                    ]);

                    if ($masterWarehouse) {
                        \App\Models\ProductInventory::create([
                            'shop_id' => $shop->id,
                            'warehouse_id' => $masterWarehouse->id,
                            'sku' => $wf->sku,
                            'nominal_amount' => $nominalAmount,
                            'nominal_currency' => $currency,
                            'voucher' => $voucherToken,
                            'is_used' => true,
                            'order_item_id' => $item->id,
                            'status' => 'sold',
                        ]);
                    }

                    $voucherKeys[] = [
                        'token' => $voucherToken,
                        'url'   => route('redeem.code', ['code' => $voucherToken]),
                        'code'  => $decryptedVoucherCode
                    ];
                }

                \Illuminate\Support\Facades\DB::commit();

                return response()->json([
                    'success' => true,
                    'total_cost' => $paymentMethod === 'native_token' ? $totalCostSl1 : $totalCostRub,
                    'currency' => $paymentMethod === 'native_token' ? 'SL1' : 'RUB',
                    'vouchers' => $voucherKeys,
                    'message' => $paymentMethod === 'native_token'
                        ? "Покупка успешно подтверждена в блоке Simple Layer 1 Ledger! Списано: " . number_format($totalCostSl1, 4) . " SL1 (включая 0.0015 SL1 комиссию сети)."
                        : "Покупка успешно подтверждена в блоке Simple Layer 1 Ledger! Списано: " . number_format($totalCostRub, 2) . " RUB."
                ]);

            } catch (\Exception $e) {
                if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                    \Illuminate\Support\Facades\DB::rollBack();
                }
                return response()->json(['error' => 'Ошибка Simple Layer 1 клиринга: ' . $e->getMessage()], 500);
            }
        }

        try {
            $vault = app(\App\Services\VaultTransitService::class);
            $serviceSku = $vault->decrypt($wf->service_sku);
            
            $wfService = new \App\Services\WildflowService();
            $availability = $wfService->checkAvailability(
                service_sku: (string)$serviceSku,
                quantity: $quantity,
                price: $isVariable ? (float)$nominalAmount : null
            );

            if (!$availability['available']) {
                try {
                    app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_STOCK_DEFICIT', $wf, [
                        'sku' => $wf->sku,
                        'nominal_amount' => $nominalAmount,
                        'requested_quantity' => $quantity,
                        'trigger' => 'buy_once_ajax_check'
                    ]);
                } catch (\Exception $e) {}

                return response()->json(['error' => 'Товара временно нет в наличии у поставщика.'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Не удалось связаться с поставщиком для проверки наличия товара: ' . $e->getMessage()], 500);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $legalEntity->refresh();
            if ($paymentMethod === 'native_token') {
                if ($legalEntity->native_token_balance < $totalCostSl1) {
                    throw new \Exception("Недостаточно средств в нативных токенах. Требуется " . number_format($totalCostSl1, 4) . " SL1, доступно " . number_format($legalEntity->native_token_balance, 4) . " SL1.");
                }
                $legalEntity->deductNativeBalance($totalCostSl1);
            } else {
                if ($legalEntity->available_balance < $totalCostRub) {
                    throw new \Exception("Недостаточно средств. Требуется " . number_format($totalCostRub, 2) . " RUB, доступно " . number_format($legalEntity->available_balance, 2) . " RUB.");
                }
                $legalEntity->deductRubBalance($totalCostRub);
            }

            $orderReference = 'DP-' . strtoupper(\Illuminate\Support\Str::random(10));
            $order = \App\Models\Order\Order::create([
                'order_id'     => $orderReference,
                'uuid'         => \Illuminate\Support\Str::uuid()->toString(),
                'status'       => 'PROCESSING',
                'sub_status'   => 'DIRECT_PURCHASE',
                'shop_id'      => $shop->id,
                'progress_id'  => 2,
                'sales_channel'=> 'manual',
                'comment'      => $paymentMethod === 'native_token'
                    ? 'Прямая разовая закупка через B2B Showcase с нативным токеном SL1.'
                    : 'Прямая разовая закупка через B2B Showcase.',
            ]);

            app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_CAPTURE', $order, [
                'amount_rub'  => $totalCostRub,
                'reference'   => $orderReference,
                'description' => $paymentMethod === 'native_token'
                    ? 'Списание за разовую закупку товара ×' . $quantity . ' в SL1'
                    : 'Списание за разовую закупку товара ×' . $quantity,
                'payment_method' => $paymentMethod,
                'assertion_id' => $passkey ? $passkey->credential_id : null,
                'gas_fee' => $paymentMethod === 'native_token' ? $gasFeeSl1 : 0.0,
                'sl1_amount' => $paymentMethod === 'native_token' ? $costSl1 : 0.0,
            ]);

            $voucherKeys = [];
            $masterWarehouse = \App\Models\Warehouse::where('shop_id', $shop->id)->where('is_main', true)->first()
                ?? \App\Models\Warehouse::where('shop_id', $shop->id)->first();

            for ($i = 0; $i < $quantity; $i++) {
                $voucherToken = \App\Helpers\GenerateSecureCode::generate($shop->voucher_prefix);
                
                $item = \App\Models\Order\OrderItems::create([
                    'key' => $voucherToken,
                    'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'activate_till' => now()->addYear()->format('Y-m-d'),
                    'sku' => $wf->sku,
                    'nominal_amount' => $nominalAmount,
                    'nominal_currency' => $currency,
                    'count' => 1,
                    'price_rub' => $tariffPriceRub * 100,
                    'price_try' => 0,
                    'type_form_id' => 2,
                    'purchase_status' => 'pending',
                ]);

                if ($masterWarehouse) {
                    \App\Models\ProductInventory::create([
                        'shop_id' => $shop->id,
                        'warehouse_id' => $masterWarehouse->id,
                        'sku' => $wf->sku,
                        'nominal_amount' => $nominalAmount,
                        'nominal_currency' => $currency,
                        'voucher' => $voucherToken,
                        'is_used' => true,
                        'order_item_id' => $item->id,
                        'status' => 'sold',
                    ]);
                }

                $voucherKeys[] = [
                    'token' => $voucherToken,
                    'url'   => route('redeem.code', ['code' => $voucherToken])
                ];
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'total_cost' => $paymentMethod === 'native_token' ? $totalCostSl1 : $totalCostRub,
                'currency' => $paymentMethod === 'native_token' ? 'SL1' : 'RUB',
                'vouchers' => $voucherKeys,
                'message' => $paymentMethod === 'native_token'
                    ? "Покупка успешно подтверждена! Списано: " . number_format($totalCostSl1, 4) . " SL1 (включая 0.0015 SL1 комиссию сети)."
                    : "Покупка успешно подтверждена! Списано: " . number_format($totalCostRub, 2) . " RUB."
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 📦 B2B Orders SPA Management Methods
    public function getOrdersData(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $query = \App\Models\Order\Order::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with(['items', 'shop']);

        // Filter by search (Order ID or SKU)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhereHas('items', fn($qi) => $qi->where('sku', 'like', "%{$search}%"));
            });
        }

        // Filter by status tab
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

        $mappedOrders = collect($paginator->items())->map(function ($order) {
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
                'transaction_ref' => $order->transactionReference(),
                'source_order_id' => $order->order_id,
                'shop_name' => $order->shop->name ?? 'System',
                'sku' => $item->sku ?? '—',
                'price_rub' => ($item->price_rub ?? 0) / 100,
                'progress_id' => $order->progress_id,
                'is_test' => (bool)$order->is_test,
                'key' => $code,
                'created_at' => $order->created_at->format('d.m.Y H:i')
            ];
        });

        return response()->json([
            'success' => true,
            'orders' => $mappedOrders,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total()
        ]);
    }

    public function syncOrders(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $shops = $legalEntity->shops;
        if ($shops->isEmpty()) {
            return response()->json(['error' => 'Нет доступных магазинов'], 400);
        }

        $newOrdersCount = 0;

        foreach ($shops as $shop) {
            if (!$shop->is_active) {
                continue;
            }

            if (empty($shop->ym_client_id) || empty($shop->ym_token)) {
                continue;
            }

            $ymService = new \App\Http\Services\YmService($shop);

            try {
                $orderList = $ymService->getOrders([
                    'include_sandbox' => true,
                    'from_date' => date('d-m-Y', strtotime('-30 days')),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("syncOrders: getOrders failed for shop {$shop->name}", [$e->getMessage()]);
                continue;
            }

            if (empty($orderList)) {
                continue;
            }

            foreach ($orderList as $ymOrderShort) {
                $ym_order_id = data_get($ymOrderShort, 'id');
                $status = data_get($ymOrderShort, 'status', 'PROCESSING');
                $substatus = data_get($ymOrderShort, 'substatus');

                $existingOrder = \App\Models\Order\Order::where('order_id', $ym_order_id)
                    ->where('shop_id', $shop->id)
                    ->first();

                if ($existingOrder) {
                    if ($existingOrder->status !== $status) {
                        $existingOrder->update([
                            'status' => $status,
                            'sub_status' => $substatus,
                        ]);
                    }
                    continue;
                }

                try {
                    $order_full_info = $ymService->getOrder($ym_order_id);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("syncOrders: getOrder failed for #{$ym_order_id}", [$e->getMessage()]);
                    continue;
                }

                $items = data_get($order_full_info, 'items', []);
                $buyer = data_get($order_full_info, 'buyer', []);
                $client_info = [
                    'id' => data_get($buyer, 'id'),
                    'lastName' => data_get($buyer, 'lastName'),
                    'firstName' => data_get($buyer, 'firstName'),
                    'middleName' => data_get($buyer, 'middleName'),
                    'phone' => data_get($buyer, 'phone'),
                    'email' => data_get($buyer, 'email'),
                ];

                try {
                    \Illuminate\Support\Facades\DB::beginTransaction();

                    $newOrder = \App\Models\Order\Order::create([
                        'order_id' => $ym_order_id,
                        'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                        'status' => $status,
                        'sub_status' => $substatus,
                        'info' => $order_full_info,
                        'client_info' => $client_info,
                        'shop_id' => $shop->id,
                        'is_test' => data_get($order_full_info, 'fake', false),
                        'progress_id' => 1,
                    ]);

                    app(\App\Services\LedgerService::class)->record($shop, 'ORDER_RECEIVE', $newOrder, [
                        'external_id' => $ym_order_id,
                        'channel' => 'yandex_sync',
                        'is_test' => data_get($order_full_info, 'fake', false),
                    ]);

                    $insertItems = [];
                    foreach ($items as $item) {
                        $sku = data_get($item, 'offerId');
                        if (!$sku) {
                            continue;
                        }

                        $type_form_id = \App\Models\Product::queryByOfferSku($sku)->value('type_form_id');

                        $insertItems[] = [
                            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                            'order_id' => $newOrder->id,
                            'sku' => $sku,
                            'count' => data_get($item, 'count', 1),
                            'price_rub' => (int) (data_get($item, 'price', 0) * 100),
                            'price_try' => (int) (data_get($item, 'buyerPrice', 0) * 100),
                            'type_form_id' => $type_form_id,
                            'activate_till' => now()->addYear()->format('Y-m-d'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (!empty($insertItems)) {
                        \App\Models\Order\OrderItems::insert($insertItems);
                    }

                    $isFake = data_get($order_full_info, 'fake', false);
                    $newOrder->comments()->create([
                        'user_id' => null,
                        'comment' => '🔄 Заказ добавлен вручную через синхронизацию с Яндекс.Маркетом' . ($isFake ? ' (ТЕСТ)' : ''),
                    ]);

                    if ($isFake) {
                        $newOrder->comments()->create([
                            'user_id' => null,
                            'comment' => '⚠️ Внимание! Это тестовый заказ Яндекс.Маркета (Sandbox). Реальная закупка товара производиться не будет.',
                        ]);
                    }

                    \Illuminate\Support\Facades\DB::commit();
                    $newOrdersCount++;

                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    \Illuminate\Support\Facades\Log::error('syncOrders: failed to create order', [
                        'order_id' => $ym_order_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $newOrdersCount > 0 ? "Синхронизировано новых заказов: {$newOrdersCount}" : "Новых заказов не найдено"
        ]);
    }

    public function getOrderDetails($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $order = \App\Models\Order\Order::where('id', $id)
            ->whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with(['items', 'comments', 'shop'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $items = $order->items->map(function ($item) {
            $code = $item->key ?: '—';
            if (str_starts_with((string)$code, 'vault:')) {
                try {
                    $code = app(\App\Services\VaultTransitService::class)->decrypt($code);
                } catch (\Exception $e) {
                    $code = '🔒 Ошибка дешифрования';
                }
            }

            $activationUrl = '#';
            if ($item->sku) {
                $activationUrl = '/redeem?code=' . urlencode($code);
            }

            return [
                'transaction_ref' => $item->transactionReference(),
                'sku' => $item->sku,
                'count' => $item->count,
                'price_rub' => $item->price_rub / 100,
                'key' => $code,
                'url' => $activationUrl,
                'activate_till' => $item->activate_till
            ];
        });

        $comments = $order->comments->map(function ($comment) {
            return [
                'text' => $comment->comment,
                'created_at' => $comment->created_at->format('d.m.Y H:i')
            ];
        });

        $clientInfo = $order->client_info;
        if (is_string($clientInfo)) {
            $clientInfo = json_decode($clientInfo, true);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'transaction_ref' => $order->transactionReference(),
                'source_order_id' => $order->order_id,
                'shop_name' => $order->shop->name ?? 'System',
                'status' => $order->status,
                'progress_id' => $order->progress_id,
                'created_at' => $order->created_at->format('d.m.Y H:i'),
                'buyer' => [
                    'name' => trim((data_get($clientInfo, 'firstName', '') . ' ' . data_get($clientInfo, 'lastName', ''))),
                    'email' => data_get($clientInfo, 'email') ?: '—',
                    'phone' => data_get($clientInfo, 'phone') ?: '—'
                ],
                'items' => $items,
                'comments' => $comments
            ]
        ]);
    }

    public function getCatalogData(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $status = $request->input('status', 'all');
        $search = $request->input('search', '');

        $query = \App\Models\Product::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->with(['shop'])
            ->latest();

        // 1. Apply status filter
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'errors') {
            $query->where(fn($q) => $q->whereNotNull('ym_errors')
                ->where('ym_errors', '!=', '')
                ->where('ym_errors', '!=', '[]')
                ->where('ym_errors', '!=', '{}')
            );
        }

        // 2. Apply search filter
        if (!empty($search)) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('vendor', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
            );
        }

        $paginated = $query->paginate(15);

        $products = collect($paginated->items())->map(function($p) {
            $errors = [];
            if ($p->ym_errors) {
                $decoded = is_array($p->ym_errors) ? $p->ym_errors : json_decode($p->ym_errors, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $err) {
                        $errors[] = data_get($err, 'message') ?: data_get($err, 'error') ?: json_encode($err);
                    }
                }
            }

            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'vendor' => $p->vendor ?: '—',
                'category' => $p->category ?: 'Другое',
                'price_rub' => round(($p->price_rub ?? 0) / 100, 2),
                'is_active' => (bool)$p->is_active,
                'shop_name' => $p->shop->name ?? '—',
                'errors' => $errors,
                'created_at' => $p->created_at ? $p->created_at->format('d.m.Y H:i') : '—'
            ];
        });

        return response()->json([
            'success' => true,
            'products' => $products,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage()
        ]);
    }

    public function toggleProductStatus($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $product = \App\Models\Product::where('id', $id)
            ->whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Товар не найден или не принадлежит вашей компании'], 404);
        }

        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'success' => true,
            'is_active' => $product->is_active,
            'message' => $product->is_active ? 'Товар успешно активирован!' : 'Товар перенесен в архив!'
        ]);
    }

    public function getShopsData(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $status = $request->input('status', 'all');
        $search = $request->input('search', '');

        $query = \App\Models\Shop::where('legal_entity_id', $legalEntity->id)
            ->latest();

        // 1. Apply status filter
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'sandbox') {
            $query->where('is_sandbox', true);
        }

        // 2. Apply search filter
        if (!empty($search)) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('domain', 'like', "%{$search}%")
            );
        }

        $paginated = $query->paginate(12);

        $shops = collect($paginated->items())->map(function($shop) {
            return [
                'id' => $shop->id,
                'name' => $shop->name,
                'domain' => $shop->domain ?: '—',
                'is_active' => (bool)$shop->is_active,
                'is_sandbox' => (bool)$shop->is_sandbox,
                'import_status' => $shop->import_status ?: 'idle',
                'import_progress' => (int)($shop->import_progress ?: 0),
                'product_count' => $shop->products()->count()
            ];
        });

        return response()->json([
            'success' => true,
            'shops' => $shops,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage()
        ]);
    }

    public function toggleShopActive($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $shop = \App\Models\Shop::where('id', $id)
            ->where('legal_entity_id', $legalEntity->id)
            ->first();

        if (!$shop) {
            return response()->json(['error' => 'Магазин не найден'], 404);
        }

        $shop->is_active = !$shop->is_active;
        $shop->save();

        return response()->json([
            'success' => true,
            'is_active' => (bool)$shop->is_active,
            'message' => $shop->is_active ? 'Магазин активирован!' : 'Магазин приостановлен!'
        ]);
    }

    public function toggleShopSandbox($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'No legal entity configured'], 400);
        }

        $shop = \App\Models\Shop::where('id', $id)
            ->where('legal_entity_id', $legalEntity->id)
            ->first();

        if (!$shop) {
            return response()->json(['error' => 'Магазин не найден'], 404);
        }

        $shop->is_sandbox = !$shop->is_sandbox;
        $shop->save();

        return response()->json([
            'success' => true,
            'is_sandbox' => (bool)$shop->is_sandbox,
            'message' => $shop->is_sandbox ? 'Режим песочницы включен!' : 'Магазин переведен в боевой режим!'
        ]);
    }

    public function getTicketsData(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $status = $request->input('status', 'all');
        $search = $request->input('search', '');

        $query = \App\Models\Ticket::where('seller_id', Auth::id())
            ->with(['shop'])
            ->latest();

        if ($status === 'open') {
            $query->where('status', 'open');
        } elseif ($status === 'closed') {
            $query->where('status', 'closed');
        }

        if (!empty($search)) {
            $query->where('subject', 'like', "%{$search}%");
        }

        $paginated = $query->paginate(10);
        $mapped = collect($paginated->items())->map(function($t) {
            return [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status ?: 'open',
                'priority' => $t->priority ?: 'normal',
                'shop_name' => $t->shop ? $t->shop->name : 'Общие вопросы',
                'updated_at' => $t->updated_at ? $t->updated_at->format('d.m.Y H:i') : '—',
                'last_reply_at' => $t->last_reply_at ? $t->last_reply_at->format('d.m.Y H:i') : '—'
            ];
        });

        return response()->json([
            'success' => true,
            'tickets' => $mapped,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage()
        ]);
    }

    public function createTicket(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'subject' => 'required|string|max:255',
            'priority' => 'required|string|in:low,medium,high',
            'message' => 'required|string',
            'shop_id' => 'required|integer'
        ]);

        $shopId = $request->input('shop_id');
        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'Профиль юридического лица не найден'], 400);
        }

        $shop = \App\Models\Shop::where('id', $shopId)
            ->where('legal_entity_id', $legalEntity->id)
            ->first();
        if (!$shop) {
            return response()->json(['error' => 'Некорректный или чужой магазин'], 400);
        }

        $ticket = \App\Models\Ticket::create([
            'seller_id' => Auth::id(),
            'shop_id' => $shopId,
            'subject' => $request->input('subject'),
            'priority' => $request->input('priority'),
            'status' => 'open',
            'last_reply_at' => now()
        ]);

        \App\Models\TicketMessage::create([
            'ticket_id' => $ticket->id,
            'seller_id' => Auth::id(),
            'message' => $request->input('message'),
            'is_admin_reply' => false
        ]);

        return response()->json([
            'success' => true,
            'ticket_id' => $ticket->id,
            'message' => 'Обращение успешно создано!'
        ]);
    }

    public function runAiAudit(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'Профиль юридического лица не найден'], 400);
        }

        $shop = $legalEntity->shops()->first();
        if (!$shop) {
            return response()->json(['error' => 'Магазин не найден для анализа'], 400);
        }

        try {
            $analyst = app(\App\Services\Ai\PartnerAnalystService::class);
            $result = $analyst->analyze($shop);

            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Сбой при запуске ИИ-анализа: ' . $e->getMessage()], 500);
        }
    }

    public function sendAiChatMessage(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'message' => 'required|string',
            'history' => 'nullable|array'
        ]);

        $message = $request->input('message');
        
        try {
            $analyst = app(\App\Services\Ai\PartnerAnalystService::class);
            $aiContent = $analyst->chat($user, $message);

            return response()->json([
                'success' => true,
                'content' => $aiContent,
                'time' => now()->format('H:i')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'content' => "Я временно не могу связаться с Ollama. Проверьте запуск Llama 3 (Детали ошибки: " . $e->getMessage() . ")",
                'time' => now()->format('H:i')
            ]);
        }
    }

    public function getTicketDetails($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $ticket = \App\Models\Ticket::where('id', $id)
            ->where('seller_id', Auth::id())
            ->first();

        if (!$ticket) {
            return response()->json(['error' => 'Обращение не найдено'], 404);
        }

        $messages = $ticket->messages()->get()->map(function($m) {
            return [
                'id' => $m->id,
                'message' => $m->message ?: '',
                'is_admin' => (bool)$m->is_admin_reply,
                'sender' => $m->is_admin_reply ? 'Поддержка Meanly' : ($m->seller ? $m->seller->name : 'Менеджер'),
                'created_at' => $m->created_at ? $m->created_at->format('d.m.Y H:i') : '—'
            ];
        });

        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status ?: 'open',
                'priority' => $ticket->priority ?: 'normal',
                'shop_name' => $ticket->shop ? $ticket->shop->name : 'Общие вопросы',
                'updated_at' => $ticket->updated_at ? $ticket->updated_at->format('d.m.Y H:i') : '—'
            ],
            'messages' => $messages
        ]);
    }

    public function replyToTicket(\Illuminate\Http\Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $ticket = \App\Models\Ticket::where('id', $id)
            ->where('seller_id', Auth::id())
            ->first();

        if (!$ticket) {
            return response()->json(['error' => 'Обращение не найдено'], 404);
        }

        if ($ticket->status === 'closed') {
            return response()->json(['error' => 'Обращение уже закрыто'], 400);
        }

        $request->validate([
            'message' => 'required|string'
        ]);

        $msg = \App\Models\TicketMessage::create([
            'ticket_id' => $ticket->id,
            'seller_id' => Auth::id(),
            'message' => $request->input('message'),
            'is_admin_reply' => false
        ]);

        $ticket->last_reply_at = now();
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $msg->id,
                'message' => $msg->message,
                'is_admin' => false,
                'sender' => $user->name ?: 'Менеджер',
                'created_at' => $msg->created_at ? $msg->created_at->format('d.m.Y H:i') : '—'
            ]
        ]);
    }

    /**
     * getWarehousesData
     */
    public function getWarehousesData(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['success' => true, 'warehouses' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = \App\Models\Warehouse::where('is_main', true)
            ->whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id));

        $search = $request->input('search');
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $warehouses = $query->with('shop')
            ->latest()
            ->paginate(10);

        $mapped = collect($warehouses->items())->map(function ($w) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'shop_name' => $w->shop->name ?? '—',
                'is_active' => (bool)$w->is_active,
                'created_at' => $w->created_at ? $w->created_at->format('d.m.Y H:i') : '—',
            ];
        });

        return response()->json([
            'success' => true,
            'warehouses' => $mapped,
            'total' => $warehouses->total(),
            'current_page' => $warehouses->currentPage(),
            'last_page' => $warehouses->lastPage()
        ]);
    }

    /**
     * createWarehouse
     */
    public function createWarehouse(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'shop_id' => 'required|integer',
        ]);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'Профиль юридического лица не найден'], 400);
        }

        $shop = \App\Models\Shop::where('id', $request->input('shop_id'))
            ->where('legal_entity_id', $legalEntity->id)
            ->first();

        if (!$shop) {
            return response()->json(['error' => 'Некорректный или чужой магазин'], 400);
        }

        $warehouse = \App\Models\Warehouse::create([
            'shop_id' => $shop->id,
            'name' => $request->input('name'),
            'is_main' => true,
            'is_active' => true,
            'channel' => null,
            'ym_id' => null,
            'type' => 'master',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Мастер-склад "' . $warehouse->name . '" успешно создан!',
            'warehouse_id' => $warehouse->id,
        ]);
    }

    /**
     * toggleWarehouseActive
     */
    public function toggleWarehouseActive(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'Профиль юридического лица не найден'], 400);
        }

        $warehouse = \App\Models\Warehouse::where('id', $id)
            ->whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id))
            ->first();

        if (!$warehouse) {
            return response()->json(['error' => 'Склад не найден'], 404);
        }

        $warehouse->is_active = !$warehouse->is_active;
        $warehouse->save();

        return response()->json([
            'success' => true,
            'is_active' => (bool)$warehouse->is_active,
            'message' => 'Статус склада изменен на ' . ($warehouse->is_active ? 'Активен' : 'Неактивен'),
        ]);
    }

    /**
     * getActivationsData
     */
    public function getActivationsData(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['success' => true, 'activations' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = \App\Models\Procurement::whereHas('shop', fn($q) => $q->where('legal_entity_id', $legalEntity->id));

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $search = $request->input('search');
        if ($search) {
            $query->whereHas('product', fn($q) => $q->where('name', 'like', '%' . $search . '%'));
        }

        $activations = $query->with(['product', 'warehouse', 'shop'])
            ->latest()
            ->paginate(10);

        $mapped = collect($activations->items())->map(function ($p) {
            return [
                'id' => $p->id,
                'date' => $p->completed_at ? $p->completed_at->format('d.m.Y H:i') : ($p->created_at ? $p->created_at->format('d.m.Y H:i') : '—'),
                'product_name' => $p->product->name ?? '—',
                'sku' => $p->product->sku ?? '—',
                'warehouse_name' => $p->warehouse->name ?? '—',
                'count' => $p->count,
                'total_price_rub' => round($p->total_price / 100, 2),
                'status' => $p->status,
            ];
        });

        return response()->json([
            'success' => true,
            'activations' => $mapped,
            'total' => $activations->total(),
            'current_page' => $activations->currentPage(),
            'last_page' => $activations->lastPage(),
            'available_balance_rub' => (float)$legalEntity->available_balance
        ]);
    }

    /**
     * createActivation
     */
    public function createActivation(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $request->validate([
            'shop_id' => 'required|integer',
            'product_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'count' => 'required|integer|min:1',
        ]);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'Профиль юридического лица не найден'], 400);
        }

        $shop = \App\Models\Shop::where('id', $request->input('shop_id'))
            ->where('legal_entity_id', $legalEntity->id)
            ->first();
        if (!$shop) {
            return response()->json(['error' => 'Некорректный или чужой магазин'], 400);
        }

        $product = \App\Models\Product::where('id', $request->input('product_id'))
            ->where('shop_id', $shop->id)
            ->first();
        if (!$product) {
            return response()->json(['error' => 'Некорректный или чужой товар'], 400);
        }

        $warehouse = \App\Models\Warehouse::where('id', $request->input('warehouse_id'))
            ->where('shop_id', $shop->id)
            ->first();
        if (!$warehouse) {
            return response()->json(['error' => 'Некорректный или чужой склад назначения'], 400);
        }

        $count = (int)$request->input('count');
        $pricePerItem = $product->purchase_price_rub ?? 0;
        $totalCostRub = ($count * $pricePerItem) / 100;

        if ($totalCostRub > (float)$legalEntity->available_balance) {
            return response()->json([
                'error' => 'Недостаточно средств на балансе. Требуется: ' . number_format($totalCostRub, 2, '.', ' ') . ' ₽, доступно: ' . number_format((float)$legalEntity->available_balance, 2, '.', ' ') . ' ₽'
            ], 400);
        }

        $procurement = \App\Models\Procurement::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'count' => $count,
            'price_per_item' => $pricePerItem,
            'total_price' => $count * $pricePerItem,
            'status' => 'pending',
            'completed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Запрос на активацию товара "' . $product->name . '" (кол-во: ' . $count . ' шт.) успешно создан!',
            'procurement_id' => $procurement->id,
        ]);
    }

    /**
     * getShopOptions
     */
    public function getShopOptions($shopId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) {
            return response()->json(['error' => 'Профиль юридического лица не найден'], 400);
        }

        $shop = \App\Models\Shop::where('id', $shopId)
            ->where('legal_entity_id', $legalEntity->id)
            ->first();

        if (!$shop) {
            return response()->json(['error' => 'Магазин не найден'], 404);
        }

        $products = \App\Models\Product::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'purchase_price_rub', 'sku'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'purchase_price_rub' => $p->purchase_price_rub,
                    'sku' => $p->sku,
                ];
            });

        $warehouses = \App\Models\Warehouse::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    // 🎫 B2B Voucher Code Registry SPA Controller Methods
    public function getVouchersData(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $shops = $legalEntity->shops;
        $query = \App\Models\ProductInventory::whereIn('shop_id', $shops->pluck('id'));

        // 1. Filter: Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 2. Filter: Search Query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('voucher', 'like', "%{$search}%")
                  ->orWhereHas('orderItem.order', function ($oq) use ($search) {
                      $oq->where('order_id', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = $query->with(['orderItem.order'])->latest('id')->paginate(10);

        // Map items to follow neomorphic UI structure
        $items = collect($paginator->items())->map(function ($record) {
            $skuBidx = $record->sku_bidx ?? '';
            $art = 'ID-' . strtoupper(substr(md5($skuBidx ?: $record->sku), 0, 8));

            // Eager-load verification status
            $latestLedger = $record->ledgerEntries()->latest('id')->first();
            $fingerprint = $latestLedger ? $latestLedger->fingerprint : null;

            return [
                'id' => $record->id,
                'transaction_ref' => $record->transactionReference(),
                'created_at' => $record->created_at ? $record->created_at->toISOString() : null,
                'created_at_formatted' => $record->created_at ? $record->created_at->format('d.m.Y H:i') : '—',
                'sku' => $record->sku,
                'art' => $art,
                'code' => $record->voucher,
                'status' => $record->status,
                'order_transaction_ref' => $record->orderItem?->order?->transactionReference(),
                'source_order_id' => $record->orderItem?->order?->order_id,
                'order_url' => $record->orderItem?->order ? \App\Filament\Partner\Resources\OrderResource::getUrl('edit', ['record' => $record->orderItem->order->id]) : null,
                'fingerprint' => $fingerprint,
                'has_proof' => true
            ];
        });

        return response()->json([
            'success' => true,
            'vouchers' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function getVoucherDetails($id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $voucher = \App\Models\ProductInventory::whereIn('shop_id', $legalEntity->shops->pluck('id'))
            ->where('id', $id)
            ->with(['orderItem.order'])
            ->first();

        if (!$voucher) {
            return response()->json(['error' => 'Ваучер не найден'], 404);
        }

        $latestLedger = $voucher->ledgerEntries()->latest('id')->first();

        return response()->json([
            'success' => true,
            'voucher' => [
                'id' => $voucher->id,
                'transaction_ref' => $voucher->transactionReference(),
                'sku' => $voucher->sku,
                'sku_bidx' => $voucher->sku_bidx,
                'art' => 'ID-' . strtoupper(substr(md5($voucher->sku_bidx ?: $voucher->sku), 0, 8)),
                'code' => $voucher->voucher,
                'status' => $voucher->status,
                'created_at_formatted' => $voucher->created_at ? $voucher->created_at->format('d.m.Y H:i:s') : '—',
                'order_transaction_ref' => $voucher->orderItem?->order?->transactionReference(),
                'source_order_id' => $voucher->orderItem?->order?->order_id,
                'order_url' => $voucher->orderItem?->order ? \App\Filament\Partner\Resources\OrderResource::getUrl('edit', ['record' => $voucher->orderItem->order->id]) : null,
                'fingerprint' => $latestLedger ? $latestLedger->fingerprint : null,
                'ledger_signature' => $latestLedger ? $latestLedger->signature : null,
                'ledger_created' => $latestLedger && $latestLedger->created_at ? $latestLedger->created_at->format('d.m.Y H:i:s') : null,
            ]
        ]);
    }

    // 💰 B2B Finance & Billing SPA Controller Methods
    public function getFinanceData(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        // Filter: Only load ruble-affecting financial operations
        $query = \App\Models\SovereignLedger::where('legal_entity_id', $legalEntity->id)
            ->where(function ($q) {
                $q->whereIn('event_type', [
                    'FINANCE_DEPOSIT',
                    'FINANCE_HOLD',
                    'FINANCE_CAPTURE',
                    'FINANCE_RELEASE',
                    'VOUCHER_MANUAL_ADJUSTMENT'
                ])->orWhere('event_type', 'like', 'FINANCE_%');
            });

        // 1. Filter: Status (All, Credits, Debits)
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'credit') {
                $query->where(function($q) {
                    $q->whereRaw("CAST(json_unquote(json_extract(payload, '$.amount_rub')) AS DECIMAL(15,2)) > 0")
                      ->orWhereRaw("CAST(json_unquote(json_extract(payload, '$.amount')) AS DECIMAL(15,2)) > 0");
                });
            } elseif ($request->status === 'debit') {
                $query->where(function($q) {
                    $q->whereRaw("CAST(json_unquote(json_extract(payload, '$.amount_rub')) AS DECIMAL(15,2)) < 0")
                      ->orWhereRaw("CAST(json_unquote(json_extract(payload, '$.amount')) AS DECIMAL(15,2)) < 0");
                });
            }
        }

        // 2. Filter: Search Query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                  ->orWhere('trigger_source', 'like', "%{$search}%")
                  ->orWhereRaw("json_unquote(json_extract(payload, '$.description')) like ?", ["%{$search}%"]);
            });
        }

        $paginator = $query->latest('id')->paginate(10);

        $transactions = collect($paginator->items())->map(function ($record) {
            $payload = $record->payload ?? [];
            $amount = (float) ($payload['amount_rub'] ?? $payload['amount'] ?? 0);
            $description = $payload['description'] ?? str_replace('_', ' ', $record->event_type);

            return [
                'transaction_ref' => $record->transactionReference(),
                'event_type' => $record->event_type,
                'event_type_formatted' => str_replace('_', ' ', $record->event_type),
                'amount' => $amount,
                'amount_formatted' => ($amount >= 0 ? '+' : '') . number_format($amount, 2, '.', ' ') . ' ₽',
                'description' => $description,
                'trigger_source' => $record->trigger_source,
                'fingerprint' => $record->fingerprint,
                'created_at_formatted' => $record->created_at ? $record->created_at->format('d.m.Y H:i') : '—',
            ];
        });

        $sovereignRequests = \App\Models\SovereignBalanceRequest::where('legal_entity_id', $legalEntity->id)
            ->latest()
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'type' => $r->type,
                    'type_formatted' => $r->type === 'top_up' ? 'Пополнение баланса' : 'Кредитная линия',
                    'amount' => (float)$r->amount,
                    'amount_formatted' => number_format($r->amount, 2, '.', ' ') . ' ₽',
                    'currency' => $r->currency,
                    'status' => $r->status,
                    'status_formatted' => match($r->status) {
                        'pending' => 'Ожидает подписи админа',
                        'approved' => 'Успешно исполнен ✅',
                        'rejected' => 'Отклонен ❌',
                        default => $r->status,
                    },
                    'l1_address' => $r->l1_address,
                    'signature_assertion' => $r->signature_assertion,
                    'comment' => $r->comment,
                    'created_at_formatted' => $r->created_at ? $r->created_at->format('d.m.Y H:i') : '—',
                ];
            });

        return response()->json([
            'success' => true,
            'balances' => [
                'available' => (float) ($legalEntity->available_balance ?? 0.00),
                'available_formatted' => number_format($legalEntity->available_balance ?? 0.00, 2, '.', ' ') . ' ₽',
                'reserved' => (float) ($legalEntity->reserved_balance ?? 0.00),
                'reserved_formatted' => number_format($legalEntity->reserved_balance ?? 0.00, 2, '.', ' ') . ' ₽',
                'total' => (float) ($legalEntity->balance ?? 0.00),
                'total_formatted' => number_format($legalEntity->balance ?? 0.00, 2, '.', ' ') . ' ₽',
            ],
            'transactions' => $transactions,
            'sovereign_requests' => $sovereignRequests,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function traceSimpleLayer1(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $validated = $request->validate([
            'reference' => 'required|string|max:64',
        ]);

        $trace = app(\App\Services\SimpleLayer1TraceService::class)
            ->trace($validated['reference'], $legalEntity->id);

        if (! $trace) {
            return response()->json([
                'success' => false,
                'message' => 'Simple Layer 1 transaction reference not found for this legal entity.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'trace' => $trace,
        ]);
    }

    public function simulateDeposit(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) return response()->json(['error' => 'Legal Entity not found'], 404);

        $request->validate([
            'amount' => 'required|numeric|min:100|max:1000000',
        ]);

        $amount = (float) $request->amount;

        DB::transaction(function () use ($legalEntity, $amount) {
            // 1. Perform atomic balance updates
            $legalEntity->increment('available_balance', $amount);
            $legalEntity->increment('balance', $amount);

            // 2. Commit transaction into deterministic Sovereign Ledger
            app(\App\Services\LedgerService::class)->record(
                null,
                'FINANCE_DEPOSIT',
                $legalEntity,
                [
                    'amount' => $amount,
                    'amount_rub' => $amount,
                    'description' => "Симуляционное пополнение баланса мерчанта на " . number_format($amount, 2, '.', ' ') . " ₽",
                    'meta' => [
                        'method' => 'simulation_gateway_v1',
                        'currency' => 'RUB'
                    ]
                ],
                $legalEntity
            );
        });

        // Refetch updated balances
        $legalEntity->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Баланс успешно пополнен!',
            'balances' => [
                'available' => (float) ($legalEntity->available_balance ?? 0.00),
                'available_formatted' => number_format($legalEntity->available_balance ?? 0.00, 2, '.', ' ') . ' ₽',
                'reserved' => (float) ($legalEntity->reserved_balance ?? 0.00),
                'reserved_formatted' => number_format($legalEntity->reserved_balance ?? 0.00, 2, '.', ' ') . ' ₽',
                'total' => (float) ($legalEntity->balance ?? 0.00),
                'total_formatted' => number_format($legalEntity->balance ?? 0.00, 2, '.', ' ') . ' ₽',
            ],
        ]);
    }

    public function sovereignBalanceRequestOptions(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $json = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction::class)->execute($user);
        $optionsArray = json_decode($json, true);
        
        $optionsArray['rpId'] = $request->getHost();
        
        $signingOptions = json_encode($optionsArray);
        session(['sovereign_request_signing_options' => $signingOptions]);

        return response()->json($optionsArray);
    }

    public function createSovereignBalanceRequest(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $legalEntity = $user->legalEntities()->first();
        if (!$legalEntity) return response()->json(['error' => 'Организация не найдена'], 404);

        $request->validate([
            'type' => 'required|in:top_up,grant_credit',
            'amount' => 'required|numeric|min:1',
            'comment' => 'nullable|string|max:1000',
            'assertion' => 'required|array',
        ]);

        $signingOptions = session('sovereign_request_signing_options');
        if (!$signingOptions) {
            return response()->json(['error' => 'Контекст подписи утерян. Пожалуйста, обновите страницу.'], 422);
        }

        try {
            $assertion = json_encode($request->input('assertion'));
            $passkey = app(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class)->execute(
                $assertion,
                $signingOptions
            );

            if (!$passkey || $passkey->authenticatable_id !== $user->id) {
                return response()->json(['error' => 'Недействительная или неавторизованная подпись транзакции.'], 422);
            }
            
            try {
                $l1Address = app(\App\Services\L1IdentityService::class)->addressFromPasskey($passkey);
            } catch (\InvalidArgumentException $error) {
                return response()->json(['error' => 'Публичный ключ Passkey не найден.'], 422);
            }

            // 🛡️ Strict L1 Identity check & self-healing bind
            $registeredAddress = $legalEntity->agreement_metadata['l1_address'] ?? null;
            if ($registeredAddress && $l1Address !== $registeredAddress) {
                return response()->json(['error' => "Криптографическая ошибка: подпись сгенерирована ключом L1 ({$l1Address}), который не совпадает с вашим зарегистрированным L1 адресом ({$registeredAddress})."], 422);
            }

            if (empty($registeredAddress)) {
                $meta = $legalEntity->agreement_metadata ?? [];
                $meta['l1_address'] = $l1Address;
                $meta['passkey_id'] = $passkey->id;
                $meta['signer_role'] = $meta['signer_role'] ?? 'ceo';
                $meta['signer_name'] = $meta['signer_name'] ?? $user->getFullName();
                $meta['signed_at'] = $meta['signed_at'] ?? now()->toIso8601String();
                $meta['signature_type'] = $meta['signature_type'] ?? 'passkey_assertion_v1';
                $legalEntity->update(['agreement_metadata' => $meta]);
            }

            $sbRequest = \App\Models\SovereignBalanceRequest::create([
                'legal_entity_id' => $legalEntity->id,
                'type' => $request->type,
                'amount' => (float) $request->amount,
                'currency' => 'RUB',
                'status' => 'pending',
                'l1_address' => $l1Address,
                'passkey_id' => $passkey->id,
                'signature_assertion' => $request->input('assertion'),
                'comment' => $request->comment,
            ]);

            app(\App\Services\LedgerService::class)->record(
                shop: null,
                eventType: 'SOVEREIGN_REQUEST_CREATED',
                entity: $sbRequest,
                payload: [
                    'request_id' => $sbRequest->id,
                    'type' => $sbRequest->type,
                    'amount' => $sbRequest->amount,
                    'currency' => $sbRequest->currency,
                    'l1_address' => $l1Address,
                    'passkey_id' => $passkey->id,
                    'comment' => $sbRequest->comment,
                ],
                legalEntity: $legalEntity,
                triggerSource: "DID:PASSKEY:{$l1Address}",
                inputData: $request->only(['type', 'amount', 'comment'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Суверенный запрос успешно отправлен и подписан L1 ключом!',
                'request' => $sbRequest
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Криптографическая проверка подписи не удалась: ' . $e->getMessage()], 422);
        }
    }
}
