<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Create a default company for the user
        $company = Company::create([
            'name' => $user->name . " Studio",
            'user_id' => $user->id,
            'credits' => 1000,
            'plan' => 'starter',
            'monthly_credit_limit' => 1000,
            'personal_company' => true,
        ]);
        // Attach owner to pivot
        if (method_exists($company, 'users')) {
            $company->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        }
        $user->forceFill(['current_company_id' => $company->id])->save();

        Auth::login($user);
        
        // New users need to select an engine first
        return redirect('/engine-selection');
    }

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            
            // If user hasn't selected an engine, redirect to engine selection
            if (!$user->hasSelectedEngine()) {
                return redirect()->intended('/engine-selection');
            }
            
            // If user has selected engine but no workspace in session, redirect to workspace selection
            if (!session('selected_workspace_id')) {
                return redirect()->intended('/workspace-selection');
            }
            
            // Otherwise go to chat
            return redirect()->intended('/chat');
        }

        return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
    }
}


