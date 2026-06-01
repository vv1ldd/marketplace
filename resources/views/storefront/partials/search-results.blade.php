@if(!empty($query))
    <section id="storefront" data-search-results-section>
        <div class="section-head">
            <div>
                <h2>{{ __('storefront.search_results.title', ['query' => $query]) }}</h2>
                <p>{{ __('storefront.search_results.found', ['count' => $products->total()]) }}</p>
            </div>
        </div>

        @if($products->isEmpty())
            <div class="empty">{{ __('storefront.search_results.empty') }}</div>
        @else
            <section class="grid">
                @foreach($products as $product)
                    @php
                        $variantGroup = (array) data_get($product, 'variant_group', []);
                        $isGrouped = (bool) ($variantGroup['is_grouped'] ?? false);
                        $variantSummary = collect([
                            $isGrouped ? trans_choice('storefront.search_results.variants', (int) ($variantGroup['variant_count'] ?? 0), ['count' => (int) ($variantGroup['variant_count'] ?? 0)]) : null,
                            $isGrouped && ($variantGroup['region_count'] ?? 0) > 0 ? trans_choice('storefront.search_results.regions_count', (int) ($variantGroup['region_count'] ?? 0), ['count' => (int) ($variantGroup['region_count'] ?? 0)]) : null,
                            $isGrouped && ($variantGroup['nominal_count'] ?? 0) > 0 ? trans_choice('storefront.search_results.nominals', (int) ($variantGroup['nominal_count'] ?? 0), ['count' => (int) ($variantGroup['nominal_count'] ?? 0)]) : null,
                        ])->filter()->implode(' · ');
                    @endphp
                    <article class="card">
                        <a href="{{ $product['url'] }}">
                            <div class="card-body">
                                <div class="product-title">{{ $product['name'] }}</div>
                                <div class="meta">{{ $product['category_label'] }}</div>
                                @if($isGrouped)
                                    <div style="margin-top: 6px;">
                                        <span class="tag" style="background: #efe6ff; border: 2px solid var(--line); font-size: 11px; padding: 4px 8px; box-shadow: 2px 2px 0 var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">{{ $variantSummary }}</span>
                                    </div>
                                @endif
                                @if(!empty($product['search_match_label']))
                                    <div style="margin-top: 6px;">
                                        <span class="tag" style="background: #fff7d6; border: 2px solid var(--line); font-size: 11px; padding: 4px 8px; box-shadow: 2px 2px 0 var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">{{ $product['search_match_label'] }}</span>
                                    </div>
                                @endif
                                @if(!empty($product['face_value']) && (float)$product['face_value'] > 0)
                                    <div style="margin-top: 6px; margin-bottom: 6px;">
                                        <span class="tag" style="background: #e7fff2; border: 2px solid var(--line); font-size: 11px; padding: 4px 8px; box-shadow: 2px 2px 0 var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">{{ __('storefront.search_results.top_up') }}: {{ $product['face_value'] }} {{ $product['face_value_currency'] }}</span>
                                    </div>
                                @endif
                                <div class="seller-line">{{ $product['has_selected_offer'] ? __('storefront.search_results.available_for_purchase') : __('storefront.search_results.soon_for_sale') }}</div>
                                <span class="status-pill {{ $product['has_selected_offer'] ? '' : 'network' }}">{{ $product['has_selected_offer'] ? __('storefront.search_results.available') : __('storefront.search_results.soon_available') }}</span>
                                @if($isGrouped && !empty($variantGroup['regions']))
                                    <div class="offer-summary">{{ __('storefront.search_results.regions') }}: {{ collect($variantGroup['regions'])->take(4)->implode(', ') }}@if(($variantGroup['region_count'] ?? 0) > 4) {{ __('storefront.search_results.and_others') }}@endif</div>
                                @endif
                                @if(data_get($product, 'selected_offer'))
                                    <div class="price">{{ data_get($product, 'selected_offer.price.label', number_format((float) data_get($product, 'selected_offer.price.amount'), 2, '.', ' ')) }}</div>
                                    <div class="offer-summary">{{ __('storefront.search_results.seller') }}: {{ data_get($product, 'selected_offer.seller.name') }} · {{ data_get($product, 'selected_offer.availability') }}</div>
                                @else
                                    <div class="offer-summary">{{ __('storefront.search_results.purchase_pending') }}</div>
                                @endif
                                <div class="card-actions">
                                    <span class="btn btn-primary">{{ data_get($product, 'cta_label', __('storefront.search_results.open')) }}</span>
                                </div>
                            </div>
                        </a>
                    </article>
                @endforeach
            </section>

            @if($products->hasPages())
                @php
                    $currentPage = $products->currentPage();
                    $lastPage = $products->lastPage();
                    $pageWindow = [];
                    $previousRenderedPage = null;

                    for ($page = 1; $page <= $lastPage; $page++) {
                        if ($page === 1 || $page === $lastPage || abs($page - $currentPage) <= 2) {
                            $pageWindow[] = $page;
                        }
                    }
                @endphp
                <nav class="pagination-neo" aria-label="{{ __('storefront.search_results.pagination_label') }}">
                    @if($products->onFirstPage())
                        <span class="disabled" aria-hidden="true">←</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}#storefront" rel="prev">←</a>
                    @endif

                    @foreach($pageWindow as $page)
                        @if($previousRenderedPage && $page > $previousRenderedPage + 1)
                            <span class="disabled">…</span>
                        @endif

                        @if($page === $products->currentPage())
                            <span class="active">{{ $page }}</span>
                        @else
                            <a href="{{ $products->url($page) }}#storefront">{{ $page }}</a>
                        @endif

                        @php($previousRenderedPage = $page)
                    @endforeach

                    @if($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}#storefront" rel="next">→</a>
                    @else
                        <span class="disabled" aria-hidden="true">→</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endif
