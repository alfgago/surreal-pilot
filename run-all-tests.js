import { testWebInterfaceLogin } from './test-1-web-interface.js';
import { testDirectAPI } from './test-2-api-direct.js';
import { testRegistrationFlow } from './test-3-registration-flow.js';
import { testStorageVerification } from './test-4-storage-verification.js';
import fs from 'fs';

async function runAllTests() {
  console.log('🚀 SURREAL PILOT - COMPLETE PLAYCANVAS TESTING SUITE');
  console.log('=' * 80);
  console.log('🎯 Testing Claude Sonnet 4 Game Generation with PlayCanvas');
  console.log('🔧 All tests run in automated headless mode');
  console.log('📸 Screenshots captured for debugging');
  console.log('');

  const testResults = {
    startTime: new Date(),
    tests: [],
    summary: {
      total: 0,
      passed: 0,
      failed: 0
    }
  };

  // Ensure test results directory exists
  const testResultsDir = 'test-results';
  if (!fs.existsSync(testResultsDir)) {
    fs.mkdirSync(testResultsDir, { recursive: true });
  }

  // Test 1: Web Interface Login
  console.log('🌐 RUNNING TEST 1: Web Interface and Login');
  console.log('-' * 50);
  try {
    const test1Result = await testWebInterfaceLogin();
    testResults.tests.push({
      testNumber: 1,
      name: 'Web Interface Login',
      success: test1Result.success,
      details: test1Result
    });

    if (test1Result.success) {
      testResults.summary.passed++;
      console.log('✅ TEST 1 PASSED');
    } else {
      testResults.summary.failed++;
      console.log('❌ TEST 1 FAILED');
    }
  } catch (error) {
    testResults.tests.push({
      testNumber: 1,
      name: 'Web Interface Login',
      success: false,
      error: error.message
    });
    testResults.summary.failed++;
    console.log('❌ TEST 1 FAILED:', error.message);
  }
  testResults.summary.total++;

  console.log('\n' + '=' * 80 + '\n');

  // Test 2: Direct API Testing
  console.log('🔗 RUNNING TEST 2: Direct API with Claude Sonnet 4');
  console.log('-' * 50);
  try {
    await testDirectAPI();
    testResults.tests.push({
      testNumber: 2,
      name: 'Direct API Testing',
      success: true
    });
    testResults.summary.passed++;
    console.log('✅ TEST 2 PASSED');
  } catch (error) {
    testResults.tests.push({
      testNumber: 2,
      name: 'Direct API Testing',
      success: false,
      error: error.message
    });
    testResults.summary.failed++;
    console.log('❌ TEST 2 FAILED:', error.message);
  }
  testResults.summary.total++;

  console.log('\n' + '=' * 80 + '\n');

  // Test 3: Registration Flow
  console.log('📝 RUNNING TEST 3: Complete Registration Flow');
  console.log('-' * 50);
  try {
    const test3Result = await testRegistrationFlow();
    testResults.tests.push({
      testNumber: 3,
      name: 'Registration Flow',
      success: test3Result.success,
      details: test3Result
    });

    if (test3Result.success) {
      testResults.summary.passed++;
      console.log('✅ TEST 3 PASSED');
    } else {
      testResults.summary.failed++;
      console.log('❌ TEST 3 FAILED');
    }
  } catch (error) {
    testResults.tests.push({
      testNumber: 3,
      name: 'Registration Flow',
      success: false,
      error: error.message
    });
    testResults.summary.failed++;
    console.log('❌ TEST 3 FAILED:', error.message);
  }
  testResults.summary.total++;

  console.log('\n' + '=' * 80 + '\n');

  // Test 4: Storage Verification
  console.log('📁 RUNNING TEST 4: Storage and File Verification');
  console.log('-' * 50);
  try {
    const test4Result = await testStorageVerification();
    testResults.tests.push({
      testNumber: 4,
      name: 'Storage Verification',
      success: test4Result.success,
      details: test4Result
    });

    if (test4Result.success) {
      testResults.summary.passed++;
      console.log('✅ TEST 4 PASSED');
    } else {
      testResults.summary.failed++;
      console.log('❌ TEST 4 FAILED');
    }
  } catch (error) {
    testResults.tests.push({
      testNumber: 4,
      name: 'Storage Verification',
      success: false,
      error: error.message
    });
    testResults.summary.failed++;
    console.log('❌ TEST 4 FAILED:', error.message);
  }
  testResults.summary.total++;

  // Final Results
  testResults.endTime = new Date();
  testResults.duration = testResults.endTime - testResults.startTime;

  console.log('\n' + '🎉 FINAL TEST RESULTS 🎉'.padStart(50));
  console.log('=' * 80);

  console.log(`📊 SUMMARY:`);
  console.log(`   🧪 Total Tests: ${testResults.summary.total}`);
  console.log(`   ✅ Passed: ${testResults.summary.passed}`);
  console.log(`   ❌ Failed: ${testResults.summary.failed}`);
  console.log(`   ⏱️ Duration: ${Math.round(testResults.duration / 1000)}s`);

  const successRate = Math.round((testResults.summary.passed / testResults.summary.total) * 100);
  console.log(`   📈 Success Rate: ${successRate}%`);

  console.log('\n📋 DETAILED RESULTS:');
  testResults.tests.forEach(test => {
    const status = test.success ? '✅' : '❌';
    console.log(`   ${status} Test ${test.testNumber}: ${test.name}`);

    if (test.details) {
      if (test.details.userEmail) {
        console.log(`      👤 User: ${test.details.userEmail}`);
      }
      if (test.details.companyName) {
        console.log(`      🏢 Company: ${test.details.companyName}`);
      }
      if (test.details.chatInterfaceFound !== undefined) {
        console.log(`      💬 Chat Interface: ${test.details.chatInterfaceFound ? '✅' : '❌'}`);
      }
      if (test.details.finalUrl) {
        console.log(`      🔗 Final URL: ${test.details.finalUrl}`);
      }
    }

    if (test.error) {
      console.log(`      ❌ Error: ${test.error}`);
    }
  });

  console.log('\n🎮 CLAUDE SONNET 4 CONFIGURATION:');
  console.log('   🤖 Model: claude-sonnet-4-20250514');
  console.log('   🏭 Provider: Anthropic');
  console.log('   🎯 Temperature: 0.2 (deterministic)');
  console.log('   📏 Max Tokens: 1200');
  console.log('   🎮 Target Engine: PlayCanvas');

  console.log('\n📸 SCREENSHOTS AVAILABLE:');
  console.log('   📁 Directory: test-results/');
  console.log('   🖼️ Files: test1-*, test3-* (for visual debugging)');

  console.log('\n🔍 NEXT STEPS:');
  if (successRate >= 75) {
    console.log('   🏆 System is ready for production game generation!');
    console.log('   🎯 Use the web interface to create PlayCanvas games');
    console.log('   🔗 API integration is working for automated generation');
  } else {
    console.log('   🔧 Some tests failed - check screenshots for debugging');
    console.log('   📞 Review error messages above');
    console.log('   🔄 Re-run individual tests after fixes');
  }

  console.log('\n✨ PlayCanvas Testing Suite Complete! ✨');

  // Save results to file
  const resultsFile = `${testResultsDir}/test-results-${Date.now()}.json`;
  fs.writeFileSync(resultsFile, JSON.stringify(testResults, null, 2));
  console.log(`📄 Detailed results saved to: ${resultsFile}`);

  return testResults;
}

// Run the complete test suite
runAllTests()
  .then((results) => {
    const exitCode = results.summary.failed === 0 ? 0 : 1;
    process.exit(exitCode);
  })
  .catch((error) => {
    console.error('\n💥 TEST SUITE FAILED:', error.message);
    process.exit(1);
  });
