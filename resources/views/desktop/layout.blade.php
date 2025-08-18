<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SurrealPilot Desktop' }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom styles -->
    <style>
        .chat-container {
            height: calc(100vh - 120px);
        }

        .message-bubble {
            max-width: 80%;
            word-wrap: break-word;
        }

        .user-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .ai-message {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .typing-indicator {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-[#111217] text-white">
    <!-- Navigation -->
    <nav class="bg-[#111217] border-b border-slate-800 px-6 py-4">
        @include('components.app.header')
    </nav>

    <!-- Main Content -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Status Bar -->
    <div class="bg-[#111217] border-t border-slate-800 px-6 py-2">
        <div class="flex items-center justify-between text-sm text-slate-400">
            <div class="flex items-center space-x-4">
                <span id="connection-status" class="flex items-center">
                    <div class="w-2 h-2 bg-[#F8B14F] rounded-full mr-2"></div>
                    Connected
                </span>
                <span id="server-port">Port: Loading...</span>
            </div>
            <div class="flex items-center space-x-4">
                <span id="provider-status">Provider: Loading...</span>
                <span>Ready for UE Integration</span>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // CSRF token setup
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Update status bar
        async function updateStatus() {
            try {
                const response = await fetch('/api/desktop/server-info');
                const data = await response.json();

                document.getElementById('server-port').textContent = `Port: ${data.port}`;

                // Update connection status
                const statusEl = document.getElementById('connection-status');
                statusEl.innerHTML = '<div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>Connected';

            } catch (error) {
                console.error('Failed to update status:', error);
                const statusEl = document.getElementById('connection-status');
                statusEl.innerHTML = '<div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div>Disconnected';
            }
        }

        // Update provider status
        async function updateProviderStatus() {
            try {
                const response = await fetch('/api/providers');
                const data = await response.json();

                document.getElementById('provider-status').textContent = `Provider: ${data.default || 'None'}`;

            } catch (error) {
                console.error('Failed to update provider status:', error);
                document.getElementById('provider-status').textContent = 'Provider: Error';
            }
        }

        // Initialize status updates
        document.addEventListener('DOMContentLoaded', function() {
            updateStatus();
            updateProviderStatus();

            // Update status every 30 seconds
            setInterval(updateStatus, 30000);
            setInterval(updateProviderStatus, 30000);
        });
    </script>

    @stack('scripts')
</body>
</html>
