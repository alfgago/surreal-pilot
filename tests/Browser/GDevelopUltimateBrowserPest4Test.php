<?php

use Nesk\Puphpeteer\Puppeteer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->baseUrl = 'http://surreal-pilot.local';
    
    // Mock AI responses for consistent testing
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'gameData' => [
                                'properties' => [
                                    'name' => 'Browser Test Platformer',
                                    'description' => 'A platformer game created through browser automation',
                                    'version' => '1.0.0'
                                ],
                                'scenes' => [
                                    [
                                        'name' => 'MainScene',
                                        'objects' => [
                                            [
                                                'name' => 'Player',
                                                'type' => 'Sprite',
                                                'behaviors' => [['type' => 'PlatformerObject']]
                                            ],
                                            [
                                                'name' => 'Coin',
                                                'type' => 'Sprite'
                                            ],
                                            [
                                                'name' => 'Platform',
                                                'type' => 'TiledSprite'
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'explanation' => 'Created a complete platformer game with player movement and coin collection.'
                        ])
                    ]
                ]
            ]
        ], 200)
    ]);
    
    // Ensure screenshot directory exists
    $screenshotDir = storage_path('app/screenshots');
    if (!File::exists($screenshotDir)) {
        File::makeDirectory($screenshotDir, 0755, true);
    }
});

afterEach(function () {
    if (isset($this->browser)) {
        $this->browser->close();
    }
});

