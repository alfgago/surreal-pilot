<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Requests\UpdateApiKeysRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show the settings page
     */
    public function index(): Response
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

        // Get current user preferences
        $preferences = $user->preferences ?? [];
        
        // Get current API key status (don't expose actual keys)
        $apiKeyStatus = [];
        if ($company) {
            $apiKeyStatus = [
                'openai' => !empty($company->openai_api_key_enc),
                'anthropic' => !empty($company->anthropic_api_key_enc),
                'gemini' => !empty($company->gemini_api_key_enc),
                'playcanvas' => !empty($company->playcanvas_api_key),
            ];
        }

        return Inertia::render('Settings/Index', [
            'providers' => $providers,
            'preferences' => $preferences,
            'apiKeyStatus' => $apiKeyStatus,
            'isCompanyOwner' => $company && $company->user_id === $user->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'plan' => $company->plan,
            ] : null,
        ]);
    }    
/**
     * Update user preferences
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        
        // Update user preferences
        $preferences = $user->preferences ?? [];
        
        foreach ($request->validated() as $key => $value) {
            data_set($preferences, $key, $value);
        }
        
        $user->update(['preferences' => $preferences]);

        return redirect()->route('settings')->with('success', 'Settings updated successfully!');
    }

    /**
     * Update API keys for the company
     */
    public function updateApiKeys(UpdateApiKeysRequest $request): RedirectResponse
    {
        $company = Auth::user()->currentCompany;
        
        if (!$company) {
            return redirect()->route('settings')->with('error', 'No company found.');
        }

        $data = [];
        
        // Encrypt and store API keys
        if ($request->filled('openai_api_key')) {
            $data['openai_api_key_enc'] = Crypt::encryptString($request->openai_api_key);
        }
        
        if ($request->filled('anthropic_api_key')) {
            $data['anthropic_api_key_enc'] = Crypt::encryptString($request->anthropic_api_key);
        }
        
        if ($request->filled('gemini_api_key')) {
            $data['gemini_api_key_enc'] = Crypt::encryptString($request->gemini_api_key);
        }
        
        if ($request->filled('playcanvas_api_key')) {
            $data['playcanvas_api_key'] = $request->playcanvas_api_key;
        }
        
        if ($request->filled('playcanvas_project_id')) {
            $data['playcanvas_project_id'] = $request->playcanvas_project_id;
        }

        $company->update($data);

        return redirect()->route('settings')->with('success', 'API keys updated successfully!');
    }

    /**
     * Remove an API key
     */
    public function removeApiKey(string $provider): RedirectResponse
    {
        $company = Auth::user()->currentCompany;
        
        if (!$company || $company->user_id !== Auth::id()) {
            return redirect()->route('settings')->with('error', 'Unauthorized.');
        }

        $data = [];
        
        switch ($provider) {
            case 'openai':
                $data['openai_api_key_enc'] = null;
                break;
            case 'anthropic':
                $data['anthropic_api_key_enc'] = null;
                break;
            case 'gemini':
                $data['gemini_api_key_enc'] = null;
                break;
            case 'playcanvas':
                $data['playcanvas_api_key'] = null;
                $data['playcanvas_project_id'] = null;
                break;
            default:
                return redirect()->route('settings')->with('error', 'Invalid provider.');
        }

        $company->update($data);

        return redirect()->route('settings')->with('success', ucfirst($provider) . ' API key removed successfully!');
    }
}