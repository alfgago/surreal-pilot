/**
 * @jest-environment jsdom
 */

import { RecentChatsComponent } from '../../resources/js/components/recent-chats.js';

// Mock fetch globally
global.fetch = jest.fn();

// Mock CSRF token
document.head.innerHTML = '<meta name="csrf-token" content="test-token">';

describe('RecentChatsComponent', () => {
    let container;
    let component;

    beforeEach(() => {
        // Reset fetch mock
        fetch.mockClear();
        
        // Create container element
        container = document.createElement('div');
        container.id = 'test-recent-chats';
        container.dataset.workspaceId = '1';
        container.dataset.limit = '10';
        document.body.appendChild(container);

        // Create required elements
        container.innerHTML = `
            <div id="recent-chats-loading" class="hidden">Loading...</div>
            <div id="recent-chats-list"></div>
            <div id="recent-chats-empty" class="hidden">No conversations</div>
            <div id="recent-chats-error" class="hidden">Error loading</div>
            <button id="refresh-recent-chats">Refresh</button>
            <button id="retry-recent-chats">Retry</button>
        `;

        // Create modal elements
        const modal = document.createElement('div');
        modal.id = 'delete-conversation-modal';
        modal.className = 'hidden';
        modal.innerHTML = `
            <button id="close-delete-modal">Close</button>
            <button id="cancel-delete">Cancel</button>
            <button id="confirm-delete">Delete</button>
            <div id="delete-conversation-title"></div>
            <div id="delete-conversation-preview"></div>
        `;
        document.body.appendChild(modal);
    });

    afterEach(() => {
        if (component) {
            component.destroy();
        }
        document.body.innerHTML = '';
    });

    describe('Initialization', () => {
        test('initializes with correct configuration', () => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats',
                workspaceId: '1',
                limit: 10
            });

            expect(component.workspaceId).toBe('1');
            expect(component.limit).toBe(10);
            expect(component.conversations).toEqual([]);
            expect(component.selectedConversationId).toBeNull();
        });

        test('reads configuration from data attributes', () => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });

            expect(component.workspaceId).toBe('1');
            expect(component.limit).toBe(10);
        });

        test('handles missing container gracefully', () => {
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            
            component = new RecentChatsComponent({
                containerId: 'non-existent-container'
            });

            expect(consoleSpy).toHaveBeenCalledWith(
                'Recent Chats container not found:',
                'non-existent-container'
            );
            
            consoleSpy.mockRestore();
        });
    });

    describe('Loading Conversations', () => {
        beforeEach(() => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });
        });

        test('loads workspace conversations successfully', async () => {
            const mockConversations = [
                {
                    id: 1,
                    title: 'Test Conversation',
                    last_message_preview: 'Hello world',
                    message_count: 5,
                    updated_at: '2024-01-01T12:00:00Z'
                }
            ];

            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    success: true,
                    conversations: mockConversations
                })
            });

            await component.loadConversations();

            expect(fetch).toHaveBeenCalledWith(
                '/api/workspaces/1/conversations',
                expect.objectContaining({
                    method: 'GET',
                    headers: expect.objectContaining({
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': 'test-token'
                    })
                })
            );

            expect(component.conversations).toEqual(mockConversations);
        });

        test('loads recent conversations when no workspace specified', async () => {
            component.workspaceId = null;
            
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    success: true,
                    conversations: []
                })
            });

            await component.loadConversations();

            expect(fetch).toHaveBeenCalledWith(
                '/api/conversations/recent?limit=10',
                expect.any(Object)
            );
        });

        test('handles loading errors', async () => {
            fetch.mockRejectedValueOnce(new Error('Network error'));

            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            
            await component.loadConversations();

            expect(consoleSpy).toHaveBeenCalledWith(
                'Error loading conversations:',
                expect.any(Error)
            );
            
            consoleSpy.mockRestore();
        });

        test('shows appropriate states during loading', async () => {
            const loadingElement = document.getElementById('recent-chats-loading');
            const listElement = document.getElementById('recent-chats-list');
            const emptyElement = document.getElementById('recent-chats-empty');

            // Mock a slow response
            fetch.mockImplementationOnce(() => 
                new Promise(resolve => setTimeout(() => resolve({
                    ok: true,
                    json: async () => ({ success: true, conversations: [] })
                }), 100))
            );

            const loadPromise = component.loadConversations();

            // Should show loading state initially
            expect(loadingElement.classList.contains('hidden')).toBe(false);

            await loadPromise;

            // Should show empty state after loading
            expect(loadingElement.classList.contains('hidden')).toBe(true);
            expect(emptyElement.classList.contains('hidden')).toBe(false);
        });
    });

    describe('Conversation Rendering', () => {
        beforeEach(() => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });
        });

        test('renders conversations correctly', () => {
            const mockConversations = [
                {
                    id: 1,
                    title: 'Test Conversation',
                    last_message_preview: 'Hello world',
                    message_count: 5,
                    updated_at: '2024-01-01T12:00:00Z'
                },
                {
                    id: 2,
                    title: null, // Test untitled conversation
                    last_message_preview: 'Another message',
                    message_count: 2,
                    updated_at: '2024-01-01T11:00:00Z'
                }
            ];

            component.conversations = mockConversations;
            component.renderConversations();

            const listElement = document.getElementById('recent-chats-list');
            const conversationItems = listElement.querySelectorAll('.conversation-item');

            expect(conversationItems).toHaveLength(2);
            expect(conversationItems[0].dataset.conversationId).toBe('1');
            expect(conversationItems[1].dataset.conversationId).toBe('2');
            
            // Check that untitled conversation shows default title
            expect(conversationItems[1].textContent).toContain('Untitled Chat');
        });

        test('renders workspace information when enabled', () => {
            component.showWorkspaceInfo = true;
            component.conversations = [{
                id: 1,
                title: 'Test Conversation',
                last_message_preview: 'Hello world',
                message_count: 5,
                updated_at: '2024-01-01T12:00:00Z',
                workspace: {
                    id: 1,
                    name: 'Test Workspace',
                    engine_type: 'playcanvas'
                }
            }];

            component.renderConversations();

            const listElement = document.getElementById('recent-chats-list');
            expect(listElement.innerHTML).toContain('Test Workspace (playcanvas)');
        });

        test('escapes HTML in conversation content', () => {
            component.conversations = [{
                id: 1,
                title: '<script>alert("xss")</script>',
                last_message_preview: '<img src="x" onerror="alert(1)">',
                message_count: 1,
                updated_at: '2024-01-01T12:00:00Z'
            }];

            component.renderConversations();

            const listElement = document.getElementById('recent-chats-list');
            expect(listElement.innerHTML).not.toContain('<script>');
            expect(listElement.innerHTML).not.toContain('<img');
            expect(listElement.innerHTML).toContain('&lt;script&gt;');
        });
    });

    describe('Conversation Selection', () => {
        beforeEach(() => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });
            
            component.conversations = [
                { id: 1, title: 'Conversation 1', message_count: 5, updated_at: '2024-01-01T12:00:00Z' },
                { id: 2, title: 'Conversation 2', message_count: 3, updated_at: '2024-01-01T11:00:00Z' }
            ];
            component.renderConversations();
        });

        test('selects conversation on click', () => {
            const conversationItem = document.querySelector('[data-conversation-id="1"]');
            const eventSpy = jest.fn();
            
            container.addEventListener('conversationSelected', eventSpy);
            
            conversationItem.click();

            expect(component.selectedConversationId).toBe(1);
            expect(eventSpy).toHaveBeenCalledWith(
                expect.objectContaining({
                    detail: expect.objectContaining({
                        conversationId: 1,
                        conversation: expect.objectContaining({ id: 1 })
                    })
                })
            );
        });

        test('updates visual selection', () => {
            component.selectConversation(1);

            const selectedItem = document.querySelector('[data-conversation-id="1"]');
            const unselectedItem = document.querySelector('[data-conversation-id="2"]');

            expect(selectedItem.classList.contains('ring-2')).toBe(true);
            expect(selectedItem.classList.contains('ring-indigo-500')).toBe(true);
            expect(unselectedItem.classList.contains('ring-2')).toBe(false);
        });

        test('calls callback when provided', () => {
            const callback = jest.fn();
            component.onConversationSelected = callback;

            component.selectConversation(1);

            expect(callback).toHaveBeenCalledWith(1, expect.objectContaining({ id: 1 }));
        });
    });

    describe('Conversation Deletion', () => {
        beforeEach(() => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });
            
            component.conversations = [
                { id: 1, title: 'Conversation 1', last_message_preview: 'Hello', message_count: 5, updated_at: '2024-01-01T12:00:00Z' }
            ];
            component.renderConversations();
        });

        test('shows delete modal on delete button click', () => {
            const deleteButton = document.querySelector('.delete-conversation-btn');
            const modal = document.getElementById('delete-conversation-modal');

            deleteButton.click();

            expect(modal.classList.contains('hidden')).toBe(false);
            expect(component.deleteConversationId).toBe(1);
            
            const titleElement = document.getElementById('delete-conversation-title');
            const previewElement = document.getElementById('delete-conversation-preview');
            expect(titleElement.textContent).toBe('Conversation 1');
            expect(previewElement.textContent).toBe('Hello');
        });

        test('hides modal on cancel', () => {
            component.showDeleteModal(1);
            const cancelButton = document.getElementById('cancel-delete');
            const modal = document.getElementById('delete-conversation-modal');

            cancelButton.click();

            expect(modal.classList.contains('hidden')).toBe(true);
            expect(component.deleteConversationId).toBeNull();
        });

        test('deletes conversation successfully', async () => {
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });

            const eventSpy = jest.fn();
            container.addEventListener('conversationDeleted', eventSpy);

            component.deleteConversationId = 1;
            await component.confirmDelete();

            expect(fetch).toHaveBeenCalledWith(
                '/api/conversations/1',
                expect.objectContaining({
                    method: 'DELETE'
                })
            );

            expect(component.conversations).toHaveLength(0);
            expect(eventSpy).toHaveBeenCalledWith(
                expect.objectContaining({
                    detail: { conversationId: 1 }
                })
            );
        });

        test('handles deletion errors', async () => {
            fetch.mockRejectedValueOnce(new Error('Network error'));
            
            const alertSpy = jest.spyOn(window, 'alert').mockImplementation();
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

            component.deleteConversationId = 1;
            await component.confirmDelete();

            expect(alertSpy).toHaveBeenCalledWith('Failed to delete conversation. Please try again.');
            expect(consoleSpy).toHaveBeenCalled();
            
            alertSpy.mockRestore();
            consoleSpy.mockRestore();
        });
    });

    describe('Utility Methods', () => {
        beforeEach(() => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });
        });

        test('formats dates correctly', () => {
            const now = new Date();
            
            // Just now
            expect(component.formatDate(now.toISOString())).toBe('Just now');
            
            // Minutes ago
            const minutesAgo = new Date(now.getTime() - 30 * 60 * 1000);
            expect(component.formatDate(minutesAgo.toISOString())).toBe('30m ago');
            
            // Hours ago
            const hoursAgo = new Date(now.getTime() - 2 * 60 * 60 * 1000);
            expect(component.formatDate(hoursAgo.toISOString())).toBe('2h ago');
            
            // Days ago
            const daysAgo = new Date(now.getTime() - 3 * 24 * 60 * 60 * 1000);
            expect(component.formatDate(daysAgo.toISOString())).toBe('3d ago');
        });

        test('escapes HTML correctly', () => {
            expect(component.escapeHtml('<script>alert("xss")</script>'))
                .toBe('&lt;script&gt;alert("xss")&lt;/script&gt;');
            
            expect(component.escapeHtml('Normal text')).toBe('Normal text');
            expect(component.escapeHtml('')).toBe('');
        });

        test('gets CSRF token correctly', () => {
            expect(component.getCsrfToken()).toBe('test-token');
        });
    });

    describe('Public API', () => {
        beforeEach(() => {
            component = new RecentChatsComponent({
                containerId: 'test-recent-chats'
            });
        });

        test('refresh method calls loadConversations', async () => {
            const loadSpy = jest.spyOn(component, 'loadConversations').mockResolvedValue();
            
            component.refresh();
            
            expect(loadSpy).toHaveBeenCalled();
            loadSpy.mockRestore();
        });

        test('addConversation method adds to list', () => {
            const newConversation = {
                id: 99,
                title: 'New Conversation',
                message_count: 0,
                updated_at: '2024-01-01T12:00:00Z'
            };

            component.addConversation(newConversation);

            expect(component.conversations).toHaveLength(1);
            expect(component.conversations[0]).toEqual(newConversation);
        });

        test('updateConversation method updates existing conversation', () => {
            component.conversations = [
                { id: 1, title: 'Old Title', message_count: 5 }
            ];

            component.updateConversation(1, { title: 'New Title', message_count: 6 });

            expect(component.conversations[0].title).toBe('New Title');
            expect(component.conversations[0].message_count).toBe(6);
        });

        test('removeConversation method removes from list', () => {
            component.conversations = [
                { id: 1, title: 'Conversation 1' },
                { id: 2, title: 'Conversation 2' }
            ];
            component.selectedConversationId = 1;

            component.removeConversation(1);

            expect(component.conversations).toHaveLength(1);
            expect(component.conversations[0].id).toBe(2);
            expect(component.selectedConversationId).toBeNull();
        });

        test('setWorkspaceId method updates workspace and reloads', () => {
            const loadSpy = jest.spyOn(component, 'loadConversations').mockResolvedValue();
            
            component.setWorkspaceId('2');
            
            expect(component.workspaceId).toBe('2');
            expect(loadSpy).toHaveBeenCalled();
            loadSpy.mockRestore();
        });
    });
});