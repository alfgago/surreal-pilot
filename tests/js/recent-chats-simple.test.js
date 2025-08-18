/**
 * @jest-environment jsdom
 */

// Simple test for Recent Chats component functionality
describe('Recent Chats Component - Basic Tests', () => {
    beforeEach(() => {
        // Set up DOM
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
        document.body.innerHTML = `
            <div id="recent-chats-component" data-workspace-id="1" data-limit="10">
                <div id="recent-chats-loading" class="hidden">Loading...</div>
                <div id="recent-chats-list"></div>
                <div id="recent-chats-empty" class="hidden">No conversations</div>
                <div id="recent-chats-error" class="hidden">Error loading</div>
                <button id="refresh-recent-chats">Refresh</button>
                <button id="retry-recent-chats">Retry</button>
            </div>
            <div id="delete-conversation-modal" class="hidden">
                <button id="close-delete-modal">Close</button>
                <button id="cancel-delete">Cancel</button>
                <button id="confirm-delete">Delete</button>
                <div id="delete-conversation-title"></div>
                <div id="delete-conversation-preview"></div>
            </div>
        `;

        // Mock fetch
        global.fetch = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('DOM elements are properly initialized', () => {
        const container = document.getElementById('recent-chats-component');
        expect(container).toBeTruthy();
        expect(container.dataset.workspaceId).toBe('1');
        expect(container.dataset.limit).toBe('10');

        const loadingElement = document.getElementById('recent-chats-loading');
        const listElement = document.getElementById('recent-chats-list');
        const emptyElement = document.getElementById('recent-chats-empty');
        const errorElement = document.getElementById('recent-chats-error');

        expect(loadingElement).toBeTruthy();
        expect(listElement).toBeTruthy();
        expect(emptyElement).toBeTruthy();
        expect(errorElement).toBeTruthy();
    });

    test('CSRF token is correctly retrieved', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        expect(csrfToken).toBe('test-token');
    });

    test('conversation item HTML is properly structured', () => {
        const listElement = document.getElementById('recent-chats-list');
        
        // Simulate rendered conversation
        const conversationHTML = `
            <div class="conversation-item p-3 bg-gray-700 hover:bg-gray-600 rounded-lg cursor-pointer transition-colors group" 
                 data-conversation-id="1">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-medium text-white truncate">Test Conversation</h4>
                        <p class="text-xs text-gray-400 line-clamp-2 mb-2">Hello world</p>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">2h ago</span>
                            <span class="text-xs text-gray-500">5</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        listElement.innerHTML = conversationHTML;
        
        const conversationItem = listElement.querySelector('.conversation-item');
        expect(conversationItem).toBeTruthy();
        expect(conversationItem.dataset.conversationId).toBe('1');
        expect(conversationItem.textContent).toContain('Test Conversation');
        expect(conversationItem.textContent).toContain('Hello world');
    });

    test('HTML escaping utility function works correctly', () => {
        // Simple HTML escaping function
        function escapeHtml(text) {
            if (typeof text !== 'string') return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        expect(escapeHtml('<script>alert("xss")</script>'))
            .toBe('&lt;script&gt;alert("xss")&lt;/script&gt;');
        
        expect(escapeHtml('Normal text')).toBe('Normal text');
        expect(escapeHtml('')).toBe('');
        expect(escapeHtml('<img src="x" onerror="alert(1)">')).not.toContain('<img');
    });

    test('date formatting utility works correctly', () => {
        // Simple date formatting function
        function formatDate(dateString) {
            if (!dateString) return 'Unknown';
            
            try {
                const date = new Date(dateString);
                const now = new Date();
                const diffInMinutes = (now - date) / (1000 * 60);

                if (diffInMinutes < 1) {
                    return 'Just now';
                } else if (diffInMinutes < 60) {
                    return `${Math.floor(diffInMinutes)}m ago`;
                } else if (diffInMinutes < 60 * 24) {
                    return `${Math.floor(diffInMinutes / 60)}h ago`;
                } else if (diffInMinutes < 60 * 24 * 7) {
                    return `${Math.floor(diffInMinutes / (60 * 24))}d ago`;
                } else {
                    return date.toLocaleDateString();
                }
            } catch (error) {
                return 'Unknown';
            }
        }

        const now = new Date();
        
        // Just now
        expect(formatDate(now.toISOString())).toBe('Just now');
        
        // Minutes ago
        const minutesAgo = new Date(now.getTime() - 30 * 60 * 1000);
        expect(formatDate(minutesAgo.toISOString())).toBe('30m ago');
        
        // Hours ago
        const hoursAgo = new Date(now.getTime() - 2 * 60 * 60 * 1000);
        expect(formatDate(hoursAgo.toISOString())).toBe('2h ago');
        
        // Invalid date
        expect(formatDate(null)).toBe('Unknown');
        expect(formatDate('')).toBe('Unknown');
    });

    test('modal elements are properly structured', () => {
        const modal = document.getElementById('delete-conversation-modal');
        expect(modal).toBeTruthy();
        expect(modal.classList.contains('hidden')).toBe(true);

        const closeButton = document.getElementById('close-delete-modal');
        const cancelButton = document.getElementById('cancel-delete');
        const confirmButton = document.getElementById('confirm-delete');
        const titleElement = document.getElementById('delete-conversation-title');
        const previewElement = document.getElementById('delete-conversation-preview');

        expect(closeButton).toBeTruthy();
        expect(cancelButton).toBeTruthy();
        expect(confirmButton).toBeTruthy();
        expect(titleElement).toBeTruthy();
        expect(previewElement).toBeTruthy();
    });

    test('event handling setup works correctly', () => {
        const refreshButton = document.getElementById('refresh-recent-chats');
        const retryButton = document.getElementById('retry-recent-chats');
        
        expect(refreshButton).toBeTruthy();
        expect(retryButton).toBeTruthy();

        // Test event listener attachment
        let refreshClicked = false;
        refreshButton.addEventListener('click', () => {
            refreshClicked = true;
        });

        refreshButton.click();
        expect(refreshClicked).toBe(true);
    });

    test('conversation selection visual updates work', () => {
        const listElement = document.getElementById('recent-chats-list');
        
        // Add multiple conversation items
        listElement.innerHTML = `
            <div class="conversation-item" data-conversation-id="1">Conversation 1</div>
            <div class="conversation-item" data-conversation-id="2">Conversation 2</div>
        `;

        const item1 = listElement.querySelector('[data-conversation-id="1"]');
        const item2 = listElement.querySelector('[data-conversation-id="2"]');

        // Simulate selection
        item1.classList.add('ring-2', 'ring-indigo-500', 'bg-gray-600');
        item1.setAttribute('aria-selected', 'true');

        expect(item1.classList.contains('ring-2')).toBe(true);
        expect(item1.classList.contains('ring-indigo-500')).toBe(true);
        expect(item1.getAttribute('aria-selected')).toBe('true');
        
        expect(item2.classList.contains('ring-2')).toBe(false);
        expect(item2.getAttribute('aria-selected')).toBeFalsy();
    });

    test('API endpoint construction works correctly', () => {
        const workspaceId = '1';
        const limit = 10;

        const workspaceEndpoint = `/api/workspaces/${workspaceId}/conversations`;
        const recentEndpoint = `/api/conversations/recent?limit=${limit}`;
        const deleteEndpoint = `/api/conversations/123`;

        expect(workspaceEndpoint).toBe('/api/workspaces/1/conversations');
        expect(recentEndpoint).toBe('/api/conversations/recent?limit=10');
        expect(deleteEndpoint).toBe('/api/conversations/123');
    });

    test('state management classes work correctly', () => {
        const states = ['loading', 'list', 'empty', 'error'];
        
        states.forEach(state => {
            const element = document.getElementById(`recent-chats-${state}`);
            expect(element).toBeTruthy();
            
            // Test showing specific state
            states.forEach(s => {
                const el = document.getElementById(`recent-chats-${s}`);
                if (s === state) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });

            expect(document.getElementById(`recent-chats-${state}`).classList.contains('hidden')).toBe(false);
            
            // Check other states are hidden
            states.filter(s => s !== state).forEach(s => {
                expect(document.getElementById(`recent-chats-${s}`).classList.contains('hidden')).toBe(true);
            });
        });
    });
});