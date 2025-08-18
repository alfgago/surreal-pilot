@props([
    'workspaceId' => null,
    'showWorkspaceInfo' => false,
    'limit' => 20,
    'containerClass' => 'bg-gray-800 rounded-lg',
    'headerClass' => 'p-4 border-b border-gray-700',
    'gridClass' => 'p-4',
    'emptyStateClass' => 'text-center py-12'
])

<div class="{{ $containerClass }}" id="my-games-component" data-workspace-id="{{ $workspaceId }}" data-limit="{{ $limit }}">
    <!-- Header -->
    <div class="{{ $headerClass }}">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">My Games</h3>
            <div class="flex items-center space-x-2">
                <button id="toggle-games-view" class="text-gray-400 hover:text-white transition-colors" title="Toggle view">
                    <svg class="w-5 h-5 grid-view-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    <svg class="w-5 h-5 list-view-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                </button>
                <button id="refresh-games" class="text-gray-400 hover:text-white transition-colors" title="Refresh">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div id="games-loading" class="p-8 text-center hidden">
        <div class="inline-flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-400">Loading games...</span>
        </div>
    </div>

    <!-- Games Grid/List -->
    <div id="games-container" class="{{ $gridClass }}">
        <!-- Games will be populated here -->
    </div>

    <!-- Empty State -->
    <div id="games-empty" class="{{ $emptyStateClass }} hidden">
        <div class="w-24 h-24 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-white mb-2">No games yet</h3>
        <p class="text-gray-400 mb-8 max-w-md mx-auto">
            Start creating amazing games with AI assistance. Choose between PlayCanvas for web/mobile games or Unreal Engine for high-end 3D experiences.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button onclick="window.location.href='/chat'" 
                    class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 flex items-center justify-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>Create New Game</span>
            </button>
        </div>
    </div>

    <!-- Error State -->
    <div id="games-error" class="p-8 text-center hidden">
        <svg class="h-12 w-12 text-red-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-red-400 text-sm">Failed to load games</p>
        <button id="retry-games" class="mt-2 text-indigo-400 hover:text-indigo-300 text-sm">
            Try again
        </button>
    </div>
</div>

<!-- Game Details Modal -->
<div id="game-details-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-white" id="modal-game-title">Game Details</h3>
                    <button id="close-game-modal" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Game Preview -->
                <div id="modal-game-preview" class="mb-6">
                    <!-- Preview content will be populated here -->
                </div>

                <!-- Game Info -->
                <div id="modal-game-info" class="space-y-4">
                    <!-- Game info will be populated here -->
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-700">
                    <button id="modal-delete-game" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition duration-200">
                        Delete Game
                    </button>
                    <button id="modal-launch-game" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
                        Launch Game
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-game-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Delete Game</h3>
                <button id="close-delete-game-modal" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="mb-6">
                <p class="text-gray-300">Are you sure you want to delete this game?</p>
                <p class="text-gray-400 text-sm mt-2">This action cannot be undone.</p>
                <div id="delete-game-info" class="mt-3 p-3 bg-gray-700 rounded-lg">
                    <p class="text-white font-medium" id="delete-game-title">Game Title</p>
                    <p class="text-gray-400 text-sm" id="delete-game-description">Game description...</p>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button id="cancel-delete-game" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200">
                    Cancel
                </button>
                <button id="confirm-delete-game" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition duration-200">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
class MyGamesComponent {
    constructor(containerId = 'my-games-component') {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('My Games container not found:', containerId);
            return;
        }

        this.workspaceId = this.container.dataset.workspaceId;
        this.limit = parseInt(this.container.dataset.limit) || 20;
        this.games = [];
        this.selectedGameId = null;
        this.deleteGameId = null;
        this.viewMode = 'grid'; // 'grid' or 'list'

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
        this.containerElement?.addEventListener('click', (e) => {
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
        });

        // Close modals on outside click
        this.gameModal?.addEventListener('click', (e) => {
            if (e.target === this.gameModal) {
                this.hideGameModal();
            }
        });

        this.deleteModal?.addEventListener('click', (e) => {
            if (e.target === this.deleteModal) {
                this.hideDeleteModal();
            }
        });
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
        this.showState('loading');

