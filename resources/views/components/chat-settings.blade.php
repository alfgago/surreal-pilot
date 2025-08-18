@props([
    'modalId' => 'chat-settings-modal',
    'triggerId' => 'open-chat-settings',
    'containerClass' => 'fixed inset-0 bg-black bg-opacity-50 z-50 hidden',
    'modalClass' => 'bg-gray-800 rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto',
    'headerClass' => 'p-6 border-b border-gray-700',
    'bodyClass' => 'p-6 space-y-6',
    'footerClass' => 'p-6 border-t border-gray-700 flex justify-end space-x-3'
])

<!-- Chat Settings Modal -->
<div id="{{ $modalId }}" class="{{ $containerClass }}" role="dialog" aria-labelledby="chat-settings-title" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="{{ $modalClass }}" role="document">
            <!-- Header -->
            <div class="{{ $headerClass }}">
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
            <div class="{{ $bodyClass }}">
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
            <div class="{{ $footerClass }}">
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

<!-- Success Toast -->
<div id="settings-success-toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden">
    <div class="flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span>Settings saved successfully!</span>
    </div>
</div>

<!-- Error Toast -->
<div id="settings-error-toast" class="fixed top-4 right-4 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 hidden">
    <div class="flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span id="error-toast-message">Failed to save settings</span>
    </div>
</div>

@push('scripts')
<script>
class ChatSettingsComponent {
    constructor(options = {}) {
        this.modalId = options.modalId || 'chat-settings-modal';
        this.triggerId = options.triggerId || 'open-chat-settings';
        
        this.modal = document.getElementById(this.modalId);
        this.trigger = document.getElementById(this.triggerId);
        
        if (!this.modal) {
            console.error('Chat Settings modal not found:', this.modalId);
            return;
        }

        // State
        this.currentSettings = {};
        this.availableModels = [];
        this.isLoading = false;
        this.isSaving = false;

        // Event callbacks
        this.onSettingsSaved = options.onSettingsSaved || null;
        this.onError = options.onError || null;

        this.initializeElements();
        this.initializeEventListeners();
    }

    initializeElements() {
        this.form = document.getElementById('chat-settings-form');
        this.loadingElement = document.getElementById('settings-loading');
        this.errorElement = document.getElementById('settings-error');
        this.modelSelection = document.getElementById('model-selection');
        this.temperatureSlider = document.getElementById('temperature-slider');
        this.temperatureValue = document.getElementById('temperature-value');
        this.maxTokensInput = document.getElementById('max-tokens');
        this.streamingCheckbox = document.getElementById('streaming-enabled');
        this.modelInfo = document.getElementById('model-info');
        this.successToast = document.getElementById('settings-success-toast');
        this.errorToast = document.getElementById('settings-error-toast');
        
        // Buttons
        this.closeButton = document.getElementById('close-chat-settings');
        this.cancelButton = document.getElementById('cancel-settings');
        this.resetButton = document.getElementById('reset-settings');
        this.saveButton = document.getElementById('save-settings');
        this.retryButton = document.getElementById('retry-settings');
    }

