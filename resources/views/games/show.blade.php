@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-4">
                <a href="{{ route('games') }}"
                   class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">{{ $workspace->name ?? 'Game #' . $workspace->id }}</h1>
                    <p class="text-gray-400">{{ ucfirst($workspace->engine_type) }} Game</p>
                </div>
            </div>

            <div class="flex space-x-3">
                @if($workspace->engine_type === 'playcanvas' && $workspace->preview_url)
                    <a href="{{ $workspace->preview_url }}" target="_blank"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        Play Game
                    </a>
                @endif
                <a href="{{ route('chat') }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Continue in Chat
                </a>
            </div>
        </div>

        <!-- Game Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Game Preview -->
                @if($workspace->engine_type === 'playcanvas' && $workspace->preview_url)
                    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
                        <div class="p-4 border-b border-gray-700">
                            <h3 class="text-lg font-semibold text-white">Game Preview</h3>
                        </div>
                        <div class="aspect-video bg-black">
                            <iframe src="{{ $workspace->preview_url }}"
                                    class="w-full h-full"
                                    frameborder="0"
                                    allowfullscreen></iframe>
                        </div>
                    </div>
                @else
                    <div class="bg-gray-800 rounded-lg border border-gray-700 p-8">
                        <div class="text-center">
                            <div class="w-24 h-24 bg-{{ $workspace->engine_type === 'playcanvas' ? 'blue' : 'orange' }}-600 rounded-lg flex items-center justify-center mx-auto mb-4">
                                @if($workspace->engine_type === 'playcanvas')
                                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                @else
                                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                    </svg>
                                @endif
                            </div>
                            <h3 class="text-xl font-semibold text-white mb-2">{{ ucfirst($workspace->engine_type) }} Project</h3>
                            <p class="text-gray-400">
                                @if($workspace->engine_type === 'playcanvas')
                                    Your PlayCanvas game is ready for development. Continue building in the chat interface.
                                @else
                                    Your Unreal Engine project is configured. Use the desktop plugin to manage this project.
                                @endif
                            </p>
                        </div>
                    </div>
                @endif

                <!-- Description -->
                @if($workspace->description)
                    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-white mb-3">Description</h3>
                        <p class="text-gray-300">{{ $workspace->description }}</p>
                    </div>
                @endif

                <!-- Recent Activity -->
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-gray-300">Game created</span>
                            <span class="text-gray-400">{{ $workspace->created_at->diffForHumans() }}</span>
                        </div>
                        @if($workspace->updated_at > $workspace->created_at)
                            <div class="flex items-center space-x-3 text-sm">
                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                <span class="text-gray-300">Last updated</span>
                                <span class="text-gray-400">{{ $workspace->updated_at->diffForHumans() }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Game Info -->
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Game Info</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-400">Engine</label>
                            <p class="text-white font-medium">{{ ucfirst($workspace->engine_type) }}</p>
                        </div>

                        <div>
                            <label class="text-sm text-gray-400">Status</label>
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 {{ $workspace->status === 'ready' ? 'bg-green-500' : ($workspace->status === 'building' ? 'bg-yellow-500' : 'bg-red-500') }} rounded-full"></div>
                                <span class="text-white capitalize">{{ $workspace->status ?? 'Unknown' }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-gray-400">Created</label>
                            <p class="text-white">{{ $workspace->created_at->format('M j, Y') }}</p>
                        </div>

                        @if($workspace->engine_type === 'playcanvas')
                            <div>
                                <label class="text-sm text-gray-400">Workspace ID</label>
                                <p class="text-white font-mono">{{ $workspace->id }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Quick Actions</h3>

                    <div class="space-y-3">
                        <a href="{{ route('chat') }}?workspace={{ $workspace->engine_type }}"
                           class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <span>Continue in Chat</span>
                        </a>

                        @if($workspace->engine_type === 'playcanvas' && $workspace->preview_url)
                            <a href="{{ $workspace->preview_url }}" target="_blank"
                               class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Play Game</span>
                            </a>
                        @endif

                        <button class="w-full bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center space-x-2"
                                onclick="shareGame()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                            </svg>
                            <span>Share Game</span>
                        </button>

                        <button class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center space-x-2"
                                onclick="deleteGame()">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Delete Game</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function shareGame() {
    const gameUrl = window.location.href;
    const gameName = '{{ $workspace->name ?? "Game #" . $workspace->id }}';

    if (navigator.share) {
        navigator.share({
            title: gameName,
            text: `Check out my ${gameName} created with SurrealPilot!`,
            url: gameUrl
        });
    } else {
        // Fallback to clipboard
        navigator.clipboard.writeText(gameUrl).then(() => {
            alert('Game URL copied to clipboard!');
        });
    }
}

function deleteGame() {
    if (confirm('Are you sure you want to delete this game? This action cannot be undone.')) {
        // Implement delete functionality
        alert('Delete functionality will be implemented soon.');
    }
}
</script>
@endpush
