<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompactPaginatorWindow
{
    private const int NEIGHBOUR_PAGES = 2;

    /** Show every page number when the result set is this many pages or fewer. */
    private const int SHOW_ALL_PAGES_THRESHOLD = 7;

    /**
     * Build a compact, windowed list of pagination elements for a Blade partial.
     *
     * Each element is either a page-number/url map or an ellipsis string, matching
     * Laravel's default pagination view shape.
     *
     * @return list<array<int, string>|string>
     */
    public static function elements(LengthAwarePaginator $paginator): array
    {
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        if ($last <= 1) {
            return [];
        }

        if ($last <= self::SHOW_ALL_PAGES_THRESHOLD) {
            return [self::pageLinks($paginator, 1, $last)];
        }

        $elements = [self::pageLinks($paginator, 1, 1)];

        $rangeStart = max(2, $current - self::NEIGHBOUR_PAGES);
        $rangeEnd = min($last - 1, $current + self::NEIGHBOUR_PAGES);

        if ($rangeStart > 2) {
            $elements[] = '...';
        }

        if ($rangeStart <= $rangeEnd) {
            $elements[] = self::pageLinks($paginator, $rangeStart, $rangeEnd);
        }

        if ($rangeEnd < $last - 1) {
            $elements[] = '...';
        }

        if ($last > 1) {
            $elements[] = self::pageLinks($paginator, $last, $last);
        }

        return $elements;
    }

    /**
     * @return array<int, string>
     */
    private static function pageLinks(LengthAwarePaginator $paginator, int $from, int $to): array
    {
        $links = [];

        for ($page = $from; $page <= $to; $page++) {
            $links[$page] = $paginator->url($page);
        }

        return $links;
    }
}
