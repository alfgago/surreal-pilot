/**
 * Simple Chat Settings Component Tests
 * 
 * Basic functionality tests for the Chat Settings component.
 */

// Mock fetch
global.fetch = jest.fn();

// Mock DOM methods
global.confirm = jest.fn(() => true);
global.alert = jest.fn();

// Mock CSRF token
document.head.innerHTML = '<meta name="csrf-token" content="test-token">';

describe('Chat Settings Component - Simple Tests', () => {
    let mockContainer;

    beforeEach(() => {
        // Reset mocks
        fetch.mockClear();
        confirm.mockClear();
        alert.mockClear();

        // Create minimal DOM structure
        mockContainer = document.createElement('div');
        mockContainer.innerHTML = `
            <div id="chat-settings-modal" class="hidden">
                <form id="chat-settings-form">
                    <div id="model-selection"></div>
                    <input type="range" id="temperature-slider" name="temperature" value="0.7">
                    <span id="temperature-value">0.7</span>
                    <input type="number" id="max-tokens" name="max_tokens" value="1024">
                    <input type="checkbox" id="streaming-enabled" name="streaming_enabled" checked>
                    <div id="model-info" class="hidden">
                        <span id="model-provider">-</span>
                        <span id="model-engine">-</span>
                        <span id="model-description">-</span>
                    </div>
                </form>
                <button id="close-chat-settings">Close</button>
                <button id="save-settings">Save</button>
                <div id="settings-loading" class="hidden">Loading</div>
                <div id="settings-error" class="hidden">Error</div>
            </div>
            <button id="open-chat-settings">Open</button>
            <div id="settings-success-toast" class="hidden">Success</div>
            <div id="settings-error-toast" class="hidden">
                <span id="error-toast-message">Error</span>
            </div>
        `;
        document.body.appendChild(mockContainer);

        // Mock successful API responses
        fetch.mockImplementation((url) => {
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
                                name: 'PlayCanvas Model',
                                provider: 'anthropic',
                                description: 'Optimized for PlayCanvas',
                                available: true,
                                engine_type: 'playcanvas'
                            }
                        ]
                    })
                });
            }
            return Promise.reject(new Error('Not found'));
        });
    });

    afterEach(() => {
        document.body.removeChild(mockContainer);
    });

    test('should initialize component', () => {
        // Import and create component
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        expect(component).toBeDefined();
        expect(component.modal).toBeTruthy();
        expect(component.modalId).toBe('chat-settings-modal');
    });

    test('should open and close modal', () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        // Open modal
        component.open();
        expect(component.modal.classList.contains('hidden')).toBe(false);

        // Close modal
        component.close();
        expect(component.modal.classList.contains('hidden')).toBe(true);
    });

    test('should load settings from API', async () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        await component.loadSettings();

        expect(fetch).toHaveBeenCalledTimes(2); // settings + models
        expect(component.currentSettings).toEqual({
            ai_model: 'claude-sonnet-4-20250514',
            temperature: 0.7,
            max_tokens: 1024,
            streaming_enabled: true
        });
        expect(component.availableModels).toHaveLength(1);
    });

    test('should handle temperature slider change', () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        const slider = document.getElementById('temperature-slider');
        const valueDisplay = document.getElementById('temperature-value');

        // Simulate slider change
        slider.value = '1.2';
        component.handleTemperatureChange({ target: { value: '1.2' } });

        expect(valueDisplay.textContent).toBe('1.2');
        expect(component.hasUnsavedChanges).toBe(true);
    });

    test('should validate settings correctly', async () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });
        
        // Load models first
        await component.loadSettings();

        // Valid settings
        const validSettings = {
            ai_model: 'claude-sonnet-4-20250514',
            temperature: 0.7,
            max_tokens: 1024,
            streaming_enabled: true
        };
        expect(component.validateSettings(validSettings).valid).toBe(true);

        // Invalid temperature
        const invalidTemp = { ...validSettings, temperature: 3.0 };
        expect(component.validateSettings(invalidTemp).valid).toBe(false);

        // Invalid max tokens
        const invalidTokens = { ...validSettings, max_tokens: 10000 };
        expect(component.validateSettings(invalidTokens).valid).toBe(false);

        // Missing model
        const noModel = { ...validSettings, ai_model: '' };
        expect(component.validateSettings(noModel).valid).toBe(false);
    });

    test('should get form data correctly', () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        // Set form values
        document.getElementById('temperature-slider').value = '0.8';
        document.getElementById('max-tokens').value = '2048';
        document.getElementById('streaming-enabled').checked = false;

        // Create a radio button for model selection
        const modelRadio = document.createElement('input');
        modelRadio.type = 'radio';
        modelRadio.name = 'ai_model';
        modelRadio.value = 'gpt-4';
        modelRadio.checked = true;
        document.getElementById('model-selection').appendChild(modelRadio);

        const formData = component.getFormData();

        expect(formData).toEqual({
            ai_model: 'gpt-4',
            temperature: 0.8,
            max_tokens: 2048,
            streaming_enabled: false
        });
    });

    test('should handle API errors', async () => {
        fetch.mockRejectedValue(new Error('Network error'));

        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        await component.loadSettings();

        // Should show error state
        const errorElement = document.getElementById('settings-error');
        expect(errorElement.classList.contains('hidden')).toBe(false);
    });

    test('should save settings successfully', async () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        // Load initial settings
        await component.loadSettings();

        // Mock save response
        fetch.mockImplementationOnce(() => Promise.resolve({
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

    test('should reset settings to defaults', async () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        // Mock reset response
        fetch.mockImplementationOnce(() => Promise.resolve({
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

        expect(confirm).toHaveBeenCalledWith('Are you sure you want to reset all settings to defaults?');
        expect(fetch).toHaveBeenCalledWith('/api/chat/settings/reset', expect.any(Object));
    });

    test('should update model info when model changes', async () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        // Load models
        await component.loadSettings();

        // Update model info
        component.updateModelInfo('claude-sonnet-4-20250514');

        const providerElement = document.getElementById('model-provider');
        const engineElement = document.getElementById('model-engine');
        const descriptionElement = document.getElementById('model-description');
        const infoPanel = document.getElementById('model-info');

        expect(providerElement.textContent).toBe('anthropic');
        expect(engineElement.textContent).toBe('playcanvas');
        expect(descriptionElement.textContent).toBe('Optimized for PlayCanvas');
        expect(infoPanel.classList.contains('hidden')).toBe(false);
    });

    test('should handle unsaved changes warning', () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        // Mark as changed
        component.markAsChanged();
        expect(component.hasUnsavedChanges).toBe(true);

        // Try to close with changes
        confirm.mockReturnValue(false);
        component.handleClose();

        expect(confirm).toHaveBeenCalledWith('You have unsaved changes. Are you sure you want to close without saving?');
    });

    test('should provide public API methods', async () => {
        const { ChatSettingsComponent } = require('../../resources/js/components/chat-settings.js');
        const component = new ChatSettingsComponent({ autoLoad: false });

        await component.loadSettings();

        // Test public methods
        expect(component.getCurrentSettings()).toEqual(component.currentSettings);
        expect(component.getAvailableModels()).toEqual(component.availableModels);
        expect(component.isOpen()).toBe(true); // Modal starts hidden
        expect(component.hasChanges()).toBe(false);

        // Test setting settings
        component.setSettings({ temperature: 1.5 });
        expect(component.currentSettings.temperature).toBe(1.5);

        // Test getting selected model
        const selectedModel = component.getSelectedModel();
        expect(selectedModel.id).toBe('claude-sonnet-4-20250514');
    });
});