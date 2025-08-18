<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1f2937">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SurrealPilot">
    <title>{{ $title ?? 'SurrealPilot Mobile' }}</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Mobile-optimized styles -->
    <style>
        /* Touch-friendly sizing */
        .touch-target {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Mobile chat interface */
        .mobile-chat-container {
            height: 100vh;
            height: 100dvh; /* Dynamic viewport height for mobile */
        }
        
        /* Message bubbles optimized for mobile */
        .mobile-message-bubble {
            max-width: 85%;
            word-wrap: break-word;
            font-size: 16px; /* Prevent zoom on iOS */
        }
        
        /* Touch-friendly input */
        .mobile-input {
            font-size: 16px; /* Prevent zoom on iOS */
            border-radius: 12px;
            padding: 12px 16px;
        }
        
        /* Modal optimizations */
        .mobile-modal {
            border-radius: 20px 20px 0 0;
            max-height: 90vh;
        }
        
        /* Landscape orientation adjustments */
        @media screen and (orientation: landscape) and (max-height: 500px) {
            .landscape-compact {
                padding: 8px;
            }
            
            .landscape-compact .mobile-message-bubble {
                font-size: 14px;
            }
            
            .landscape-compact .mobile-input {
                padding: 8px 12px;
            }
        }
        
        /* Safe area handling for notched devices */
        .safe-area-top {
            padding-top: env(safe-area-inset-top);
        }
        
        .safe-area-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        /* Smooth animations for mobile */
        .mobile-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Loading states */
        .mobile-loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Haptic feedback simulation */
        .haptic-feedback:active {
            transform: scale(0.95);
            transition: transform 0.1s ease;
        }
    </style>
</head>
<body class="bg-gray-900 text-white overflow-hidden">
    <!-- Mobile Navigation -->
    <nav class="bg-gray-800 border-b border-gray-700 safe-area-top">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h1 class="text-lg font-bold text-white">SurrealPilot</h1>
                    <div id="mobile-connection-status" class="flex items-center">
                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-xs text-green-400">Ready</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-2">
                    <!-- Credit Balance -->
                    <div id="mobile-credit-badge" class="bg-green-900 text-green-300 px-2 py-1 rounded-full text-xs font-medium">
                        <span id="mobile-credit-amount">Loading...</span>
                    </div>
                    
                    <!-- Menu Button -->
                    <button id="mobile-menu-btn" class="touch-target p-2 text-gray-300 hover:text-white haptic-feedback">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden mobile-transition">
        <div class="fixed right-0 top-0 h-full w-80 bg-gray-800 shadow-xl mobile-transition transform translate-x-full" id="mobile-menu-panel">
            <div class="p-4 border-b border-gray-700 safe-area-top">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">Menu</h2>
                    <button id="mobile-menu-close" class="touch-target p-2 text-gray-300 hover:text-white haptic-feedback">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-4 space-y-4">
                <!-- Navigation Links -->
                <div class="space-y-2">
                    <a href="{{ route('mobile.chat') }}" class="block w-full text-left px-4 py-3 text-white bg-blue-600 rounded-lg touch-target haptic-feedback">
                        <svg class="w-5 h-5 inline mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Chat
                    </a>
                    
                    <button id="mobile-demos-btn" class="block w-full text-left px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg touch-target haptic-feedback mobile-transition">
                        <svg class="w-5 h-5 inline mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        Demo Templates
                    </button>
                    
                    <button id="mobile-workspaces-btn" class="block w-full text-left px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg touch-target haptic-feedback mobile-transition">
                        <svg class="w-5 h-5 inline mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        My Workspaces
                    </button>
                </div>
                
                <!-- Settings -->
                <div class="border-t border-gray-700 pt-4">
                    <h3 class="text-sm font-medium text-gray-400 mb-2">Settings</h3>
                    <div class="space-y-2">
                        <div class="px-4 py-2">
                            <label class="block text-sm text-gray-300 mb-1">AI Provider</label>
                            <select id="mobile-provider-select" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white mobile-input">
                                @foreach($providers ?? [] as $key => $provider)
                                    <option value="{{ $key }}">{{ $provider['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // CSRF token setup
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
        const mobileMenuPanel = document.getElementById('mobile-menu-panel');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        
        function openMobileMenu() {
            mobileMenuOverlay.classList.remove('hidden');
            setTimeout(() => {
                mobileMenuPanel.classList.remove('translate-x-full');
            }, 10);
        }
        
        function closeMobileMenu() {
            mobileMenuPanel.classList.add('translate-x-full');
            setTimeout(() => {
                mobileMenuOverlay.classList.add('hidden');
            }, 300);
        }
        
        mobileMenuBtn.addEventListener('click', openMobileMenu);
        mobileMenuClose.addEventListener('click', closeMobileMenu);
        mobileMenuOverlay.addEventListener('click', (e) => {
            if (e.target === mobileMenuOverlay) {
                closeMobileMenu();
            }
        });
        
        // Credit balance updates
        async function updateMobileCreditBalance() {
            try {
                const response = await fetch('/api/credits/balance');
                const data = await response.json();
                
                const creditAmount = document.getElementById('mobile-credit-amount');
                const creditBadge = document.getElementById('mobile-credit-badge');
                
                creditAmount.textContent = data.credits.toLocaleString();
                
                // Update badge color based on credit level
                if (data.credits < 100) {
                    creditBadge.className = 'bg-red-900 text-red-300 px-2 py-1 rounded-full text-xs font-medium';
                } else if (data.credits < 500) {
                    creditBadge.className = 'bg-yellow-900 text-yellow-300 px-2 py-1 rounded-full text-xs font-medium';
                } else {
                    creditBadge.className = 'bg-green-900 text-green-300 px-2 py-1 rounded-full text-xs font-medium';
                }
            } catch (error) {
                console.error('Failed to update credit balance:', error);
                document.getElementById('mobile-credit-amount').textContent = 'Error';
            }
        }
        
        // Initialize mobile features
        document.addEventListener('DOMContentLoaded', function() {
            updateMobileCreditBalance();
            
            // Update credit balance every 30 seconds
            setInterval(updateMobileCreditBalance, 30000);
            
            // Add haptic feedback simulation for supported devices
            if ('vibrate' in navigator) {
                document.querySelectorAll('.haptic-feedback').forEach(element => {
                    element.addEventListener('touchstart', () => {
                        navigator.vibrate(10); // Light haptic feedback
                    });
                });
            }
        });
    </script>
    
    @stack('scripts')
</body>
</html>