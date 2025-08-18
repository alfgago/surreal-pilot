<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Show the user profile
     */
    public function index(): View
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
                // Add more stats as needed
            }
        } catch (\Exception $e) {
            // Graceful fallback
        }

        return view('profile.index', compact('user', 'company', 'stats'));
    }
}
