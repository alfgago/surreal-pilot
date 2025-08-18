@extends('layouts.app')

@section('title', 'Chat Settings Demo')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white">Chat Settings Component Demo</h1>
            <p class="text-gray-400 mt-1">Test the Chat Settings modal component functionality</p>
        </div>

        <!-- Demo Controls -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Demo Controls</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Open Settings Button -->
                <button id="open-chat-settings" 
                        class="flex items-center justify-center px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Open Chat Settings
                </button>

                <!-- Refresh Settings Button -->
                <button id="refresh-settings" 
                        class="flex items-center justify-center px-4 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh Settings
                </button>

                <!-- Show Current Settings Button -->
                <button id="show-current-settings" 
                        class="flex items-center justify-center px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Show Current Settings
                </button>
            </div>
        </div>

        <!-- Current Settings Display -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Current Settings</h2>
            <div id="current-settings-display" class="bg-gray-700 rounded-lg p-4">
                <p class="text-gray-400 text-center">Click "Show Current Settings" to display current configuration</p>
            </div>
        </div>

        <!-- Event Log -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Event Log</h2>
                <button id="clear-log" 
                        class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded transition-colors">
                    Clear Log
                </button>
            </div>
            <div id="event-log" class="bg-gray-700 rounded-lg p-4 max-h-64 overflow-y-auto">
                <p class="text-gray-400 text-sm">Event log will appear here...</p>
            </div>
        </div>
    </div>
</div>

<!-- Include the Chat Settings Component -->
<x-chat-settings />

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/components/chat-settings.css') }}">
@endpush

@push('scripts')
<script type="module">
import { ChatSettingsComponent } from '{{ asset('js/components/chat-settings.js') }}';

document.addEventListener('DOMContentLoaded', function() {
    const eventLog = document.getElementById('event-log');
    const currentSettingsDisplay = document.getElementById('current-settings-display');
    
    function logEvent(message, data = null) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = 'text-sm mb-2 pb-2 border-b border-gray-600 last:border-b-0';
        
        let content = `<span class="text-gray-400">[${timestamp}]</span> <span class="text-white">${message}</span>`;
        
        if (data) {
            content += `<pre class="text-xs text-gray-300 mt-1 bg-gray-800 p-2 rounded overflow-x-auto">${JSON.stringify(data, null, 2)}</pre>`;
        }
        
        logEntry.innerHTML = content;
        eventLog.insertBefore(logEntry, eventLog.firstChild);
        
        // Keep only last 20 entries
        while (eventLog.children.length > 20) {
            eventLog.removeChild(eventLog.lastChild);
        }
    }

    function displayCurrentSettings(settings) {
        if (!settings || Object.keys(settings).length === 0) {
            currentSettingsDisplay.innerHTML = '<p class="text-gray-400 text-center">No settings available</p>';
            return;
        }

        const settingsHtml = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="text-sm font-medium text-gray-300 mb-2">AI Model</h4>
                    <p class="text-white text-sm">${settings.ai_model || 'Not set'}</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-300 mb-2">Temperature</h4>
                    <p class="text-white text-sm">${settings.temperature || 'Not set'}</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-300 mb-2">Max Tokens</h4>
                    <p class="text-white text-sm">${settings.max_tokens || 'Not set'}</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-300 mb-2">Streaming</h4>
                    <p class="text-white text-sm">${settings.streaming_enabled ? 'Enabled' : 'Disabled'}</p>
                </div>
            </div>
        `;
        
        currentSettingsDisplay.innerHTML = settingsHtml;
    }

    // Initialize the Chat Settings component with event callbacks
    const chatSettings = new ChatSettingsComponent({
        onSettingsSaved: (settings) => {
            logEvent('Settings saved successfully', settings);
            displayCurrentSettings(settings);
        },
        onSettingsChanged: (settings) => {
            logEvent('Settings changed (unsaved)', settings);
        },
        onModelChanged: (modelId, model) => {
            logEvent('Model changed', { modelId, model });
        },
        onError: (error) => {
            logEvent('Error occurred', { message: error.message, stack: error.stack });
        }
    });

    // Store reference globally for debugging
    window.chatSettingsDemo = chatSettings;

    // Demo control event listeners
    document.getElementById('refresh-settings')?.addEventListener('click', () => {
        logEvent('Refreshing settings...');
        chatSettings.refresh().then(() => {
            logEvent('Settings refreshed');
        }).catch(error => {
            logEvent('Failed to refresh settings', { error: error.message });
        });
    });

    document.getElementById('show-current-settings')?.addEventListener('click', () => {
        const settings = chatSettings.getCurrentSettings();
        logEvent('Current settings requested', settings);
        displayCurrentSettings(settings);
    });

    document.getElementById('clear-log')?.addEventListener('click', () => {
        eventLog.innerHTML = '<p class="text-gray-400 text-sm">Event log cleared...</p>';
    });

    // Listen to component events
    const modal = document.getElementById('chat-settings-modal');
    if (modal) {
        modal.addEventListener('settingsOpened', () => {
            logEvent('Settings modal opened');
        });

        modal.addEventListener('settingsClosed', () => {
            logEvent('Settings modal closed');
        });

        modal.addEventListener('settingsLoaded', (e) => {
            logEvent('Settings loaded', e.detail);
            displayCurrentSettings(e.detail.settings);
        });

        modal.addEventListener('settingsLoadError', (e) => {
            logEvent('Settings load error', e.detail);
        });

        modal.addEventListener('settingsSaved', (e) => {
            logEvent('Settings saved (event)', e.detail);
        });

        modal.addEventListener('settingsSaveError', (e) => {
            logEvent('Settings save error (event)', e.detail);
        });

        modal.addEventListener('settingsReset', (e) => {
            logEvent('Settings reset', e.detail);
            displayCurrentSettings(e.detail.settings);
        });

        modal.addEventListener('modelChanged', (e) => {
            logEvent('Model changed (event)', e.detail);
        });
    }

    // Initial log entry
    logEvent('Chat Settings Demo initialized');
});
</script>
@endpush