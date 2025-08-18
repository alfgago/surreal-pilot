#!/usr/bin/env node

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import puppeteer from 'puppeteer';
import { promises as fs } from 'fs';
import path from 'path';

class PuppeteerMCPServer {
  constructor() {
    this.server = new Server(
      {
        name: 'surreal-pilot-puppeteer',
        version: '1.0.0',
      },
      {
        capabilities: {
          tools: {},
        },
      }
    );

    this.browser = null;
    this.currentPage = null;
    this.setupToolHandlers();
  }

  setupToolHandlers() {
    this.server.setRequestHandler(ListToolsRequestSchema, async () => {
      return {
        tools: [
          {
            name: 'launch_browser',
            description: 'Launch a new browser instance',
            inputSchema: {
              type: 'object',
              properties: {
                headless: {
                  type: 'boolean',
                  description: 'Run browser in headless mode',
                  default: true,
                },
                width: {
                  type: 'number',
                  description: 'Browser width',
                  default: 1280,
                },
                height: {
                  type: 'number',
                  description: 'Browser height',
                  default: 720,
                },
              },
            },
          },
          {
            name: 'navigate_to_url',
            description: 'Navigate to a specific URL',
            inputSchema: {
              type: 'object',
              properties: {
                url: {
                  type: 'string',
                  description: 'URL to navigate to',
                },
                waitFor: {
                  type: 'string',
                  description: 'CSS selector to wait for after navigation',
                },
              },
              required: ['url'],
            },
          },
          {
            name: 'test_game_generation',
            description: 'Test game generation flow in SurrealPilot',
            inputSchema: {
              type: 'object',
              properties: {
                baseUrl: {
                  type: 'string',
                  description: 'Base URL of SurrealPilot application',
                  default: 'http://surreal-pilot.local',
                },
                gamePrompt: {
                  type: 'string',
                  description: 'Prompt for game generation',
                  default: 'Create a simple 2D platformer game with a player character that can jump and collect coins.',
                },
                loginEmail: {
                  type: 'string',
                  description: 'Email for login',
                  default: 'test@example.com',
                },
                loginPassword: {
                  type: 'string',
                  description: 'Password for login',
                  default: 'password123',
                },
              },
            },
          },
          {
            name: 'click_element',
            description: 'Click on an element',
            inputSchema: {
              type: 'object',
              properties: {
                selector: {
                  type: 'string',
                  description: 'CSS selector for the element to click',
                },
                waitFor: {
                  type: 'number',
                  description: 'Wait time after click (ms)',
                  default: 1000,
                },
              },
              required: ['selector'],
            },
          },
          {
            name: 'type_text',
            description: 'Type text into an input field',
            inputSchema: {
              type: 'object',
              properties: {
                selector: {
                  type: 'string',
                  description: 'CSS selector for the input field',
                },
                text: {
                  type: 'string',
                  description: 'Text to type',
                },
                clear: {
                  type: 'boolean',
                  description: 'Clear field before typing',
                  default: true,
                },
              },
              required: ['selector', 'text'],
            },
          },
          {
            name: 'take_screenshot',
            description: 'Take a screenshot of the current page',
            inputSchema: {
              type: 'object',
              properties: {
                path: {
                  type: 'string',
                  description: 'Path to save screenshot',
                  default: 'screenshot.png',
                },
                fullPage: {
                  type: 'boolean',
                  description: 'Take full page screenshot',
                  default: true,
                },
              },
            },
          },
          {
            name: 'get_page_content',
            description: 'Get the current page content',
            inputSchema: {
              type: 'object',
              properties: {
                selector: {
                  type: 'string',
                  description: 'CSS selector to get specific content (optional)',
                },
              },
            },
          },
          {
            name: 'check_storage_files',
            description: 'Check if game files were created in storage',
            inputSchema: {
              type: 'object',
              properties: {
                storagePath: {
                  type: 'string',
                  description: 'Path to storage directory',
                  default: '../storage',
                },
              },
            },
          },
          {
            name: 'close_browser',
            description: 'Close the browser instance',
            inputSchema: {
              type: 'object',
              properties: {},
            },
          },
        ],
      };
    });

    this.server.setRequestHandler(CallToolRequestSchema, async (request) => {
      const { name, arguments: args } = request.params;

      try {
        switch (name) {
          case 'launch_browser':
            return await this.launchBrowser(args);
          case 'navigate_to_url':
            return await this.navigateToUrl(args);
          case 'test_game_generation':
            return await this.testGameGeneration(args);
          case 'click_element':
            return await this.clickElement(args);
          case 'type_text':
            return await this.typeText(args);
          case 'take_screenshot':
            return await this.takeScreenshot(args);
          case 'get_page_content':
            return await this.getPageContent(args);
          case 'check_storage_files':
            return await this.checkStorageFiles(args);
          case 'close_browser':
            return await this.closeBrowser(args);
          default:
            throw new Error(`Unknown tool: ${name}`);
        }
      } catch (error) {
        return {
          content: [
            {
              type: 'text',
              text: `Error executing ${name}: ${error.message}`,
            },
          ],
        };
      }
    });
  }

