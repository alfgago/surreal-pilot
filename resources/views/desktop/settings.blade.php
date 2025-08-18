@extends('desktop.layout')

@section('content')
<div class="container mx-auto px-6 py-8 max-w-4xl">
    <h1 class="text-3xl font-bold text-white mb-8">Settings</h1>
    
    <!-- Settings Form -->
    <div class="space-y-8">
        <!-- AI Provider Configuration -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h2 class="text-xl font-semibold text-white mb-4">AI Provider Configuration</h2>
            
            <div class="space-y-6">
                <!-- Preferred Provider -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Preferred Provider</label>
                    <select id="preferred-provider" class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="gemini">Google Gemini</option>
                        <option value="ollama">Ollama (Local)</option>
                    </select>
                </div>
                
                <!-- API Keys -->
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">OpenAI API Key</label>
                        <input type="password" id="openai-key" placeholder="sk-..." 
                               class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Anthropic API Key</label>
                        <input type="password" id="anthropic-key" placeholder="sk-ant-..." 
                               class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Google Gemini API Key</label>
                        <input type="password" id="gemini-key" placeholder="AI..." 
                               class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Ollama Status</label>
                        <div class="flex items-center space-x-2">
                            <div id="ollama-status-indicator" class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                            <span id="ollama-status-text" class="text-gray-300">Checking...</span>
                            <button id="test-ollama" class="text-blue-400 hover:text-blue-300 text-sm underline">Test</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SaaS Integration -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h2 class="text-xl font-semibold text-white mb-4">SaaS Integration</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">SaaS API URL</label>
                    <input type="url" id="saas-url" placeholder="https://api.surrealpilot.com" 
                           class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">SaaS API Token</label>
                    <input type="password" id="saas-token" placeholder="Your SaaS API token" 
                           class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-center space-x-4">
                    <button id="test-saas" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition duration-200">
                        Test Connection
                    </button>
                    <div id="saas-test-result" class="text-sm"></div>
                </div>
            </div>
        </div>
        
        <!-- Server Configuration -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h2 class="text-xl font-semibold text-white mb-4">Server Configuration</h2>
            
            <div class="space-y-4">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Current Port</label>
                        <div class="flex items-center space-x-2">
                            <input type="number" id="server-port" min="8000" max="9999" readonly
                                   class="bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span class="text-gray-400 text-sm">(Auto-detected)</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Server URL</label>
                        <input type="text" id="server-url" readonly
                               class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-gray-400">
                    </div>
                </div>
                
                <div class="bg-gray-700 rounded-md p-4">
                    <h3 class="text-sm font-medium text-gray-300 mb-2">UE Plugin Configuration</h3>
                    <p class="text-sm text-gray-400 mb-2">
                        The Unreal Engine plugin should connect to this URL. If the port changes, 
                        the plugin will automatically read the new port from the config file.
                    </p>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-500">Config file:</span>
                        <code class="text-xs bg-gray-800 px-2 py-1 rounded text-green-400" id="config-path">~/.surrealpilot/config.json</code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="flex justify-between items-center">
            <div class="space-x-4">
                <button id="save-settings" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                    Save Settings
                </button>
                <button id="reset-settings" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                    Reset to Defaults
                </button>
            </div>
            
            <div id="save-status" class="text-sm"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
class SettingsManager {
    constructor() {
        this.initializeElements();
        this.loadSettings();
        this.initializeEventListeners();
        this.testOllamaConnection();
    }
    
    initializeElements() {
        this.preferredProvider = document.getElementById('preferred-provider');
        this.openaiKey = document.getElementById('openai-key');
        this.anthropicKey = document.getElementById('anthropic-key');
        this.geminiKey = document.getElementById('gemini-key');
        this.saasUrl = document.getElementById('saas-url');
        this.saasToken = document.getElementById('saas-token');
        this.serverPort = document.getElementById('server-port');
        this.serverUrl = document.getElementById('server-url');
        this.configPath = document.getElementById('config-path');
        this.saveButton = document.getElementById('save-settings');
        this.resetButton = document.getElementById('reset-settings');
        this.saveStatus = document.getElementById('save-status');
        this.testOllamaButton = document.getElementById('test-ollama');
        this.testSaasButton = document.getElementById('test-saas');
        this.ollamaStatusIndicator = document.getElementById('ollama-status-indicator');
        this.ollamaStatusText = document.getElementById('ollama-status-text');
        this.saasTestResult = document.getElementById('saas-test-result');
    }
    
    initializeEventListeners() {
        this.saveButton.addEventListener('click', () => this.saveSettings());
        this.resetButton.addEventListener('click', () => this.resetSettings());
        this.testOllamaButton.addEventListener('click', () => this.testOllamaConnection());
        this.testSaasButton.addEventListener('click', () => this.testSaasConnection());
    }
    
    async loadSettings() {
        try {
            const response = await fetch('/api/desktop/config');
            const data = await response.json();
            
            if (data.config) {
                const config = data.config;
                
                this.preferredProvider.value = config.preferred_provider || 'openai';
                this.saasUrl.value = config.saas_url || 'https://api.surrealpilot.com';
                
                // Don't load actual API keys for security, just show if they exist
                if (config.api_keys) {
                    if (config.api_keys.openai) this.openaiKey.placeholder = 'API key configured';
                    if (config.api_keys.anthropic) this.anthropicKey.placeholder = 'API key configured';
                    if (config.api_keys.gemini) this.geminiKey.placeholder = 'API key configured';
                }
                
                if (config.saas_token) {
                    this.saasToken.placeholder = 'Token configured';
                }
            }
            
            // Load server info
            this.serverPort.value = data.server_port || 8000;
            this.serverUrl.value = `http://127.0.0.1:${data.server_port || 8000}`;
            
        } catch (error) {
            console.error('Failed to load settings:', error);
            this.showStatus('Failed to load settings', 'error');
        }
    }
    
