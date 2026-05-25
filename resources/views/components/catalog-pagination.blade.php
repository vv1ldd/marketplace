@props([
    'paginator',
    'window' => 2,
])

@if($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $window = max(1, (int) $window);
        $pages = collect([1, $lastPage]);

        for ($page = max(1, $currentPage - $window); $page <= min($lastPage, $currentPage + $window); $page++) {
            $pages->push($page);
        }

        $pages = $pages
            ->filter(fn (int $page): bool => $page >= 1 && $page <= $lastPage)
            ->unique()
            ->sort()
            ->values();

        $previousRenderedPage = null;
    @endphp

    <nav class="catalog-pagination" aria-label="Пагинация каталога">
        @if($paginator->onFirstPage())
            <span class="catalog-pagination__item catalog-pagination__item--control catalog-pagination__item--disabled" aria-disabled="true">← Назад</span>
        @else
            <a class="catalog-pagination__item catalog-pagination__item--control" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Назад</a>
        @endif

        <div class="catalog-pagination__pages">
            @foreach($pages as $page)
                @if(! is_null($previousRenderedPage) && $page > $previousRenderedPage + 1)
                    <span class="catalog-pagination__ellipsis" aria-hidden="true">…</span>
                @endif

                @if($page === $currentPage)
                    <span class="catalog-pagination__item catalog-pagination__item--active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="catalog-pagination__item" href="{{ $paginator->url($page) }}" aria-label="Страница {{ $page }}">{{ $page }}</a>
                @endif

                @php $previousRenderedPage = $page; @endphp
            @endforeach
        </div>

        @if($paginator->hasMorePages())
            <a class="catalog-pagination__item catalog-pagination__item--control" href="{{ $paginator->nextPageUrl() }}" rel="next">Вперёд →</a>
        @else
            <span class="catalog-pagination__item catalog-pagination__item--control catalog-pagination__item--disabled" aria-disabled="true">Вперёд →</span>
        @endif
    </nav>
@endif
