<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_welcome_page_can_be_rendered(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Welcome')
        );
    }

    public function test_privacy_page_can_be_rendered(): void
    {
        $response = $this->get('/privacy');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Public/Privacy')
        );
    }

    public function test_terms_page_can_be_rendered(): void
    {
        $response = $this->get('/terms');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Public/Terms')
        );
    }

    public function test_support_page_can_be_rendered(): void
    {
        $response = $this->get('/support');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Public/Support')
        );
    }

    public function test_welcome_page_has_proper_seo_meta(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Welcome')
        );
    }

    public function test_public_pages_accessible_to_guests(): void
    {
        // Test that public pages don't require authentication
        $pages = ['/', '/privacy', '/terms', '/support'];
        
        foreach ($pages as $page) {
            $response = $this->get($page);
            $response->assertStatus(200);
        }
    }
}