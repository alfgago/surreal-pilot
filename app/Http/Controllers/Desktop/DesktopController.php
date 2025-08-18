<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesktopController extends Controller
{
    /**
     * Show the desktop application main page
     */
    public function index(): View
    {
        return view('desktop.index', [
            'title' => 'SurrealPilot Desktop',
            'version' => config('nativephp.version'),
        ]);
    }

    /**
     * Show the desktop settings page
     */
    public function settings(): View
    {
        return view('desktop.settings', [
            'title' => 'Settings - SurrealPilot',
        ]);
    }
}