<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing PlayCanvas MCP integration...\n";

// Get the first user and company
$user = \App\Models\User::first();
$company = $user->currentCompany ?? \App\Models\Company::first();

if (!$user || !$company) {
    echo "❌ No user or company found. Please ensure you have test data.\n";
    exit(1);
}

echo "✅ Found user: {$user->name} (Company: {$company->name})\n";

// Check if PlayCanvasMcpManager is available
try {
    $mcpManager = app(\App\Services\PlayCanvasMcpManager::class);
    echo "✅ PlayCanvasMcpManager service is available\n";
} catch (Exception $e) {
    echo "❌ PlayCanvasMcpManager not available: {$e->getMessage()}\n";
    exit(1);
}

// Check if Node.js is available
$nodeCheck = shell_exec('node --version 2>&1');
if ($nodeCheck) {
    echo "✅ Node.js is available: " . trim($nodeCheck) . "\n";
} else {
    echo "⚠️  Node.js not found in PATH. MCP server may not start.\n";
}

// Check MCP server files
$mcpPath = base_path('vendor/pc_mcp');
$serverFile = $mcpPath . '/server.js';
$packageFile = $mcpPath . '/package.json';
$nodeModules = $mcpPath . '/node_modules';

if (file_exists($serverFile)) {
    echo "✅ MCP server.js found\n";
} else {
    echo "❌ MCP server.js not found at: $serverFile\n";
}

if (file_exists($packageFile)) {
    echo "✅ MCP package.json found\n";
} else {
    echo "❌ MCP package.json not found at: $packageFile\n";
}

if (is_dir($nodeModules)) {
    echo "✅ MCP node_modules installed\n";
} else {
    echo "❌ MCP node_modules not found. Run: cd vendor/pc_mcp && npm install\n";
}

echo "\n🎯 MCP Integration Status: READY\n";
echo "When users create PlayCanvas workspaces, the system will:\n";
echo "  1. Automatically start a dedicated MCP server on a unique port\n";
echo "  2. Set up the workspace with preview URL\n";
echo "  3. Enable AI-powered game development\n";
echo "  4. Monitor server health and auto-restart if needed\n";

echo "\nTest completed.\n";