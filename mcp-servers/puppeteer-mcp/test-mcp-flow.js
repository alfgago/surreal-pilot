#!/usr/bin/env node

// Test script to interact with SurrealPilot via Puppeteer MCP
import { spawn } from 'child_process';
import { promises as fs } from 'fs';

class SurrealPilotTester {
    constructor() {
        this.mcpProcess = null;
        this.results = [];
    }

    async startMCPServer() {
        console.log('🚀 Starting Puppeteer MCP Server...');

        this.mcpProcess = spawn('node', ['server.js'], {
            cwd: './mcp-servers/puppeteer-mcp',
            stdio: ['pipe', 'pipe', 'pipe']
        });

        // Give server time to start
        await new Promise(resolve => setTimeout(resolve, 2000));

        return this.mcpProcess;
    }

    async sendMCPCommand(toolName, args = {}) {
        if (!this.mcpProcess) {
            throw new Error('MCP server not started');
        }

        const request = {
            jsonrpc: "2.0",
            id: Date.now(),
            method: "tools/call",
            params: {
                name: toolName,
                arguments: args
            }
        };

        return new Promise((resolve, reject) => {
            let responseData = '';

            const timeout = setTimeout(() => {
                reject(new Error('MCP command timeout'));
            }, 30000);

            this.mcpProcess.stdout.on('data', (data) => {
                responseData += data.toString();

                // Try to parse JSON response
                try {
                    const lines = responseData.split('\n').filter(line => line.trim());
                    for (const line of lines) {
                        if (line.includes('"result"')) {
                            clearTimeout(timeout);
                            const response = JSON.parse(line);
                            resolve(response.result);
                            return;
                        }
                    }
                } catch (e) {
                    // Continue collecting data
                }
            });

            this.mcpProcess.stderr.on('data', (data) => {
                console.error('MCP Error:', data.toString());
            });

            // Send the command
            this.mcpProcess.stdin.write(JSON.stringify(request) + '\n');
        });
    }

    async testCompleteFlow() {
        console.log('🎮 Testing SurrealPilot Complete Flow\n');

        try {
            // Step 1: Launch Browser
            console.log('📱 Step 1: Launching browser...');
            const launchResult = await this.sendMCPCommand('launch_browser', {
                headless: false,
                width: 1280,
                height: 720
            });
            console.log('✅', launchResult.content[0].text);
            this.results.push({ step: 'launch_browser', status: 'success', result: launchResult });

            // Step 2: Navigate to SurrealPilot
            console.log('\n🌐 Step 2: Navigating to SurrealPilot...');
            const navResult = await this.sendMCPCommand('navigate_to_url', {
                url: 'http://surreal-pilot.local/',
                waitFor: 'body'
            });
            console.log('✅', navResult.content[0].text);
            this.results.push({ step: 'navigate', status: 'success', result: navResult });

            // Step 3: Take initial screenshot
            console.log('\n📸 Step 3: Taking initial screenshot...');
            const screenshotResult = await this.sendMCPCommand('take_screenshot', {
                path: 'test-results/01-homepage.png',
                fullPage: true
            });
            console.log('✅', screenshotResult.content[0].text);

            // Step 4: Test game generation flow
            console.log('\n🎯 Step 4: Testing game generation...');
            const gameTestResult = await this.sendMCPCommand('test_game_generation', {
                baseUrl: 'http://surreal-pilot.local',
                gamePrompt: 'Create a simple 2D platformer game with a blue character that can jump and collect yellow coins. Add basic physics and collision detection.',
                loginEmail: 'test@example.com',
                loginPassword: 'password123'
            });

            console.log('🎮 Game Generation Test Results:');
            console.log(gameTestResult.content[0].text);
            this.results.push({ step: 'game_generation', status: 'success', result: gameTestResult });

            // Step 5: Check storage for generated files
            console.log('\n📁 Step 5: Checking for generated game files...');
            const storageResult = await this.sendMCPCommand('check_storage_files', {
                storagePath: './storage'
            });
            console.log('📁 Storage Check Results:');
            console.log(storageResult.content[0].text);
            this.results.push({ step: 'storage_check', status: 'success', result: storageResult });

            // Step 6: Take final screenshot
            console.log('\n📸 Step 6: Taking final screenshot...');
            await this.sendMCPCommand('take_screenshot', {
                path: 'test-results/02-final-result.png',
                fullPage: true
            });

            // Step 7: Close browser
            console.log('\n🔒 Step 7: Closing browser...');
            const closeResult = await this.sendMCPCommand('close_browser', {});
            console.log('✅', closeResult.content[0].text);

        } catch (error) {
            console.error('❌ Test failed:', error.message);
            this.results.push({ step: 'error', status: 'failed', error: error.message });
        }

        // Generate test report
        await this.generateReport();
    }

    async generateReport() {
        console.log('\n📊 Generating Test Report...');

        const report = {
            timestamp: new Date().toISOString(),
            testName: 'SurrealPilot Complete Flow Test',
            results: this.results,
            summary: {
                total_steps: this.results.length,
                successful_steps: this.results.filter(r => r.status === 'success').length,
                failed_steps: this.results.filter(r => r.status === 'failed').length
            }
        };

        await fs.writeFile('test-results/flow-test-report.json', JSON.stringify(report, null, 2));
        console.log('✅ Test report saved to test-results/flow-test-report.json');

        // Print summary
        console.log('\n📋 TEST SUMMARY:');
        console.log(`✅ Successful steps: ${report.summary.successful_steps}`);
        console.log(`❌ Failed steps: ${report.summary.failed_steps}`);
        console.log(`📊 Total steps: ${report.summary.total_steps}`);

        const successRate = (report.summary.successful_steps / report.summary.total_steps * 100).toFixed(1);
        console.log(`🎯 Success rate: ${successRate}%`);
    }

    async cleanup() {
        if (this.mcpProcess) {
            this.mcpProcess.kill();
            console.log('🧹 MCP server stopped');
        }
    }
}

// Run the test
async function main() {
    const tester = new SurrealPilotTester();

    try {
        await tester.startMCPServer();
        await tester.testCompleteFlow();
    } catch (error) {
        console.error('💥 Test suite failed:', error);
    } finally {
        await tester.cleanup();
    }
}

main().catch(console.error);
