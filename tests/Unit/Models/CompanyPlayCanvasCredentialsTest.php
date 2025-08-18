<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CompanyPlayCanvasCredentialsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_has_playcanvas_credentials_returns_true_when_both_fields_present()
    {
        // Arrange
        $company = Company::factory()->create([
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Act & Assert
        $this->assertTrue($company->hasPlayCanvasCredentials());
    }

    public function test_has_playcanvas_credentials_returns_false_when_api_key_missing()
    {
        // Arrange
        $company = Company::factory()->create([
            'playcanvas_api_key' => null,
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Act & Assert
        $this->assertFalse($company->hasPlayCanvasCredentials());
    }

    public function test_has_playcanvas_credentials_returns_false_when_project_id_missing()
    {
        // Arrange
        $company = Company::factory()->create([
            'playcanvas_api_key' => 'test-api-key',
            'playcanvas_project_id' => null
        ]);

        // Act & Assert
        $this->assertFalse($company->hasPlayCanvasCredentials());
    }

    public function test_has_playcanvas_credentials_returns_false_when_both_fields_missing()
    {
        // Arrange
        $company = Company::factory()->create([
            'playcanvas_api_key' => null,
            'playcanvas_project_id' => null
        ]);

        // Act & Assert
        $this->assertFalse($company->hasPlayCanvasCredentials());
    }

    public function test_has_playcanvas_credentials_returns_false_when_fields_empty_strings()
    {
        // Arrange
        $company = Company::factory()->create([
            'playcanvas_api_key' => '',
            'playcanvas_project_id' => ''
        ]);

        // Act & Assert
        $this->assertFalse($company->hasPlayCanvasCredentials());
    }

    public function test_playcanvas_api_key_is_hidden_in_serialization()
    {
        // Arrange
        $company = Company::factory()->create([
            'playcanvas_api_key' => 'secret-api-key',
            'playcanvas_project_id' => 'test-project-id'
        ]);

        // Act
        $serialized = $company->toArray();

        // Assert
        $this->assertArrayNotHasKey('playcanvas_api_key', $serialized);
        $this->assertArrayHasKey('playcanvas_project_id', $serialized);
    }
}