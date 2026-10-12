<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_page_uses_the_agreed_award_names(): void
    {
        $this->get(route('awards'))->assertOk()->assertSeeInOrder([
            'Champion Award', 'First Runner-up Award', 'Male MVP Award', 'Female MVP Award',
            'Orange Cap Award', 'Purple Cap Award', 'Super Striker Award', 'Most Four Hits Award',
            'Green Dot Ball Award', 'Player of the Match Award',
        ])->assertDontSee('Super Striker of the Tournament')->assertDontSee('4s of the Tournament')->assertDontSee('Match winner');
    }

    public function test_awards_page_trophy_images_exist(): void
    {
        foreach (['award_1', 'award_2', 'award_gold', 'award_silver', 'mvp_male', 'mvp_female', 'super_striker', 'most_fours', 'green_dot'] as $file) {
            $this->assertFileExists(public_path("award/{$file}.avif"));
        }
    }

    public function test_past_seasons_lists_every_season_with_champions_and_runners_up(): void
    {
        $this->get(route('seasons'))->assertOk()->assertSeeInOrder(['2025', '2024', '2019', '2018', '2015']);

        foreach (['GTN', 'Synopsys', 'Codegen', 'Virtusa', 'SimCentric Technologies', 'DirectFN', '99X', 'WSO2'] as $company) {
            $this->get(route('seasons'))->assertSee($company);
        }
    }

    public function test_leaderboard_counts_podium_finishes_and_ranks_by_them(): void
    {
        $html = $this->get(route('seasons'))->getContent();

        // Synopsys (2024 champion, 2025 runner-up) and Virtusa (2015 runner-up, 2019 champion) have two each
        $this->assertSame(2, preg_match_all('/data-count="2"/', $html));
        $this->assertSame(6, preg_match_all('/data-count="1"/', $html));
        $this->assertStringContainsString('Who keeps making the podium', $html);
        $this->assertStringNotContainsString('Full circle', $html);
        $this->assertStringNotContainsString('Champion / Runner-up', $html);
    }

    public function test_every_past_season_logo_exists(): void
    {
        foreach (['gtn', 'synopsys', 'virtusa', 'simcentric', 'directfn', '99x', 'wso2'] as $logo) {
            $this->assertFileExists(public_path("images/rankings/{$logo}.avif"));
        }
        $this->assertFileExists(public_path('partner/codegen.avif'));
        $this->assertFileExists(public_path('images/crown.avif'));
    }

    public function test_navigation_links_to_past_seasons_and_not_to_a_rankings_page(): void
    {
        $this->get('/')->assertSee('Past Seasons')->assertSee(route('seasons'), false)->assertDontSee('Past Rankings');
        $this->get('/past-rankings')->assertNotFound();
    }

    public function test_every_memories_photo_exists_and_alt_text_is_set(): void
    {
        $html = $this->get(route('memories'))->assertOk()->getContent();
        preg_match_all('#/images/memories/(memory-\d+\.avif)" alt="([^"]*)"#', $html, $m);

        $this->assertCount(58, $m[1]);
        foreach ($m[1] as $i => $file) {
            $this->assertFileExists(public_path("images/memories/{$file}"));
            $this->assertNotSame('', trim($m[2][$i]), "{$file} has no alt text");
        }
    }

    public function test_no_dashes_in_page_copy(): void
    {
        foreach (['/', '/awards', '/past-seasons', '/rules', '/timeline', '/memories'] as $path) {
            $text = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $this->get($path)->getContent()));
            $this->assertStringNotContainsString('—', $text, "em dash on {$path}");
            $this->assertStringNotContainsString('–', $text, "en dash on {$path}");
        }
    }

    public function test_rules_page_shows_the_umpire_illustration(): void
    {
        $this->get(route('rules'))->assertOk()->assertSee('header_umpire_authority.avif', false)->assertSee('Umpire Authority')
            ->assertDontSee('kroome111')->assertSee('Hit the Grounds 2026');
        $this->assertFileExists(public_path('images/rulebook_illustrations/orange/header_umpire_authority.avif'));
    }

    public function test_committee_cards_show_contact_details_as_links(): void
    {
        $this->get('/')
            ->assertSee('mailto:akindu.22@cse.mrt.ac.lk', false)
            ->assertSee('tel:+94701663373', false)
            ->assertSee('Partnership Committee')
            ->assertSee('Finance Committee');

        foreach (['akindu', 'devindi', 'sadil', 'samadhi', 'eshin', 'umen'] as $person) {
            $this->assertFileExists(public_path("people/{$person}.avif"));
        }
    }

    public function test_landing_hero_text_and_glass_card_share_one_animation_trigger(): void
    {
        $this->get('/')
            ->assertSee('id="about-text"', false)
            ->assertSee('data-aos-anchor="#about-text"', false)
            ->assertSee('htg-logo-orange.avif', false);
        $this->assertFileExists(public_path('images/htg-logo-orange.avif'));
    }

    public function test_timeline_countdown_follows_the_calendar(): void
    {
        $this->travelTo(\Carbon\Carbon::create(2026, 11, 20, 9, 0, 0, 'Asia/Colombo'));
        $this->get(route('timeline'))->assertSee('Last Day')->assertSee('1 Day');

        $this->travelTo(\Carbon\Carbon::create(2026, 11, 21, 9, 0, 0, 'Asia/Colombo'));
        $this->get(route('timeline'))->assertSee('The Tournament')->assertSee('Today')->assertSee('Closed');
    }

    public function test_unknown_pages_return_404(): void
    {
        $this->get('/support')->assertNotFound();
        $this->get('/committee')->assertNotFound();
    }
}
