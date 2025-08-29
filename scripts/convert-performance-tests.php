<?php

/**
 * Convert performance test files from PHPUnit to Pest format
 */

$testFiles = [
    'tests/Browser/MobileResponsiveTest.php',
    'tests/Browser/RealtimePerformanceTest.php',
    'tests/Browser/AccessibilityTest.php',
    'tests/Browser/CrossBrowserCompatibilityTest.php',
    'tests/Browser/MobileDeviceTest.php'
];

foreach ($testFiles as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Convert class-based tests to Pest format
    $content = preg_replace('/class\s+(\w+)\s+extends\s+DuskTestCase\s*\{[^}]*use\s+DatabaseTransactions;[^}]*protected\s+function\s+setUp\(\)[^}]*\}/', '', $content);
    
    // Add beforeEach
    $beforeEach = "beforeEach(function () {\n    \$this->testUser = User::where('email', 'alfredo@5e.cr')->first();\n});";
    
    // Replace class definition with beforeEach
    $content = preg_replace('/^<\?php\s*\n\nuse Laravel\\\Dusk\\\Browser;\nuse Tests\\\DuskTestCase;\nuse Illuminate\\\Foundation\\\Testing\\\DatabaseTransactions;/', "<?php\n\nuse App\\Models\\User;\nuse Laravel\\Dusk\\Browser;\n\n$beforeEach", $content);
    
    // Convert public function test methods to Pest test functions
    $content = preg_replace('/public\s+function\s+test_([^(]+)\(\):\s*void\s*\{/', "test('$1', function () {", $content);
    
    // Convert method names from snake_case to readable format
    $content = preg_replace_callback("/test\('([^']+)'/", function($matches) {
        return "test('" . str_replace('_', ' ', $matches[1]) . "'";
    }, $content);
    
    // Remove closing class brace and replace with closing function brace
    $content = preg_replace('/\s*\}\s*$/', '});', $content);
    
    // Fix method closures
    $content = preg_replace('/\s*\}\s*\n\s*test\(/', "\n});\n\ntest(", $content);
    
    file_put_contents($file, $content);
    echo "Converted: $file\n";
}

echo "Conversion complete!\n";