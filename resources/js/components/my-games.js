/**
 * My Games Component
 * 
 * A comprehensive component for displaying and managing user games
 * with support for grid/list views, game launching, and deletion.
 */

export class MyGamesComponent {
    constructor(containerId = 'my-games-component', options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('My Games container not found:', containerId);
            return;
        }

        // Configuration
        this.workspaceId = this.container.dataset.workspaceId || options.workspaceId;
        this.limit = parseInt(this.container.dataset.limit) || options.limit || 20;
        this.showWorkspaceInfo = options.showWorkspaceInfo ?? false;
        
        // State
        this.games = [];
        this.selectedGameId = null;
        this.deleteGameId = null;
        this.viewMode = options.viewMode || 'grid'; // 'grid' or 'list'
        this.isLoading = false;

        // API endpoints
        this.endpoints = {
            workspaceGames: (workspaceId) => `/api/workspaces/${workspaceId}/games`,
            recentGames: (limit) => `/api/games/recent?limit=${limit}`,
            deleteGame: (gameId) => `/api/games/${gameId}`,
            ...options.endpoints
        };

        this.initializeElements();
        this.initializeEventListeners();
        this.loadGames();
    }

    initializeElements() {
        this.loadingElement = document.getElementById('games-loading');
        this.containerElement = document.getElementById('games-container');
        this.emptyElement = document.getElementById('games-empty');
        this.errorElement = document.getElementById('games-error');
        this.gameModal = document.getElementById('game-details-modal');
        this.deleteModal = document.getElementById('delete-game-modal');
        this.refreshButton = document.getElementById('refresh-games');
        this.retryButton = document.getElementById('retry-games');
        this.toggleViewButton = document.getElementById('toggle-games-view');
    }

    initializeEventListeners() {
        // Refresh button
        this.refreshButton?.addEventListener('click', () => this.loadGames());
        
        // Retry button
        this.retryButton?.addEventListener('click', () => this.loadGames());

        // Toggle view button
        this.toggleViewButton?.addEventListener('click', () => this.toggleView());

        // Game modal events
        document.getElementById('close-game-modal')?.addEventListener('click', () => this.hideGameModal());
        document.getElementById('modal-launch-game')?.addEventListener('click', () => this.launchSelectedGame());
        document.getElementById('modal-delete-game')?.addEventListener('click', () => this.showDeleteModal(this.selectedGameId));

        // Delete modal events
        document.getElementById('close-delete-game-modal')?.addEventListener('click', () => this.hideDeleteModal());
        document.getElementById('cancel-delete-game')?.addEventListener('click', () => this.hideDeleteModal());
        document.getElementById('confirm-delete-game')?.addEventListener('click', () => this.confirmDelete());

        // Game selection and actions
        this.containerElement?.addEventListener('click', (e) => this.handleGameClick(e));

        // Close modals on outside click
        this.gameModal?.addEventListener('click', (e) => {
            if (e.target === this.gameModal) this.hideGameModal();
        });

        this.deleteModal?.addEventListener('click', (e) => {
            if (e.target === this.deleteModal) this.hideDeleteModal();
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => this.handleKeydown(e));
    }

    handleGameClick(e) {
        const gameItem = e.target.closest('.game-item');
        const deleteButton = e.target.closest('.delete-game-btn');
        const launchButton = e.target.closest('.launch-game-btn');

        if (deleteButton) {
            e.stopPropagation();
            const gameId = parseInt(deleteButton.dataset.gameId);
            this.showDeleteModal(gameId);
        } else if (launchButton) {
            e.stopPropagation();
            const gameId = parseInt(launchButton.dataset.gameId);
            this.launchGame(gameId);
        } else if (gameItem) {
            const gameId = parseInt(gameItem.dataset.gameId);
            this.showGameDetails(gameId);
        }
    }

    handleKeydown(e) {
        // Close modals with Escape key
        if (e.key === 'Escape') {
            if (!this.gameModal?.classList.contains('hidden')) {
                this.hideGameModal();
            } else if (!this.deleteModal?.classList.contains('hidden')) {
                this.hideDeleteModal();
            }
        }
    }

    showState(state) {
        const states = ['loading', 'container', 'empty', 'error'];
        states.forEach(s => {
            const element = document.getElementById(`games-${s === 'container' ? 'container' : s}`);
            if (element) {
                if (s === 'container') {
                    element.style.display = s === state ? 'block' : 'none';
                } else {
                    element.classList.toggle('hidden', s !== state);
                }
            }
        });
    }

    async loadGames() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showState('loading');

        try {
            const endpoint = this.workspaceId 
                ? this.endpoints.workspaceGames(this.workspaceId)
                : this.endpoints.recentGames(this.limit);

            const response = await this.fetchWithAuth(endpoint);
            const data = await response.json();

            if (data.success) {
                this.games = data.games || [];
                this.renderGames();
                
                if (this.games.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('container');
                }

                this.dispatchEvent('gamesLoaded', { games: this.games });
            } else {
                throw new Error(data.message || 'Failed to load games');
            }
        } catch (error) {
            console.error('Error loading games:', error);
            this.showState('error');
            this.dispatchEvent('gamesLoadError', { error });
        } finally {
            this.isLoading = false;
        }
    }

    async fetchWithAuth(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                ...options.headers
            },
            credentials: 'same-origin',
            ...options
        };

        return fetch(url, defaultOptions);
    }

    toggleView() {
        this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
        
        // Update button icons
        const gridIcon = this.toggleViewButton?.querySelector('.grid-view-icon');
        const listIcon = this.toggleViewButton?.querySelector('.list-view-icon');
        
        if (this.viewMode === 'grid') {
            gridIcon?.classList.remove('hidden');
            listIcon?.classList.add('hidden');
        } else {
            gridIcon?.classList.add('hidden');
            listIcon?.classList.remove('hidden');
        }

        this.renderGames();
        this.dispatchEvent('viewModeChanged', { viewMode: this.viewMode });
    }

    renderGames() {
        if (!this.containerElement) return;

        const isGridView = this.viewMode === 'grid';
        const containerClass = isGridView 
            ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'
            : 'space-y-4';

        this.containerElement.className = `p-4 ${containerClass}`;
        this.containerElement.innerHTML = this.games.map(game => {
            return isGridView ? this.renderGameCard(game) : this.renderGameListItem(game);
        }).join('');
    }

    renderGameCard(game) {
        const title = game.title || 'Untitled Game';
        const description = game.description || 'No description available';
        const createdAt = this.formatDate(game.created_at);
        const engineType = game.engine_type || 'unknown';
        const engineColor = engineType === 'playcanvas' ? 'blue' : 'orange';
        const hasPreview = game.has_preview || game.display_url;

        return `
            <div class="game-item bg-gray-700 rounded-lg border border-gray-600 overflow-hidden hover:border-gray-500 transition-all duration-200 cursor-pointer group" 
                 data-game-id="${game.id}"
                 tabindex="0"
                 role="button"
                 aria-label="View details for ${this.escapeHtml(title)}">
                <!-- Game Thumbnail -->
                <div class="h-48 bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center relative">
                    ${game.thumbnail_url ? `
                        <img src="${game.thumbnail_url}" alt="${this.escapeHtml(title)}" 
                             class="w-full h-full object-cover">
                    ` : `
                        <div class="text-center">
                            <div class="w-16 h-16 bg-${engineColor}-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                                ${this.getEngineIcon(engineType)}
                            </div>
                            <span class="text-${engineColor}-400 text-sm font-medium">${this.getEngineDisplayName(engineType)}</span>
                        </div>
                    `}
                    
                    <!-- Action buttons overlay -->
                    <div class="absolute top-2 right-2 flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        ${hasPreview ? `
                            <button class="launch-game-btn bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-lg transition-colors"
                                    data-game-id="${game.id}" 
                                    title="Launch Game"
                                    aria-label="Launch ${this.escapeHtml(title)}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                        ` : ''}
                        <button class="delete-game-btn bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors"
                                data-game-id="${game.id}" 
                                title="Delete Game"
                                aria-label="Delete ${this.escapeHtml(title)}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Game Info -->
                <div class="p-4">
                    <h4 class="text-lg font-semibold text-white mb-2 truncate">
                        ${this.escapeHtml(title)}
                    </h4>
                    <p class="text-gray-400 text-sm mb-3 line-clamp-2">
                        ${this.escapeHtml(description)}
                    </p>

                    <!-- Status & Metadata -->
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span>Created ${createdAt}</span>
                        <div class="flex items-center space-x-2">
                            ${game.is_published ? '<span class="text-green-400">Published</span>' : '<span class="text-yellow-400">Draft</span>'}
                        </div>
                    </div>

                    ${game.workspace && this.showWorkspaceInfo ? `
                        <div class="mt-2 pt-2 border-t border-gray-600">
                            <p class="text-xs text-indigo-400">
                                ${this.escapeHtml(game.workspace.name)} (${this.getEngineDisplayName(game.workspace.engine_type)})
                            </p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    renderGameListItem(game) {
        const title = game.title || 'Untitled Game';
        const description = game.description || 'No description available';
        const createdAt = this.formatDate(game.created_at);
        const engineType = game.engine_type || 'unknown';
        const engineColor = engineType === 'playcanvas' ? 'blue' : 'orange';
        const hasPreview = game.has_preview || game.display_url;

        return `
            <div class="game-item bg-gray-700 rounded-lg border border-gray-600 p-4 hover:border-gray-500 transition-all duration-200 cursor-pointer group" 
                 data-game-id="${game.id}"
                 tabindex="0"
                 role="button"
                 aria-label="View details for ${this.escapeHtml(title)}">
                <div class="flex items-center space-x-4">
                    <!-- Game Thumbnail -->
                    <div class="w-20 h-20 bg-gradient-to-br from-gray-600 to-gray-700 rounded-lg flex items-center justify-center flex-shrink-0">
                        ${game.thumbnail_url ? `
                            <img src="${game.thumbnail_url}" alt="${this.escapeHtml(title)}" 
                                 class="w-full h-full object-cover rounded-lg">
                        ` : `
                            <div class="w-8 h-8 bg-${engineColor}-600 rounded flex items-center justify-center">
                                ${this.getEngineIcon(engineType, 'w-4 h-4')}
                            </div>
                        `}
                    </div>

                    <!-- Game Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-lg font-semibold text-white truncate">
                                    ${this.escapeHtml(title)}
                                </h4>
                                <p class="text-gray-400 text-sm mb-2 line-clamp-1">
                                    ${this.escapeHtml(description)}
                                </p>
                                <div class="flex items-center space-x-4 text-xs text-gray-500">
                                    <span>Created ${createdAt}</span>
                                    <span class="text-${engineColor}-400">${this.getEngineDisplayName(engineType)}</span>
                                    ${game.is_published ? '<span class="text-green-400">Published</span>' : '<span class="text-yellow-400">Draft</span>'}
                                </div>
                                ${game.workspace && this.showWorkspaceInfo ? `
                                    <p class="text-xs text-indigo-400 mt-1">
                                        ${this.escapeHtml(game.workspace.name)}
                                    </p>
                                ` : ''}
                            </div>

                            <!-- Action buttons -->
                            <div class="flex space-x-2 ml-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                ${hasPreview ? `
                                    <button class="launch-game-btn bg-blue-600 hover:bg-blue-700 text-white p-2 rounded transition-colors"
                                            data-game-id="${game.id}" 
                                            title="Launch Game"
                                            aria-label="Launch ${this.escapeHtml(title)}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                ` : ''}
                                <button class="delete-game-btn bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-colors"
                                        data-game-id="${game.id}" 
                                        title="Delete Game"
                                        aria-label="Delete ${this.escapeHtml(title)}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    showGameDetails(gameId) {
        const game = this.games.find(g => g.id === gameId);
        if (!game) return;

        this.selectedGameId = gameId;

        // Populate modal content
        document.getElementById('modal-game-title').textContent = game.title || 'Untitled Game';
        
        // Game preview
        const previewContainer = document.getElementById('modal-game-preview');
        if (game.display_url) {
            previewContainer.innerHTML = `
                <div class="aspect-video bg-black rounded-lg overflow-hidden">
                    <iframe src="${game.display_url}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                </div>
            `;
        } else if (game.thumbnail_url) {
            previewContainer.innerHTML = `
                <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden flex items-center justify-center">
                    <img src="${game.thumbnail_url}" alt="${this.escapeHtml(game.title)}" class="max-w-full max-h-full object-contain">
                </div>
            `;
        } else {
            const engineColor = game.engine_type === 'playcanvas' ? 'blue' : 'orange';
            previewContainer.innerHTML = `
                <div class="aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-24 h-24 bg-${engineColor}-600 rounded-lg flex items-center justify-center mx-auto mb-4">
                            ${this.getEngineIcon(game.engine_type, 'w-12 h-12')}
                        </div>
                        <p class="text-${engineColor}-400 font-medium">${this.getEngineDisplayName(game.engine_type)} Game</p>
                    </div>
                </div>
            `;
        }

        // Game info
        const infoContainer = document.getElementById('modal-game-info');
        infoContainer.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400">Description</label>
                    <p class="text-white">${this.escapeHtml(game.description || 'No description available')}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Engine</label>
                    <p class="text-white">${this.getEngineDisplayName(game.engine_type)}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Status</label>
                    <p class="text-white">${game.is_published ? 'Published' : 'Draft'}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Created</label>
                    <p class="text-white">${this.formatDate(game.created_at)}</p>
                </div>
                ${game.workspace ? `
                    <div class="md:col-span-2">
                        <label class="text-sm text-gray-400">Workspace</label>
                        <p class="text-white">${this.escapeHtml(game.workspace.name)}</p>
                    </div>
                ` : ''}
            </div>
        `;

        // Update launch button visibility
        const launchButton = document.getElementById('modal-launch-game');
        if (game.display_url) {
            launchButton.style.display = 'block';
        } else {
            launchButton.style.display = 'none';
        }

        this.gameModal?.classList.remove('hidden');
        this.dispatchEvent('gameDetailsShown', { game });
    }

    hideGameModal() {
        this.gameModal?.classList.add('hidden');
        this.selectedGameId = null;
        this.dispatchEvent('gameDetailsHidden');
    }

    launchGame(gameId) {
        const game = this.games.find(g => g.id === gameId);
        if (!game || !game.display_url) return;

        window.open(game.display_url, '_blank');
        this.dispatchEvent('gameLaunched', { game });
    }

    launchSelectedGame() {
        if (this.selectedGameId) {
            this.launchGame(this.selectedGameId);
        }
    }

    showDeleteModal(gameId) {
        this.deleteGameId = gameId;
        const game = this.games.find(g => g.id === gameId);
        
        if (game) {
            document.getElementById('delete-game-title').textContent = game.title || 'Untitled Game';
            document.getElementById('delete-game-description').textContent = game.description || 'No description available';
        }

        this.deleteModal?.classList.remove('hidden');
        this.hideGameModal(); // Close game details modal if open
        this.dispatchEvent('deleteModalShown', { game });
    }

    hideDeleteModal() {
        this.deleteModal?.classList.add('hidden');
        this.deleteGameId = null;
        this.dispatchEvent('deleteModalHidden');
    }

    async confirmDelete() {
        if (!this.deleteGameId) return;

        try {
            const response = await this.fetchWithAuth(this.endpoints.deleteGame(this.deleteGameId), {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                // Remove game from local array
                const deletedGame = this.games.find(g => g.id === this.deleteGameId);
                this.games = this.games.filter(g => g.id !== this.deleteGameId);
                
                // Re-render the list
                this.renderGames();
                
                // Show appropriate state
                if (this.games.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('container');
                }

                this.dispatchEvent('gameDeleted', { gameId: this.deleteGameId, game: deletedGame });
                this.hideDeleteModal();
            } else {
                throw new Error(data.message || 'Failed to delete game');
            }
        } catch (error) {
            console.error('Error deleting game:', error);
            this.dispatchEvent('gameDeleteError', { error, gameId: this.deleteGameId });
            alert('Failed to delete game. Please try again.');
        }
    }

    // Utility methods
    getEngineIcon(engineType, sizeClass = 'w-8 h-8') {
        if (engineType === 'playcanvas') {
            return `<svg class="${sizeClass} text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>`;
        } else {
            return `<svg class="${sizeClass} text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
            </svg>`;
        }
    }

    getEngineDisplayName(engineType) {
        switch (engineType) {
            case 'playcanvas':
                return 'PlayCanvas';
            case 'unreal':
                return 'Unreal Engine';
            default:
                return 'Unknown';
        }
    }

    formatDate(dateString) {
        if (!dateString) return 'Unknown';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffInHours = (now - date) / (1000 * 60 * 60);

        if (diffInHours < 1) {
            return 'Just now';
        } else if (diffInHours < 24) {
            return `${Math.floor(diffInHours)}h ago`;
        } else if (diffInHours < 24 * 7) {
            return `${Math.floor(diffInHours / 24)}d ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, {
            detail: { ...detail, component: this }
        });
        this.container.dispatchEvent(event);
    }

    // Public API methods
    refresh() {
        return this.loadGames();
    }

    getGames() {
        return [...this.games];
    }

    addGame(game) {
        this.games.unshift(game);
        this.renderGames();
        if (this.games.length === 1) {
            this.showState('container');
        }
        this.dispatchEvent('gameAdded', { game });
    }

    updateGame(gameId, updates) {
        const index = this.games.findIndex(g => g.id === gameId);
        if (index !== -1) {
            this.games[index] = { ...this.games[index], ...updates };
            this.renderGames();
            this.dispatchEvent('gameUpdated', { gameId, updates, game: this.games[index] });
        }
    }

    removeGame(gameId) {
        const game = this.games.find(g => g.id === gameId);
        this.games = this.games.filter(g => g.id !== gameId);
        this.renderGames();
        
        if (this.games.length === 0) {
            this.showState('empty');
        }
        
        this.dispatchEvent('gameRemoved', { gameId, game });
    }

    setViewMode(mode) {
        if (['grid', 'list'].includes(mode) && mode !== this.viewMode) {
            this.viewMode = mode;
            this.renderGames();
            this.dispatchEvent('viewModeChanged', { viewMode: this.viewMode });
        }
    }

    destroy() {
        // Clean up event listeners and references
        this.container = null;
        this.games = [];
        this.dispatchEvent('componentDestroyed');
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const myGamesContainer = document.getElementById('my-games-component');
    if (myGamesContainer) {
        window.myGamesComponent = new MyGamesComponent();
    }
});

export default MyGamesComponent;