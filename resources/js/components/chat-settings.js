/**
 * Chat Settings Component
 * 
 * A reusable component for managing chat settings including AI model selection,
 * temperature, max tokens, and streaming options.
 */
export class ChatSettingsComponent {
    constructor(options = {}) {
        this.modalId = options.modalId || 'chat-settings-modal';
        this.triggerId = options.triggerId || 'open-chat-settings';
        
        this.modal = document.getElementById(this.modalId);
        this.trigger = document.getElementById(this.triggerId);
        
        if (!this.modal) {
            console.error('Chat Settings modal not found:', this.modalId);
            return;
        }

        // Configuration
        this.autoLoad = options.autoLoad !== false;
        this.validateOnChange = options.validateOnChange !== false;
        this.showToasts = options.showToasts !== false;

        // State
        this.currentSettings = {};
        this.availableModels = [];
        this.isLoading = false;
        this.isSaving = false;
        this.hasUnsavedChanges = false;

        // Event callbacks
        this.onSettingsSaved = options.onSettingsSaved || null;
        this.onSettingsChanged = options.onSettingsChanged || null;
        this.onError = options.onError || null;
        this.onModelChanged = options.onModelChanged || null;

        this.initializeElements();
        this.initializeEventListeners();

        if (this.autoLoad) {
            this.loadSettings();
        }
    }

    initializeElements() {
        // Form elements
        this.form = document.getElementById('chat-settings-form');
        this.modelSelection = document.getElementById('model-selection');
        this.temperatureSlider = document.getElementById('temperature-slider');
        this.temperatureValue = document.getElementById('temperature-value');
        this.maxTokensInput = document.getElementById('max-tokens');
        this.streamingCheckbox = document.getElementById('streaming-enabled');
        this.modelInfo = document.getElementById('model-info');

        // State elements
        this.loadingElement = document.getElementById('settings-loading');
        this.errorElement = document.getElementById('settings-error');
        this.successToast = document.getElementById('settings-success-toast');
        this.errorToast = document.getElementById('settings-error-toast');
        
        // Button elements
        this.closeButton = document.getElementById('close-chat-settings');
        this.cancelButton = document.getElementById('cancel-settings');
        this.resetButton = document.getElementById('reset-settings');
        this.saveButton = document.getElementById('save-settings');
        this.retryButton = document.getElementById('retry-settings');

        // Model info elements
        this.modelProvider = document.getElementById('model-provider');
        this.modelEngine = document.getElementById('model-engine');
        this.modelDescription = document.getElementById('model-description');
    }

