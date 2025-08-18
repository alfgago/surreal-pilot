/**
 * Chat Settings Component Tests
 */

// Mock DOM elements and APIs
const mockFetch = jest.fn();
global.fetch = mockFetch;

// Mock DOM methods
Object.defineProperty(window, 'confirm', {
    value: jest.fn(() => true),
    writable: true
});

Object.defineProperty(window, 'alert', {
    value: jest.fn(),
    writable: true
});

// Mock CSRF token
document.head.innerHTML = '<meta name="csrf-token" content="test-token">';

// Import the component
const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');

describe('ChatSettingsComponent', () => {
    let container;
    let component;

    beforeEach(() => {
        // Reset mocks
        mockFetch.mockClear();
        window.confirm.mockClear();
        window.alert.mockClear();

        // Create DOM structure
        container = document.createElement('div');
        container.innerHTML = `
            <div id="chat-settings-modal" class="hidden" aria-hidden="true">
                <form id="chat-settings-form">
                    <div id="model-selection"></div>
                    <input type="range" id="temperature-slider" name="temperature" min="0" max="2" step="0.1" value="0.7">
                    <span id="temperature-value">0.7</span>
                    <input type="number" id="max-tokens" name="max_tokens" min="1" max="8000" value="1024">
                    <input type="checkbox" id="streaming-enabled" name="streaming_enabled" checked>
                    <div id="model-info" class="hidden">
                        <span id="model-provider">-</span>
                        <span id="model-engine">-</span>
                        <span id="model-description">-</span>
                    </div>
                </form>
                <button id="close-chat-settings">Close</button>
                <button id="cancel-settings">Cancel</button>
                <button id="reset-settings">Reset</button>
                <button id="save-settings">Save</button>
                <button id="retry-settings">Retry</button>
                <div id="settings-loading" class="hidden">Loading...</div>
                <div id="settings-error" class="hidden">Error</div>
            </div>
            <button id="open-chat-settings">Open Settings</button>
            <div id="settings-success-toast" class="hidden">Success</div>
            <div id="settings-error-toast" class="hidden">
                <span id="error-toast-message">Error</span>
            </div>
        `;
        
        document.body.appendChild(container);

        // Mock successful API responses
        mockFetch.mockImplementation((url) => {
            if (url.includes('/api/chat/settings') && !url.includes('/reset')) {
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
                        models: [
                            {
                                id: 'claude-sonnet-4-20250514',
                                name: 'Claude Sonnet 4',
                                provider: 'anthropic',
                                description: 'Advanced AI model',
                                available: true,
                                engine_type: 'playcanvas'
                            },
                            {
                                id: 'gpt-4',
                                name: 'GPT-4',
                                provider: 'openai',
                                description: 'OpenAI GPT-4',
                                available: true,
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

    describe('Initialization', () => {
        test('should initialize with default options', () => {
            component = new ChatSettingsComponent();
            
            expect(component.modalId).toBe('chat-settings-modal');
            expect(component.triggerId).toBe('open-chat-settings');
            expect(component.modal).toBeTruthy();
            expect(component.trigger).toBeTruthy();
        });

        test('should initialize with custom options', () => {
            component = new ChatSettingsComponent({
                modalId: 'custom-modal',
                autoLoad: false,
                showToasts: false
            });
            
            expect(component.autoLoad).toBe(false);
            expect(component.showToasts).toBe(false);
        });

        test('should handle missing modal element', () => {
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            
            component = new ChatSettingsComponent({ modalId: 'non-existent' });
            
            expect(consoleSpy).toHaveBeenCalledWith('Chat Settings modal not found:', 'non-existent');
            consoleSpy.mockRestore();
        });
    });

    describe('Modal Operations', () => {
        beforeEach(() => {
            component = new ChatSettingsComponent({ autoLoad: false });
        });

        test('should open modal', async () => {
            await component.open();
            
            expect(component.modal.classList.contains('hidden')).toBe(false);
            expect(component.modal.getAttribute('aria-hidden')).toBe('false');
            expect(document.body.style.overflow).toBe('hidden');
        });

        test('should close modal', () => {
            component.open();
            component.close();
            
            expect(component.modal.classList.contains('hidden')).toBe(true);
            expect(component.modal.getAttribute('aria-hidden')).toBe('true');
            expect(document.body.style.overflow).toBe('');
        });

        test('should handle close with unsaved changes', () => {
            component.hasUnsavedChanges = true;
            window.confirm.mockReturnValue(false);
            
            component.handleClose();
            
            expect(window.confirm).toHaveBeenCalledWith('You have unsaved changes. Are you sure you want to close without saving?');
            expect(component.modal.classList.contains('hidden')).toBe(true); // Should still be hidden initially
        });
    });

    describe('Settings Loading', () => {
        beforeEach(() => {
            component = new ChatSettingsComponent({ autoLoad: false });
        });

        test('should load settings successfully', async () => {
            await component.loadSettings();
            
            expect(mockFetch).toHaveBeenCalledTimes(2);
            expect(component.currentSettings).toEqual({
                ai_model: 'claude-sonnet-4-20250514',
                temperature: 0.7,
                max_tokens: 1024,
                streaming_enabled: true
            });
            expect(component.availableModels).toHaveLength(2);
        });

        test('should handle loading errors', async () => {
            mockFetch.mockRejectedValue(new Error('Network error'));
            
            await component.loadSettings();
            
            expect(component.errorElement.classList.contains('hidden')).toBe(false);
        });

        test('should not load if already loading', async () => {
            component.isLoading = true;
            
            await component.loadSettings();
            
            expect(mockFetch).not.toHaveBeenCalled();
        });
    });

    describe('Form Operations', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should populate form with current settings', () => {
            component.populateForm();
            
            expect(component.temperatureSlider.value).toBe('0.7');
            expect(component.temperatureValue.textContent).toBe('0.7');
            expect(component.maxTokensInput.value).toBe('1024');
            expect(component.streamingCheckbox.checked).toBe(true);
        });

        test('should get form data correctly', () => {
            const formData = component.getFormData();
            
            expect(formData).toEqual({
                ai_model: 'claude-sonnet-4-20250514',
                temperature: 0.7,
                max_tokens: 1024,
                streaming_enabled: true
            });
        });

        test('should handle temperature change', () => {
            const event = { target: { value: '1.2' } };
            
            component.handleTemperatureChange(event);
            
            expect(component.temperatureValue.textContent).toBe('1.2');
            expect(component.hasUnsavedChanges).toBe(true);
        });

        test('should handle model change', () => {
            const mockCallback = jest.fn();
            component.onModelChanged = mockCallback;
            
            component.handleModelChange('gpt-4');
            
            expect(component.hasUnsavedChanges).toBe(true);
            expect(mockCallback).toHaveBeenCalledWith('gpt-4', expect.any(Object));
        });
    });

    describe('Settings Validation', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should validate valid settings', () => {
            const settings = {
                ai_model: 'claude-sonnet-4-20250514',
                temperature: 0.7,
                max_tokens: 1024,
                streaming_enabled: true
            };
            
            const result = component.validateSettings(settings);
            
            expect(result.valid).toBe(true);
        });

        test('should reject missing AI model', () => {
            const settings = {
                temperature: 0.7,
                max_tokens: 1024,
                streaming_enabled: true
            };
            
            const result = component.validateSettings(settings);
            
            expect(result.valid).toBe(false);
            expect(result.message).toBe('Please select an AI model');
        });

        test('should reject invalid temperature', () => {
            const settings = {
                ai_model: 'claude-sonnet-4-20250514',
                temperature: 3.0, // Too high
                max_tokens: 1024,
                streaming_enabled: true
            };
            
            const result = component.validateSettings(settings);
            
            expect(result.valid).toBe(false);
            expect(result.message).toBe('Temperature must be between 0 and 2');
        });

        test('should reject invalid max tokens', () => {
            const settings = {
                ai_model: 'claude-sonnet-4-20250514',
                temperature: 0.7,
                max_tokens: 10000, // Too high
                streaming_enabled: true
            };
            
            const result = component.validateSettings(settings);
            
            expect(result.valid).toBe(false);
            expect(result.message).toBe('Max tokens must be between 1 and 8000');
        });

        test('should reject unavailable model', () => {
            // Add an unavailable model
            component.availableModels.push({
                id: 'unavailable-model',
                name: 'Unavailable Model',
                available: false
            });

            const settings = {
                ai_model: 'unavailable-model',
                temperature: 0.7,
                max_tokens: 1024,
                streaming_enabled: true
            };
            
            const result = component.validateSettings(settings);
            
            expect(result.valid).toBe(false);
            expect(result.message).toBe('Selected AI model is not available');
        });
    });

    describe('Settings Saving', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should save settings successfully', async () => {
            mockFetch.mockImplementationOnce(() => Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: true,
                    settings: {
                        ai_model: 'gpt-4',
                        temperature: 0.8,
                        max_tokens: 2048,
                        streaming_enabled: false
                    }
                })
            }));

            // Simulate form submission
            const event = { preventDefault: jest.fn() };
            await component.handleSubmit(event);
            
            expect(event.preventDefault).toHaveBeenCalled();
            expect(component.hasUnsavedChanges).toBe(false);
        });

        test('should handle save errors', async () => {
            mockFetch.mockImplementationOnce(() => Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: false,
                    message: 'Validation failed'
                })
            }));

            const event = { preventDefault: jest.fn() };
            await component.handleSubmit(event);
            
            expect(component.hasUnsavedChanges).toBe(true); // Should remain true on error
        });

        test('should not save if already saving', async () => {
            component.isSaving = true;
            
            const event = { preventDefault: jest.fn() };
            await component.handleSubmit(event);
            
            expect(event.preventDefault).toHaveBeenCalled();
            // Should not make additional fetch calls beyond the initial load
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    describe('Settings Reset', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should reset settings successfully', async () => {
            mockFetch.mockImplementationOnce(() => Promise.resolve({
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
            }));

            await component.handleReset();
            
            expect(window.confirm).toHaveBeenCalledWith('Are you sure you want to reset all settings to defaults?');
            expect(mockFetch).toHaveBeenCalledWith('/api/chat/settings/reset', expect.any(Object));
        });

        test('should not reset if user cancels', async () => {
            window.confirm.mockReturnValue(false);
            
            await component.handleReset();
            
            expect(window.confirm).toHaveBeenCalled();
            // Should not make reset API call (only initial load calls)
            expect(mockFetch).toHaveBeenCalledTimes(2);
        });
    });

    describe('Model Information', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should update model info when model changes', () => {
            component.updateModelInfo('gpt-4');
            
            expect(component.modelProvider.textContent).toBe('openai');
            expect(component.modelEngine.textContent).toBe('General');
            expect(component.modelDescription.textContent).toBe('OpenAI GPT-4');
            expect(component.modelInfo.classList.contains('hidden')).toBe(false);
        });

        test('should hide model info for unknown model', () => {
            component.updateModelInfo('unknown-model');
            
            expect(component.modelInfo.classList.contains('hidden')).toBe(true);
        });
    });

    describe('Public API', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should return current settings', () => {
            const settings = component.getCurrentSettings();
            
            expect(settings).toEqual({
                ai_model: 'claude-sonnet-4-20250514',
                temperature: 0.7,
                max_tokens: 1024,
                streaming_enabled: true
            });
        });

        test('should return available models', () => {
            const models = component.getAvailableModels();
            
            expect(models).toHaveLength(2);
            expect(models[0].id).toBe('claude-sonnet-4-20250514');
        });

        test('should return selected model', () => {
            const model = component.getSelectedModel();
            
            expect(model.id).toBe('claude-sonnet-4-20250514');
            expect(model.name).toBe('Claude Sonnet 4');
        });

        test('should check if modal is open', () => {
            expect(component.isOpen()).toBe(true); // Modal starts hidden
            
            component.open();
            expect(component.isOpen()).toBe(true);
        });

        test('should check for unsaved changes', () => {
            expect(component.hasChanges()).toBe(false);
            
            component.markAsChanged();
            expect(component.hasChanges()).toBe(true);
        });

        test('should set settings programmatically', () => {
            const newSettings = {
                temperature: 1.5,
                max_tokens: 2048
            };
            
            component.setSettings(newSettings);
            
            expect(component.currentSettings.temperature).toBe(1.5);
            expect(component.currentSettings.max_tokens).toBe(2048);
            expect(component.temperatureSlider.value).toBe('1.5');
        });
    });

    describe('Event Handling', () => {
        beforeEach(async () => {
            component = new ChatSettingsComponent({ autoLoad: false });
            await component.loadSettings();
        });

        test('should dispatch custom events', () => {
            const eventSpy = jest.fn();
            component.modal.addEventListener('settingsChanged', eventSpy);
            
            component.markAsChanged();
            
            expect(eventSpy).toHaveBeenCalledWith(expect.objectContaining({
                detail: expect.objectContaining({
                    settings: expect.any(Object)
                })
            }));
        });

        test('should handle keyboard events', () => {
            component.open();
            
            const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' });
            document.dispatchEvent(escapeEvent);
            
            expect(component.modal.classList.contains('hidden')).toBe(true);
        });
    });

    describe('Error Handling', () => {
        beforeEach(() => {
            component = new ChatSettingsComponent({ autoLoad: false });
        });

        test('should handle network errors gracefully', async () => {
            mockFetch.mockRejectedValue(new Error('Network error'));
            
            await component.loadSettings();
            
            expect(component.errorElement.classList.contains('hidden')).toBe(false);
        });

        test('should handle API errors gracefully', async () => {
            mockFetch.mockImplementation(() => Promise.resolve({
                ok: false,
                status: 500,
                statusText: 'Internal Server Error'
            }));
            
            await component.loadSettings();
            
            expect(component.errorElement.classList.contains('hidden')).toBe(false);
        });
    });
});