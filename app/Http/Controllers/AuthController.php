<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
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
        return redirect('/chat');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/chat');
        }

        return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
    }
}


