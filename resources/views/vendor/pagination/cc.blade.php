@if ($paginator->hasPages())
    @php
        $elements = \App\Support\CompactPaginatorWindow::elements($paginator);
    @endphp
    <nav class="cc-pagination" aria-label="Issues pagination" data-test="issues-pagination">
        <div class="cc-pagination__group" aria-label="First and previous pages">
            @if ($paginator->onFirstPage())
                <span class="cc-pagination__link cc-pagination__link--disabled" aria-disabled="true">First</span>
            @else
                <a class="cc-pagination__link" href="{{ $paginator->url(1) }}">First</a>
            @endif

            @if ($paginator->onFirstPage())
                <span class="cc-pagination__link cc-pagination__link--disabled" aria-disabled="true">Previous</span>
            @else
                <a class="cc-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
        </div>

        <div class="cc-pagination__pages" role="group" aria-label="Page numbers">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="cc-pagination__ellipsis muted" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="cc-pagination__link cc-pagination__link--active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="cc-pagination__link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        <div class="cc-pagination__group" aria-label="Next and last pages">
            @if ($paginator->hasMorePages())
                <a class="cc-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="cc-pagination__link cc-pagination__link--disabled" aria-disabled="true">Next</span>
            @endif

            @if ($paginator->onLastPage())
                <span class="cc-pagination__link cc-pagination__link--disabled" aria-disabled="true">Last</span>
            @else
                <a class="cc-pagination__link" href="{{ $paginator->url($paginator->lastPage()) }}">Last</a>
            @endif
        </div>
    </nav>
@endif
