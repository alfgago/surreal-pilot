@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-bold text-white mb-8">Billing & Credits</h1>

    <!-- Current Plan -->
    <div class="bg-gray-800 border border-gray-700 rounded p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Current Plan</h2>
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-300">{{ $company->plan ?? 'Free' }} Plan</p>
                <p class="text-sm text-gray-400">{{ $company->name }}</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-white">{{ number_format($company->credits ?? 0) }}</p>
                <p class="text-sm text-gray-400">Credits remaining</p>
            </div>
        </div>
    </div>

    <!-- Billing Actions -->
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Upgrade Plan -->
        <div class="bg-gray-800 border border-gray-700 rounded p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Upgrade Plan</h3>
            <p class="text-gray-300 mb-4">Get more credits and features with a premium plan.</p>
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                View Plans
            </button>
        </div>

        <!-- Buy Credits -->
        <div class="bg-gray-800 border border-gray-700 rounded p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Buy Credits</h3>
            <p class="text-gray-300 mb-4">Purchase additional credits for your projects.</p>
            <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                Buy Credits
            </button>
        </div>
    </div>

    <!-- Usage History -->
    <div class="bg-gray-800 border border-gray-700 rounded p-6 mt-8">
        <h3 class="text-lg font-semibold text-white mb-4">Usage History</h3>
        <div class="text-gray-400">
            <p>Usage history will be displayed here.</p>
        </div>
    </div>
</div>
@endsection