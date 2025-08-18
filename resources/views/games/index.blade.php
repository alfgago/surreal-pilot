@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">My Games</h1>
                    <p class="text-gray-400 mt-1">Manage your PlayCanvas and Unreal Engine projects</p>
                </div>
                <a href="{{ route('chat') }}"
                   class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span>Create New Game</span>
                </a>
            </div>
        </div>

        @if($workspaces->count() > 0)
            <!-- Games Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($workspaces as $workspace)
                    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden hover:border-gray-600 transition-colors">
                        <!-- Game Preview -->
                        <div class="h-48 bg-gradient-to-br from-gray-700 to-gray-800 flex items-center justify-center">
                            @if($workspace->engine_type === 'playcanvas')
                                <div class="text-center">
                                    <div class="w-16 h-16 bg-blue-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-blue-400 text-sm font-medium">PlayCanvas</span>
                                </div>
                            @else
                                <div class="text-center">
                                    <div class="w-16 h-16 bg-orange-600 rounded-lg flex items-center justify-center mx-auto mb-2">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-orange-400 text-sm font-medium">Unreal Engine</span>
                                </div>
                            @endif
                        </div>

                        <!-- Game Info -->
                        <div class="p-6">
                            <h3 class="text-xl font-semibold text-white mb-2">
                                {{ $workspace->name ?? 'Untitled Game #' . $workspace->id }}
                            </h3>
                            <p class="text-gray-400 text-sm mb-4">
                                {{ $workspace->description ?? 'No description available' }}
                            </p>

                            <!-- Status & Actions -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 {{ $workspace->status === 'ready' ? 'bg-green-500' : ($workspace->status === 'building' ? 'bg-yellow-500' : 'bg-red-500') }} rounded-full"></div>
                                    <span class="text-xs text-gray-400 capitalize">{{ $workspace->status ?? 'Unknown' }}</span>
                                </div>

                                <div class="flex space-x-2">
                                    @if($workspace->engine_type === 'playcanvas' && $workspace->preview_url)
                                        <a href="{{ $workspace->preview_url }}" target="_blank"
                                           class="text-blue-400 hover:text-blue-300 text-sm">
                                            Preview
                                        </a>
                                    @endif
                                    <a href="{{ route('games.show', $workspace->id) }}"
                                       class="text-purple-400 hover:text-purple-300 text-sm">
                                        Manage
                                    </a>
                                </div>
                            </div>

                            <!-- Metadata -->
                            <div class="mt-4 pt-4 border-t border-gray-700 text-xs text-gray-500">
                                <div class="flex justify-between">
                                    <span>Created {{ $workspace->created_at->diffForHumans() }}</span>
                                    <span>Updated {{ $workspace->updated_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="w-24 h-24 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M16 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">No games yet</h3>
                <p class="text-gray-400 mb-8 max-w-md mx-auto">
                    Start creating amazing games with Claude Sonnet 4. Choose between PlayCanvas for web/mobile games or Unreal Engine for high-end 3D experiences.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('chat') }}?workspace=playcanvas"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span>Create PlayCanvas Game</span>
                    </a>
                    <a href="{{ route('chat') }}?workspace=unreal"
                       class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                        <span>Create Unreal Game</span>
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add any games-specific JavaScript here
    console.log('Games page loaded');
});
</script>
@endpush
