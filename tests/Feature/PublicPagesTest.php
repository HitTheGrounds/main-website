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

    public function test_support_page_can_be_rendered(): void
    {
        $response = $this->get(route('support'));

        $response->assertStatus(200);
    }

    public function test_timeline_page_can_be_rendered(): void
    {
        $response = $this->get(route('timeline'));

        $response->assertStatus(200);
    }

    public function test_awards_page_can_be_rendered(): void
    {
        $response = $this->get(route('awards'));

        $response->assertStatus(200);
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
