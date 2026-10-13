<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_urls_render_the_custom_404_page(): void
    {
        foreach (['/no-such-page', '/awards/extra/segments', '/admin/nothing-here', '/company/nothing-here'] as $url) {
            $this->get($url)->assertNotFound()->assertSee('Bowled! That page is out.')->assertSee('Error 404');
        }
    }

    public function test_the_404_page_links_back_to_the_site(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertSee('href="' . route('home') . '"', false)
            ->assertSee('href="' . route('timeline') . '"', false)
            ->assertSee('Back to home')->assertSee('View timeline');
    }

    public function test_the_404_page_uses_the_public_layout_and_page_title(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertSee('<title>Page not found | Hit the Grounds 2026</title>', false)
            ->assertSee('Company Login');
    }

    public function test_the_404_page_has_an_accessible_heading_and_respects_reduced_motion(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertSee('aria-label="404"', false)
            ->assertSee('role="img"', false)
            ->assertSee('prefers-reduced-motion: reduce', false);
    }

    public function test_the_404_page_does_not_leak_internal_details(): void
    {
        $this->get('/no-such-page')->assertNotFound()
            ->assertDontSee('NotFoundHttpException')
            ->assertDontSee('vendor/laravel')
            ->assertDontSee('No query results');
    }

    public function test_a_missing_record_in_a_model_route_shows_the_404_page(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->signedIn($user)->get('/company/teams/999999')->assertNotFound()->assertSee('Bowled! That page is out.');
    }

    public function test_the_404_page_does_not_include_analytics_on_admin_paths(): void
    {
        $this->get('/admin/nothing-here')->assertNotFound()->assertDontSee('googletagmanager.com', false);
    }

    public function test_real_pages_are_not_affected(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Bowled! That page is out.');
        $this->get(route('awards'))->assertOk();
    }
}
