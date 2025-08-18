@extends('layouts.app')

@section('title', 'Choose Your Workspace')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center mb-4">
                <img src="{{ $engineInfo['icon'] }}" alt="{{ $engineInfo['name'] }} Icon" class="h-12 w-12 mr-3" />
                <h1 class="text-4xl font-bold text-gray-900">{{ $engineInfo['name'] }} Workspaces</h1>
            </div>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                Choose an existing workspace or create a new one to start building your game.
            </p>
            <div class="mt-4">
                <a href="{{ route('engine.clear') }}" 
                   onclick="event.preventDefault(); document.getElementById('clear-engine-form').submit();"
                   class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    Change Engine
                </a>
                <form id="clear-engine-form" action="{{ route('engine.clear') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <p class="text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <div>
                        <h3 class="text-red-800 font-medium">Please fix the following errors:</h3>
                        <ul class="mt-2 text-red-700 text-sm list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-2 gap-8">
            <!-- Existing Workspaces -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Your Workspaces</h2>
                
                @if($workspaces->count() > 0)
                    <div class="space-y-4">
                        @foreach($workspaces as $workspace)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow-md transition-all">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900">{{ $workspace->name }}</h3>
                                        @if($workspace->description)
                                            <p class="text-gray-600 text-sm mt-1">{{ $workspace->description }}</p>
                                        @endif
                                        <div class="flex items-center mt-2 text-xs text-gray-500">
                                            <span>Updated {{ $workspace->updated_at->diffForHumans() }}</span>
                                            @if($workspace->template)
                                                <span class="ml-3">• Template: {{ $workspace->template->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <form action="{{ route('workspace.select') }}" method="POST" class="ml-4">
                                        @csrf
                                        <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
                                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                                            Select
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="h-16 w-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No workspaces yet</h3>
                        <p class="text-gray-600">Create your first {{ $engineInfo['name'] }} workspace to get started.</p>
                    </div>
                @endif
            </div>

            <!-- Create New Workspace -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Create New Workspace</h2>
                
                <form action="{{ route('workspace.create') }}" method="POST" id="create-workspace-form">
                    @csrf
                    
                    <!-- Workspace Name -->
                    <div class="mb-6">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Workspace Name *
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="{{ old('name') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-300 @enderror"
                            placeholder="My {{ $engineInfo['name'] }} Game"
                            required
                        >
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="mb-6">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('description') border-red-300 @enderror"
                            placeholder="Describe your game project..."
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Template Selection -->
                    <div class="mb-6">
                        <label for="template_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Starting Template
                        </label>
                        <select 
                            id="template_id" 
                            name="template_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('template_id') border-red-300 @enderror"
                        >
                            <option value="">Start from scratch</option>
                            <!-- Templates will be loaded via JavaScript -->
                        </select>
                        @error('template_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500">Choose a template to get started quickly, or start from scratch.</p>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg font-semibold text-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        id="create-btn"
                    >
                        Create Workspace
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateSelect = document.getElementById('template_id');
    const createBtn = document.getElementById('create-btn');
    const createForm = document.getElementById('create-workspace-form');

    // Load available templates
    async function loadTemplates() {
        try {
            const response = await fetch('/workspace-selection/templates', {
                headers: {
                    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content}`,
                    'Accept': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.templates) {
                    data.templates.forEach(template => {
                        const option = document.createElement('option');
                        option.value = template.id;
                        option.textContent = template.name;
                        if (template.description) {
                            option.title = template.description;
                        }
                        templateSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading templates:', error);
        }
    }

    // Handle form submission
    createForm.addEventListener('submit', function() {
        createBtn.disabled = true;
        createBtn.textContent = 'Creating...';
    });

    // Load templates on page load
    loadTemplates();
});
</script>
@endsection