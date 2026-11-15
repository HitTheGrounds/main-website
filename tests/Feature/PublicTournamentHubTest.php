<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicTournamentHubTest extends TestCase
{
    use DatabaseMigrations;

    private function registerTournamentRoutes(): void
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_disabled_feature_has_no_public_hub(): void
    {
        config(['tournament.enabled' => false]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        $this->get('/tournament')->assertNotFound();
        $this->assertFalse(Route::has('tournament.index'));
    }

    public function test_registered_public_route_is_blocked_when_feature_is_disabled(): void
    {
        $this->registerTournamentRoutes();
        config(['tournament.enabled' => false]);
        $this->get('/tournament')->assertNotFound();
    }

    public function test_guests_can_open_default_matches_and_follow_normal_links(): void
    {
        $this->registerTournamentRoutes();
        $response = $this->get('/tournament')->assertOk()->assertViewIs('tournament.index')
            ->assertViewHas('tab', 'matches')->assertViewHas('year', 2026)
            ->assertSee('Tournament sections')->assertSee('aria-current="page"', false)
            ->assertSee('Refresh the page')->assertSee('index, follow, max-image-preview:large');

        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            $response->assertSee('href="'.route('tournament.index', ['tab' => $tab]).'"', false);
        }
        foreach (['wire:poll', 'setInterval(', 'WebSocket(', 'Match record', 'Publish result'] as $privateOrLiveContent) {
            $response->assertDontSee($privateOrLiveContent, false);
        }
        $this->get('/scorer/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/tournament/scorers')->assertRedirect(route('login'));
        $this->post('/tournament')->assertStatus(405);
    }

    public static function sections(): array
    {
        return [['matches', 'Matches'], ['table', 'Table'], ['bracket', 'Bracket'], ['stats', 'Stats']];
    }

    #[DataProvider('sections')]
    public function test_each_section_is_a_separate_get_page(string $tab, string $label): void
    {
        $this->registerTournamentRoutes();
        $this->get('/tournament?tab='.$tab)->assertOk()->assertViewHas('tab', $tab)
            ->assertSee('<h2 id="tournament-section-heading" class="font-heading text-3xl sm:text-4xl">'.$label.'</h2>', false);
    }

    public static function invalidSections(): array
    {
        return [['unknown'], ['../scorer'], ['<script>alert(1)</script>'], [['matches']]];
    }

    #[DataProvider('invalidSections')]
    public function test_unknown_and_non_string_sections_are_rejected(mixed $tab): void
    {
        $this->registerTournamentRoutes();
        $this->get('/tournament?'.http_build_query(['tab' => $tab]))->assertNotFound();
    }

    public function test_edition_comes_from_configuration_not_query_input(): void
    {
        $this->registerTournamentRoutes();
        $this->get('/tournament?year=2025')->assertOk()->assertViewHas('year', 2026);
        config(['tournament.year' => 0]);
        $this->get('/tournament')->assertStatus(503)->assertSee('Service Unavailable');
    }
}
