@extends('layouts.app')

@section('title', 'My Games Component Demo')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">My Games Component Demo</h1>
            <p class="text-gray-400">
                This page demonstrates the My Games component functionality including game grid/list view, 
                game selection, launch functionality, and deletion with confirmation.
            </p>
        </div>

        <!-- Demo Controls -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Demo Controls</h2>
            <div class="flex flex-wrap gap-4">
                <button id="demo-add-game" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Add Demo Game
                </button>
                <button id="demo-clear-games" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Clear All Games
                </button>
                <button id="demo-load-sample" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Load Sample Games
                </button>
                <button id="demo-simulate-error" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Simulate Error
                </button>
            </div>
        </div>

        <!-- Component Variations -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Full Featured Component -->
            <div>
                <h2 class="text-xl font-semibold text-white mb-4">Full Featured (All Workspaces)</h2>
                <x-my-games 
                    :show-workspace-info="true"
                    :limit="10"
                    container-class="bg-gray-800 rounded-lg border border-gray-700"
                />
            </div>

            <!-- Workspace Specific Component -->
            <div>
                <h2 class="text-xl font-semibold text-white mb-4">Workspace Specific</h2>
                <x-my-games 
                    :workspace-id="1"
                    :show-workspace-info="false"
                    :limit="5"
                    container-class="bg-gray-800 rounded-lg border border-gray-700"
                />
            </div>
        </div>

        <!-- Compact Version -->
        <div class="mt-8">
            <h2 class="text-xl font-semibold text-white mb-4">Compact Version</h2>
            <div class="max-w-md">
                <x-my-games 
                    :limit="3"
                    container-class="bg-gray-800 rounded-lg border border-gray-700"
                    header-class="p-3 border-b border-gray-700"
                    grid-class="p-3"
                    empty-state-class="text-center py-8"
                />
            </div>
        </div>

        <!-- Usage Examples -->
        <div class="mt-12 bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Usage Examples</h2>
            
            <div class="space-y-6">
                <!-- Basic Usage -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-2">Basic Usage</h3>
                    <div class="bg-gray-900 rounded-lg p-4">
                        <code class="text-green-400 text-sm">
                            &lt;x-my-games /&gt;
                        </code>
                    </div>
                </div>

                <!-- Workspace Specific -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-2">Workspace Specific</h3>
                    <div class="bg-gray-900 rounded-lg p-4">
                        <code class="text-green-400 text-sm">
                            &lt;x-my-games :workspace-id="$workspace->id" /&gt;
                        </code>
                    </div>
                </div>

                <!-- Custom Styling -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-2">Custom Styling</h3>
                    <div class="bg-gray-900 rounded-lg p-4">
                        <code class="text-green-400 text-sm">
                            &lt;x-my-games<br>
                            &nbsp;&nbsp;:limit="20"<br>
                            &nbsp;&nbsp;:show-workspace-info="true"<br>
                            &nbsp;&nbsp;container-class="bg-blue-900 rounded-xl"<br>
                            &nbsp;&nbsp;header-class="p-6 border-b border-blue-700"<br>
                            /&gt;
                        </code>
                    </div>
                </div>

                <!-- JavaScript Integration -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-2">JavaScript Integration</h3>
                    <div class="bg-gray-900 rounded-lg p-4">
                        <code class="text-green-400 text-sm">
                            // Access the component instance<br>
                            const myGames = window.myGamesComponent;<br><br>
                            
                            // Listen for events<br>
                            document.addEventListener('gameDeleted', (e) => {<br>
                            &nbsp;&nbsp;console.log('Game deleted:', e.detail.gameId);<br>
                            });<br><br>
                            
                            // Refresh games<br>
                            myGames.refresh();<br><br>
                            
                            // Add a new game<br>
                            myGames.addGame(gameData);
                        </code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features List -->
        <div class="mt-8 bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Component Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-medium text-white mb-3">Display Features</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Grid and list view modes</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Game thumbnails and metadata</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Engine type indicators</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Creation dates and status</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Empty state with call-to-action</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-medium text-white mb-3">Interactive Features</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Game launch functionality</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Game deletion with confirmation</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Detailed game modal</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Refresh and error handling</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Responsive design</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Demo controls
    const addGameBtn = document.getElementById('demo-add-game');
    const clearGamesBtn = document.getElementById('demo-clear-games');
    const loadSampleBtn = document.getElementById('demo-load-sample');
    const simulateErrorBtn = document.getElementById('demo-simulate-error');

    // Sample game data
    const sampleGames = [
        {
            id: 1,
            title: "Space Adventure",
            description: "An exciting space exploration game with stunning visuals and engaging gameplay.",
            engine_type: "playcanvas",
            thumbnail_url: null,
            preview_url: "https://playcanv.as/p/JtL2iqIH/",
            published_url: "https://playcanv.as/p/JtL2iqIH/",
            display_url: "https://playcanv.as/p/JtL2iqIH/",
            is_published: true,
            has_preview: true,
            has_thumbnail: false,
            created_at: new Date(Date.now() - 86400000 * 2).toISOString(), // 2 days ago
            updated_at: new Date(Date.now() - 86400000).toISOString(), // 1 day ago
            workspace: {
                id: 1,
                name: "Space Games Workspace",
                engine_type: "playcanvas"
            }
        },
        {
            id: 2,
            title: "Racing Championship",
            description: "High-speed racing game with realistic physics and multiple tracks.",
            engine_type: "unreal",
            thumbnail_url: null,
            preview_url: null,
            published_url: null,
            display_url: null,
            is_published: false,
            has_preview: false,
            has_thumbnail: false,
            created_at: new Date(Date.now() - 86400000 * 5).toISOString(), // 5 days ago
            updated_at: new Date(Date.now() - 86400000 * 3).toISOString(), // 3 days ago
            workspace: {
                id: 2,
                name: "Racing Games Workspace",
                engine_type: "unreal"
            }
        },
        {
            id: 3,
            title: "Puzzle Master",
            description: "Brain-teasing puzzle game with over 100 challenging levels.",
            engine_type: "playcanvas",
            thumbnail_url: null,
            preview_url: "https://playcanv.as/p/KH37bnOk/",
            published_url: null,
            display_url: "https://playcanv.as/p/KH37bnOk/",
            is_published: false,
            has_preview: true,
            has_thumbnail: false,
            created_at: new Date(Date.now() - 86400000 * 7).toISOString(), // 1 week ago
            updated_at: new Date(Date.now() - 86400000 * 2).toISOString(), // 2 days ago
            workspace: {
                id: 3,
                name: "Puzzle Games Workspace",
                engine_type: "playcanvas"
            }
        }
    ];

    let gameIdCounter = 4;

    // Add demo game
    addGameBtn?.addEventListener('click', function() {
        const newGame = {
            id: gameIdCounter++,
            title: `Demo Game ${gameIdCounter - 1}`,
            description: `This is a demo game created for testing purposes. Game ID: ${gameIdCounter - 1}`,
            engine_type: Math.random() > 0.5 ? 'playcanvas' : 'unreal',
            thumbnail_url: null,
            preview_url: Math.random() > 0.5 ? "https://playcanv.as/p/JtL2iqIH/" : null,
            published_url: null,
            display_url: Math.random() > 0.5 ? "https://playcanv.as/p/JtL2iqIH/" : null,
            is_published: Math.random() > 0.7,
            has_preview: Math.random() > 0.5,
            has_thumbnail: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            workspace: {
                id: Math.floor(Math.random() * 3) + 1,
                name: `Demo Workspace ${Math.floor(Math.random() * 3) + 1}`,
                engine_type: Math.random() > 0.5 ? 'playcanvas' : 'unreal'
            }
        };

        // Add to all My Games components
        const components = document.querySelectorAll('#my-games-component');
        components.forEach(container => {
            const component = container.myGamesComponent || window.myGamesComponent;
            if (component) {
                component.addGame(newGame);
            }
        });
    });

    // Clear all games
    clearGamesBtn?.addEventListener('click', function() {
        const components = document.querySelectorAll('#my-games-component');
        components.forEach(container => {
            const component = container.myGamesComponent || window.myGamesComponent;
            if (component) {
                component.games = [];
                component.renderGames();
                component.showState('empty');
            }
        });
    });

    // Load sample games
    loadSampleBtn?.addEventListener('click', function() {
        const components = document.querySelectorAll('#my-games-component');
        components.forEach(container => {
            const component = container.myGamesComponent || window.myGamesComponent;
            if (component) {
                component.games = [...sampleGames];
                component.renderGames();
                component.showState('container');
            }
        });
    });

    // Simulate error
    simulateErrorBtn?.addEventListener('click', function() {
        const components = document.querySelectorAll('#my-games-component');
        components.forEach(container => {
            const component = container.myGamesComponent || window.myGamesComponent;
            if (component) {
                component.showState('error');
            }
        });
    });

    // Listen for component events
    document.addEventListener('gameDeleted', function(e) {
        console.log('Game deleted:', e.detail.gameId);
        // You could show a toast notification here
    });

    // Override the loadGames method for demo purposes
    setTimeout(() => {
        const components = document.querySelectorAll('#my-games-component');
        components.forEach(container => {
            const component = container.myGamesComponent || window.myGamesComponent;
            if (component) {
                // Store original method
                const originalLoadGames = component.loadGames;
                
                // Override with demo data
                component.loadGames = function() {
                    this.showState('loading');
                    
                    // Simulate API delay
                    setTimeout(() => {
                        this.games = [...sampleGames];
                        this.renderGames();
                        this.showState('container');
                    }, 1000);
                };
                
                // Store reference to component on container
                container.myGamesComponent = component;
            }
        });
    }, 100);
});
</script>
@endpush