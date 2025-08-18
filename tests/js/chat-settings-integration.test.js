/**
 * Chat Settings Component Integration Tests
 * 
 * These tests verify the component works correctly with real DOM interactions
 * and simulated user behavior.
 */

// Mock fetch for API calls
const mockFetch = jest.fn();
global.fetch = mockFetch;

// Mock DOM methods
Object.defineProperty(window, 'confirm', {
    value: jest.fn(() => true),
    writable: true
});

// Mock CSRF token
document.head.innerHTML = '<meta name="csrf-token" content="test-token">';

// Import the component
const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');

describe('ChatSettingsComponent Integration Tests', () => {
    let container;
    let component;

    beforeEach(() => {
        // Reset mocks
        mockFetch.mockClear();
        window.confirm.mockClear();

        // Create full DOM structure
        container = document.createElement('div');
        container.innerHTML = `
            <!-- Chat Settings Modal -->
            <div id="chat-settings-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" role="dialog" aria-labelledby="chat-settings-title" aria-hidden="true">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-gray-800 rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" role="document">
                        <!-- Header -->
                        <div class="p-6 border-b border-gray-700">
                            <div class="flex items-center justify-between">
                                <h2 id="chat-settings-title" class="text-xl font-semibold text-white">Chat Settings</h2>
                                <button id="close-chat-settings" class="text-gray-400 hover:text-white transition-colors" aria-label="Close settings">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-gray-400 mt-2">Configure your AI chat preferences and model settings</p>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-6">
                            <!-- Loading State -->
                            <div id="settings-loading" class="text-center py-8 hidden">
                                <div class="inline-flex items-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-gray-400">Loading settings...</span>
                                </div>
                            </div>

                            <!-- Settings Form -->
                            <form id="chat-settings-form" class="space-y-6">
                                <!-- AI Model Selection -->
                                <div class="space-y-3">
                                    <label class="block text-sm font-medium text-gray-300">AI Model</label>
                                    <div id="model-selection" class="space-y-2">
                                        <!-- Models will be populated here -->
                                    </div>
                                    <p class="text-xs text-gray-500">Choose the AI model for your chat conversations</p>
                                </div>

                                <!-- Temperature/Creativity -->
                                <div class="space-y-3">
                                    <label class="block text-sm font-medium text-gray-300">
                                        Creativity Level
                                        <span class="text-xs text-gray-400">(Temperature: <span id="temperature-value">0.7</span>)</span>
                                    </label>
                                    <input type="range" 
                                           id="temperature-slider" 
                                           name="temperature"
                                           min="0" 
                                           max="2" 
                                           step="0.1"
                                           value="0.7"
                                           class="w-full h-2 bg-gray-600 rounded-lg appearance-none cursor-pointer slider">
                                    <div class="flex justify-between text-xs text-gray-400">
                                        <span>Focused (0.0)</span>
                                        <span>Balanced (1.0)</span>
                                        <span>Creative (2.0)</span>
                                    </div>
                                    <p class="text-xs text-gray-500">Higher values make responses more creative but less predictable</p>
                                </div>

                                <!-- Max Tokens -->
                                <div class="space-y-3">
                                    <label for="max-tokens" class="block text-sm font-medium text-gray-300">
                                        Max Tokens
                                        <span class="text-xs text-gray-400">(Response Length)</span>
                                    </label>
                                    <input type="number" 
                                           id="max-tokens" 
                                           name="max_tokens"
                                           min="1" 
                                           max="8000" 
                                           value="1024"
                                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    <div class="flex justify-between text-xs text-gray-400">
                                        <span>Short (256)</span>
                                        <span>Medium (1024)</span>
                                        <span>Long (4096)</span>
                                        <span>Max (8000)</span>
                                    </div>
                                    <p class="text-xs text-gray-500">Maximum number of tokens in AI responses</p>
                                </div>

                                <!-- Streaming Options -->
                                <div class="space-y-3">
                                    <label class="block text-sm font-medium text-gray-300">Chat Options</label>
                                    <div class="space-y-3">
                                        <label class="flex items-center space-x-3">
                                            <input type="checkbox" 
                                                   id="streaming-enabled" 
                                                   name="streaming_enabled"
                                                   checked
                                                   class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-sm font-medium text-gray-300">Enable streaming responses</span>
                                                <p class="text-xs text-gray-400">Show AI responses in real-time as they're generated</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Model Information -->
                                <div id="model-info" class="bg-gray-700 rounded-lg p-4 hidden">
                                    <h4 class="text-sm font-medium text-white mb-2">Selected Model Information</h4>
                                    <div class="space-y-1 text-xs text-gray-400">
                                        <p><span class="font-medium">Provider:</span> <span id="model-provider">-</span></p>
                                        <p><span class="font-medium">Engine:</span> <span id="model-engine">-</span></p>
                                        <p><span class="font-medium">Description:</span> <span id="model-description">-</span></p>
                                    </div>
                                </div>
                            </form>

                            <!-- Error State -->
                            <div id="settings-error" class="text-center py-8 hidden">
                                <svg class="h-12 w-12 text-red-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-red-400 text-sm mb-2">Failed to load settings</p>
                                <button id="retry-settings" class="text-indigo-400 hover:text-indigo-300 text-sm underline">
                                    Try again
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-6 border-t border-gray-700 flex justify-end space-x-3">
                            <button type="button" 
                                    id="reset-settings" 
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                                Reset to Defaults
                            </button>
                            <button type="button" 
                                    id="cancel-settings" 
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    id="save-settings" 
                                    form="chat-settings-form"
                                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                                Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trigger Button -->
            <button id="open-chat-settings">Open Settings</button>

            <!-- Toast Notifications -->
            <div id="settings-success-toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Settings saved successfully!</span>
                </div>
            </div>

            <div id="settings-error-toast" class="fixed top-4 right-4 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span id="error-toast-message">Failed to save settings</span>
                </div>
            </div>
        `;
        
        document.body.appendChild(container);

        // Mock successful API responses
        mockFetch.mockImplementation((url, options) => {
            if (url.includes('/api/chat/settings') && options?.method === 'POST') {
                // Save settings
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        success: true,
                        settings: JSON.parse(options.body)
                    })
                });
            } else if (url.includes('/api/chat/settings/reset')) {
                // Reset settings
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        success: true,
                        settings: {
                            ai_model: 'claude-sonnet-4-20250514',
                            temperature: 0.7,
                            max_tokens: 1024,
                            streaming_enabled: true
                        }
                    })
                });
            } else if (url.includes('/api/chat/settings')) {
                // Get settings
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        success: true,
                        settings: {
                            ai_model: 'claude-sonnet-4-20250514',
                            temperature: 0.7,
                            max_tokens: 1024,
                            streaming_enabled: true
                        }
                    })
                });
            } else if (url.includes('/api/chat/models')) {
                // Get models
                return Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve({
                        success: true,
                        models: [
                            {
                                id: 'claude-sonnet-4-20250514',
                                name: 'PlayCanvas Model (claude-sonnet-4-20250514)',
                                provider: 'anthropic',
                                description: 'Optimized for PlayCanvas game development',
                                available: true,
                                engine_type: 'playcanvas'
                            },
                            {
                                id: 'gpt-4',
                                name: 'GPT-4',
                                provider: 'openai',
                                description: 'OpenAI GPT-4 model',
                                available: true,
                                engine_type: null
                            },
                            {
                                id: 'claude-3-opus',
                                name: 'Claude 3 Opus',
                                provider: 'anthropic',
                                description: 'Anthropic Claude 3 Opus model',
                                available: false,
                                engine_type: null
                            }
                        ]
                    })
                });
            }
            return Promise.reject(new Error('Not found'));
        });
    });

    afterEach(() => {
        if (component) {
            component.destroy();
        }
        document.body.removeChild(container);
    });

    describe('Full User Workflow', () => {
        test('should complete full settings workflow', async () => {
            // Initialize component
            component = new ChatSettingsComponent();
            
            // Wait for initial load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            const openButton = document.getElementById('open-chat-settings');
            openButton.click();
            
            // Wait for modal to open and load
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Verify modal is open
            const modal = document.getElementById('chat-settings-modal');
            expect(modal.classList.contains('hidden')).toBe(false);
            
            // Verify models are rendered
            const modelSelection = document.getElementById('model-selection');
            expect(modelSelection.children.length).toBeGreaterThan(0);
            
            // Change temperature
            const temperatureSlider = document.getElementById('temperature-slider');
            temperatureSlider.value = '1.2';
            temperatureSlider.dispatchEvent(new Event('input'));
            
            // Verify temperature display updated
            const temperatureValue = document.getElementById('temperature-value');
            expect(temperatureValue.textContent).toBe('1.2');
            
            // Change max tokens
            const maxTokensInput = document.getElementById('max-tokens');
            maxTokensInput.value = '2048';
            maxTokensInput.dispatchEvent(new Event('input'));
            
            // Toggle streaming
            const streamingCheckbox = document.getElementById('streaming-enabled');
            streamingCheckbox.checked = false;
            streamingCheckbox.dispatchEvent(new Event('change'));
            
            // Select different model
            const gptRadio = document.querySelector('input[value="gpt-4"]');
            if (gptRadio) {
                gptRadio.checked = true;
                gptRadio.dispatchEvent(new Event('change'));
                
                // Verify model info updated
                await new Promise(resolve => setTimeout(resolve, 50));
                const modelProvider = document.getElementById('model-provider');
                expect(modelProvider.textContent).toBe('openai');
            }
            
            // Save settings
            const saveButton = document.getElementById('save-settings');
            saveButton.click();
            
            // Wait for save to complete
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Verify modal closed
            expect(modal.classList.contains('hidden')).toBe(true);
        });

        test('should handle model selection with AI_MODEL_PLAYCANVAS', async () => {
            component = new ChatSettingsComponent();
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Verify PlayCanvas model is available
            const playcanvasRadio = document.querySelector('input[value="claude-sonnet-4-20250514"]');
            expect(playcanvasRadio).toBeTruthy();
            
            // Verify it's marked as PlayCanvas engine
            const playcanvasLabel = playcanvasRadio.closest('label');
            expect(playcanvasLabel.textContent).toContain('playcanvas');
            
            // Select PlayCanvas model
            playcanvasRadio.checked = true;
            playcanvasRadio.dispatchEvent(new Event('change'));
            
            // Verify model info shows PlayCanvas details
            await new Promise(resolve => setTimeout(resolve, 50));
            const modelEngine = document.getElementById('model-engine');
            expect(modelEngine.textContent).toBe('playcanvas');
        });

        test('should handle unavailable models correctly', async () => {
            component = new ChatSettingsComponent();
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Find unavailable model
            const unavailableRadio = document.querySelector('input[value="claude-3-opus"]');
            expect(unavailableRadio).toBeTruthy();
            expect(unavailableRadio.disabled).toBe(true);
            
            // Verify it's visually marked as unavailable
            const unavailableLabel = unavailableRadio.closest('label');
            expect(unavailableLabel.classList.contains('opacity-50')).toBe(true);
        });

        test('should validate settings before saving', async () => {
            component = new ChatSettingsComponent();
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Set invalid temperature
            const temperatureSlider = document.getElementById('temperature-slider');
            temperatureSlider.value = '3.0'; // Invalid - too high
            temperatureSlider.dispatchEvent(new Event('input'));
            
            // Set invalid max tokens
            const maxTokensInput = document.getElementById('max-tokens');
            maxTokensInput.value = '10000'; // Invalid - too high
            maxTokensInput.dispatchEvent(new Event('input'));
            
            // Try to save
            const saveButton = document.getElementById('save-settings');
            saveButton.click();
            
            // Wait for validation
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Modal should still be open due to validation error
            const modal = document.getElementById('chat-settings-modal');
            expect(modal.classList.contains('hidden')).toBe(false);
        });

        test('should reset settings to defaults', async () => {
            component = new ChatSettingsComponent();
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Change some settings
            const temperatureSlider = document.getElementById('temperature-slider');
            temperatureSlider.value = '1.5';
            temperatureSlider.dispatchEvent(new Event('input'));
            
            const maxTokensInput = document.getElementById('max-tokens');
            maxTokensInput.value = '2048';
            maxTokensInput.dispatchEvent(new Event('input'));
            
            // Reset settings
            const resetButton = document.getElementById('reset-settings');
            resetButton.click();
            
            // Wait for reset
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Verify settings were reset
            expect(temperatureSlider.value).toBe('0.7');
            expect(maxTokensInput.value).toBe('1024');
        });

        test('should handle close with unsaved changes', async () => {
            component = new ChatSettingsComponent();
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Make changes
            const temperatureSlider = document.getElementById('temperature-slider');
            temperatureSlider.value = '1.5';
            temperatureSlider.dispatchEvent(new Event('input'));
            
            // Try to close
            window.confirm.mockReturnValue(false); // User cancels
            const closeButton = document.getElementById('close-chat-settings');
            closeButton.click();
            
            // Modal should still be open
            const modal = document.getElementById('chat-settings-modal');
            expect(modal.classList.contains('hidden')).toBe(false);
            expect(window.confirm).toHaveBeenCalledWith('You have unsaved changes. Are you sure you want to close without saving?');
            
            // Now allow close
            window.confirm.mockReturnValue(true);
            closeButton.click();
            
            // Modal should be closed
            expect(modal.classList.contains('hidden')).toBe(true);
        });

        test('should handle keyboard navigation', async () => {
            component = new ChatSettingsComponent();
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            const modal = document.getElementById('chat-settings-modal');
            expect(modal.classList.contains('hidden')).toBe(false);
            
            // Press Escape to close
            const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' });
            document.dispatchEvent(escapeEvent);
            
            // Modal should be closed
            expect(modal.classList.contains('hidden')).toBe(true);
        });

        test('should handle API errors gracefully', async () => {
            // Mock API error
            mockFetch.mockRejectedValue(new Error('Network error'));
            
            component = new ChatSettingsComponent();
            
            // Wait for load attempt
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Should show error state
            const errorElement = document.getElementById('settings-error');
            expect(errorElement.classList.contains('hidden')).toBe(false);
            
            // Retry should work
            mockFetch.mockClear();
            mockFetch.mockImplementation((url) => {
                if (url.includes('/api/chat/settings')) {
                    return Promise.resolve({
                        ok: true,
                        json: () => Promise.resolve({
                            success: true,
                            settings: {
                                ai_model: 'claude-sonnet-4-20250514',
                                temperature: 0.7,
                                max_tokens: 1024,
                                streaming_enabled: true
                            }
                        })
                    });
                } else if (url.includes('/api/chat/models')) {
                    return Promise.resolve({
                        ok: true,
                        json: () => Promise.resolve({
                            success: true,
                            models: []
                        })
                    });
                }
                return Promise.reject(new Error('Not found'));
            });
            
            const retryButton = document.getElementById('retry-settings');
            retryButton.click();
            
            // Wait for retry
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Should show form now
            const form = document.getElementById('chat-settings-form');
            expect(form.classList.contains('hidden')).toBe(false);
        });
    });

    describe('Component Events', () => {
        test('should emit events during workflow', async () => {
            const events = [];
            
            component = new ChatSettingsComponent({
                onSettingsSaved: (settings) => events.push({ type: 'saved', settings }),
                onSettingsChanged: (settings) => events.push({ type: 'changed', settings }),
                onModelChanged: (modelId, model) => events.push({ type: 'modelChanged', modelId, model }),
                onError: (error) => events.push({ type: 'error', error })
            });
            
            // Wait for load
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Open modal
            document.getElementById('open-chat-settings').click();
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Make changes
            const temperatureSlider = document.getElementById('temperature-slider');
            temperatureSlider.value = '1.2';
            temperatureSlider.dispatchEvent(new Event('input'));
            
            // Should have change event
            expect(events.some(e => e.type === 'changed')).toBe(true);
            
            // Change model
            const gptRadio = document.querySelector('input[value="gpt-4"]');
            if (gptRadio) {
                gptRadio.checked = true;
                gptRadio.dispatchEvent(new Event('change'));
                
                // Should have model change event
                expect(events.some(e => e.type === 'modelChanged')).toBe(true);
            }
            
            // Save settings
            const saveButton = document.getElementById('save-settings');
            saveButton.click();
            
            // Wait for save
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Should have saved event
            expect(events.some(e => e.type === 'saved')).toBe(true);
        });
    });
});