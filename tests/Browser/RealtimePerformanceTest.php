<?php

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;



    test('chat message response latency', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Measure message send latency
            $startTime = microtime(true);
            
            $browser->type('[data-testid="message-input"]', 'Test latency message')
                    ->click('[data-testid="send-button"]')
                    ->waitFor('[data-testid="message-Test latency message"]', 5);
            
            $responseTime = microtime(true) - $startTime;
            
            $this->assertLessThan(0.5, $responseTime, "Message response took {$responseTime}s, should be under 500ms");
        });
});

test('streaming response performance', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Send message that triggers AI response
            $browser->type('[data-testid="message-input"]', 'Hello AI, please respond')
                    ->click('[data-testid="send-button"]');
            
            // Measure time to first streaming chunk
            $startTime = microtime(true);
            
            $browser->waitFor('[data-testid="streaming-response"]', 10);
            
            $firstChunkTime = microtime(true) - $startTime;
            
            $this->assertLessThan(2.0, $firstChunkTime, "First streaming chunk took {$firstChunkTime}s, should be under 2s");
            
            // Wait for streaming to complete
            $browser->waitUntilMissing('[data-testid="typing-indicator"]', 30);
            
            // Verify response was received
            $browser->assertPresent('[data-testid^="ai-message-"]');
        });
});

test('websocket connection performance', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Check WebSocket connection status
            $connectionStatus = $browser->driver->executeScript('
                return window.Echo && window.Echo.connector ? "connected" : "disconnected";
            ');
            
            $this->assertEquals('connected', $connectionStatus, 'WebSocket should be connected');
            
            // Test connection latency
            $startTime = microtime(true);
            
            // Trigger a real-time event
            $browser->type('[data-testid="message-input"]', 'Real-time test')
                    ->click('[data-testid="send-button"]');
            
            // Wait for typing indicator (real-time feature)
            $browser->waitFor('[data-testid="typing-indicator"]', 3);
            
            $realtimeLatency = microtime(true) - $startTime;
            
            $this->assertLessThan(0.1, $realtimeLatency, "Real-time event took {$realtimeLatency}s, should be under 100ms");
        });
});

test('concurrent user performance', function () {
        // Test with multiple browser instances to simulate concurrent users
        $this->browse(function (Browser $browser1, Browser $browser2) {
            // Setup both browsers
            $browser1->loginAs($this->testUser)
                     ->visit('/chat')
                     ->waitFor('[data-testid="chat-interface"]', 5);
            
            $browser2->loginAs($this->testUser)
                     ->visit('/chat')
                     ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Send messages simultaneously
            $startTime = microtime(true);
            
            $browser1->type('[data-testid="message-input"]', 'Concurrent message 1')
                     ->click('[data-testid="send-button"]');
            
            $browser2->type('[data-testid="message-input"]', 'Concurrent message 2')
                     ->click('[data-testid="send-button"]');
            
            // Wait for both messages to appear
            $browser1->waitFor('[data-testid="message-Concurrent message 1"]', 5);
            $browser2->waitFor('[data-testid="message-Concurrent message 2"]', 5);
            
            $concurrentResponseTime = microtime(true) - $startTime;
            
            $this->assertLessThan(1.0, $concurrentResponseTime, 
                "Concurrent messages took {$concurrentResponseTime}s, should be under 1s");
        });
});

test('memory usage during long chat session', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Get initial memory usage
            $initialMemory = $browser->driver->executeScript('
                return performance.memory ? performance.memory.usedJSHeapSize : 0;
            ');
            
            // Send multiple messages to simulate long session
            for ($i = 1; $i <= 10; $i++) {
                $browser->type('[data-testid="message-input"]', "Test message {$i}")
                        ->click('[data-testid="send-button"]')
                        ->waitFor("[data-testid=\"message-Test message {$i}\"]", 3);
                
                // Small delay between messages
                $browser->pause(100);
            }
            
            // Get final memory usage
            $finalMemory = $browser->driver->executeScript('
                return performance.memory ? performance.memory.usedJSHeapSize : 0;
            ');
            
            if ($initialMemory > 0 && $finalMemory > 0) {
                $memoryIncrease = $finalMemory - $initialMemory;
                $memoryIncreaseMB = $memoryIncrease / (1024 * 1024);
                
                $this->assertLessThan(50, $memoryIncreaseMB, 
                    "Memory usage increased by {$memoryIncreaseMB}MB, should be under 50MB");
            }
        });
});

test('network efficiency', function () {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->testUser)
                    ->visit('/chat')
                    ->waitFor('[data-testid="chat-interface"]', 5);
            
            // Clear performance entries
            $browser->driver->executeScript('performance.clearResourceTimings();');
            
            // Send a message
            $browser->type('[data-testid="message-input"]', 'Network efficiency test')
                    ->click('[data-testid="send-button"]')
                    ->waitFor('[data-testid="message-Network efficiency test"]', 5);
            
            // Check network requests
            $networkRequests = $browser->driver->executeScript('
                return performance.getEntriesByType("resource").map(entry => ({
                    name: entry.name,
                    transferSize: entry.transferSize,
                    duration: entry.duration
                }));
            ');
            
            // Verify API requests are efficient
            foreach ($networkRequests as $request) {
                if (str_contains($request['name'], '/api/')) {
                    $this->assertLessThan(1000, $request['duration'], 
                        "API request {$request['name']} took {$request['duration']}ms");
                    
                    if ($request['transferSize'] > 0) {
                        $this->assertLessThan(100000, $request['transferSize'], 
                            "API request {$request['name']} transferred {$request['transferSize']} bytes");
                    }
                }
            }
        });
});