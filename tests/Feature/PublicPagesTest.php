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

    public function test_timeline_countdown_cards_count_the_days_left(): void
    {
        $this->travelTo(\Carbon\Carbon::create(2026, 10, 12, 12, 0, 0, 'Asia/Colombo'));

        $this->get(route('timeline'))
            ->assertOk()
            ->assertSee(['Countdown to the Tournament', '40 Days', 'Until November 21, 2026', 'Registration Closes In', '27 Days', 'Open Oct 14 - Nov 8, 2026']);
    }

    public function test_timeline_countdown_cards_after_the_event(): void
    {
        $this->travelTo(\Carbon\Carbon::create(2026, 11, 25, 12, 0, 0, 'Asia/Colombo'));

        $this->get(route('timeline'))
            ->assertOk()
            ->assertSee(['Done', 'Closed'])
            ->assertDontSee(['Countdown to the Tournament', 'Registration Closes In']);
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
        ]);
        $response->assertDontSee([
            'October 11th, 2025',
            'October 21st, 2025',
            'November 12th, 2025',
            'January 24th, 2026',
        ]);
    }

    public function test_past_seasons_page_can_be_rendered(): void
    {
        $response = $this->get(route('seasons'));

        $response->assertOk();
        $response->assertSee(['Past', 'Seasons', 'GTN', 'Synopsys', 'Codegen', 'Virtusa', 'SimCentric Technologies', 'DirectFN', '99X', 'WSO2', '2025', '2015']);
    }

    public function test_there_is_no_past_rankings_route(): void
    {
        $this->get('/past-rankings')->assertNotFound();
    }

    public function test_awards_page_can_be_rendered(): void
    {
        $response = $this->get(route('awards'));

        $response->assertOk();
        $response->assertSee([
            'Champion Award',
            'First Runner-up Award',
            'Male MVP Award',
            'Female MVP Award',
            'Orange Cap Award',
            'Purple Cap Award',
            'Super Striker Award',
            'Most Four Hits Award',
            'Green Dot Ball Award',
            'Player of the Match Award',
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

    public function test_teams_university_page_is_only_for_signed_in_users(): void
    {
        $this->get(route('teams.university'))->assertRedirect(route('login'));
    }
}
