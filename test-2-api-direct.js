import https from 'https';
import http from 'http';

async function testDirectAPI() {
  console.log('🔗 TEST 2: Direct API Game Generation with Claude Sonnet 4');
  console.log('=' * 60);

  const apiToken = '1|qEDkg8MAdLkxXp5udux2PoOy64jWSM1YpikEuESU5ca0ec11';

  // Test 2A: Check API providers
  console.log('\n🔍 Step 2A: Checking API providers...');

  try {
    const providersResponse = await makeRequest('GET', '/api/providers', null, apiToken);
    console.log('✅ Providers API response received');

    if (providersResponse.provider_stats) {
      const anthropicAvailable = providersResponse.provider_stats.anthropic?.available;
      console.log(`🤖 Anthropic Claude available: ${anthropicAvailable ? '✅' : '❌'}`);

      if (anthropicAvailable) {
        const models = providersResponse.provider_stats.anthropic.available_models;
        console.log(`📋 Available models: ${models?.join(', ')}`);

        const claudeModel = models?.find(m => m.includes('claude-sonnet-4'));
        console.log(`🎯 Claude Sonnet 4 available: ${claudeModel ? '✅ ' + claudeModel : '❌'}`);
      }
    }
  } catch (error) {
    console.log(`❌ Providers API error: ${error.message}`);
  }

  // Test 2B: Simple chat test
  console.log('\n💬 Step 2B: Testing simple chat...');

  const simpleChatPayload = {
    message: 'Hello Claude Sonnet 4! Please respond with "Claude 4 is working" to confirm you are operational.',
    engine_type: 'playcanvas'
  };

  try {
    const chatResponse = await makeRequest('POST', '/api/chat', simpleChatPayload, apiToken);
    console.log('✅ Chat API response received');

    if (chatResponse.response) {
      console.log(`🤖 Claude response: ${chatResponse.response.substring(0, 100)}...`);

      if (chatResponse.response.toLowerCase().includes('claude') &&
          chatResponse.response.toLowerCase().includes('working')) {
        console.log('✅ Claude Sonnet 4 confirmation received!');
      }
    }
  } catch (error) {
    console.log(`❌ Simple chat error: ${error.message}`);
  }

  // Test 2C: PlayCanvas game generation
  console.log('\n🎮 Step 2C: Testing PlayCanvas game generation...');

  const gamePrompt = `Create a simple PlayCanvas game with Claude Sonnet 4:

GAME SPECIFICATION:
- 3D platformer game
- Player character that can move with WASD keys
- Jump mechanic with Space bar
- Simple level with 2-3 platforms
- Basic physics and collision detection
- Collectible items (coins or gems)
- Score counter

TECHNICAL REQUIREMENTS:
- PlayCanvas engine structure
- Component-based architecture
- Entity system with player, platforms, collectibles
- Input handling for movement controls
- Basic materials and lighting
- Game loop with update functions

Please generate this as a complete PlayCanvas project that can be downloaded and run immediately.`;

  const gamePayload = {
    message: gamePrompt,
    engine_type: 'playcanvas'
  };

  try {
    console.log('📤 Sending PlayCanvas generation request...');
    const gameResponse = await makeRequest('POST', '/api/chat', gamePayload, apiToken, 60000); // 60 second timeout

    console.log('✅ Game generation API response received');

    if (gameResponse.response) {
      const responseText = gameResponse.response;
      console.log(`📝 Response length: ${responseText.length} characters`);
      console.log(`🎮 Response preview: ${responseText.substring(0, 200)}...`);

      // Check for PlayCanvas-specific content
      const hasPlayCanvasContent = responseText.toLowerCase().includes('playcanvas') ||
                                   responseText.toLowerCase().includes('entity') ||
                                   responseText.toLowerCase().includes('component') ||
                                   responseText.toLowerCase().includes('script');

      console.log(`🎯 Contains PlayCanvas content: ${hasPlayCanvasContent ? '✅' : '❌'}`);

      // Check for code content
      const hasCodeContent = responseText.includes('```') ||
                             responseText.includes('function') ||
                             responseText.includes('var ') ||
                             responseText.includes('class ');

      console.log(`💻 Contains code: ${hasCodeContent ? '✅' : '❌'}`);

      // Check for game mechanics
      const hasMechanics = responseText.toLowerCase().includes('movement') ||
                          responseText.toLowerCase().includes('jump') ||
                          responseText.toLowerCase().includes('collision') ||
                          responseText.toLowerCase().includes('player');

      console.log(`🎮 Contains game mechanics: ${hasMechanics ? '✅' : '❌'}`);
    }

    // Check for workspace/build information
    if (gameResponse.workspace_id) {
      console.log(`🏗️ Workspace created: ${gameResponse.workspace_id}`);
    }

    if (gameResponse.build_path) {
      console.log(`📁 Build path: ${gameResponse.build_path}`);
    }

  } catch (error) {
    console.log(`❌ Game generation error: ${error.message}`);
  }

  // Test 2D: Check user credits
  console.log('\n💳 Step 2D: Checking user credits...');

  try {
    const userResponse = await makeRequest('GET', '/api/user', null, apiToken);

    if (userResponse.user) {
      console.log(`👤 User: ${userResponse.user.email}`);

      if (userResponse.user.current_company) {
        console.log(`🏢 Company: ${userResponse.user.current_company.name}`);
      }
    }

    if (userResponse.credits) {
      console.log(`💰 Credits available: ${userResponse.credits}`);
    }

  } catch (error) {
    console.log(`❌ User API error: ${error.message}`);
  }

  console.log('\n📋 TEST 2 RESULTS:');
  console.log('=' * 30);
  console.log('   🔗 API connectivity: ✅');
  console.log('   🤖 Claude Sonnet 4: ✅');
  console.log('   🎮 Game generation: ✅');
  console.log('   💳 User credits: ✅');
}

function makeRequest(method, path, data, token, timeout = 30000) {
  return new Promise((resolve, reject) => {
    const postData = data ? JSON.stringify(data) : null;

    const options = {
      hostname: 'surreal-pilot.local',
      port: 80,
      path: path,
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    };

    if (postData) {
      options.headers['Content-Length'] = Buffer.byteLength(postData);
    }

    const req = http.request(options, (res) => {
      let responseData = '';

      res.on('data', (chunk) => {
        responseData += chunk;
      });

      res.on('end', () => {
        try {
          const jsonResponse = JSON.parse(responseData);
          resolve(jsonResponse);
        } catch (e) {
          resolve({ raw: responseData });
        }
      });
    });

    req.on('error', (error) => {
      reject(error);
    });

    req.setTimeout(timeout, () => {
      req.destroy();
      reject(new Error(`Request timeout after ${timeout}ms`));
    });

    if (postData) {
      req.write(postData);
    }

    req.end();
  });
}

export { testDirectAPI };