    initializeEventListeners() {
        // Modal controls
        this.trigger?.addEventListener('click', () => this.open());
        this.closeButton?.addEventListener('click', () => this.handleClose());
        this.cancelButton?.addEventListener('click', () => this.handleClose());
        
        // Form controls
        this.resetButton?.addEventListener('click', () => this.handleReset());
        this.form?.addEventListener('submit', (e) => this.handleSubmit(e));
        this.retryButton?.addEventListener('click', () => this.loadSettings());
        
        // Input change tracking
        this.temperatureSlider?.addEventListener('input', (e) => this.handleTemperatureChange(e));
        this.maxTokensInput?.addEventListener('input', () => this.markAsChanged());
        this.streamingCheckbox?.addEventListener('change', () => this.markAsChanged());

        // Model selection change
        this.modelSelection?.addEventListener('change', (e) => {
            if (e.target.name === 'ai_model') {
                this.handleModelChange(e.target.value);
            }
        });

        // Close modal on outside click
        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.handleClose();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.classList.contains('hidden')) {
                this.handleClose();
            }
        });

        // Prevent accidental navigation with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }

    async open() {
        this.modal.classList.remove('hidden');
        this.modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        
        // Focus management
        setTimeout(() => {
            const firstFocusable = this.modal.querySelector('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])');
            firstFocusable?.focus();
        }, 100);

        // Load settings if not already loaded
        if (!this.availableModels.length) {
            await this.loadSettings();
        }

        // Dispatch open event
        this.dispatchEvent('settingsOpened');
    }

    handleClose() {
        if (this.hasUnsavedChanges) {
            if (!confirm('You have unsaved changes. Are you sure you want to close without saving?')) {
                return;
            }
        }

        this.close();
    }

    close() {
        this.modal.classList.add('hidden');
        this.modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = ''; // Restore scrolling
        
        // Return focus to trigger
        this.trigger?.focus();

        // Reset unsaved changes flag
        this.hasUnsavedChanges = false;

        // Dispatch close event
        this.dispatchEvent('settingsClosed');
    }

    showState(state) {
        const states = ['loading', 'form', 'error'];
        states.forEach(s => {
            const element = s === 'form' ? this.form : document.getElementById(`settings-${s}`);
            if (element) {
                element.classList.toggle('hidden', s !== state);
            }
        });
    }

    async loadSettings() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showState('loading');

        try {
            // Load settings and models in parallel
            const [settingsResponse, modelsResponse] = await Promise.all([
                this.fetchWithAuth('/api/chat/settings'),
                this.fetchWithAuth('/api/chat/models')
            ]);

            const settingsData = await settingsResponse.json();
            const modelsData = await modelsResponse.json();

            if (settingsData.success && modelsData.success) {
                this.currentSettings = settingsData.settings;
                this.availableModels = modelsData.models;
                
                this.renderModelSelection();
                this.populateForm();
                this.showState('form');

                // Dispatch loaded event
                this.dispatchEvent('settingsLoaded', {
                    settings: this.currentSettings,
                    models: this.availableModels
                });
            } else {
                throw new Error(settingsData.message || modelsData.message || 'Failed to load data');
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showState('error');
            
            if (this.onError) {
                this.onError(error);
            }

            this.dispatchEvent('settingsLoadError', { error });
        } finally {
            this.isLoading = false;
        }
    }

    renderModelSelection() {
        if (!this.modelSelection || !this.availableModels.length) return;

        this.modelSelection.innerHTML = this.availableModels.map(model => {
            const isSelected = this.currentSettings.ai_model === model.id;
            const availabilityClass = model.available ? 'border-gray-600' : 'border-gray-700 opacity-50';
            const selectedClass = isSelected ? 'border-indigo-500 bg-indigo-900' : '';
            const modelIdSafe = model.id.replace(/[^a-zA-Z0-9]/g, '_');

            return `
                <div class="model-option">
                    <input type="radio" 
                           name="ai_model" 
                           value="${this.escapeHtml(model.id)}"
                           id="model_${modelIdSafe}"
                           class="sr-only peer"
                           ${isSelected ? 'checked' : ''}
                           ${!model.available ? 'disabled' : ''}
                           aria-describedby="model_${modelIdSafe}_desc">
                    <label for="model_${modelIdSafe}"
                           class="flex items-center p-4 bg-gray-700 rounded-lg border-2 ${availabilityClass} ${selectedClass} cursor-pointer hover:bg-gray-600 peer-checked:border-indigo-500 peer-checked:bg-indigo-900 transition-all ${!model.available ? 'cursor-not-allowed' : ''}">
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <div class="model-name">${this.escapeHtml(model.name)}</div>
                                ${model.engine_type ? `<span class="model-engine-tag">${this.escapeHtml(model.engine_type)}</span>` : ''}
                            </div>
                            <div id="model_${modelIdSafe}_desc" class="model-description">${this.escapeHtml(model.description)}</div>
                            <div class="flex items-center justify-between mt-2">
                                <div class="model-provider">Provider: ${this.escapeHtml(model.provider)}</div>
                                <div class="model-availability ${model.available ? 'available' : 'unavailable'}">
                                    ${model.available ? 'Available' : 'Not available'}
                                </div>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="model-radio-indicator"></div>
                        </div>
                    </label>
                </div>
            `;
        }).join('');
    }

    populateForm() {
        if (!this.currentSettings) return;

        // Set temperature
        if (this.temperatureSlider && this.temperatureValue) {
            const temperature = this.currentSettings.temperature || 0.7;
            this.temperatureSlider.value = temperature;
            this.temperatureValue.textContent = temperature;
        }

        // Set max tokens
        if (this.maxTokensInput) {
            this.maxTokensInput.value = this.currentSettings.max_tokens || 1024;
        }

        // Set streaming
        if (this.streamingCheckbox) {
            this.streamingCheckbox.checked = this.currentSettings.streaming_enabled !== false;
        }

        // Update model info
        this.updateModelInfo(this.currentSettings.ai_model);

        // Reset unsaved changes flag
        this.hasUnsavedChanges = false;
    }

    handleTemperatureChange(e) {
        this.temperatureValue.textContent = e.target.value;
        this.markAsChanged();
    }

    handleModelChange(modelId) {
        this.updateModelInfo(modelId);
        this.markAsChanged();

        if (this.onModelChanged) {
            const model = this.availableModels.find(m => m.id === modelId);
            this.onModelChanged(modelId, model);
        }

        this.dispatchEvent('modelChanged', { modelId, model: this.availableModels.find(m => m.id === modelId) });
    }

    updateModelInfo(modelId) {
        const model = this.availableModels.find(m => m.id === modelId);
        
        if (model && this.modelInfo) {
            if (this.modelProvider) this.modelProvider.textContent = model.provider || '-';
            if (this.modelEngine) this.modelEngine.textContent = model.engine_type || 'General';
            if (this.modelDescription) this.modelDescription.textContent = model.description || '-';
            this.modelInfo.classList.remove('hidden');
        } else if (this.modelInfo) {
            this.modelInfo.classList.add('hidden');
        }
    }

    markAsChanged() {
        this.hasUnsavedChanges = true;
        
        if (this.onSettingsChanged) {
            this.onSettingsChanged(this.getFormData());
        }

        this.dispatchEvent('settingsChanged', { settings: this.getFormData() });
    }

    getFormData() {
        if (!this.form) return {};

        const formData = new FormData(this.form);
        return {
            ai_model: formData.get('ai_model'),
            temperature: parseFloat(formData.get('temperature')),
            max_tokens: parseInt(formData.get('max_tokens')),
            streaming_enabled: formData.get('streaming_enabled') === 'on',
        };
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        if (this.isSaving) return;
        
        this.isSaving = true;
        const originalText = this.saveButton.textContent;
        this.saveButton.textContent = 'Saving...';
        this.saveButton.disabled = true;

        try {
            const settings = this.getFormData();

            // Validate settings
            const validation = this.validateSettings(settings);
            if (!validation.valid) {
                throw new Error(validation.message);
            }

            const response = await this.fetchWithAuth('/api/chat/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(settings)
            });

            const data = await response.json();

            if (data.success) {
                this.currentSettings = data.settings;
                this.hasUnsavedChanges = false;
                
                if (this.showToasts) {
                    this.showSuccessToast('Settings saved successfully!');
                }
                
                if (this.onSettingsSaved) {
                    this.onSettingsSaved(this.currentSettings);
                }
                
                this.dispatchEvent('settingsSaved', { settings: this.currentSettings });
                
                // Close modal after a short delay
                setTimeout(() => this.close(), 1000);
            } else {
                throw new Error(data.message || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            
            if (this.showToasts) {
                this.showErrorToast(error.message || 'Failed to save settings');
            }
            
            if (this.onError) {
                this.onError(error);
            }

            this.dispatchEvent('settingsSaveError', { error });
        } finally {
            this.isSaving = false;
            this.saveButton.textContent = originalText;
            this.saveButton.disabled = false;
        }
    }

    async handleReset() {
        if (!confirm('Are you sure you want to reset all settings to defaults?')) {
            return;
        }

        try {
            const response = await this.fetchWithAuth('/api/chat/settings/reset', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                this.currentSettings = data.settings;
                this.populateForm();
                
                if (this.showToasts) {
                    this.showSuccessToast('Settings reset to defaults');
                }

                this.dispatchEvent('settingsReset', { settings: this.currentSettings });
            } else {
                throw new Error(data.message || 'Failed to reset settings');
            }
        } catch (error) {
            console.error('Error resetting settings:', error);
            
            if (this.showToasts) {
                this.showErrorToast('Failed to reset settings');
            }

            if (this.onError) {
                this.onError(error);
            }
        }
    }

    validateSettings(settings) {
        // Validate AI model
        if (!settings.ai_model) {
            return { valid: false, message: 'Please select an AI model' };
        }

        const model = this.availableModels.find(m => m.id === settings.ai_model);
        if (!model) {
            return { valid: false, message: 'Invalid AI model selected' };
        }

        if (!model.available) {
            return { valid: false, message: 'Selected AI model is not available' };
        }

        // Validate temperature
        if (isNaN(settings.temperature) || settings.temperature < 0 || settings.temperature > 2) {
            return { valid: false, message: 'Temperature must be between 0 and 2' };
        }

        // Validate max tokens
        if (isNaN(settings.max_tokens) || settings.max_tokens < 1 || settings.max_tokens > 8000) {
            return { valid: false, message: 'Max tokens must be between 1 and 8000' };
        }

        return { valid: true };
    }

    showSuccessToast(message) {
        if (this.successToast) {
            const messageElement = this.successToast.querySelector('span');
            if (messageElement) messageElement.textContent = message;
            
            this.successToast.classList.remove('hidden');
            setTimeout(() => {
                this.successToast.classList.add('hidden');
            }, 3000);
        }
    }

    showErrorToast(message) {
        if (this.errorToast) {
            const messageElement = document.getElementById('error-toast-message');
            if (messageElement) messageElement.textContent = message;
            
            this.errorToast.classList.remove('hidden');
            setTimeout(() => {
                this.errorToast.classList.add('hidden');
            }, 5000);
        }
    }

    async fetchWithAuth(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken(),
                ...options.headers
            },
            credentials: 'same-origin'
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response;
    }

    escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, { detail });
        this.modal.dispatchEvent(event);
    }

    // Public API methods
    getCurrentSettings() {
        return { ...this.currentSettings };
    }

    getAvailableModels() {
        return [...this.availableModels];
    }

    refresh() {
        return this.loadSettings();
    }

    isOpen() {
        return !this.modal.classList.contains('hidden');
    }

    hasChanges() {
        return this.hasUnsavedChanges;
    }

    setSettings(settings) {
        this.currentSettings = { ...this.currentSettings, ...settings };
        this.populateForm();
    }

    getSelectedModel() {
        return this.availableModels.find(m => m.id === this.currentSettings.ai_model);
    }

    destroy() {
        // Clean up event listeners and restore body overflow
        document.body.style.overflow = '';
        
        // Remove beforeunload listener
        window.removeEventListener('beforeunload', this.handleBeforeUnload);
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const chatSettingsModal = document.getElementById('chat-settings-modal');
    if (chatSettingsModal && !window.chatSettingsComponent) {
        window.chatSettingsComponent = new ChatSettingsComponent();
    }
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ChatSettingsComponent };
}