    initializeEventListeners() {
        // Modal controls
        this.trigger?.addEventListener('click', () => this.open());
        this.closeButton?.addEventListener('click', () => this.close());
        this.cancelButton?.addEventListener('click', () => this.close());
        
        // Form controls
        this.resetButton?.addEventListener('click', () => this.resetSettings());
        this.form?.addEventListener('submit', (e) => this.handleSubmit(e));
        this.retryButton?.addEventListener('click', () => this.loadSettings());
        
        // Temperature slider
        this.temperatureSlider?.addEventListener('input', (e) => {
            this.temperatureValue.textContent = e.target.value;
        });

        // Model selection change
        this.modelSelection?.addEventListener('change', (e) => {
            if (e.target.name === 'ai_model') {
                this.updateModelInfo(e.target.value);
            }
        });

        // Close modal on outside click
        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.classList.contains('hidden')) {
                this.close();
            }
        });
    }

    async open() {
        this.modal.classList.remove('hidden');
        this.modal.setAttribute('aria-hidden', 'false');
        
        // Focus the first focusable element
        setTimeout(() => {
            const firstFocusable = this.modal.querySelector('button, input, select, textarea');
            firstFocusable?.focus();
        }, 100);

        await this.loadSettings();
    }

    close() {
        this.modal.classList.add('hidden');
        this.modal.setAttribute('aria-hidden', 'true');
        
        // Return focus to trigger if it exists
        this.trigger?.focus();
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
                fetch('/api/chat/settings', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    credentials: 'same-origin'
                }),
                fetch('/api/chat/models', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    credentials: 'same-origin'
                })
            ]);

            if (!settingsResponse.ok || !modelsResponse.ok) {
                throw new Error('Failed to load settings or models');
            }

            const settingsData = await settingsResponse.json();
            const modelsData = await modelsResponse.json();

            if (settingsData.success && modelsData.success) {
                this.currentSettings = settingsData.settings;
                this.availableModels = modelsData.models;
                
                this.renderModelSelection();
                this.populateForm();
                this.showState('form');
            } else {
                throw new Error(settingsData.message || modelsData.message || 'Failed to load data');
            }
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showState('error');
            
            if (this.onError) {
                this.onError(error);
            }
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

            return `
                <div class="relative">
                    <input type="radio" 
                           name="ai_model" 
                           value="${model.id}"
                           id="model_${model.id.replace(/[^a-zA-Z0-9]/g, '_')}"
                           class="sr-only peer"
                           ${isSelected ? 'checked' : ''}
                           ${!model.available ? 'disabled' : ''}>
                    <label for="model_${model.id.replace(/[^a-zA-Z0-9]/g, '_')}"
                           class="flex items-center p-4 bg-gray-700 rounded-lg border-2 ${availabilityClass} ${selectedClass} cursor-pointer hover:bg-gray-600 peer-checked:border-indigo-500 peer-checked:bg-indigo-900 transition-all ${!model.available ? 'cursor-not-allowed' : ''}">
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <div class="font-medium text-white">${this.escapeHtml(model.name)}</div>
                                ${model.engine_type ? `<span class="text-xs px-2 py-1 bg-indigo-600 text-white rounded">${model.engine_type}</span>` : ''}
                            </div>
                            <div class="text-sm text-gray-400">${this.escapeHtml(model.description)}</div>
                            <div class="flex items-center justify-between mt-2">
                                <div class="text-xs text-gray-500">Provider: ${this.escapeHtml(model.provider)}</div>
                                ${!model.available ? '<div class="text-xs text-red-400">Not available</div>' : '<div class="text-xs text-green-400">Available</div>'}
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="w-4 h-4 border-2 border-gray-400 rounded-full peer-checked:border-indigo-500 peer-checked:bg-indigo-500"></div>
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
            this.temperatureSlider.value = this.currentSettings.temperature || 0.7;
            this.temperatureValue.textContent = this.currentSettings.temperature || 0.7;
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
    }

    updateModelInfo(modelId) {
        const model = this.availableModels.find(m => m.id === modelId);
        
        if (model && this.modelInfo) {
            document.getElementById('model-provider').textContent = model.provider || '-';
            document.getElementById('model-engine').textContent = model.engine_type || 'General';
            document.getElementById('model-description').textContent = model.description || '-';
            this.modelInfo.classList.remove('hidden');
        } else if (this.modelInfo) {
            this.modelInfo.classList.add('hidden');
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        if (this.isSaving) return;
        
        this.isSaving = true;
        const originalText = this.saveButton.textContent;
        this.saveButton.textContent = 'Saving...';
        this.saveButton.disabled = true;

        try {
            const formData = new FormData(this.form);
            const settings = {
                ai_model: formData.get('ai_model'),
                temperature: parseFloat(formData.get('temperature')),
                max_tokens: parseInt(formData.get('max_tokens')),
                streaming_enabled: formData.get('streaming_enabled') === 'on',
            };

            const response = await fetch('/api/chat/settings', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(settings)
            });

            const data = await response.json();

            if (data.success) {
                this.currentSettings = data.settings;
                this.showSuccessToast('Settings saved successfully!');
                
                if (this.onSettingsSaved) {
                    this.onSettingsSaved(this.currentSettings);
                }
                
                // Close modal after a short delay
                setTimeout(() => this.close(), 1000);
            } else {
                throw new Error(data.message || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showErrorToast(error.message || 'Failed to save settings');
            
            if (this.onError) {
                this.onError(error);
            }
        } finally {
            this.isSaving = false;
            this.saveButton.textContent = originalText;
            this.saveButton.disabled = false;
        }
    }

    async resetSettings() {
        if (!confirm('Are you sure you want to reset all settings to defaults?')) {
            return;
        }

        try {
            const response = await fetch('/api/chat/settings/reset', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.currentSettings = data.settings;
                this.populateForm();
                this.showSuccessToast('Settings reset to defaults');
            } else {
                throw new Error(data.message || 'Failed to reset settings');
            }
        } catch (error) {
            console.error('Error resetting settings:', error);
            this.showErrorToast('Failed to reset settings');
        }
    }

    showSuccessToast(message) {
        if (this.successToast) {
            this.successToast.querySelector('span').textContent = message;
            this.successToast.classList.remove('hidden');
            setTimeout(() => {
                this.successToast.classList.add('hidden');
            }, 3000);
        }
    }

    showErrorToast(message) {
        if (this.errorToast) {
            document.getElementById('error-toast-message').textContent = message;
            this.errorToast.classList.remove('hidden');
            setTimeout(() => {
                this.errorToast.classList.add('hidden');
            }, 5000);
        }
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

    // Public API methods
    getCurrentSettings() {
        return { ...this.currentSettings };
    }

    getAvailableModels() {
        return [...this.availableModels];
    }

    refresh() {
        this.loadSettings();
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const chatSettingsModal = document.getElementById('chat-settings-modal');
    if (chatSettingsModal && !window.chatSettingsComponent) {
        window.chatSettingsComponent = new ChatSettingsComponent();
    }
});
</script>
@endpush