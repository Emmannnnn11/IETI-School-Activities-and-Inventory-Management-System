@if ($paginator->hasPages())
    <nav class="d-flex justify-content-center" aria-label="{{ __('Pagination Navigation') }}">
        <ul class="pagination">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    <span class="page-link" aria-hidden="true">
                        <span aria-hidden="true">&lsaquo;</span>
                        <span class="ietip-history-page-label ms-1">@lang('pagination.previous')</span>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
                        <span aria-hidden="true">&lsaquo;</span>
                        <span class="ietip-history-page-label ms-1">@lang('pagination.previous')</span>
                    </a>
                </li>
            @endif

            {{-- Pagination elements (numbers + ellipsis) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
                        <span class="ietip-history-page-label me-1" aria-hidden="true">@lang('pagination.next')</span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    <span class="page-link" aria-hidden="true">
                        <span class="ietip-history-page-label me-1" aria-hidden="true">@lang('pagination.next')</span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif

