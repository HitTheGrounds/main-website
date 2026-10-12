<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_page_has_its_own_title(): void
    {
        $titles = [
            '/awards' => 'Awards | Hit the Grounds 2026',
            '/past-seasons' => 'Past Seasons | Hit the Grounds 2026',
            '/rules' => 'Rules and Regulations | Hit the Grounds 2026',
            '/timeline' => 'Event Timeline | Hit the Grounds 2026',
            '/memories' => 'Memories | Hit the Grounds 2026',
            '/teams/industry' => 'Industry Teams | Hit the Grounds 2026',
            '/login' => 'Company Login | Hit the Grounds 2026',
        ];

        foreach ($titles as $path => $title) {
            $this->get($path)->assertOk()->assertSee('<title>'.$title.'</title>', false);
        }
    }

    public function test_home_page_title_uses_the_site_name_and_year(): void
    {
        $this->get('/')->assertSee('<title>'.config('app.name').' 2026</title>', false);
    }

    public function test_link_preview_tags_point_at_the_current_image(): void
    {
        $this->get('/')
            ->assertSee('property="og:image" content="'.asset('og-cover-2.png').'"', false)
            ->assertSee('name="twitter:image" content="'.asset('og-cover-2.png').'"', false)
            ->assertSee('property="og:image:width" content="1200"', false)
            ->assertSee('property="og:image:height" content="630"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertDontSee('cover.png"', false);
    }

    public function test_the_link_preview_image_exists_and_has_the_right_size(): void
    {
        $path = public_path('og-cover-2.png');

        $this->assertFileExists($path);
        $this->assertSame([1200, 630], array_slice(getimagesize($path), 0, 2));
        $this->assertFileDoesNotExist(public_path('cover.png'));
    }

    public function test_analytics_is_not_loaded_outside_production(): void
    {
        $this->get('/')->assertDontSee('googletagmanager.com', false);
    }

    public function test_analytics_loads_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-LWY179NW5C', false)
            ->assertSee("gtag('config', 'G-LWY179NW5C')", false);

        $this->get('/login')->assertSee('googletagmanager.com', false);
    }

    public function test_analytics_is_skipped_for_admin_paths_even_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/admin/register')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_analytics_id_can_be_switched_off_by_config(): void
    {
        $this->app['env'] = 'production';
        config(['services.google_analytics.id' => null]);

        $this->get('/')->assertDontSee('googletagmanager.com', false);
    }
}
