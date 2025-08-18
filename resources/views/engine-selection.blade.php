@extends('layouts.app')

@section('title', 'Choose Your Game Engine')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Choose Your Game Engine</h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                Select the game engine you'd like to work with. SurrealPilot provides AI assistance tailored to your chosen platform.
            </p>
        </div>

        <!-- Engine Selection Cards -->
        <div class="grid md:grid-cols-2 gap-8 mb-8" id="engine-selection">
            <!-- Loading State -->
            <div id="loading-engines" class="col-span-2 text-center py-12">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                <p class="text-gray-600">Loading available engines...</p>
            </div>

            <!-- Engine Cards will be populated by JavaScript -->
        </div>

        <!-- Continue Button -->
        <div class="text-center">
            <button 
                id="continue-btn" 
                class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold text-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                disabled
            >
                Continue to Workspace Selection
            </button>
        </div>

        <!-- Error Message -->
        <div id="error-message" class="hidden mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                    <div class="mt-2 text-sm text-red-700" id="error-text"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Engine Card Template -->
<template id="engine-card-template">
    <div class="engine-card bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 cursor-pointer border-2 border-transparent hover:border-indigo-200" data-engine-type="">
        <div class="p-8">
            <!-- Engine Icon -->
            <div class="flex justify-center mb-6">
                <img class="engine-icon h-16 w-16" src="" alt="" />
            </div>
            
            <!-- Engine Info -->
            <div class="text-center mb-6">
                <h3 class="engine-name text-2xl font-bold text-gray-900 mb-2"></h3>
                <p class="engine-description text-gray-600 mb-4"></p>
            </div>
            
            <!-- Features -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Key Features:</h4>
                <ul class="engine-features space-y-2"></ul>
            </div>
            
            <!-- Selection Indicator -->
            <div class="selection-indicator hidden">
                <div class="flex items-center justify-center text-indigo-600">
                    <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="font-semibold">Selected</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const engineSelection = document.getElementById('engine-selection');
    const loadingEngines = document.getElementById('loading-engines');
    const continueBtn = document.getElementById('continue-btn');
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const engineCardTemplate = document.getElementById('engine-card-template');
    
    let selectedEngine = null;
    let availableEngines = [];

    // Load available engines
    async function loadEngines() {
        try {
            const response = await fetch('/api/engines', {
                headers: {
                    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content}`,
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load engines');
            }

            availableEngines = data.data;
            renderEngines();
            
        } catch (error) {
            console.error('Error loading engines:', error);
            showError('Failed to load available engines. Please refresh the page and try again.');
        }
    }

    // Render engine cards
    function renderEngines() {
        loadingEngines.style.display = 'none';
        
        availableEngines.forEach(engine => {
            const card = createEngineCard(engine);
            engineSelection.appendChild(card);
        });
    }

    // Create engine card from template
    function createEngineCard(engine) {
        const template = engineCardTemplate.content.cloneNode(true);
        const card = template.querySelector('.engine-card');
        
        // Set data attributes
        card.setAttribute('data-engine-type', engine.type);
        
        // Populate content
        template.querySelector('.engine-icon').src = engine.icon;
        template.querySelector('.engine-icon').alt = engine.name + ' Icon';
        template.querySelector('.engine-name').textContent = engine.name;
        template.querySelector('.engine-description').textContent = engine.description;
        
        // Populate features
        const featuresList = template.querySelector('.engine-features');
        engine.features.forEach(feature => {
            const li = document.createElement('li');
            li.className = 'flex items-center text-sm text-gray-600';
            li.innerHTML = `
                <svg class="h-4 w-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                ${feature}
            `;
            featuresList.appendChild(li);
        });
        
        // Add click handler
        card.addEventListener('click', () => selectEngine(engine.type));
        
        return template;
    }

    // Select an engine
    function selectEngine(engineType) {
        // Remove selection from all cards
        document.querySelectorAll('.engine-card').forEach(card => {
            card.classList.remove('border-indigo-500', 'bg-indigo-50');
            card.querySelector('.selection-indicator').classList.add('hidden');
        });
        
        // Add selection to clicked card
        const selectedCard = document.querySelector(`[data-engine-type="${engineType}"]`);
        selectedCard.classList.add('border-indigo-500', 'bg-indigo-50');
        selectedCard.querySelector('.selection-indicator').classList.remove('hidden');
        
        selectedEngine = engineType;
        continueBtn.disabled = false;
        hideError();
    }

    // Save engine preference and continue
    async function saveEnginePreference() {
        if (!selectedEngine) return;
        
        continueBtn.disabled = true;
        continueBtn.textContent = 'Saving...';
        
        try {
            const response = await fetch('/api/user/engine-preference', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    engine_type: selectedEngine
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to save engine preference');
            }

            // Redirect to workspace selection
            window.location.href = '/workspace-selection';
            
        } catch (error) {
            console.error('Error saving engine preference:', error);
            showError('Failed to save your engine preference. Please try again.');
            continueBtn.disabled = false;
            continueBtn.textContent = 'Continue to Workspace Selection';
        }
    }

    // Show error message
    function showError(message) {
        errorText.textContent = message;
        errorMessage.classList.remove('hidden');
    }

    // Hide error message
    function hideError() {
        errorMessage.classList.add('hidden');
    }

    // Event listeners
    continueBtn.addEventListener('click', saveEnginePreference);

    // Load engines on page load
    loadEngines();
});
</script>
@endsection