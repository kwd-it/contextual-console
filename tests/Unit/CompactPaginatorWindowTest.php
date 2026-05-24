<?php

use App\Support\CompactPaginatorWindow;
use Illuminate\Pagination\LengthAwarePaginator;

it('shows all page numbers when there are only a few pages', function () {
    $paginator = new LengthAwarePaginator([], 700, 100, 3);
    $paginator->setPath('/issues');

    $elements = CompactPaginatorWindow::elements($paginator);

    expect($elements)->toHaveCount(1);
    expect(array_keys($elements[0]))->toBe([1, 2, 3, 4, 5, 6, 7]);
});

it('builds a compact window with ellipses for many pages', function () {
    $paginator = new LengthAwarePaginator([], 3000, 100, 10);
    $paginator->setPath('/issues');

    $elements = CompactPaginatorWindow::elements($paginator);

    expect($elements)->toHaveCount(5);
    expect($elements[0])->toBe([1 => $paginator->url(1)]);
    expect($elements[1])->toBe('...');
    expect(array_keys($elements[2]))->toBe([8, 9, 10, 11, 12]);
    expect($elements[3])->toBe('...');
    expect($elements[4])->toBe([30 => $paginator->url(30)]);
});

it('does not include every page number when the result set spans many pages', function () {
    $paginator = new LengthAwarePaginator([], 3000, 100, 10);
    $paginator->setPath('/issues');

    $pageNumbers = collect(CompactPaginatorWindow::elements($paginator))
        ->flatMap(fn ($element) => is_array($element) ? array_keys($element) : [])
        ->all();

    expect($pageNumbers)->toBe([1, 8, 9, 10, 11, 12, 30]);
    expect($pageNumbers)->not->toContain(2);
    expect($pageNumbers)->not->toContain(20);
});

it('preserves query string parameters in generated page urls', function () {
    $paginator = new LengthAwarePaginator([], 3000, 100, 10);
    $paginator->setPath('/issues')->appends(['issue_status' => 'open', 'severity' => 'error']);

    $elements = CompactPaginatorWindow::elements($paginator);

    expect($elements[0][1])->toContain('issue_status=open');
    expect($elements[0][1])->toContain('severity=error');
    expect($elements[2][10])->toContain('page=10');
    expect($elements[4][30])->toContain('page=30');
});
