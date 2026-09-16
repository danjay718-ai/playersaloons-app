<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    public function test_named_paginator_controls_target_the_correct_list_and_include_all_page_groups(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 1000, 10, 50, [
            'path' => '/admin/cms',
            'pageName' => 'games_page',
        ]);

        $html = (string) $paginator->links('vendor.livewire.custom-pagination');

        $this->assertStringContainsString("previousPage('games_page')", $html);
        $this->assertStringContainsString("nextPage('games_page')", $html);
        $this->assertStringContainsString("gotoPage(100, 'games_page')", $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('...', $html);
    }

    public function test_controller_pagination_uses_real_links_and_keeps_filters(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 30, 10, 2, [
            'path' => '/admin/head-to-head',
        ]);
        $paginator->appends(['status' => 'active']);

        $html = (string) $paginator->links('vendor.livewire.custom-pagination', ['livewire' => false]);

        $this->assertStringNotContainsString('wire:click', $html);
        $this->assertStringContainsString('/admin/head-to-head?status=active&amp;page=3', $html);
    }

    public function test_simple_pagination_and_single_page_results_render_without_errors(): void
    {
        $simple = new Paginator(range(1, 11), 10, 1, ['pageName' => 'content_page']);
        $html = (string) $simple->links('vendor.livewire.custom-pagination');
        $this->assertStringContainsString("nextPage('content_page')", $html);

        $singlePage = new LengthAwarePaginator([1], 1, 10);
        $this->assertStringNotContainsString('<nav', (string) $singlePage->links('vendor.livewire.custom-pagination'));
    }
}
