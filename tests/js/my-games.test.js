/**
 * My Games Component Tests
 * 
 * Comprehensive test suite for the My Games component functionality
 */

import { MyGamesComponent } from '../../resources/js/components/my-games.js';

// Mock data
const mockGames = [
    {
        id: 1,
        title: "Space Adventure",
        description: "An exciting space exploration game",
        engine_type: "playcanvas",
        thumbnail_url: null,
        preview_url: "https://playcanv.as/p/JtL2iqIH/",
        published_url: "https://playcanv.as/p/JtL2iqIH/",
        display_url: "https://playcanv.as/p/JtL2iqIH/",
        is_published: true,
        has_preview: true,
        has_thumbnail: false,
        created_at: new Date(Date.now() - 86400000 * 2).toISOString(),
        updated_at: new Date(Date.now() - 86400000).toISOString(),
        workspace: {
            id: 1,
            name: "Space Games Workspace",
            engine_type: "playcanvas"
        }
    },
    {
        id: 2,
        title: "Racing Championship",
        description: "High-speed racing game",
        engine_type: "unreal",
        thumbnail_url: null,
        preview_url: null,
        published_url: null,
        display_url: null,
        is_published: false,
        has_preview: false,
        has_thumbnail: false,
        created_at: new Date(Date.now() - 86400000 * 5).toISOString(),
        updated_at: new Date(Date.now() - 86400000 * 3).toISOString(),
        workspace: {
            id: 2,
            name: "Racing Games Workspace",
            engine_type: "unreal"
        }
    }
];

// Mock DOM setup
function setupMockDOM() {
    document.body.innerHTML = `
        <div id="my-games-component" data-workspace-id="" data-limit="20">
            <div id="games-loading" class="hidden"></div>
            <div id="games-container"></div>
            <div id="games-empty" class="hidden"></div>
            <div id="games-error" class="hidden"></div>
            <button id="refresh-games"></button>
            <button id="retry-games"></button>
            <button id="toggle-games-view"></button>
        </div>
        
        <div id="game-details-modal" class="hidden">
            <div id="modal-game-title"></div>
            <div id="modal-game-preview"></div>
            <div id="modal-game-info"></div>
            <button id="close-game-modal"></button>
            <button id="modal-launch-game"></button>
            <button id="modal-delete-game"></button>
        </div>
        
        <div id="delete-game-modal" class="hidden">
            <div id="delete-game-title"></div>
            <div id="delete-game-description"></div>
            <button id="close-delete-game-modal"></button>
            <button id="cancel-delete-game"></button>
            <button id="confirm-delete-game"></button>
        </div>
        
        <meta name="csrf-token" content="test-token">
    `;
}

// Mock fetch
global.fetch = jest.fn();

