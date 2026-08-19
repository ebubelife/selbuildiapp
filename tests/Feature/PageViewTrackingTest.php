<?php

namespace Tests\Feature;

use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_page_load_is_recorded(): void
    {
        $this->get('/');

        $this->assertDatabaseHas('page_views', ['path' => '/']);
    }

    public function test_the_admin_panel_is_not_recorded_as_a_visit(): void
    {
        $this->get('/s/admin/build');

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_post_requests_are_not_recorded(): void
    {
        PageView::query()->delete();

        $this->post('/deploy-hook', ['token' => 'invalid']);

        $this->assertDatabaseCount('page_views', 0);
    }
}