    async saveSettings() {
        try {
            this.saveButton.disabled = true;
            this.showStatus('Saving...', 'info');
            
            const settings = {
                preferred_provider: this.preferredProvider.value,
                saas_url: this.saasUrl.value,
            };
            
            // Only include API keys if they were changed
            const apiKeys = {};
            if (this.openaiKey.value) apiKeys.openai = this.openaiKey.value;
            if (this.anthropicKey.value) apiKeys.anthropic = this.anthropicKey.value;
            if (this.geminiKey.value) apiKeys.gemini = this.geminiKey.value;
            
            if (Object.keys(apiKeys).length > 0) {
                settings.api_keys = apiKeys;
            }
            
            if (this.saasToken.value) {
                settings.saas_token = this.saasToken.value;
            }
            
            const response = await fetch('/api/desktop/config', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken
                },
                body: JSON.stringify(settings)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.showStatus('Settings saved successfully', 'success');
                
                // Clear password fields after saving
                this.openaiKey.value = '';
                this.anthropicKey.value = '';
                this.geminiKey.value = '';
                this.saasToken.value = '';
                
                // Update placeholders
                if (settings.api_keys?.openai) this.openaiKey.placeholder = 'API key configured';
                if (settings.api_keys?.anthropic) this.anthropicKey.placeholder = 'API key configured';
                if (settings.api_keys?.gemini) this.geminiKey.placeholder = 'API key configured';
                if (settings.saas_token) this.saasToken.placeholder = 'Token configured';
                
            } else {
                this.showStatus(result.message || 'Failed to save settings', 'error');
            }
            
        } catch (error) {
            console.error('Failed to save settings:', error);
            this.showStatus('Failed to save settings', 'error');
        } finally {
            this.saveButton.disabled = false;
        }
    }
    
    async resetSettings() {
        if (!confirm('Are you sure you want to reset all settings to defaults? This will clear all API keys.')) {
            return;
        }
        
        try {
            // Reset form to defaults
            this.preferredProvider.value = 'openai';
            this.openaiKey.value = '';
            this.anthropicKey.value = '';
            this.geminiKey.value = '';
            this.saasUrl.value = 'https://api.surrealpilot.com';
            this.saasToken.value = '';
            
            // Reset placeholders
            this.openaiKey.placeholder = 'sk-...';
            this.anthropicKey.placeholder = 'sk-ant-...';
            this.geminiKey.placeholder = 'AI...';
            this.saasToken.placeholder = 'Your SaaS API token';
            
            this.showStatus('Settings reset to defaults', 'info');
            
        } catch (error) {
            console.error('Failed to reset settings:', error);
            this.showStatus('Failed to reset settings', 'error');
        }
    }
    
    async testOllamaConnection() {
        try {
            this.ollamaStatusText.textContent = 'Testing...';
            this.ollamaStatusIndicator.className = 'w-3 h-3 bg-yellow-500 rounded-full';
            
            const response = await fetch('/api/desktop/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken
                },
                body: JSON.stringify({ service: 'ollama' })
            });
            
            const result = await response.json();
            
            if (result.status === 'connected') {
                this.ollamaStatusText.textContent = 'Connected';
                this.ollamaStatusIndicator.className = 'w-3 h-3 bg-green-500 rounded-full';
            } else {
                this.ollamaStatusText.textContent = 'Not Available';
                this.ollamaStatusIndicator.className = 'w-3 h-3 bg-red-500 rounded-full';
            }
            
        } catch (error) {
            this.ollamaStatusText.textContent = 'Error';
            this.ollamaStatusIndicator.className = 'w-3 h-3 bg-red-500 rounded-full';
        }
    }
    
    async testSaasConnection() {
        try {
            this.testSaasButton.disabled = true;
            this.saasTestResult.textContent = 'Testing...';
            this.saasTestResult.className = 'text-sm text-yellow-400';
            
            const response = await fetch('/api/desktop/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken
                },
                body: JSON.stringify({ service: 'saas' })
            });
            
            const result = await response.json();
            
            if (result.status === 'connected') {
                this.saasTestResult.textContent = 'Connected successfully';
                this.saasTestResult.className = 'text-sm text-green-400';
            } else {
                this.saasTestResult.textContent = result.message || 'Connection failed';
                this.saasTestResult.className = 'text-sm text-red-400';
            }
            
        } catch (error) {
            this.saasTestResult.textContent = 'Connection error';
            this.saasTestResult.className = 'text-sm text-red-400';
        } finally {
            this.testSaasButton.disabled = false;
        }
    }
    
    showStatus(message, type) {
        this.saveStatus.textContent = message;
        
        switch (type) {
            case 'success':
                this.saveStatus.className = 'text-sm text-green-400';
                break;
            case 'error':
                this.saveStatus.className = 'text-sm text-red-400';
                break;
            case 'info':
                this.saveStatus.className = 'text-sm text-blue-400';
                break;
            default:
                this.saveStatus.className = 'text-sm text-gray-400';
        }
        
        // Clear status after 5 seconds
        setTimeout(() => {
            this.saveStatus.textContent = '';
        }, 5000);
    }
}

// Initialize settings manager when page loads
document.addEventListener('DOMContentLoaded', function() {
    new SettingsManager();
});
</script>
@endpush