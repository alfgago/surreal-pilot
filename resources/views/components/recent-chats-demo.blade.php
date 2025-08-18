@extends('layouts.app')

@section('title', 'Recent Chats Component Demo')

@push('styles')
<link href="{{ asset('css/components/recent-chats.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Recent Chats Component Demo</h1>
            <p class="text-gray-400">Interactive demonstration of the Recent Chats component with different configurations.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-8">
            <!-- Workspace-specific Recent Chats -->
            <div class="space-y-4">
                <h2 class="text-xl font-semibold text-white">Workspace Conversations</h2>
                <p class="text-gray-400 text-sm">Shows conversations for a specific workspace</p>
                
                <x-recent-chats 
                    :workspace-id="1"
                    :limit="5"
                    container-class="bg-gray-800 rounded-lg border border-gray-700"
                    header-class="p-4 border-b border-gray-700"
                    list-class="p-4 space-y-2 max-h-80 overflow-y-auto"
                />
            </div>

            <!-- Global Recent Chats -->
            <div class="space-y-4">
                <h2 class="text-xl font-semibold text-white">All Recent Chats</h2>
                <p class="text-gray-400 text-sm">Shows recent conversations across all workspaces</p>
                
                <x-recent-chats 
                    :show-workspace-info="true"
                    :limit="10"
                    container-class="bg-gray-800 rounded-lg border border-gray-700"
                    header-class="p-4 border-b border-gray-700"
                    list-class="p-4 space-y-2 max-h-80 overflow-y-auto"
                />
            </div>

            <!-- Compact Recent Chats -->
            <div class="space-y-4">
                <h2 class="text-xl font-semibold text-white">Compact View</h2>
                <p class="text-gray-400 text-sm">Compact version for sidebars</p>
                
                <x-recent-chats 
                    :limit="8"
                    container-class="bg-gray-800 rounded-lg border border-gray-700"
                    header-class="p-3 border-b border-gray-700"
                    list-class="p-3 space-y-1 max-h-64 overflow-y-auto"
                    item-class="p-2 bg-gray-700 hover:bg-gray-600 rounded cursor-pointer transition-colors group text-sm"
                />
            </div>
        </div>

        <!-- Event Demonstration -->
        <div class="mt-12">
            <h2 class="text-2xl font-semibold text-white mb-4">Event Handling Demo</h2>
            <div class="bg-gray-800 rounded-lg p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-lg font-medium text-white mb-4">Interactive Recent Chats</h3>
                        <div id="demo-recent-chats">
                            <x-recent-chats 
                                :show-workspace-info="true"
                                :limit="5"
                            />
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-white mb-4">Event Log</h3>
                        <div id="event-log" class="bg-gray-900 rounded-lg p-4 h-80 overflow-y-auto">
                            <p class="text-gray-400 text-sm">Events will appear here...</p>
                        </div>
                        <button id="clear-log" class="mt-2 px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm">
                            Clear Log
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Testing -->
        <div class="mt-12">
            <h2 class="text-2xl font-semibold text-white mb-4">API Testing</h2>
            <div class="bg-gray-800 rounded-lg p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-lg font-medium text-white mb-4">Component Controls</h3>
                        <div class="space-y-3">
                            <button id="refresh-conversations" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded">
                                Refresh Conversations
                            </button>
                            <button id="add-test-conversation" class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
                                Add Test Conversation
                            </button>
                            <button id="toggle-auto-refresh" class="w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded">
                                Toggle Auto Refresh
                            </button>
                            <div class="flex space-x-2">
                                <input type="number" id="workspace-id-input" placeholder="Workspace ID" 
                                       class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white">
                                <button id="set-workspace" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                                    Set Workspace
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-white mb-4">Component State</h3>
                        <div id="component-state" class="bg-gray-900 rounded-lg p-4 text-sm">
                            <div class="space-y-2 text-gray-300">
                                <div>Selected: <span id="selected-conversation" class="text-indigo-400">None</span></div>
                                <div>Total Conversations: <span id="total-conversations" class="text-green-400">0</span></div>
                                <div>Workspace ID: <span id="current-workspace" class="text-yellow-400">All</span></div>
                                <div>Auto Refresh: <span id="auto-refresh-status" class="text-blue-400">Off</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/components/recent-chats.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize demo components
    const demoComponent = new RecentChatsComponent({
        containerId: 'demo-recent-chats',
        showWorkspaceInfo: true,
        autoRefresh: false,
        onConversationSelected: function(conversationId, conversation) {
            logEvent('Conversation Selected', { conversationId, title: conversation?.title });
            updateComponentState();
        },
        onConversationDeleted: function(conversationId) {
            logEvent('Conversation Deleted', { conversationId });
            updateComponentState();
        },
        onError: function(error) {
            logEvent('Error', { message: error.message });
        }
    });

    // Event logging
    function logEvent(eventName, data) {
        const eventLog = document.getElementById('event-log');
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = 'mb-2 p-2 bg-gray-800 rounded text-xs';
        logEntry.innerHTML = `
            <div class="text-indigo-400 font-medium">${timestamp} - ${eventName}</div>
            <div class="text-gray-300 mt-1">${JSON.stringify(data, null, 2)}</div>
        `;
        eventLog.insertBefore(logEntry, eventLog.firstChild);
        
        // Keep only last 20 entries
        while (eventLog.children.length > 20) {
            eventLog.removeChild(eventLog.lastChild);
        }
    }

    // Component state updates
    function updateComponentState() {
        document.getElementById('selected-conversation').textContent = 
            demoComponent.getSelectedConversationId() || 'None';
        document.getElementById('total-conversations').textContent = 
            demoComponent.getConversations().length;
        document.getElementById('current-workspace').textContent = 
            demoComponent.workspaceId || 'All';
    }

    // Control buttons
    document.getElementById('refresh-conversations').addEventListener('click', function() {
        demoComponent.refresh();
        logEvent('Manual Refresh', {});
    });

    document.getElementById('add-test-conversation').addEventListener('click', function() {
        const testConversation = {
            id: Date.now(),
            title: `Test Conversation ${Math.floor(Math.random() * 1000)}`,
            last_message_preview: 'This is a test conversation created for demo purposes.',
            message_count: Math.floor(Math.random() * 50),
            updated_at: new Date().toISOString(),
            workspace: {
                id: 1,
                name: 'Demo Workspace',
                engine_type: 'playcanvas'
            }
        };
        
        demoComponent.addConversation(testConversation);
        logEvent('Test Conversation Added', testConversation);
        updateComponentState();
    });

    let autoRefreshEnabled = false;
    document.getElementById('toggle-auto-refresh').addEventListener('click', function() {
        autoRefreshEnabled = !autoRefreshEnabled;
        if (autoRefreshEnabled) {
            demoComponent.startAutoRefresh();
            document.getElementById('auto-refresh-status').textContent = 'On';
        } else {
            demoComponent.stopAutoRefresh();
            document.getElementById('auto-refresh-status').textContent = 'Off';
        }
        logEvent('Auto Refresh Toggled', { enabled: autoRefreshEnabled });
    });

    document.getElementById('set-workspace').addEventListener('click', function() {
        const workspaceId = document.getElementById('workspace-id-input').value;
        demoComponent.setWorkspaceId(workspaceId || null);
        logEvent('Workspace Changed', { workspaceId: workspaceId || 'All' });
        updateComponentState();
    });

    document.getElementById('clear-log').addEventListener('click', function() {
        document.getElementById('event-log').innerHTML = '<p class="text-gray-400 text-sm">Events will appear here...</p>';
    });

    // Listen to component events
    const demoContainer = document.getElementById('demo-recent-chats');
    demoContainer.addEventListener('conversationsLoaded', function(e) {
        logEvent('Conversations Loaded', { count: e.detail.conversations.length });
        updateComponentState();
    });

    demoContainer.addEventListener('conversationSelected', function(e) {
        logEvent('Conversation Selected (Event)', e.detail);
    });

    demoContainer.addEventListener('conversationDeleted', function(e) {
        logEvent('Conversation Deleted (Event)', e.detail);
    });

    // Initial state update
    updateComponentState();
});
</script>
@endpush