/**
 * My Games Component Integration Tests
 * 
 * Tests the My Games component integration with the Laravel backend
 */

describe('My Games Component Integration', () => {
    let component;
    let mockFetch;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div id="my-games-component" data-workspace-id="" data-limit="20">
                <div id="games-loading" class="hidden"></div>
                <div id="games-container"></div>
                <div id="games-empty" class="hidden"></div>
                <div id="games-error" class="hidden"></div>
                <button id="refresh-games"></button>
                <button id="retry-games"></button>
                <button id="toggle-games-view">
                    <svg class="grid-view-icon"></svg>
                    <svg class="list-view-icon hidden"></svg>
                </button>
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
            
            <meta name="csrf-token" content="test-csrf-token">
        `;

        // Mock fetch
        mockFetch = jest.fn();
        global.fetch = mockFetch;

        // Mock window.open
        global.open = jest.fn();
    });

    afterEach(() => {
        if (component) {
            component.destroy();
        }
        jest.clearAllMocks();
    });

    describe('API Integration', () => {
        test('should make correct API call for recent games', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            // Import and initialize component
            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(mockFetch).toHaveBeenCalledWith('/api/games/recent?limit=20', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': 'test-csrf-token'
                },
                credentials: 'same-origin'
            });
        });

        test('should make correct API call for workspace games', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent('my-games-component', { workspaceId: '123' });

            await component.loadGames();

            expect(mockFetch).toHaveBeenCalledWith('/api/workspaces/123/games', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': 'test-csrf-token'
                },
                credentials: 'same-origin'
            });
        });

        test('should handle API response with games', async () => {
            const mockGames = [
                {
                    id: 1,
                    title: 'Test Game',
                    description: 'A test game',
                    engine_type: 'playcanvas',
                    display_url: 'https://example.com/game',
                    is_published: true,
                    has_preview: true,
                    created_at: new Date().toISOString(),
                    workspace: {
                        id: 1,
                        name: 'Test Workspace',
                        engine_type: 'playcanvas'
                    }
                }
            ];

            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: mockGames
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(component.games).toEqual(mockGames);
            expect(document.getElementById('games-container').style.display).not.toBe('none');
        });

        test('should handle API error response', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: false,
                    message: 'Unauthorized'
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(component.games).toEqual([]);
            expect(document.getElementById('games-error').classList.contains('hidden')).toBe(false);
        });

        test('should handle network error', async () => {
            mockFetch.mockRejectedValue(new Error('Network error'));

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(component.games).toEqual([]);
            expect(document.getElementById('games-error').classList.contains('hidden')).toBe(false);
        });
    });

    describe('Game Deletion Integration', () => {
        test('should make correct delete API call', async () => {
            // Setup initial games
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    games: [{
                        id: 1,
                        title: 'Test Game',
                        description: 'A test game',
                        engine_type: 'playcanvas'
                    }]
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();
            await component.loadGames();

            // Mock delete response
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    message: 'Game deleted successfully'
                })
            });

            component.deleteGameId = 1;
            await component.confirmDelete();

            expect(mockFetch).toHaveBeenCalledWith('/api/games/1', {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': 'test-csrf-token'
                },
                credentials: 'same-origin'
            });
        });

        test('should handle delete success', async () => {
            // Setup initial games
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    games: [{
                        id: 1,
                        title: 'Test Game',
                        description: 'A test game',
                        engine_type: 'playcanvas'
                    }]
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();
            await component.loadGames();

            expect(component.games).toHaveLength(1);

            // Mock delete response
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    message: 'Game deleted successfully'
                })
            });

            component.deleteGameId = 1;
            await component.confirmDelete();

            expect(component.games).toHaveLength(0);
            expect(document.getElementById('games-empty').classList.contains('hidden')).toBe(false);
        });

        test('should handle delete error', async () => {
            // Setup initial games
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    games: [{
                        id: 1,
                        title: 'Test Game',
                        description: 'A test game',
                        engine_type: 'playcanvas'
                    }]
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();
            await component.loadGames();

            // Mock delete error response
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: false,
                    message: 'Delete failed'
                })
            });

            // Mock alert
            const alertSpy = jest.spyOn(window, 'alert').mockImplementation();

            component.deleteGameId = 1;
            await component.confirmDelete();

            expect(component.games).toHaveLength(1); // Game should still be there
            expect(alertSpy).toHaveBeenCalledWith('Failed to delete game. Please try again.');

            alertSpy.mockRestore();
        });
    });

    describe('User Interactions', () => {
        test('should handle game launch', async () => {
            const mockGames = [{
                id: 1,
                title: 'Test Game',
                description: 'A test game',
                engine_type: 'playcanvas',
                display_url: 'https://example.com/game',
                has_preview: true
            }];

            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: mockGames
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();
            await component.loadGames();
            component.renderGames();

            // Click launch button
            const launchButton = document.querySelector('.launch-game-btn');
            launchButton.click();

            expect(global.open).toHaveBeenCalledWith('https://example.com/game', '_blank');
        });

        test('should handle view mode toggle', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            expect(component.viewMode).toBe('grid');

            const toggleButton = document.getElementById('toggle-games-view');
            toggleButton.click();

            expect(component.viewMode).toBe('list');
        });

        test('should handle refresh button', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            const loadGamesSpy = jest.spyOn(component, 'loadGames');

            const refreshButton = document.getElementById('refresh-games');
            refreshButton.click();

            expect(loadGamesSpy).toHaveBeenCalled();
        });
    });

    describe('Event Dispatching', () => {
        test('should dispatch gamesLoaded event', async () => {
            const mockGames = [{
                id: 1,
                title: 'Test Game',
                engine_type: 'playcanvas'
            }];

            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: mockGames
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            const eventListener = jest.fn();
            component.container.addEventListener('gamesLoaded', eventListener);

            await component.loadGames();

            expect(eventListener).toHaveBeenCalledWith(
                expect.objectContaining({
                    detail: expect.objectContaining({
                        games: mockGames,
                        component: component
                    })
                })
            );
        });

        test('should dispatch gameDeleted event', async () => {
            // Setup initial games
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    games: [{
                        id: 1,
                        title: 'Test Game',
                        engine_type: 'playcanvas'
                    }]
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();
            await component.loadGames();

            const eventListener = jest.fn();
            component.container.addEventListener('gameDeleted', eventListener);

            // Mock delete response
            mockFetch.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    message: 'Game deleted successfully'
                })
            });

            component.deleteGameId = 1;
            await component.confirmDelete();

            expect(eventListener).toHaveBeenCalledWith(
                expect.objectContaining({
                    detail: expect.objectContaining({
                        gameId: 1,
                        component: component
                    })
                })
            );
        });
    });

    describe('Error Handling', () => {
        test('should handle malformed API response', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    // Missing success field
                    games: []
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(document.getElementById('games-error').classList.contains('hidden')).toBe(false);
        });

        test('should handle JSON parse error', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.reject(new Error('Invalid JSON'))
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(document.getElementById('games-error').classList.contains('hidden')).toBe(false);
        });
    });

    describe('CSRF Token Handling', () => {
        test('should include CSRF token in requests', async () => {
            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(mockFetch).toHaveBeenCalledWith(
                expect.any(String),
                expect.objectContaining({
                    headers: expect.objectContaining({
                        'X-CSRF-TOKEN': 'test-csrf-token'
                    })
                })
            );
        });

        test('should handle missing CSRF token', async () => {
            // Remove CSRF token
            document.querySelector('meta[name="csrf-token"]').remove();

            mockFetch.mockResolvedValue({
                json: () => Promise.resolve({
                    success: true,
                    games: []
                })
            });

            const { MyGamesComponent } = await import('../../resources/js/components/my-games.js');
            component = new MyGamesComponent();

            await component.loadGames();

            expect(mockFetch).toHaveBeenCalledWith(
                expect.any(String),
                expect.objectContaining({
                    headers: expect.objectContaining({
                        'X-CSRF-TOKEN': ''
                    })
                })
            );
        });
    });
});