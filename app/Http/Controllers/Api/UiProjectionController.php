<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LlmServiceFactsService;
use App\Services\ProviderNetworkCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UiProjectionController extends Controller
{
    public function show(Request $request, string $surface, ?string $path = null): JsonResponse
    {
        $surface = Str::slug($surface);
        $path = trim((string) $path, '/');

        $projection = match ($surface) {
            'business' => $this->businessProjection($path),
            'services' => $this->servicesProjection($path),
            'partner' => $this->partnerProjection($path),
            'redeem' => $this->redeemProjection($path),
            'ops' => $this->opsProjection($path),
            'reader' => $this->readerProjection(),
            'terminal' => $this->terminalProjection(),
            'catalog-network' => $this->networkProjection($path),
            'meanly-ai' => $this->aiProjection(),
            'products-search' => $this->productsSearchProjection($request),
            'store' => $this->storeProjection($path),
            'catalog' => $this->catalogProjection($path),
            'errors' => $this->errorProjection($path),
            default => $this->genericProjection($surface, $path),
        };

        return response()->json([
            'contract' => [
                'name' => 'ui-projection',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'surface' => $surface,
            'path' => $path,
            'projection' => $projection + [
                'actions' => $this->actions(['VIEW']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessProjection(string $path): array
    {
        $services = app(LlmServiceFactsService::class)->services()->take(6)->values();

        return [
            'type' => 'business_projection',
            'eyebrow' => 'Merchant Center',
            'title' => $path === 'register' ? 'Register a Meanly merchant account' : 'Meanly Merchant Center',
            'lead' => 'Start seller onboarding, connect company data, and manage merchant operations from one protected workspace.',
            'sections' => [
                $this->section('Next actions', 'Start by connecting your Meanly identity, then add company details when you are ready to sell.', [
                    $this->card('Connect Meanly identity', 'Use Meanly One to confirm who is starting the merchant profile. If you already have a request, we will show its current status instead.', '/business/register'),
                    $this->card('Add company details', 'Enter business email, INN, signer details, and accept the offer. After that we review the company and open seller tools when approved.', '/business/register'),
                ]),
                $this->section('Merchant operating layer', 'Meanly connects the legal entity, catalog, channels, checkout, fulfillment, and support into one merchant workspace.', [
                    $this->card('Identity and legal entity', 'Merchant Center starts with Meanly identity, verified email, INN lookup, offer signing, and moderation state. The same identity then controls seller permissions.', '/business/register'),
                    $this->card('Catalog and stock source', 'Products stay canonical inside Meanly: face value, region, provider stock, local inventory, and channel mappings are projected outward instead of duplicated per marketplace.', '/services/marketplace-channel-adapters'),
                    $this->card('Checkout to fulfillment', 'Orders connect payment status, reservation, provider redemption, voucher delivery, and support traceability so the buyer sees one clear fulfillment flow.', '/services/digital-voucher-fulfillment'),
                    $this->card('Operations and finance', 'Seller workspace keeps orders, balance, channel readiness, alerts, and support context together, while protected actions remain gated by authority checks.', '/services/simple-layer-one-clearing'),
                ]),
                $this->section('Merchant services', 'Operational services available from Meanly Merchant Center.', $services->map(
                    fn (array $service): array => $this->card((string) $service['name'], (string) ($service['description'] ?? $service['outcome'] ?? ''), '/services/'.$service['slug'])
                )->all()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function servicesProjection(string $path): array
    {
        $facts = app(LlmServiceFactsService::class);
        $service = $path !== '' ? $facts->find($path) : null;
        $services = $facts->services()->values();

        return [
            'type' => 'services_projection',
            'eyebrow' => 'Services projection',
            'title' => $service['name'] ?? 'Meanly Merchant Center services',
            'lead' => $service['description'] ?? 'Service facts are emitted by Laravel and rendered by the Next projection surface.',
            'sections' => [
                ...($service ? $this->selectedServiceSections($service) : $this->serviceOverviewSections()),
                $this->section($service ? 'Related services' : 'Available services', 'Machine-readable service facts from Laravel.', $services->map(
                    fn (array $item): array => $this->card((string) $item['name'], (string) ($item['outcome'] ?? $item['description'] ?? ''), '/services/'.$item['slug'])
                )->all()),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $service
     * @return array<int, array<string, mixed>>
     */
    private function selectedServiceSections(array $service): array
    {
        return [
            $this->section('How the merchant setup works', 'Merchant Center wires operational data to the seller workspace and buyer-facing checkout.', [
                $this->card('1. Merchant context', 'We bind the service to a verified legal entity, seller profile, commercial terms, and the identity allowed to operate it.', '/business/register'),
                $this->card('2. Data and channel mapping', 'Catalog, stock, fulfillment rules, category mappings, and credentials are normalized before they are exposed to storefronts or external channels.', null),
                $this->card('3. Protected operations', 'Checkout, order updates, voucher issue, support cases, balance movement, and moderation decisions stay traceable instead of becoming disconnected scripts.', null),
            ]),
            $this->section('What you receive', 'The service result is an operational capability, not a static integration document.', [
                $this->card('Deliverable', (string) ($service['deliverable'] ?? 'Configured Meanly Merchant Center capability ready for seller operations.'), null),
                $this->card('Merchant outcome', (string) ($service['outcome'] ?? 'The team can run the selected commerce flow through Meanly.'), null),
                $this->card('Execution mode', (string) ($service['execution_mode'] ?? 'Managed onboarding with API and workspace support.'), null),
                $this->card('SLA / timing', trim(((string) ($service['delivery_time'] ?? 'Timing depends on setup')).'. '.((string) ($service['sla'] ?? 'Review and activation happen through Merchant Center.'))), null),
            ]),
            $this->section('Merchant Center scope', 'Typical merchant connection points covered by this service.', [
                $this->card('Seller workspace', 'Legal entity state, permissions, alerts, orders, catalog, finance, and support are visible from the same React workspace.', '/partner'),
                $this->card('Buyer storefront', 'The buyer sees product availability, checkout, payment status, and fulfillment status without seeing the internal provider workflow.', '/catalog'),
                $this->card('Operational APIs', 'Meanly can receive catalog, stock, order, fulfillment, and support events through controlled endpoints and channel adapters.', '/services/marketplace-channel-adapters'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serviceOverviewSections(): array
    {
        return [
            $this->section('Merchant Center model', 'Services are grouped around the same operating path: onboard a verified company, connect catalog and channels, then run checkout and fulfillment with traceability.', [
                $this->card('Onboard the company', 'Verify identity, business email, legal entity, signer authority, and moderation status before seller tools open.', '/business/register'),
                $this->card('Connect commerce data', 'Map catalog, stock, provider supply, pricing, and channel-specific listing requirements into Meanly projections.', '/services/marketplace-channel-adapters'),
                $this->card('Operate orders safely', 'Keep payment, fulfillment, support, and balance events tied to the same order history for both buyer and seller teams.', '/services/simple-layer-one-clearing'),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerProjection(string $path): array
    {
        return [
            'type' => 'partner_projection',
            'eyebrow' => 'Partner projection',
            'title' => $path === '' ? 'Partner workspace' : Str::headline(str_replace('/', ' ', $path)),
            'lead' => 'Partner dashboard state is projected from Laravel. Causal writes remain protected by backend auth, intent, and partner guards.',
            'sections' => [
                $this->section('Workspace modules', 'Legacy partner dashboard modules now have a projection target in Next.', [
                    $this->card('Onboarding', 'Continue with Meanly and submit legal entity details.', '/partner/register'),
                    $this->card('Orders', 'Partner order views are backed by Laravel dashboard data endpoints.', '/partner/dashboard/orders'),
                    $this->card('Catalog', 'Catalog management remains a backend-authorized transition surface.', '/partner/dashboard/catalog'),
                    $this->card('Tickets', 'Support conversations are rendered from partner dashboard DTOs.', '/partner/dashboard/tickets'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function redeemProjection(string $path): array
    {
        $step = $path === '' ? 'code' : $path;

        return [
            'type' => 'redeem_projection',
            'eyebrow' => 'Redeem projection',
            'title' => 'Redeem code activation',
            'lead' => 'The activation flow is a React projection over redeem verification and activation APIs.',
            'sections' => [
                $this->section('Flow state', 'Each step submits intent to Laravel; React does not decide activation eligibility.', [
                    $this->card('1. Code', 'Verify the voucher or order code with Laravel.', '/redeem/code'),
                    $this->card('2. Email', 'Request and verify delivery email challenge.', '/redeem/email'),
                    $this->card('3. Activation', 'Submit activation data to the backend authority.', '/redeem/activation'),
                    $this->card('Current step', Str::headline($step), '/redeem/'.$step),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opsProjection(string $path): array
    {
        return [
            'type' => 'ops_projection',
            'eyebrow' => 'Ops projection',
            'title' => $path === '' ? 'Meanly operations' : Str::headline($path),
            'lead' => 'Operational panels are read models over Laravel data and command endpoints. Backend auth remains mandatory for causal actions.',
            'sections' => [
                $this->section('Ops modules', 'Projection targets for the former Blade operations dashboard.', [
                    $this->card('Dashboard', 'Operational KPIs, providers, channels, and growth read models.', '/ops'),
                    $this->card('Decision console', 'Continuity and decision traces from backend services.', '/ops/decision-console'),
                    $this->card('Provider sync', 'Provider catalog sync status and transitions.', '/ops/dashboard/providers'),
                    $this->card('Search integrations', 'Search demand and zero-layer ingestion status.', '/ops/dashboard/search-integrations'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function networkProjection(string $path): array
    {
        $network = app(ProviderNetworkCatalogService::class);
        $categories = $network->categorySummaries()->take(12)->values();

        return [
            'type' => 'provider_network_projection',
            'eyebrow' => 'Provider network',
            'title' => $path === '' ? 'Provider network catalog' : Str::headline($path),
            'lead' => 'Provider candidates are rendered as supply projections. Seller eligibility and checkout are still backend transitions.',
            'sections' => [
                $this->section('Network categories', 'Candidate counts by canonical category.', $categories->map(
                    fn (array $category): array => $this->card((string) ($category['label_en'] ?? $category['slug']), ((int) $category['candidate_count']).' candidates', '/catalog-network/'.$category['slug'])
                )->all()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readerProjection(): array
    {
        return [
            'type' => 'reader_projection',
            'eyebrow' => 'Trust reader',
            'title' => 'Constitutional artifact reader',
            'lead' => 'Reader UI is a projection shell for verifying artifacts against trusted roots.',
            'sections' => [
                $this->section('Trust profiles', 'Verification logic belongs to backend and trusted artifacts; this screen renders the chosen profile.', [
                    $this->card('Simple L1 Primary', 'Verify against Simple L1 primary genesis roots.', '/reader'),
                    $this->card('Commerce federation', 'Verify commerce-zone artifacts and receipts.', '/reader'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function terminalProjection(): array
    {
        return [
            'type' => 'terminal_projection',
            'eyebrow' => 'Terminal projection',
            'title' => 'Meanly terminal',
            'lead' => 'Terminal tools render runtime and trust-state projections without becoming the authority for them.',
            'sections' => [
                $this->section('Runtime panels', 'Projection targets for the former terminal Blade page.', [
                    $this->card('Trust profile', 'View active trust roots and epoch context.', '/terminal'),
                    $this->card('Commerce state', 'Inspect projected commerce runtime state.', '/terminal'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aiProjection(): array
    {
        return [
            'type' => 'ai_projection',
            'eyebrow' => 'Meanly AI',
            'title' => 'Ask the catalog',
            'lead' => 'The AI surface is a projection over catalog retrieval and understanding APIs.',
            'sections' => [
                $this->section('Available actions', 'React renders the assistant shell; Laravel handles retrieval and answer generation.', [
                    $this->card('Catalog retrieval', 'Use backend catalog retrieval for grounded answers.', '/catalog'),
                    $this->card('Intent understanding', 'Intent parsing remains a backend projection.', '/meanly-ai'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productsSearchProjection(Request $request): array
    {
        return [
            'type' => 'products_search_projection',
            'eyebrow' => 'Search projection',
            'title' => 'Product search',
            'lead' => 'Search requests are projected through catalog DTOs, preserving the old `/products-search` entrypoint.',
            'sections' => [
                $this->section('Search handoff', 'Use the catalog projection for results and filters.', [
                    $this->card('Open catalog search', 'Query: '.((string) $request->query('q', '')), '/catalog'.($request->getQueryString() ? '?'.$request->getQueryString() : '')),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeProjection(string $path): array
    {
        return [
            'type' => 'store_projection',
            'eyebrow' => 'Store compatibility',
            'title' => $path === '' ? 'Meanly storefront' : Str::headline($path),
            'lead' => 'The former `/store` surface is now a Next projection over Storefront API DTOs.',
            'sections' => [
                $this->section('Storefront projections', 'Buyer actions are rendered from backend transitions.', [
                    $this->card('Catalog', 'Browse products and provider network projections.', '/catalog'),
                    $this->card('Vault', 'Open buyer vault through Simple L1.', '/vault'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogProjection(string $path): array
    {
        return [
            'type' => 'catalog_compat_projection',
            'eyebrow' => 'Catalog projection',
            'title' => $path === '' ? 'Catalog' : Str::headline($path),
            'lead' => 'Legacy catalog paths are rendered by Next and backed by Storefront catalog DTOs.',
            'sections' => [
                $this->section('Catalog path', 'This route now resolves to a projection instead of Blade.', [
                    $this->card('All catalog', 'Browse backend-defined products and actions.', '/catalog'),
                    $this->card('Provider network', 'View heterogeneous provider supply projections.', '/catalog-network'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorProjection(string $path): array
    {
        return [
            'type' => 'error_projection',
            'eyebrow' => 'System projection',
            'title' => $path === '' ? 'System status' : 'Error '.$path,
            'lead' => 'System pages are rendered in Next and can cite backend request identifiers when present.',
            'sections' => [
                $this->section('Recovery', 'Return to a user-facing projection route.', [
                    $this->card('Catalog', 'Go back to the buyer catalog.', '/catalog'),
                    $this->card('Vault', 'Open the Simple L1 vault.', '/vault'),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericProjection(string $surface, string $path): array
    {
        return [
            'type' => 'generic_ui_projection',
            'eyebrow' => 'Projection',
            'title' => Str::headline(trim($surface.'/'.$path, '/')),
            'lead' => 'This Laravel Blade surface has a Next projection target. Domain-specific DTOs can deepen this screen without changing the URL.',
            'sections' => [
                $this->section('Projection status', 'The screen is now controlled by Next and backed by Laravel API contracts.', [
                    $this->card('DTO boundary', 'No Blade HTML is embedded in this projection.', '/'),
                ]),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    private function section(string $title, string $description, array $cards): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'cards' => $cards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $title, string $description, ?string $href = null): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'href' => $href,
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    private function actions(array $allowed): array
    {
        return [
            'allowed_actions' => $allowed,
            'blocked_actions' => [],
            'next_action' => $allowed[0] ?? 'VIEW',
            'blocking_reason' => null,
        ];
    }
}
