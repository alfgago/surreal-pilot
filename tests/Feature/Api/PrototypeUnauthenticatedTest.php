<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrototypeUnauthenticatedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        $endpoints = [
            ['POST', '/api/demos'],
            ['POST', '/api/prototype', ['demo_id' => 'test', 'company_id' => 1]],
            ['GET', '/api/workspace/1/status'],
            ['GET', '/api/workspaces/stats?company_id=1'],
            ['GET', '/api/workspaces?company_id=1'],
        ];

        foreach ($endpoints as $endpoint) {
            $method = $endpoint[0];
            $url = $endpoint[1];
            $data = $endpoint[2] ?? [];
            
            $response = $this->json($method, $url, $data);
            $response->assertStatus(401);
        }
    }
}