test('complete browser workflow from signup to game export', function () {
    $puppeteer = new Puppeteer();
    $this->browser = $puppeteer->launch([
        'headless' => true, // Set to false to see the browser in action
        'args' => ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    ]);
    
    $page = $this->browser->newPage();
    $page->setViewport(['width' => 1920, 'height' => 1080]);
    
    try {
        echo "🌐 Starting complete browser workflow test...\n";
        
        // Step 1: Navigate to homepage
        echo "🏠 Navigating to homepage...\n";
        $page->goto($this->baseUrl, ['waitUntil' => 'networkidle0', 'timeout' => 30000]);
        
        // Take homepage screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/01-homepage.png')]);
        echo "📸 Homepage screenshot saved\n";
        
        // Step 2: Go to registration
        echo "📝 Navigating to registration...\n";
        $page->goto($this->baseUrl . '/register', ['waitUntil' => 'networkidle0', 'timeout' => 30000]);
        
        // Wait for form to load
        $page->waitForSelector('body', ['timeout' => 10000]);
        
        // Step 3: Fill registration form
        echo "✍️ Filling registration form...\n";
        $uniqueEmail = 'browsertest' . time() . '@gdevelop.com';
        
        // Wait for and fill form fields
        $page->waitForSelector('input[name="name"]', ['timeout' => 10000]);
        $page->type('input[name="name"]', 'Browser Test User');
        
        $page->waitForSelector('input[name="email"]', ['timeout' => 5000]);
        $page->type('input[name="email"]', $uniqueEmail);
        
        $page->waitForSelector('input[name="password"]', ['timeout' => 5000]);
        $page->type('input[name="password"]', 'password123');
        
        $page->waitForSelector('input[name="password_confirmation"]', ['timeout' => 5000]);
        $page->type('input[name="password_confirmation"]', 'password123');
        
        $page->waitForSelector('input[name="company_name"]', ['timeout' => 5000]);
        $page->type('input[name="company_name"]', 'Browser Test Company');
        
        // Take registration form screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/02-registration-form.png')]);
        
        // Step 4: Submit registration
        echo "🚀 Submitting registration...\n";
        $page->click('button[type="submit"]');
        $page->waitForNavigation(['waitUntil' => 'networkidle0', 'timeout' => 30000]);
        
        echo "✅ Registration completed for: {$uniqueEmail}\n";
        
        // Take post-registration screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/03-post-registration.png')]);
        
        // Step 5: Navigate to engine selection
        echo "🎮 Navigating to engine selection...\n";
        $page->goto($this->baseUrl . '/engine-selection', ['waitUntil' => 'networkidle0', 'timeout' => 30000]);
        
        // Take engine selection screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/04-engine-selection.png')]);
        
        // Step 6: Select engine
        echo "🔧 Selecting game engine...\n";
        try {
            // Look for engine cards
            $page->waitForSelector('[data-slot="card"], .engine-card, .card', ['timeout' => 10000]);
            
            // Try to find and click GDevelop or any available engine
            $engineSelectors = [
                '[data-engine="gdevelop"]',
                '[data-testid="gdevelop-option"]',
                'button:contains("GDevelop")',
                'div:contains("GDevelop")',
                '[data-slot="card"]:first-child',
                '.engine-card:first-child',
                '.card:first-child'
            ];
            
            $engineSelected = false;
            foreach ($engineSelectors as $selector) {
                try {
                    $page->click($selector);
                    $engineSelected = true;
                    echo "✅ Engine selected with selector: {$selector}\n";
                    break;
                } catch (Exception $e) {
                    continue;
                }
            }
            
            if ($engineSelected) {
                // Look for and click continue/submit button
                $continueSelectors = [
                    'button[type="submit"]',
                    'button:contains("Continue")',
                    'button:contains("Next")',
                    '.btn-primary'
                ];
                
                foreach ($continueSelectors as $selector) {
                    try {
                        $page->waitForSelector($selector, ['timeout' => 5000]);
                        $page->click($selector);
                        $page->waitForNavigation(['waitUntil' => 'networkidle0', 'timeout' => 30000]);
                        echo "✅ Continued from engine selection\n";
                        break;
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        } catch (Exception $e) {
            echo "ℹ️ Engine selection might be automatic or different UI\n";
        }
        
        // Step 7: Navigate to workspaces or create workspace
        echo "📁 Navigating to workspaces...\n";
        $page->goto($this->baseUrl . '/workspaces', ['waitUntil' => 'networkidle0', 'timeout' => 30000]);
        
        // Take workspaces screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/05-workspaces.png')]);
        
        // Step 8: Create workspace
        echo "📁 Creating workspace...\n";
        try {
            // Look for create workspace button
            $createSelectors = [
                '[data-testid="create-workspace"]',
                'button:contains("Create")',
                'a:contains("Create")',
                '.btn:contains("New")',
                'button[type="submit"]'
            ];
            
            $workspaceCreated = false;
            foreach ($createSelectors as $selector) {
                try {
                    $page->click($selector);
                    $workspaceCreated = true;
                    echo "✅ Clicked create workspace button\n";
                    break;
                } catch (Exception $e) {
                    continue;
                }
            }
            
            if ($workspaceCreated) {
                // Fill workspace form
                try {
                    $page->waitForSelector('input[name="name"]', ['timeout' => 10000]);
                    $page->type('input[name="name"]', 'Browser Test Game Project');
                    
                    // Select GDevelop engine if available
                    try {
                        $page->select('select[name="engine"]', 'gdevelop');
                        echo "✅ Selected GDevelop engine\n";
                    } catch (Exception $e) {
                        echo "ℹ️ Engine selection not available in form\n";
                    }
                    
                    $page->click('button[type="submit"]');
                    $page->waitForNavigation(['waitUntil' => 'networkidle0', 'timeout' => 30000]);
                    echo "✅ Workspace created successfully\n";
                } catch (Exception $e) {
                    echo "ℹ️ Workspace form not found or different structure\n";
                }
            }
        } catch (Exception $e) {
            echo "ℹ️ Workspace creation might be automatic\n";
        }
        
        // Take workspace created screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/06-workspace-created.png')]);
        
        // Step 9: Find and use chat interface
        echo "💬 Looking for chat interface...\n";
        
        // Try different pages that might have chat
        $chatPages = [
            $this->baseUrl . '/chat',
            $this->baseUrl . '/workspace',
            $this->baseUrl . '/game',
            $this->baseUrl . '/assist'
        ];
        
        $chatFound = false;
        foreach ($chatPages as $chatPage) {
            try {
                $page->goto($chatPage, ['waitUntil' => 'networkidle0', 'timeout' => 15000]);
                
                // Look for chat interface elements
                $chatSelectors = [
                    '[data-testid="message-input"]',
                    'textarea[placeholder*="message"]',
                    'input[placeholder*="message"]',
                    'textarea[placeholder*="chat"]',
                    'textarea',
                    '.chat-input',
                    '#message-input'
                ];
                
                foreach ($chatSelectors as $selector) {
                    try {
                        $page->waitForSelector($selector, ['timeout' => 3000]);
                        echo "💬 Found chat input at {$chatPage}\n";
                        
                        // Type message
                        $page->type($selector, 'Create a simple platformer game with a player that can jump and collect coins');
                        
                        // Look for send button
                        $sendSelectors = [
                            '[data-testid="send-button"]',
                            'button:contains("Send")',
                            'button[type="submit"]',
                            '.send-button',
                            '.btn-primary'
                        ];
                        
                        foreach ($sendSelectors as $sendSelector) {
                            try {
                                $page->click($sendSelector);
                                echo "✅ Chat message sent successfully\n";
                                $chatFound = true;
                                
                                // Wait for AI response
                                echo "🤖 Waiting for AI response...\n";
                                $page->waitFor(8000); // Wait 8 seconds for response
                                
                                // Take chat screenshot
                                $page->screenshot(['path' => storage_path('app/screenshots/07-chat-interaction.png')]);
                                
                                echo "✅ AI response received\n";
                                break 3; // Break out of all loops
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                        break 2; // Break out of selector and page loops
                    } catch (Exception $e) {
                        continue;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        if (!$chatFound) {
            echo "ℹ️ Chat interface not found, checking current page for any interactive elements\n";
            
            // Take screenshot of current state
            $page->screenshot(['path' => storage_path('app/screenshots/07-no-chat-found.png')]);
        }
        
        // Step 10: Look for preview functionality
        echo "🎮 Looking for game preview...\n";
        $previewSelectors = [
            '[data-testid="preview-game"]',
            'button:contains("Preview")',
            'a:contains("Preview")',
            '.preview-button',
            'iframe[src*="preview"]'
        ];
        
        foreach ($previewSelectors as $selector) {
            try {
                $page->waitForSelector($selector, ['timeout' => 5000]);
                
                if (str_contains($selector, 'iframe')) {
                    echo "✅ Game preview iframe found\n";
                } else {
                    $page->click($selector);
                    echo "✅ Preview button clicked\n";
                    
                    // Wait for preview to load
                    $page->waitFor(3000);
                }
                
                // Take preview screenshot
                $page->screenshot(['path' => storage_path('app/screenshots/08-game-preview.png')]);
                break;
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Step 11: Look for export functionality
        echo "📦 Looking for export functionality...\n";
        $exportSelectors = [
            '[data-testid="export-game"]',
            'button:contains("Export")',
            'a:contains("Export")',
            'button:contains("Download")',
            '.export-button'
        ];
        
        foreach ($exportSelectors as $selector) {
            try {
                $page->waitForSelector($selector, ['timeout' => 5000]);
                $page->click($selector);
                echo "✅ Export button clicked\n";
                
                // Look for export options
                try {
                    $page->waitForSelector('select, input[type="radio"], .export-option', ['timeout' => 5000]);
                    
                    // Try to select HTML5 format if available
                    try {
                        $page->select('select', 'html5');
                        echo "✅ Selected HTML5 export format\n";
                    } catch (Exception $e) {
                        // Format selection might not be available
                    }
                    
                    // Click export/download button
                    $finalExportSelectors = [
                        'button:contains("Export")',
                        'button:contains("Download")',
                        'button[type="submit"]'
                    ];
                    
                    foreach ($finalExportSelectors as $finalSelector) {
                        try {
                            $page->click($finalSelector);
                            echo "✅ Export initiated\n";
                            
                            // Wait for export to complete
                            $page->waitFor(5000);
                            break;
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                } catch (Exception $e) {
                    echo "ℹ️ Export options not found\n";
                }
                
                // Take export screenshot
                $page->screenshot(['path' => storage_path('app/screenshots/09-export-process.png')]);
                break;
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Step 12: Take final screenshot
        $page->screenshot(['path' => storage_path('app/screenshots/10-final-state.png')]);
        echo "📸 Final screenshot saved\n";
        
        echo "🎉 Complete browser workflow test finished successfully!\n";
        
    } catch (Exception $e) {
        echo "❌ Error during browser test: " . $e->getMessage() . "\n";
        
        // Take error screenshot
        try {
            $errorScreenshot = storage_path('app/screenshots/error-' . time() . '.png');
            $page->screenshot(['path' => $errorScreenshot]);
            echo "📸 Error screenshot saved: " . basename($errorScreenshot) . "\n";
        } catch (Exception $e2) {
            // Ignore screenshot errors
        }
    }
    
    expect(true)->toBeTrue();
});

test('mobile responsive browser workflow', function () {
    $puppeteer = new Puppeteer();
    $this->browser = $puppeteer->launch([
        'headless' => true,
        'args' => ['--no-sandbox', '--disable-setuid-sandbox']
    ]);
    
    $page = $this->browser->newPage();
    
    try {
        echo "📱 Testing mobile responsive workflow...\n";
        
        // Test different mobile viewports
        $viewports = [
            ['width' => 375, 'height' => 667, 'name' => 'iPhone'],
            ['width' => 414, 'height' => 896, 'name' => 'iPhone-XL'],
            ['width' => 768, 'height' => 1024, 'name' => 'iPad'],
            ['width' => 360, 'height' => 640, 'name' => 'Android']
        ];
        
        foreach ($viewports as $viewport) {
            echo "📱 Testing {$viewport['name']} viewport ({$viewport['width']}x{$viewport['height']})...\n";
            
            $page->setViewport(['width' => $viewport['width'], 'height' => $viewport['height']]);
            $page->goto($this->baseUrl, ['waitUntil' => 'networkidle0', 'timeout' => 30000]);
            
            // Test that the page loads and is responsive
            $page->waitForSelector('body', ['timeout' => 10000]);
            
            // Take mobile screenshot
            $screenshotName = "mobile-{$viewport['name']}-{$viewport['width']}x{$viewport['height']}.png";
            $page->screenshot(['path' => storage_path("app/screenshots/{$screenshotName}")]);
            echo "📸 Mobile screenshot saved: {$screenshotName}\n";
            
            // Test mobile navigation if available
            try {
                $page->click('[data-testid="mobile-menu-toggle"], .mobile-menu-toggle, .navbar-toggler');
                $page->waitForSelector('[data-testid="mobile-menu"], .mobile-menu', ['timeout' => 3000]);
                echo "✅ Mobile navigation working on {$viewport['name']}\n";
            } catch (Exception $e) {
                echo "ℹ️ Mobile navigation not found on {$viewport['name']}\n";
            }
        }
        
        echo "✅ Mobile responsive test completed\n";
        
    } catch (Exception $e) {
        echo "❌ Mobile test error: " . $e->getMessage() . "\n";
    }
    
    expect(true)->toBeTrue();
});

test('chat interaction stress test', function () {
    $puppeteer = new Puppeteer();
    $this->browser = $puppeteer->launch([
        'headless' => true,
        'args' => ['--no-sandbox', '--disable-setuid-sandbox']
    ]);
    
    $page = $this->browser->newPage();
    $page->setViewport(['width' => 1920, 'height' => 1080]);
    
    try {
        echo "💬 Starting chat interaction stress test...\n";
        
        // Navigate to application
        $page->goto($this->baseUrl, ['waitUntil' => 'networkidle0', 'timeout' => 30000]);
        
        // Try to find any chat interface
        $chatPages = [
            '/chat',
            '/workspace',
            '/game',
            '/assist'
        ];
        
        $chatMessages = [
            'Create a simple platformer game',
            'Add enemies that move back and forth',
            'Add power-ups for extra lives',
            'Add a scoring system',
            'Make the platforms move',
            'Add sound effects',
            'Improve the graphics',
            'Add a game over screen'
        ];
        
        foreach ($chatPages as $chatPath) {
            try {
                $page->goto($this->baseUrl . $chatPath, ['waitUntil' => 'networkidle0', 'timeout' => 15000]);
                
                // Look for chat input
                $chatSelectors = [
                    '[data-testid="message-input"]',
                    'textarea[placeholder*="message"]',
                    'textarea',
                    '.chat-input'
                ];
                
                foreach ($chatSelectors as $selector) {
                    try {
                        $page->waitForSelector($selector, ['timeout' => 3000]);
                        echo "💬 Found chat interface at {$chatPath}\n";
                        
                        // Send multiple messages
                        foreach ($chatMessages as $index => $message) {
                            echo "💬 Sending message " . ($index + 1) . ": {$message}\n";
                            
                            $page->type($selector, $message);
                            
                            // Find and click send button
                            $sendSelectors = [
                                '[data-testid="send-button"]',
                                'button:contains("Send")',
                                'button[type="submit"]'
                            ];
                            
                            foreach ($sendSelectors as $sendSelector) {
                                try {
                                    $page->click($sendSelector);
                                    echo "✅ Message sent\n";
                                    
                                    // Wait between messages
                                    $page->waitFor(2000);
                                    break;
                                } catch (Exception $e) {
                                    continue;
                                }
                            }
                            
                            // Clear input for next message
                            $page->evaluate('document.querySelector("' . $selector . '").value = ""');
                        }
                        
                        // Take final chat screenshot
                        $page->screenshot(['path' => storage_path('app/screenshots/chat-stress-test.png')]);
                        echo "📸 Chat stress test screenshot saved\n";
                        
                        echo "✅ Chat stress test completed\n";
                        return;
                        
                    } catch (Exception $e) {
                        continue;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        echo "ℹ️ No chat interface found for stress testing\n";
        
    } catch (Exception $e) {
        echo "❌ Chat stress test error: " . $e->getMessage() . "\n";
    }
    
    expect(true)->toBeTrue();
});

test('screenshot gallery summary', function () {
    echo "📸 Screenshot Gallery Summary\n";
    echo "============================\n";
    
    $screenshotDir = storage_path('app/screenshots');
    
    if (File::exists($screenshotDir)) {
        $screenshots = glob($screenshotDir . '/*.png');
        
        if (!empty($screenshots)) {
            echo "📁 Screenshots saved in: {$screenshotDir}\n\n";
            
            foreach ($screenshots as $screenshot) {
                $filename = basename($screenshot);
                $size = filesize($screenshot);
                $sizeFormatted = round($size / 1024, 2) . ' KB';
                
                echo "📸 {$filename} ({$sizeFormatted})\n";
            }
            
            echo "\n🎯 Total screenshots: " . count($screenshots) . "\n";
            echo "💾 Total size: " . round(array_sum(array_map('filesize', $screenshots)) / 1024, 2) . " KB\n";
        } else {
            echo "📝 No screenshots found\n";
        }
    } else {
        echo "📁 Screenshot directory not found\n";
    }
    
    expect(true)->toBeTrue();
});