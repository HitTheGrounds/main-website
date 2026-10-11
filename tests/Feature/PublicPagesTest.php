<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_can_be_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_memories_page_can_be_rendered(): void
    {
        $response = $this->get(route('memories'));

        $response->assertOk();
        $response->assertSeeText('Hit the Grounds Memories');
    }

    public function test_legacy_gallery_url_redirects_to_memories(): void
    {
        $response = $this->get(route('gallery'));

        $response->assertStatus(301);
        $response->assertRedirectToRoute('memories');
    }

    public function test_partners_page_can_be_rendered(): void
    {
        $response = $this->get(route('partners'));

        $response->assertStatus(200);
    }

    public function test_public_navigation_shows_only_current_items(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('memories'), false);
        $response->assertDontSee(route('partners'), false);
    }

    public function test_committee_content_is_on_home_page_and_standalone_page_is_removed(): void
    {
        $committeeUrl = route('home').'#committee';

        $homeResponse = $this->get(route('home'));

        $homeResponse->assertOk();
        $homeResponse->assertSee('id="committee"', false);
        $homeResponse->assertSeeText('Committee Leads');

        $this->get(route('rules'))
            ->assertOk()
            ->assertSee($committeeUrl, false);

        $this->get(route('registrations'))
            ->assertOk()
            ->assertSee($committeeUrl, false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee($committeeUrl, false);

        $this->get('/committee')->assertNotFound();
    }

    public function test_support_page_can_be_rendered(): void
    {
        $response = $this->get(route('support'));

        $response->assertStatus(200);
    }

    public function test_timeline_page_can_be_rendered(): void
    {
        $response = $this->get(route('timeline'));

        $response->assertOk();
        $response->assertSee([
            'Hit the Grounds 2026',
            'October 12th, 2026',
            'Event Announcement',
            'October 14th, 2026',
            'Open Registration',
            'November 8th, 2026',
            'Close Registration',
            'November 21st, 2026',
            'Event Day',
            '26 Days',
            'Oct 14 - Nov 8, 2026',
            '13 Days',
            'Nov 9 - Nov 21, 2026',
        ]);
        $response->assertDontSee([
            'October 11th, 2025',
            'October 21st, 2025',
            'November 12th, 2025',
            'January 24th, 2026',
        ]);
    }

    public function test_past_rankings_page_can_be_rendered(): void
    {
        $response = $this->get(route('rankings'));

        $response->assertOk();
        $response->assertSee(['Past', 'Rankings', 'GTN', 'Synopsys', 'Codegen', 'Virtusa', 'SimCentric Technologies', 'DirectFN', '99X', 'WSO2', '2025', '2015']);
    }

    public function test_awards_page_can_be_rendered(): void
    {
        $response = $this->get(route('awards'));

        $response->assertOk();
        $response->assertSee([
            'Champion',
            '1st Runners-up',
            'Most Valuable Player (Male)',
            'Most Valuable Player (Female)',
            'Orange Cap',
            'Purple Cap',
            'Super Striker of the Tournament',
            '4s of the Tournament',
            'Green Dot Ball Award',
            'Player of the Match',
            'most sixes hit',
            'most fours hit',
            'most dot balls delivered',
            'standout performer in the final',
        ]);
        $response->assertSee(route('register'), false);
        $response->assertDontSee([
            'Best Batsman',
            'Best Bowler',
            'Best Female Player',
        ]);
    }

    public function test_teams_industry_page_can_be_rendered(): void
    {
        $response = $this->get(route('teams.industry'));

        $response->assertStatus(200);
    }

    public function test_teams_university_page_can_be_rendered(): void
    {
        $response = $this->get(route('teams.university'));

        $response->assertStatus(200);
    }
}