  async launchBrowser(args) {
    const { headless = true, width = 1280, height = 720 } = args;

    this.browser = await puppeteer.launch({
      headless,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    this.currentPage = await this.browser.newPage();
    await this.currentPage.setViewport({ width, height });

    return {
      content: [
        {
          type: 'text',
          text: `Browser launched successfully (headless: ${headless}, ${width}x${height})`,
        },
      ],
    };
  }

  async navigateToUrl(args) {
    if (!this.currentPage) {
      throw new Error('No browser page available. Launch browser first.');
    }

    const { url, waitFor } = args;

    await this.currentPage.goto(url, { waitUntil: 'networkidle2' });

    if (waitFor) {
      await this.currentPage.waitForSelector(waitFor, { timeout: 10000 });
    }

    const title = await this.currentPage.title();

    return {
      content: [
        {
          type: 'text',
          text: `Navigated to ${url}. Page title: "${title}"`,
        },
      ],
    };
  }

  async testGameGeneration(args) {
    const {
      baseUrl = 'http://surreal-pilot.local',
      gamePrompt = 'Create a simple 2D platformer game with a player character that can jump and collect coins.',
      loginEmail = 'test@example.com',
      loginPassword = 'password123',
    } = args;

    if (!this.currentPage) {
      throw new Error('No browser page available. Launch browser first.');
    }

    let results = [];

    try {
      // Navigate to the application
      await this.currentPage.goto(baseUrl, { waitUntil: 'networkidle2' });
      results.push(`✅ Navigated to ${baseUrl}`);

      // Check for login/register
      const loginButton = await this.currentPage.$('a[href*="login"], button:contains("Login"), text=Login');
      const registerButton = await this.currentPage.$('a[href*="register"], button:contains("Register"), text=Register');

      if (loginButton) {
        await loginButton.click();
        await this.currentPage.waitForTimeout(2000);

        // Fill login form
        await this.currentPage.type('input[name="email"], input[type="email"]', loginEmail);
        await this.currentPage.type('input[name="password"], input[type="password"]', loginPassword);

        // Submit login
        const submitButton = await this.currentPage.$('button[type="submit"], input[type="submit"]');
        if (submitButton) {
          await submitButton.click();
          await this.currentPage.waitForTimeout(3000);
          results.push(`✅ Attempted login with ${loginEmail}`);
        }
      }

      // Look for chat interface
      const chatSelectors = [
        'textarea[placeholder*="message"]',
        'input[placeholder*="chat"]',
        'textarea[name="message"]',
        '[data-testid="chat-input"]',
        '.chat-input textarea',
        '.message-input',
      ];

      let chatInput = null;
      for (const selector of chatSelectors) {
        chatInput = await this.currentPage.$(selector);
        if (chatInput) break;
      }

      if (chatInput) {
        // Clear and type the game prompt
        await this.currentPage.evaluate(el => el.value = '', chatInput);
        await chatInput.type(gamePrompt);
        results.push(`✅ Entered game prompt: "${gamePrompt}"`);

        // Find and click send button
        const sendSelectors = [
          'button[type="submit"]',
          'button:contains("Send")',
          '[data-testid="send"]',
          '.send-button',
          'button.btn-primary',
        ];

        let sendButton = null;
        for (const selector of sendSelectors) {
          sendButton = await this.currentPage.$(selector);
          if (sendButton) break;
        }

        if (sendButton) {
          await sendButton.click();
          results.push(`✅ Clicked send button`);

          // Wait for response
          await this.currentPage.waitForTimeout(10000);
          results.push(`✅ Waited for AI response`);

          // Take screenshot of result
          await this.currentPage.screenshot({
            path: 'game-generation-test-result.png',
            fullPage: true
          });
          results.push(`✅ Screenshot saved: game-generation-test-result.png`);

          // Check for generated content
          const content = await this.currentPage.content();
          const hasGameContent = content.includes('game') ||
                                content.includes('canvas') ||
                                content.includes('HTML') ||
                                content.includes('created');

          if (hasGameContent) {
            results.push(`✅ Game generation content detected`);
          } else {
            results.push(`⚠️ No obvious game content detected`);
          }
        } else {
          results.push(`❌ Send button not found`);
        }
      } else {
        results.push(`❌ Chat input not found`);
      }

    } catch (error) {
      results.push(`❌ Error during game generation test: ${error.message}`);
    }

    return {
      content: [
        {
          type: 'text',
          text: results.join('\n'),
        },
      ],
    };
  }

  async clickElement(args) {
    if (!this.currentPage) {
      throw new Error('No browser page available. Launch browser first.');
    }

    const { selector, waitFor = 1000 } = args;

    await this.currentPage.waitForSelector(selector, { timeout: 10000 });
    await this.currentPage.click(selector);
    await this.currentPage.waitForTimeout(waitFor);

    return {
      content: [
        {
          type: 'text',
          text: `Clicked element: ${selector}`,
        },
      ],
    };
  }

  async typeText(args) {
    if (!this.currentPage) {
      throw new Error('No browser page available. Launch browser first.');
    }

    const { selector, text, clear = true } = args;

    await this.currentPage.waitForSelector(selector, { timeout: 10000 });

    if (clear) {
      await this.currentPage.evaluate(
        (sel) => {
          const element = document.querySelector(sel);
          if (element) element.value = '';
        },
        selector
      );
    }

    await this.currentPage.type(selector, text);

    return {
      content: [
        {
          type: 'text',
          text: `Typed "${text}" into ${selector}`,
        },
      ],
    };
  }

  async takeScreenshot(args) {
    if (!this.currentPage) {
      throw new Error('No browser page available. Launch browser first.');
    }

    const { path = 'screenshot.png', fullPage = true } = args;

    await this.currentPage.screenshot({ path, fullPage });

    return {
      content: [
        {
          type: 'text',
          text: `Screenshot saved to: ${path}`,
        },
      ],
    };
  }

  async getPageContent(args) {
    if (!this.currentPage) {
      throw new Error('No browser page available. Launch browser first.');
    }

    const { selector } = args;

    let content;
    if (selector) {
      const element = await this.currentPage.$(selector);
      content = element ? await this.currentPage.evaluate(el => el.textContent, element) : 'Element not found';
    } else {
      content = await this.currentPage.content();
    }

    return {
      content: [
        {
          type: 'text',
          text: content,
        },
      ],
    };
  }

  async checkStorageFiles(args) {
    const { storagePath = '../storage' } = args;

    try {
      const workspacesPath = path.join(storagePath, 'workspaces');
      const appPath = path.join(storagePath, 'app');

      let results = [];

      // Check workspaces directory
      try {
        const workspaceFiles = await fs.readdir(workspacesPath, { recursive: true });
        results.push(`📁 Workspaces directory: ${workspaceFiles.length} items`);
        if (workspaceFiles.length > 0) {
          results.push(`   Files: ${workspaceFiles.slice(0, 10).join(', ')}${workspaceFiles.length > 10 ? '...' : ''}`);
        }
      } catch (err) {
        results.push(`📁 Workspaces directory: Not accessible (${err.message})`);
      }

      // Check for build directories
      try {
        const storageContents = await fs.readdir(storagePath);
        const buildDirs = storageContents.filter(item => item.startsWith('test_build_') || item.includes('build'));
        if (buildDirs.length > 0) {
          results.push(`🎮 Build directories found: ${buildDirs.join(', ')}`);

          // Check contents of first build directory
          const firstBuildDir = path.join(storagePath, buildDirs[0]);
          try {
            const buildFiles = await fs.readdir(firstBuildDir);
            results.push(`   Build files: ${buildFiles.join(', ')}`);
          } catch (err) {
            results.push(`   Build directory not accessible: ${err.message}`);
          }
        } else {
          results.push(`🎮 No build directories found`);
        }
      } catch (err) {
        results.push(`📁 Storage directory not accessible: ${err.message}`);
      }

      return {
        content: [
          {
            type: 'text',
            text: results.join('\n'),
          },
        ],
      };
    } catch (error) {
      return {
        content: [
          {
            type: 'text',
            text: `Error checking storage files: ${error.message}`,
          },
        ],
      };
    }
  }

  async closeBrowser(args) {
    if (this.browser) {
      await this.browser.close();
      this.browser = null;
      this.currentPage = null;
    }

    return {
      content: [
        {
          type: 'text',
          text: 'Browser closed successfully',
        },
      ],
    };
  }

  async run() {
    const transport = new StdioServerTransport();
    await this.server.connect(transport);
    console.error('SurrealPilot Puppeteer MCP Server running on stdio');
  }
}

const server = new PuppeteerMCPServer();
server.run().catch(console.error);
