<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TournamentSiteIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    private function enableTournament(): void
    {
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_timeline_is_public_only_before_the_tournament_is_enabled(): void
    {
        foreach ([false, true, false] as $enabled) {
            config(['tournament.enabled' => $enabled]);
            $home = $this->get('/')->assertOk();
            $sitemap = $this->get('/sitemap.xml')->assertOk();
            $timeline = $this->get('/timeline');
            $notFound = $this->get('/missing-public-page')->assertNotFound();

            if ($enabled) {
                $home->assertDontSee('href="'.route('timeline').'"', false)
                    ->assertDontSee('id="timeline"', false);
                $sitemap->assertDontSee(route('timeline'), false);
                $timeline->assertNotFound();
                $notFound->assertDontSee('href="'.route('timeline').'"', false);
            } else {
                $this->assertSame(3, substr_count($home->getContent(), 'href="'.route('timeline').'"'));
                $home->assertSee('id="timeline"', false);
                $sitemap->assertSee('<loc>'.route('timeline').'</loc>', false);
                $timeline->assertOk();
                $notFound->assertSee('href="'.route('timeline').'"', false);
            }
        }
    }

    public function test_disabled_feature_has_no_tournament_navigation_or_sitemap_entry(): void
    {
        config(['tournament.enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('href="'.url('/tournament').'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(url('/tournament'), false);
        config(['tournament.enabled' => true]);
        // Also safe before routes have been registered or their cache rebuilt.
        $this->get('/')->assertOk()->assertDontSee('href="'.url('/tournament').'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(url('/tournament'), false);
    }

    public function test_enabled_public_navigation_sitemap_and_canonical_are_consistent(): void
    {
        $this->enableTournament();
        $home = $this->get('/')->assertOk()->assertSee('navbar-center hidden xl:flex', false);
        $this->assertSame(2, substr_count($home->getContent(), 'href="'.route('tournament.index').'"'));
        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            $this->get('/tournament?tab='.$tab)->assertOk()->assertSee('content="index, follow, max-image-preview:large"', false)
                ->assertSee('<link rel="canonical" href="'.route('tournament.index').'">', false)
                ->assertSee('Skip to tournament content')->assertSee('tabindex="-1"', false);
        }
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $this->assertSame(1, substr_count($sitemap->getContent(), '<loc>'.route('tournament.index').'</loc>'));
        $sitemap->assertDontSee('/scorer')->assertDontSee('/admin/tournament')->assertDontSee('?tab=');
        config(['tournament.enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('href="'.route('tournament.index').'"', false)
            ->assertSee('navbar-center hidden lg:flex', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('tournament.index'), false);
    }

    public function test_private_routes_remain_noindex_and_link_only_to_public_results(): void
    {
        $this->enableTournament();
        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $scorer->is_scorer = true;
        $scorer->save();
        $this->signedIn($scorer)->get('/scorer/dashboard')->assertOk()->assertSee('Public results')
            ->assertSee('href="'.route('tournament.index').'"', false)->assertSee('content="noindex, nofollow"', false);
        $this->get('/admin/tournament/scorers')->assertForbidden();
        $this->signedIn(User::factory()->create(['is_admin' => true]))->get('/admin/dashboard')->assertOk()
            ->assertSee('Public results')->assertSee('Scorer accounts')->assertSee('content="noindex, nofollow"', false);
        $this->get('/scorer/dashboard')->assertForbidden();
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /scorer')->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /company')->assertDontSee('Disallow: /tournament');
    }

    public function test_inclusive_copy_keeps_existing_registration_controls_and_form_bindings(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in')->assertSee('university and company representatives')
            ->assertSee('scorers and administrators')->assertSee('Enter your email');
        $this->get('/register')->assertOk()->assertSee('Organization registration')
            ->assertSee('Organization name (company or university)')->assertSee('company_name');
        $this->get('/registrations')->assertOk()->assertSee('per registered organization');
        config(['registration.enabled' => false]);
        $this->get('/register')->assertOk()->assertSee('Organization registrations are currently closed.');
    }
}
