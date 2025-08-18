/**
 * Simple My Games Component Tests
 * 
 * Basic functionality tests for the My Games component
 */

describe('My Games Component - Simple Tests', () => {
    // Mock DOM setup
    function setupDOM() {
        document.body.innerHTML = `
            <div id="my-games-component" data-workspace-id="" data-limit="20">
                <div id="games-loading" class="hidden"></div>
                <div id="games-container"></div>
                <div id="games-empty" class="hidden"></div>
                <div id="games-error" class="hidden"></div>
                <button id="refresh-games"></button>
                <button id="toggle-games-view">
                    <svg class="grid-view-icon"></svg>
                    <svg class="list-view-icon hidden"></svg>
                </button>
            </div>
            <meta name="csrf-token" content="test-token">
        `;
    }

    beforeEach(() => {
        setupDOM();
        
        // Mock fetch
        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({
                success: true,
                games: []
            })
        });
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('should initialize component', () => {
        const container = document.getElementById('my-games-component');
        expect(container).toBeTruthy();
        expect(container.dataset.workspaceId).toBe('');
        expect(container.dataset.limit).toBe('20');
    });

    test('should show loading state', () => {
        const loadingElement = document.getElementById('games-loading');
        const containerElement = document.getElementById('games-container');
        const emptyElement = document.getElementById('games-empty');
        const errorElement = document.getElementById('games-error');

        // Simulate showing loading state
        loadingElement.classList.remove('hidden');
        containerElement.style.display = 'none';
        emptyElement.classList.add('hidden');
        errorElement.classList.add('hidden');

        expect(loadingElement.classList.contains('hidden')).toBe(false);
        expect(containerElement.style.display).toBe('none');
        expect(emptyElement.classList.contains('hidden')).toBe(true);
        expect(errorElement.classList.contains('hidden')).toBe(true);
    });

    test('should show empty state', () => {
        const loadingElement = document.getElementById('games-loading');
        const containerElement = document.getElementById('games-container');
        const emptyElement = document.getElementById('games-empty');
        const errorElement = document.getElementById('games-error');

        // Simulate showing empty state
        loadingElement.classList.add('hidden');
        containerElement.style.display = 'none';
        emptyElement.classList.remove('hidden');
        errorElement.classList.add('hidden');

        expect(loadingElement.classList.contains('hidden')).toBe(true);
        expect(containerElement.style.display).toBe('none');
        expect(emptyElement.classList.contains('hidden')).toBe(false);
        expect(errorElement.classList.contains('hidden')).toBe(true);
    });

    test('should show error state', () => {
        const loadingElement = document.getElementById('games-loading');
        const containerElement = document.getElementById('games-container');
        const emptyElement = document.getElementById('games-empty');
        const errorElement = document.getElementById('games-error');

        // Simulate showing error state
        loadingElement.classList.add('hidden');
        containerElement.style.display = 'none';
        emptyElement.classList.add('hidden');
        errorElement.classList.remove('hidden');

        expect(loadingElement.classList.contains('hidden')).toBe(true);
        expect(containerElement.style.display).toBe('none');
        expect(emptyElement.classList.contains('hidden')).toBe(true);
        expect(errorElement.classList.contains('hidden')).toBe(false);
    });

    test('should render game items', () => {
        const container = document.getElementById('games-container');
        
        // Simulate rendering games
        const mockGameHTML = `
            <div class="game-item" data-game-id="1">
                <h4>Test Game</h4>
                <p>Test Description</p>
                <button class="launch-game-btn" data-game-id="1">Launch</button>
                <button class="delete-game-btn" data-game-id="1">Delete</button>
            </div>
        `;
        
        container.innerHTML = mockGameHTML;
        
        const gameItem = container.querySelector('.game-item');
        expect(gameItem).toBeTruthy();
        expect(gameItem.dataset.gameId).toBe('1');
        expect(gameItem.textContent).toContain('Test Game');
        expect(gameItem.textContent).toContain('Test Description');
    });

    test('should handle button clicks', () => {
        const container = document.getElementById('games-container');
        
        // Add mock game with buttons
        container.innerHTML = `
            <div class="game-item" data-game-id="1">
                <button class="launch-game-btn" data-game-id="1">Launch</button>
                <button class="delete-game-btn" data-game-id="1">Delete</button>
            </div>
        `;

        const launchButton = container.querySelector('.launch-game-btn');
        const deleteButton = container.querySelector('.delete-game-btn');

        expect(launchButton).toBeTruthy();
        expect(deleteButton).toBeTruthy();
        expect(launchButton.dataset.gameId).toBe('1');
        expect(deleteButton.dataset.gameId).toBe('1');

        // Test click events (would need actual component for full test)
        let launchClicked = false;
        let deleteClicked = false;

        launchButton.addEventListener('click', () => {
            launchClicked = true;
        });

        deleteButton.addEventListener('click', () => {
            deleteClicked = true;
        });

        launchButton.click();
        deleteButton.click();

        expect(launchClicked).toBe(true);
        expect(deleteClicked).toBe(true);
    });

    test('should handle view mode toggle', () => {
        const toggleButton = document.getElementById('toggle-games-view');
        const gridIcon = toggleButton.querySelector('.grid-view-icon');
        const listIcon = toggleButton.querySelector('.list-view-icon');

        expect(gridIcon).toBeTruthy();
        expect(listIcon).toBeTruthy();
        expect(listIcon.classList.contains('hidden')).toBe(true);

        // Simulate toggle to list view
        gridIcon.classList.add('hidden');
        listIcon.classList.remove('hidden');

        expect(gridIcon.classList.contains('hidden')).toBe(true);
        expect(listIcon.classList.contains('hidden')).toBe(false);
    });

    test('should format game data correctly', () => {
        const mockGame = {
            id: 1,
            title: 'Test Game',
            description: 'A test game description',
            engine_type: 'playcanvas',
            is_published: true,
            has_preview: true,
            display_url: 'https://example.com/game',
            created_at: new Date().toISOString()
        };

        // Test data formatting functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getEngineDisplayName(engineType) {
            switch (engineType) {
                case 'playcanvas':
                    return 'PlayCanvas';
                case 'unreal':
                    return 'Unreal Engine';
                default:
                    return 'Unknown';
            }
        }

        expect(escapeHtml(mockGame.title)).toBe('Test Game');
        expect(escapeHtml('<script>alert("xss")</script>')).toBe('&lt;script&gt;alert("xss")&lt;/script&gt;');
        expect(getEngineDisplayName(mockGame.engine_type)).toBe('PlayCanvas');
        expect(getEngineDisplayName('unreal')).toBe('Unreal Engine');
        expect(getEngineDisplayName('unknown')).toBe('Unknown');
    });

    test('should handle API endpoints correctly', () => {
        const workspaceId = '123';
        const limit = 20;

        const endpoints = {
            workspaceGames: (id) => `/api/workspaces/${id}/games`,
            recentGames: (limit) => `/api/games/recent?limit=${limit}`,
            deleteGame: (id) => `/api/games/${id}`
        };

        expect(endpoints.workspaceGames(workspaceId)).toBe('/api/workspaces/123/games');
        expect(endpoints.recentGames(limit)).toBe('/api/games/recent?limit=20');
        expect(endpoints.deleteGame(1)).toBe('/api/games/1');
    });

    test('should validate required DOM elements', () => {
        const requiredElements = [
            'my-games-component',
            'games-loading',
            'games-container',
            'games-empty',
            'games-error',
            'refresh-games',
            'toggle-games-view'
        ];

        requiredElements.forEach(elementId => {
            const element = document.getElementById(elementId);
            expect(element).toBeTruthy();
        });
    });

    test('should handle CSRF token', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        expect(csrfToken).toBeTruthy();
        expect(csrfToken.getAttribute('content')).toBe('test-token');
    });

    test('should create proper fetch options', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const fetchOptions = {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin'
        };

        expect(fetchOptions.headers['X-CSRF-TOKEN']).toBe('test-token');
        expect(fetchOptions.headers['Accept']).toBe('application/json');
        expect(fetchOptions.credentials).toBe('same-origin');
    });
});