describe('MyGamesComponent', () => {
    let component;

    beforeEach(() => {
        setupMockDOM();
        
        // Mock successful API response
        fetch.mockResolvedValue({
            json: () => Promise.resolve({
                success: true,
                games: mockGames
            })
        });

        component = new MyGamesComponent('my-games-component');
    });

    afterEach(() => {
        jest.clearAllMocks();
        component?.destroy();
    });

    describe('Initialization', () => {
        test('should initialize with correct default values', () => {
            expect(component.workspaceId).toBe('');
            expect(component.limit).toBe(20);
            expect(component.viewMode).toBe('grid');
            expect(component.games).toEqual([]);
        });

        test('should initialize with custom options', () => {
            const customComponent = new MyGamesComponent('my-games-component', {
                workspaceId: '123',
                limit: 10,
                viewMode: 'list',
                showWorkspaceInfo: true
            });

            expect(customComponent.workspaceId).toBe('123');
            expect(customComponent.limit).toBe(10);
            expect(customComponent.viewMode).toBe('list');
            expect(customComponent.showWorkspaceInfo).toBe(true);

            customComponent.destroy();
        });

        test('should handle missing container gracefully', () => {
            const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
            const invalidComponent = new MyGamesComponent('non-existent-container');
            
            expect(consoleSpy).toHaveBeenCalledWith('My Games container not found:', 'non-existent-container');
            consoleSpy.mockRestore();
        });
    });

    describe('Game Loading', () => {
        test('should load games successfully', async () => {
            await component.loadGames();

            expect(fetch).toHaveBeenCalledWith('/api/games/recent?limit=20', expect.any(Object));
            expect(component.games).toEqual(mockGames);
        });

        test('should load workspace-specific games', async () => {
            component.workspaceId = '123';
            await component.loadGames();

            expect(fetch).toHaveBeenCalledWith('/api/workspaces/123/games', expect.any(Object));
        });

        test('should handle API errors', async () => {
            fetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: false,
                    message: 'API Error'
                })
            });

            await component.loadGames();

            expect(component.games).toEqual([]);
            expect(document.getElementById('games-error').classList.contains('hidden')).toBe(false);
        });

        test('should handle network errors', async () => {
            fetch.mockRejectedValueOnce(new Error('Network error'));

            await component.loadGames();

            expect(component.games).toEqual([]);
            expect(document.getElementById('games-error').classList.contains('hidden')).toBe(false);
        });

        test('should show empty state when no games', async () => {
            fetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            await component.loadGames();

            expect(document.getElementById('games-empty').classList.contains('hidden')).toBe(false);
        });
    });

    describe('View Mode Toggle', () => {
        test('should toggle between grid and list view', () => {
            expect(component.viewMode).toBe('grid');

            component.toggleView();
            expect(component.viewMode).toBe('list');

            component.toggleView();
            expect(component.viewMode).toBe('grid');
        });

        test('should update view icons when toggling', () => {
            const gridIcon = document.querySelector('.grid-view-icon');
            const listIcon = document.querySelector('.list-view-icon');

            // Mock the icons
            document.getElementById('toggle-games-view').innerHTML = `
                <svg class="grid-view-icon"></svg>
                <svg class="list-view-icon hidden"></svg>
            `;

            component.toggleView();

            // Icons should be swapped
            expect(component.viewMode).toBe('list');
        });

        test('should set view mode programmatically', () => {
            component.setViewMode('list');
            expect(component.viewMode).toBe('list');

            component.setViewMode('grid');
            expect(component.viewMode).toBe('grid');

            // Should ignore invalid modes
            component.setViewMode('invalid');
            expect(component.viewMode).toBe('grid');
        });
    });

    describe('Game Rendering', () => {
        beforeEach(async () => {
            await component.loadGames();
        });

        test('should render games in grid view', () => {
            component.setViewMode('grid');
            component.renderGames();

            const container = document.getElementById('games-container');
            expect(container.className).toContain('grid');
            expect(container.querySelectorAll('.game-item')).toHaveLength(2);
        });

        test('should render games in list view', () => {
            component.setViewMode('list');
            component.renderGames();

            const container = document.getElementById('games-container');
            expect(container.className).toContain('space-y-4');
            expect(container.querySelectorAll('.game-item')).toHaveLength(2);
        });

        test('should render game cards with correct data', () => {
            component.renderGames();

            const gameItems = document.querySelectorAll('.game-item');
            expect(gameItems[0].dataset.gameId).toBe('1');
            expect(gameItems[0].textContent).toContain('Space Adventure');
            expect(gameItems[0].textContent).toContain('An exciting space exploration game');
        });

        test('should show launch button for games with preview', () => {
            component.renderGames();

            const gameItems = document.querySelectorAll('.game-item');
            const launchButton = gameItems[0].querySelector('.launch-game-btn');
            expect(launchButton).toBeTruthy();
            expect(launchButton.dataset.gameId).toBe('1');
        });

        test('should show delete button for all games', () => {
            component.renderGames();

            const gameItems = document.querySelectorAll('.game-item');
            gameItems.forEach(item => {
                const deleteButton = item.querySelector('.delete-game-btn');
                expect(deleteButton).toBeTruthy();
            });
        });
    });

    describe('Game Interactions', () => {
        beforeEach(async () => {
            await component.loadGames();
            component.renderGames();
        });

        test('should show game details when clicking game item', () => {
            const gameItem = document.querySelector('.game-item');
            gameItem.click();

            expect(component.selectedGameId).toBe(1);
            expect(document.getElementById('game-details-modal').classList.contains('hidden')).toBe(false);
            expect(document.getElementById('modal-game-title').textContent).toBe('Space Adventure');
        });

        test('should launch game when clicking launch button', () => {
            const originalOpen = window.open;
            window.open = jest.fn();

            const launchButton = document.querySelector('.launch-game-btn');
            launchButton.click();

            expect(window.open).toHaveBeenCalledWith('https://playcanv.as/p/JtL2iqIH/', '_blank');

            window.open = originalOpen;
        });

        test('should show delete modal when clicking delete button', () => {
            const deleteButton = document.querySelector('.delete-game-btn');
            deleteButton.click();

            expect(component.deleteGameId).toBe(1);
            expect(document.getElementById('delete-game-modal').classList.contains('hidden')).toBe(false);
            expect(document.getElementById('delete-game-title').textContent).toBe('Space Adventure');
        });
    });

    describe('Game Deletion', () => {
        beforeEach(async () => {
            await component.loadGames();
            component.renderGames();
        });

        test('should delete game successfully', async () => {
            fetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    message: 'Game deleted successfully'
                })
            });

            component.deleteGameId = 1;
            await component.confirmDelete();

            expect(fetch).toHaveBeenCalledWith('/api/games/1', expect.objectContaining({
                method: 'DELETE'
            }));
            expect(component.games).toHaveLength(1);
            expect(component.games[0].id).toBe(2);
        });

        test('should handle delete errors', async () => {
            fetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: false,
                    message: 'Delete failed'
                })
            });

            const alertSpy = jest.spyOn(window, 'alert').mockImplementation();
            
            component.deleteGameId = 1;
            await component.confirmDelete();

            expect(alertSpy).toHaveBeenCalledWith('Failed to delete game. Please try again.');
            expect(component.games).toHaveLength(2); // Games should remain unchanged

            alertSpy.mockRestore();
        });
    });

    describe('Public API Methods', () => {
        test('should refresh games', async () => {
            const loadGamesSpy = jest.spyOn(component, 'loadGames');
            
            await component.refresh();
            
            expect(loadGamesSpy).toHaveBeenCalled();
        });

        test('should get games copy', () => {
            component.games = mockGames;
            const games = component.getGames();
            
            expect(games).toEqual(mockGames);
            expect(games).not.toBe(mockGames); // Should be a copy
        });

        test('should add game', () => {
            const newGame = {
                id: 3,
                title: "New Game",
                description: "A new game",
                engine_type: "playcanvas"
            };

            component.addGame(newGame);

            expect(component.games[0]).toEqual(newGame);
            expect(component.games).toHaveLength(1);
        });

        test('should update game', () => {
            component.games = [...mockGames];
            
            const updates = { title: "Updated Title" };
            component.updateGame(1, updates);

            expect(component.games[0].title).toBe("Updated Title");
        });

        test('should remove game', () => {
            component.games = [...mockGames];
            
            component.removeGame(1);

            expect(component.games).toHaveLength(1);
            expect(component.games[0].id).toBe(2);
        });
    });

    describe('Utility Methods', () => {
        test('should get correct engine display name', () => {
            expect(component.getEngineDisplayName('playcanvas')).toBe('PlayCanvas');
            expect(component.getEngineDisplayName('unreal')).toBe('Unreal Engine');
            expect(component.getEngineDisplayName('unknown')).toBe('Unknown');
        });

        test('should format dates correctly', () => {
            const now = new Date();
            const oneHourAgo = new Date(now.getTime() - 60 * 60 * 1000);
            const oneDayAgo = new Date(now.getTime() - 24 * 60 * 60 * 1000);
            const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

            expect(component.formatDate(now.toISOString())).toBe('Just now');
            expect(component.formatDate(oneHourAgo.toISOString())).toBe('1h ago');
            expect(component.formatDate(oneDayAgo.toISOString())).toBe('1d ago');
            expect(component.formatDate(oneWeekAgo.toISOString())).toContain('/');
        });

        test('should escape HTML correctly', () => {
            expect(component.escapeHtml('<script>alert("xss")</script>'))
                .toBe('&lt;script&gt;alert("xss")&lt;/script&gt;');
            expect(component.escapeHtml('Normal text')).toBe('Normal text');
        });
    });

    describe('Event Handling', () => {
        test('should dispatch custom events', () => {
            const eventListener = jest.fn();
            component.container.addEventListener('gamesLoaded', eventListener);

            component.dispatchEvent('gamesLoaded', { games: mockGames });

            expect(eventListener).toHaveBeenCalledWith(
                expect.objectContaining({
                    detail: expect.objectContaining({
                        games: mockGames,
                        component: component
                    })
                })
            );
        });

        test('should handle keyboard events', () => {
            // Show game modal
            component.selectedGameId = 1;
            document.getElementById('game-details-modal').classList.remove('hidden');

            // Press Escape
            const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape' });
            document.dispatchEvent(escapeEvent);

            expect(document.getElementById('game-details-modal').classList.contains('hidden')).toBe(true);
        });
    });

    describe('Error Handling', () => {
        test('should handle missing elements gracefully', () => {
            // Remove some elements
            document.getElementById('games-loading').remove();
            document.getElementById('refresh-games').remove();

            // Should not throw errors
            expect(() => {
                component.showState('loading');
                component.initializeEventListeners();
            }).not.toThrow();
        });

        test('should handle malformed game data', () => {
            const malformedGames = [
                { id: 1 }, // Missing required fields
                { id: 2, title: null, description: null }
            ];

            component.games = malformedGames;

            expect(() => {
                component.renderGames();
            }).not.toThrow();
        });
    });

    describe('Accessibility', () => {
        beforeEach(async () => {
            await component.loadGames();
            component.renderGames();
        });

        test('should have proper ARIA labels', () => {
            const gameItems = document.querySelectorAll('.game-item');
            gameItems.forEach(item => {
                expect(item.getAttribute('aria-label')).toBeTruthy();
                expect(item.getAttribute('role')).toBe('button');
                expect(item.getAttribute('tabindex')).toBe('0');
            });
        });

        test('should have proper button labels', () => {
            const launchButton = document.querySelector('.launch-game-btn');
            const deleteButton = document.querySelector('.delete-game-btn');

            expect(launchButton.getAttribute('aria-label')).toContain('Launch');
            expect(deleteButton.getAttribute('aria-label')).toContain('Delete');
        });
    });
});

describe('MyGamesComponent Integration', () => {
    test('should work with real DOM structure', () => {
        document.body.innerHTML = `
            <div class="bg-gray-800 rounded-lg" id="my-games-component" data-workspace-id="" data-limit="20">
                <div class="p-4 border-b border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">My Games</h3>
                        <div class="flex items-center space-x-2">
                            <button id="toggle-games-view" class="text-gray-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5 grid-view-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"></svg>
                                <svg class="w-5 h-5 list-view-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"></svg>
                            </button>
                            <button id="refresh-games" class="text-gray-400 hover:text-white transition-colors"></button>
                        </div>
                    </div>
                </div>
                <div id="games-loading" class="p-8 text-center hidden"></div>
                <div id="games-container" class="p-4"></div>
                <div id="games-empty" class="text-center py-12 hidden"></div>
                <div id="games-error" class="p-8 text-center hidden"></div>
            </div>
        `;

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({
                success: true,
                games: mockGames
            })
        });

        const component = new MyGamesComponent();
        expect(component).toBeTruthy();
        expect(component.container).toBeTruthy();

        component.destroy();
    });
});