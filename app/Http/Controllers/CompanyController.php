<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    public function settings(): \Illuminate\View\View
    {
        $company = Auth::user()->currentCompany()->first();
        abort_unless($company, 404);
        return view('company.settings', compact('company'));
    }

    public function updateSettings(Request $request)
    {
        $company = Auth::user()->currentCompany()->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['nullable', 'string'],
        ]);
        $company->update($validated);
        return back()->with('success', 'Company updated');
    }

    public function invite(Request $request)
    {
        $company = Auth::user()->currentCompany()->firstOrFail();
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['nullable', 'string'],
        ]);

        $user = User::firstOrCreate(['email' => $validated['email']], [
            'name' => strtok($validated['email'], '@'),
            'password' => bcrypt(str()->random(16)),
        ]);

        // Attach to company
        $company->users()->syncWithoutDetaching([$user->id => ['role' => $validated['role'] ?? 'member']]);

        return back()->with('success', 'User invited/added to company');
    }

    public function billing(): \Illuminate\View\View
    {
        $company = Auth::user()->currentCompany()->first();
        abort_unless($company, 404);
        return view('company.billing', compact('company'));
    }

    public function providerSettings(): \Illuminate\View\View
    {
        $company = Auth::user()->currentCompany()->first();
        abort_unless($company, 404);
        return view('company.provider-settings', compact('company'));
    }

    public function updateProviderSettings(Request $request)
    {
        $company = Auth::user()->currentCompany()->firstOrFail();
        $validated = $request->validate([
            'openai_api_key' => ['nullable', 'string'],
            'anthropic_api_key' => ['nullable', 'string'],
            'google_api_key' => ['nullable', 'string'],
        ]);

        // Store API keys securely (you might want to encrypt these)
        $settings = $company->settings ?? [];
        if ($validated['openai_api_key']) {
            $settings['openai_api_key'] = $validated['openai_api_key'];
        }
        if ($validated['anthropic_api_key']) {
            $settings['anthropic_api_key'] = $validated['anthropic_api_key'];
        }
        if ($validated['google_api_key']) {
            $settings['google_api_key'] = $validated['google_api_key'];
        }

        $company->update(['settings' => $settings]);
        return back()->with('success', 'Provider settings updated');
    }
}


