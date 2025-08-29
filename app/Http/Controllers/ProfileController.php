<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user profile page
     */
    public function index(): Response
    {
        $user = Auth::user();
        $company = $user->currentCompany;

        // Get user stats
        $stats = [
            'games_created' => 0,
            'messages_sent' => 0,
            'credits_used' => 0,
            'member_since' => $user->created_at,
        ];

        // Try to get actual stats if possible
        try {
            if ($company) {
                $stats['games_created'] = \App\Models\Workspace::where('company_id', $company->id)->count();
                
                // Get credit transactions for this user's company
                $stats['credits_used'] = $company->creditTransactions()
                    ->debits()
                    ->sum('amount');
                
                // Get conversation count (approximate messages sent)
                $stats['messages_sent'] = \App\Models\Conversation::whereHas('workspace', function ($query) use ($company) {
                    $query->where('company_id', $company->id);
                })->count();
            }
        } catch (\Exception $e) {
            // Graceful fallback
        }

        // Get available timezones
        $timezones = [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time (ET)',
            'America/Chicago' => 'Central Time (CT)',
            'America/Denver' => 'Mountain Time (MT)',
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'Europe/London' => 'London (GMT)',
            'Europe/Paris' => 'Paris (CET)',
            'Europe/Berlin' => 'Berlin (CET)',
            'Asia/Tokyo' => 'Tokyo (JST)',
            'Asia/Shanghai' => 'Shanghai (CST)',
            'Australia/Sydney' => 'Sydney (AEST)',
        ];

        return Inertia::render('Settings/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'timezone' => $user->timezone,
                'avatar_url' => $user->avatar_url,
                'email_notifications' => $user->email_notifications,
                'browser_notifications' => $user->browser_notifications,
                'created_at' => $user->created_at,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'plan' => $company->plan,
                'credits' => $company->credits,
            ] : null,
            'stats' => $stats,
            'timezones' => $timezones,
        ]);
    }

    /**
     * Update the user's profile
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        
        $user->update($request->validated());

        return redirect()->route('profile')->with('success', 'Profile updated successfully!');
    }
}
