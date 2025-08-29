#!/usr/bin/env php
<?php

/**
 * Visual Testing Runner Script
 * 
 * This script runs comprehensive visual tests and generates a detailed report
 */

$startTime = microtime(true);

echo "🎯 SurrealPilot Visual Testing Suite\n";
echo "=====================================\n\n";

// Ensure we're in the project root
if (!file_exists('artisan')) {
    echo "❌ Error: Please run this script from the project root directory.\n";
    exit(1);
}

// Check if Dusk is properly configured
if (!file_exists('.env.dusk.local')) {
    echo "⚠️  Warning: .env.dusk.local not found. Creating from .env...\n";
    copy('.env', '.env.dusk.local');
}

// Ensure screenshots directory exists
$screenshotDir = 'tests/Visual/screenshots';
if (!is_dir($screenshotDir)) {
    mkdir($screenshotDir, 0755, true);
    echo "📁 Created screenshots directory: {$screenshotDir}\n";
}

// Clear previous screenshots
$files = glob($screenshotDir . '/*');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
echo "🧹 Cleared previous screenshots\n\n";

// Initialize screenshot log
file_put_contents($screenshotDir . '/screenshot_log.md', "# Visual Test Screenshots\n\nGenerated: " . date('Y-m-d H:i:s') . "\n\n");

echo "🚀 Starting visual tests...\n\n";

// Run the visual tests
$testCommands = [
    'Route Validation' => 'php artisan test tests/Visual/RouteValidationTest.php --stop-on-failure',
    'Component Interactions' => 'php artisan test tests/Visual/ComponentInteractionTest.php --stop-on-failure',
    'Complete App Flow' => 'php artisan test tests/Visual/ComprehensiveAppFlowTest.php --stop-on-failure',
];

$results = [];
$totalTests = 0;
$passedTests = 0;

foreach ($testCommands as $testName => $command) {
    echo "🧪 Running {$testName} tests...\n";
    
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    $results[$testName] = [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'return_code' => $returnCode
    ];
    
    if ($returnCode === 0) {
        echo "✅ {$testName} tests passed\n";
        $passedTests++;
    } else {
        echo "❌ {$testName} tests failed\n";
        echo "Output: " . implode("\n", array_slice($output, -5)) . "\n";
    }
    
    $totalTests++;
    echo "\n";
}

// Generate test report
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

$reportContent = "# Visual Testing Report\n\n";
$reportContent .= "**Generated:** " . date('Y-m-d H:i:s') . "\n";
$reportContent .= "**Duration:** {$duration} seconds\n";
$reportContent .= "**Tests Passed:** {$passedTests}/{$totalTests}\n\n";

$reportContent .= "## Test Results\n\n";

foreach ($results as $testName => $result) {
    $status = $result['success'] ? '✅ PASSED' : '❌ FAILED';
    $reportContent .= "### {$testName}: {$status}\n\n";
    
    if (!$result['success']) {
        $reportContent .= "**Error Output:**\n```\n" . $result['output'] . "\n```\n\n";
    }
}

// Count screenshots
$screenshots = glob($screenshotDir . '/*.png');
$screenshotCount = count($screenshots);

$reportContent .= "## Screenshots Generated\n\n";
$reportContent .= "**Total Screenshots:** {$screenshotCount}\n";
$reportContent .= "**Location:** `{$screenshotDir}/`\n\n";

if ($screenshotCount > 0) {
    $reportContent .= "### Screenshot Files\n\n";
    foreach ($screenshots as $screenshot) {
        $filename = basename($screenshot);
        $reportContent .= "- `{$filename}`\n";
    }
    $reportContent .= "\n";
}

$reportContent .= "## Next Steps\n\n";
if ($passedTests === $totalTests) {
    $reportContent .= "🎉 All visual tests passed! Your application UI is working correctly.\n\n";
    $reportContent .= "### Recommendations:\n";
    $reportContent .= "- Review screenshots for visual consistency\n";
    $reportContent .= "- Check responsive design across different devices\n";
    $reportContent .= "- Validate accessibility features\n";
} else {
    $reportContent .= "⚠️ Some visual tests failed. Please review the errors above.\n\n";
    $reportContent .= "### Troubleshooting:\n";
    $reportContent .= "- Check browser compatibility\n";
    $reportContent .= "- Verify database seeding\n";
    $reportContent .= "- Review element selectors\n";
    $reportContent .= "- Check for JavaScript errors\n";
}

// Save report
$reportFile = 'VISUAL_TESTING_REPORT.md';
file_put_contents($reportFile, $reportContent);

echo "📊 Test Summary:\n";
echo "================\n";
echo "Tests Passed: {$passedTests}/{$totalTests}\n";
echo "Screenshots: {$screenshotCount}\n";
echo "Duration: {$duration}s\n";
echo "Report: {$reportFile}\n";
echo "Screenshots: {$screenshotDir}/\n\n";

if ($passedTests === $totalTests) {
    echo "🎉 All visual tests completed successfully!\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Check the report for details.\n";
    exit(1);
}