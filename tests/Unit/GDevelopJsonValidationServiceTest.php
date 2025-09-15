<?php

use App\Services\GDevelopJsonValidationService;
use App\Exceptions\GameJsonValidationException;

describe('GDevelopJsonValidationService', function () {
    beforeEach(function () {
        $this->service = new GDevelopJsonValidationService();
    });

    describe('validateAndSanitizeGameJson', function () {
        it('validates basic game JSON structure', function () {
            $gameJson = [
                'properties' => [
                    'name' => 'Test Game',
                    'windowWidth' => 800,
                    'windowHeight' => 600
                ],
                'resources' => [],
                'objects' => [],
                'layouts' => []
            ];

            $result = $this->service->validateAndSanitizeGameJson($gameJson);

            expect($result)->toBeArray()
                ->and($result['properties']['name'])->toBe('Test Game')
                ->and($result['properties']['windowWidth'])->toBe(800)
                ->and($result['properties']['windowHeight'])->toBe(600);
        });

        it('throws exception for missing required fields', function () {
            $gameJson = [
                'properties' => []
                // Missing resources, objects, layouts
            ];

            expect(fn() => $this->service->validateAndSanitizeGameJson($gameJson))
                ->toThrow(GameJsonValidationException::class, 'Missing required field: resources');
        });

        it('throws exception for oversized JSON', function () {
            // Create a large JSON that exceeds the limit
            $largeArray = array_fill(0, 100000, 'large_string_' . str_repeat('x', 100));
            
            $gameJson = [
                'properties' => $largeArray,
                'resources' => [],
                'objects' => [],
                'layouts' => []
            ];

            expect(fn() => $this->service->validateAndSanitizeGameJson($gameJson))
                ->toThrow(GameJsonValidationException::class, 'exceeds maximum size limit');
        });

        it('sanitizes dangerous string content', function () {
            $gameJson = [
                'properties' => [
                    'name' => '<script>alert("xss")</script>Test Game',
                    'description' => 'Game with "quotes" and \'apostrophes\'',
                    'windowWidth' => 800,
                    'windowHeight' => 600
                ],
                'resources' => [],
                'objects' => [],
                'layouts' => []
            ];

            $result = $this->service->validateAndSanitizeGameJson($gameJson);

            expect($result['properties']['name'])->toBe('alert(xss)Test Game')
                ->and($result['properties']['description'])->toBe('Game with quotes and apostrophes');
        });

        it('validates and limits array sizes', function () {
            $largeResourceArray = array_fill(0, 1500, [
                'name' => 'resource',
                'file' => 'test.png',
                'kind' => 'image'
            ]);

            $gameJson = [
                'properties' => [],
                'resources' => $largeResourceArray,
                'objects' => [],
                'layouts' => []
            ];

            expect(fn() => $this->service->validateAndSanitizeGameJson($gameJson))
                ->toThrow(GameJsonValidationException::class, 'Too many resources');
        });

        it('validates resource file extensions', function () {
            $gameJson = [
                'properties' => [],
                'resources' => [
                    [
                        'name' => 'valid_image',
                        'file' => 'image.png',
                        'kind' => 'image'
                    ],
                    [
                        'name' => 'invalid_file',
                        'file' => 'malicious.exe',
                        'kind' => 'image'
                    ]
                ],
                'objects' => [],
                'layouts' => []
            ];

            $result = $this->service->validateAndSanitizeGameJson($gameJson);

            // Should only include the valid resource
            expect($result['resources'])->toHaveCount(1)
                ->and($result['resources'][0]['file'])->toBe('image.png');
        });

        it('sanitizes package names', function () {
            $gameJson = [
                'properties' => [
                    'packageName' => 'invalid-package-name!@#$%'
                ],
                'resources' => [],
                'objects' => [],
                'layouts' => []
            ];

            $result = $this->service->validateAndSanitizeGameJson($gameJson);

            expect($result['properties']['packageName'])->toBe('com.example.game');
        });

        it('limits integer values within bounds', function () {
            $gameJson = [
                'properties' => [
                    'windowWidth' => 10000, // Should be capped at 4096
                    'windowHeight' => 100,  // Should be raised to 240 minimum
                    'maxFPS' => 200        // Should be capped at 120
                ],
                'resources' => [],
                'objects' => [],
                'layouts' => []
            ];

            $result = $this->service->validateAndSanitizeGameJson($gameJson);

            expect($result['properties']['windowWidth'])->toBe(4096)
                ->and($result['properties']['windowHeight'])->toBe(240)
                ->and($result['properties']['maxFPS'])->toBe(120);
        });

        it('prevents deeply nested objects', function () {
            // Create deeply nested structure
            $deeplyNested = [];
            $current = &$deeplyNested;
            
            for ($i = 0; $i < 15; $i++) {
                $current['nested'] = [];
                $current = &$current['nested'];
            }

            $gameJson = [
                'properties' => $deeplyNested,
                'resources' => [],
                'objects' => [],
                'layouts' => []
            ];

            expect(fn() => $this->service->validateAndSanitizeGameJson($gameJson))
                ->toThrow(GameJsonValidationException::class, 'nesting depth exceeds');
        });

        it('sanitizes file paths to prevent directory traversal', function () {
            $gameJson = [
                'properties' => [],
                'resources' => [
                    [
                        'name' => 'malicious_resource',
                        'file' => '../../../etc/passwd',
                        'kind' => 'image'
                    ]
                ],
                'objects' => [],
                'layouts' => []
            ];

            $result = $this->service->validateAndSanitizeGameJson($gameJson);

            // File path should be sanitized
            expect($result['resources'][0]['file'])->not->toContain('../');
        });
    });
});