        try {
            const endpoint = this.workspaceId 
                ? `/api/workspaces/${this.workspaceId}/games`
                : `/api/games/recent?limit=${this.limit}`;

            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.games = data.games || [];
                this.renderGames();
                
                if (this.games.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('container');
                }
            } else {
                throw new Error(data.message || 'Failed to load games');
            }
        } catch (error) {
            console.error('Error loading games:', error);
            this.showState('error');
        }
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
                 data-game-id="${game.id}">
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
                                    data-game-id="${game.id}" title="Launch Game">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                        ` : ''}
                        <button class="delete-game-btn bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors"
                                data-game-id="${game.id}" title="Delete Game">
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

                    ${game.workspace && !this.workspaceId ? `
                        <div class="mt-2 pt-2 border-t border-gray-600">
                            <p class="text-xs text-indigo-400">
                                ${this.escapeHtml(game.workspace.name)} (${this.getEngineDisplayName(game.workspace.engine_type)})
                            </p>
                        </div>
                    ` : ''}
                    
                    ${game.conversation ? `
                        <div class="mt-2 pt-2 border-t border-gray-600">
                            <p class="text-xs text-green-400 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                Created from chat: ${this.escapeHtml(game.conversation.title || 'Untitled Chat')}
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
                 data-game-id="${game.id}">
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
                                ${game.workspace && !this.workspaceId ? `
                                    <p class="text-xs text-indigo-400 mt-1">
                                        ${this.escapeHtml(game.workspace.name)}
                                    </p>
                                ` : ''}
                                ${game.conversation ? `
                                    <p class="text-xs text-green-400 mt-1 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        From chat: ${this.escapeHtml(game.conversation.title || 'Untitled Chat')}
                                    </p>
                                ` : ''}
                            </div>

                            <!-- Action buttons -->
                            <div class="flex space-x-2 ml-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                ${hasPreview ? `
                                    <button class="launch-game-btn bg-blue-600 hover:bg-blue-700 text-white p-2 rounded transition-colors"
                                            data-game-id="${game.id}" title="Launch Game">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                ` : ''}
                                <button class="delete-game-btn bg-red-600 hover:bg-red-700 text-white p-2 rounded transition-colors"
                                        data-game-id="${game.id}" title="Delete Game">
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
                ${game.conversation ? `
                    <div class="md:col-span-2">
                        <label class="text-sm text-gray-400">Created from Chat</label>
                        <p class="text-white flex items-center">
                            <svg class="w-4 h-4 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            ${this.escapeHtml(game.conversation.title || 'Untitled Chat')}
                        </p>
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
    }

    hideGameModal() {
        this.gameModal?.classList.add('hidden');
        this.selectedGameId = null;
    }

    launchGame(gameId) {
        const game = this.games.find(g => g.id === gameId);
        if (!game || !game.display_url) return;

        window.open(game.display_url, '_blank');
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
    }

    hideDeleteModal() {
        this.deleteModal?.classList.add('hidden');
        this.deleteGameId = null;
    }

    async confirmDelete() {
        if (!this.deleteGameId) return;

        try {
            const response = await fetch(`/api/games/${this.deleteGameId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                // Remove game from local array
                this.games = this.games.filter(g => g.id !== this.deleteGameId);
                
                // Re-render the list
                this.renderGames();
                
                // Show appropriate state
                if (this.games.length === 0) {
                    this.showState('empty');
                } else {
                    this.showState('container');
                }

                // Dispatch deletion event
                const event = new CustomEvent('gameDeleted', {
                    detail: { gameId: this.deleteGameId }
                });
                this.container.dispatchEvent(event);

                this.hideDeleteModal();
            } else {
                throw new Error(data.message || 'Failed to delete game');
            }
        } catch (error) {
            console.error('Error deleting game:', error);
            alert('Failed to delete game. Please try again.');
        }
    }

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

    // Public methods for external control
    refresh() {
        this.loadGames();
    }

    getGames() {
        return this.games;
    }

    addGame(game) {
        this.games.unshift(game);
        this.renderGames();
        this.showState('container');
    }

    updateGame(gameId, updates) {
        const index = this.games.findIndex(g => g.id === gameId);
        if (index !== -1) {
            this.games[index] = { ...this.games[index], ...updates };
            this.renderGames();
        }
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const myGamesContainer = document.getElementById('my-games-component');
    if (myGamesContainer) {
        window.myGamesComponent = new MyGamesComponent();
    }
});
</script>
@endpush