<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Show the settings page
     */
    public function index(): View
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Get available AI providers
        $providers = [
            'anthropic' => [
                'name' => 'Claude Sonnet 4',
                'description' => 'Advanced reasoning and coding capabilities',
                'requires_key' => true,
                'status' => 'available'
            ],
            'openai' => [
                'name' => 'OpenAI GPT-4',
                'description' => 'Powerful language model for creative tasks',
                'requires_key' => true,
                'status' => 'available'
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Multimodal AI with vision capabilities',
                'requires_key' => true,
                'status' => 'available'
            ],
            'ollama' => [
                'name' => 'Ollama (Local)',
                'description' => 'Run models locally on your machine',
                'requires_key' => false,
                'status' => 'available'
            ]
        ];

        // Get current settings
        $settings = [
            'default_provider' => 'anthropic',
            'temperature' => 0.2,
            'stream_responses' => true,
            'save_history' => true,
            'notifications' => true,
        ];

        return view('settings.index', compact('providers', 'settings', 'user', 'company'));
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'default_provider' => 'required|string|in:anthropic,openai,gemini,ollama',
            'temperature' => 'required|numeric|min:0|max:1',
            'stream_responses' => 'boolean',
            'save_history' => 'boolean',
            'notifications' => 'boolean',
        ]);

        // Save settings (you can implement this based on your preferences)
        // For now, just redirect back with success message

        return redirect()->route('settings')->with('success', 'Settings updated successfully!');
    }
}
