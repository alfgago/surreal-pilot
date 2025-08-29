<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function settings(): Response
    {
        $user = Auth::user();
        $company = $user->currentCompany()->first();
        abort_unless($company, 404);

        // Load company with users and their roles, plus pending invitations
        $company->load([
            'users' => function ($query) {
                $query->select(['users.id', 'users.name', 'users.email'])
                      ->withPivot(['role', 'created_at']);
            },
            'invitations'
        ]);

        // Check if current user is the owner
        $company->is_owner = $company->user_id === $user->id;

        return Inertia::render('Company/Settings', [
            'company' => $company,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany()->firstOrFail();
        
        // Only allow owner to update company settings
        abort_unless($company->user_id === $user->id, 403, 'Only company owners can update settings');
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'in:starter,pro,enterprise'],
        ]);
        
        $company->update($validated);
        
        return back()->with('success', 'Company settings updated successfully');
    }

    public function invite(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany()->firstOrFail();
        
        // Only allow owner to invite users
        abort_unless($company->user_id === $user->id, 403, 'Only company owners can invite users');
        
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:member,admin'],
        ]);

        // Check if user is already a member
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $company->users()->where('users.id', $existingUser->id)->exists()) {
            return back()->withErrors(['email' => 'This user is already a member of the company']);
        }

        // Check if invitation already exists
        $existingInvitation = $company->invitations()->where('email', $validated['email'])->first();
        if ($existingInvitation) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email']);
        }

        // Create invitation
        $company->invitations()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        // If user already exists, add them directly to the company
        if ($existingUser) {
            $company->users()->attach($existingUser->id, ['role' => $validated['role']]);
            // Remove the invitation since user was added directly
            $company->invitations()->where('email', $validated['email'])->delete();
            return back()->with('success', 'User added to company successfully');
        }

        return back()->with('success', 'Invitation sent successfully');
    }

    public function billing(): Response
    {
        $user = Auth::user();
        $company = $user->currentCompany ?: $user->companies()->first();
        abort_unless($company, 404);

        // Get credit manager for analytics
        $creditManager = app(\App\Services\CreditManager::class);
        
        // Get usage analytics for current month
        $currentMonth = now()->startOfMonth();
        $nextMonth = now()->addMonth()->startOfMonth();
        $analytics = $creditManager->getUsageAnalytics($company, $currentMonth, $nextMonth);
        
        // Get engine usage analytics for current month
        $engineAnalytics = $creditManager->getEngineUsageAnalytics($company, $currentMonth, $nextMonth);
        
        // Get recent transactions
        $recentTransactions = $creditManager->getRecentTransactions($company, 20);
        
        // Get balance summary
        $balanceSummary = $creditManager->getBalanceSummary($company);
        
        // Get available subscription plans
        $subscriptionPlans = \App\Models\SubscriptionPlan::all();
        
        // Get current subscription info (subscriptions are tied to companies)
        // Note: Subscription functionality requires database schema updates
        $currentSubscription = null;
        
        // Get payment methods
        $paymentMethods = [];
        $defaultPaymentMethod = null;
        
        // Note: Payment methods functionality requires Stripe setup
        // For now, return empty arrays to prevent errors
        $paymentMethods = [];
        $defaultPaymentMethod = null;
        
        return Inertia::render('Company/Billing', [
            'company' => $company,
            'analytics' => $analytics,
            'engineAnalytics' => $engineAnalytics,
            'recentTransactions' => $recentTransactions,
            'balanceSummary' => $balanceSummary,
            'subscriptionPlans' => $subscriptionPlans,
            'currentSubscription' => $currentSubscription,
            'paymentMethods' => $paymentMethods,
            'defaultPaymentMethod' => $defaultPaymentMethod,
        ]);
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

    public function removeUser(Request $request, $userId)
    {
        $currentUser = Auth::user();
        $company = $currentUser->currentCompany()->firstOrFail();
        
        // Only allow owner to remove users
        abort_unless($company->user_id === $currentUser->id, 403, 'Only company owners can remove users');
        
        $userToRemove = User::findOrFail($userId);
        
        // Cannot remove the owner
        if ($userToRemove->id === $company->user_id) {
            return back()->withErrors(['error' => 'Cannot remove the company owner']);
        }
        
        // Check if user is actually a member of this company
        $membership = $company->users()->where('users.id', $userToRemove->id)->first();
        if (!$membership) {
            return back()->withErrors(['error' => 'User is not a member of this company']);
        }
        
        $company->users()->detach($userToRemove->id);
        
        return back()->with('success', 'User removed from company successfully');
    }

    public function cancelInvitation(Request $request, CompanyInvitation $invitation)
    {
        $user = Auth::user();
        $company = $user->currentCompany()->firstOrFail();
        
        // Only allow owner to cancel invitations
        abort_unless($company->user_id === $user->id, 403, 'Only company owners can cancel invitations');
        
        // Check if invitation belongs to this company
        abort_unless($invitation->company_id === $company->id, 404);
        
        $invitation->delete();
        
        return back()->with('success', 'Invitation canceled successfully');
    }

    public function updateUserRole(Request $request, $userId)
    {
        $currentUser = Auth::user();
        $company = $currentUser->currentCompany()->firstOrFail();
        
        // Only allow owner to update user roles
        abort_unless($company->user_id === $currentUser->id, 403, 'Only company owners can update user roles');
        
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:member,admin'],
        ]);
        
        $userToUpdate = User::findOrFail($userId);
        
        // Cannot change owner role
        if ($userToUpdate->id === $company->user_id) {
            return back()->withErrors(['error' => 'Cannot change the company owner role']);
        }
        
        // Check if user is actually a member of this company
        $membership = $company->users()->where('users.id', $userToUpdate->id)->first();
        if (!$membership) {
            return back()->withErrors(['error' => 'User is not a member of this company']);
        }
        
        $company->users()->updateExistingPivot($userToUpdate->id, ['role' => $validated['role']]);
        
        return back()->with('success', 'User role updated successfully');
    }

    public function updatePreferences(Request $request)
    {
        $user = Auth::user();
        $company = $user->currentCompany()->firstOrFail();
        
        // Only allow owner to update company preferences
        abort_unless($company->user_id === $user->id, 403, 'Only company owners can update preferences');
        
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'max:50'],
            'default_engine' => ['required', 'string', 'in:playcanvas,unreal'],
            'auto_save' => ['boolean'],
            'notifications_enabled' => ['boolean'],
            'collaboration_enabled' => ['boolean'],
            'public_templates' => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);
        
        $company->update(['preferences' => $validated]);
        
        return back()->with('success', 'Company preferences updated successfully');
    }
}


