import fs from 'fs';
import path from 'path';

async function testStorageVerification() {
  console.log('📁 TEST 4: Game Storage and File Verification');
  console.log('=' * 50);

  const results = {
    storageLocations: [],
    recentFiles: [],
    workspaces: [],
    builds: [],
    totalFiles: 0
  };

  // Define storage locations to check
  const storagePaths = [
    'storage/workspaces',
    'storage/app/public/templates',
    'storage/generated_games',
    'storage/app/public/games',
    'storage/builds',
    'public/downloads',
    'storage'
  ];

  console.log('\n📂 Step 1: Checking storage directories...');

  for (const storagePath of storagePaths) {
    try {
      if (fs.existsSync(storagePath)) {
        const stats = fs.statSync(storagePath);
        if (stats.isDirectory()) {
          const files = fs.readdirSync(storagePath);
          console.log(`✅ ${storagePath}: ${files.length} items`);

          results.storageLocations.push({
            path: storagePath,
            exists: true,
            itemCount: files.length,
            files: files.slice(0, 5) // First 5 files
          });

          results.totalFiles += files.length;
        }
      } else {
        console.log(`❌ ${storagePath}: Not found`);
        results.storageLocations.push({
          path: storagePath,
          exists: false
        });
      }
    } catch (error) {
      console.log(`⚠️ ${storagePath}: Error - ${error.message}`);
    }
  }

  // Step 2: Look for test_build directories
  console.log('\n🏗️ Step 2: Checking for test builds...');

  try {
    if (fs.existsSync('storage')) {
      const storageItems = fs.readdirSync('storage');
      const testBuilds = storageItems.filter(item => item.startsWith('test_build_'));

      if (testBuilds.length > 0) {
        console.log(`✅ Found ${testBuilds.length} test build directories:`);
        testBuilds.forEach(build => {
          console.log(`   📦 ${build}`);

          // Check contents of build directory
          try {
            const buildPath = path.join('storage', build);
            const buildFiles = fs.readdirSync(buildPath);
            console.log(`      Files: ${buildFiles.join(', ')}`);

            results.builds.push({
              name: build,
              path: buildPath,
              files: buildFiles
            });
          } catch (e) {
            console.log(`      Error reading build: ${e.message}`);
          }
        });
      } else {
        console.log('ℹ️ No test_build directories found');
      }
    }
  } catch (error) {
    console.log(`❌ Error checking builds: ${error.message}`);
  }

  // Step 3: Look for workspace directories
  console.log('\n🏢 Step 3: Checking workspace structure...');

  const workspacePath = 'storage/workspaces';
  try {
    if (fs.existsSync(workspacePath)) {
      const workspaces = fs.readdirSync(workspacePath);
      console.log(`✅ Found ${workspaces.length} workspace entries`);

      workspaces.forEach(workspace => {
        try {
          const wsPath = path.join(workspacePath, workspace);
          const wsStats = fs.statSync(wsPath);

          if (wsStats.isDirectory()) {
            const wsFiles = fs.readdirSync(wsPath);
            console.log(`   🗂️ ${workspace}: ${wsFiles.length} files`);

            results.workspaces.push({
              name: workspace,
              path: wsPath,
              fileCount: wsFiles.length,
              files: wsFiles.slice(0, 3)
            });
          }
        } catch (e) {
          console.log(`   ⚠️ ${workspace}: ${e.message}`);
        }
      });
    } else {
      console.log('ℹ️ No workspaces directory found');
    }
  } catch (error) {
    console.log(`❌ Error checking workspaces: ${error.message}`);
  }

  // Step 4: Look for recent files
  console.log('\n📅 Step 4: Checking for recent files (last 10 minutes)...');

  const recentThreshold = Date.now() - (10 * 60 * 1000); // 10 minutes ago

  function checkRecentFiles(dirPath, relativePath = '') {
    try {
      if (!fs.existsSync(dirPath)) return;

      const items = fs.readdirSync(dirPath);

      items.forEach(item => {
        const itemPath = path.join(dirPath, item);
        const itemRelativePath = path.join(relativePath, item);

        try {
          const stats = fs.statSync(itemPath);

          if (stats.isFile() && stats.mtime.getTime() > recentThreshold) {
            const ageMinutes = Math.round((Date.now() - stats.mtime.getTime()) / 60000);
            console.log(`   📄 ${itemRelativePath} (${ageMinutes}m ago)`);

            results.recentFiles.push({
              path: itemRelativePath,
              ageMinutes: ageMinutes,
              size: stats.size
            });
          } else if (stats.isDirectory() && !item.startsWith('.')) {
            // Recursively check subdirectories (max 2 levels deep)
            if (relativePath.split(path.sep).length < 2) {
              checkRecentFiles(itemPath, itemRelativePath);
            }
          }
        } catch (e) {
          // Skip files that can't be accessed
        }
      });
    } catch (error) {
      // Skip directories that can't be accessed
    }
  }

  // Check storage and public directories for recent files
  checkRecentFiles('storage', 'storage');
  checkRecentFiles('public', 'public');

  if (results.recentFiles.length === 0) {
    console.log('ℹ️ No recent files found in last 10 minutes');
  }

  // Step 5: Check specific game file types
  console.log('\n🎮 Step 5: Looking for game-related files...');

  const gameFileExtensions = ['.html', '.js', '.json', '.zip', '.tar.gz'];
  const gameFiles = [];

  function findGameFiles(dirPath, relativePath = '') {
    try {
      if (!fs.existsSync(dirPath)) return;

      const items = fs.readdirSync(dirPath);

      items.forEach(item => {
        const itemPath = path.join(dirPath, item);
        const itemRelativePath = path.join(relativePath, item);

        try {
          const stats = fs.statSync(itemPath);

          if (stats.isFile()) {
            const ext = path.extname(item).toLowerCase();
            if (gameFileExtensions.includes(ext)) {
              gameFiles.push({
                path: itemRelativePath,
                extension: ext,
                size: stats.size,
                modified: stats.mtime
              });
            }
          } else if (stats.isDirectory() && !item.startsWith('.') &&
                     relativePath.split(path.sep).length < 2) {
            findGameFiles(itemPath, itemRelativePath);
          }
        } catch (e) {
          // Skip inaccessible files
        }
      });
    } catch (error) {
      // Skip inaccessible directories
    }
  }

  findGameFiles('storage', 'storage');
  findGameFiles('public', 'public');

  console.log(`✅ Found ${gameFiles.length} potential game files:`);
  gameFiles.slice(0, 10).forEach(file => {
    const sizeKB = Math.round(file.size / 1024);
    console.log(`   🎯 ${file.path} (${file.extension}, ${sizeKB}KB)`);
  });

  // Step 6: Database workspace check
  console.log('\n🗄️ Step 6: Checking database for workspaces...');

  try {
    // We would need to run a database query here, but for this test
    // we'll simulate checking for workspace records
    console.log('ℹ️ Database workspace check would require Laravel connection');
    console.log('   Use: php artisan tinker --execute="echo App\\Models\\Workspace::count() . \' workspaces found\';"');
  } catch (error) {
    console.log(`❌ Database check error: ${error.message}`);
  }

  // Final summary
  console.log('\n📋 TEST 4 RESULTS:');
  console.log('=' * 30);
  console.log(`   📂 Storage locations checked: ${results.storageLocations.length}`);
  console.log(`   ✅ Existing directories: ${results.storageLocations.filter(l => l.exists).length}`);
  console.log(`   📄 Total files found: ${results.totalFiles}`);
  console.log(`   🏗️ Test builds: ${results.builds.length}`);
  console.log(`   🏢 Workspaces: ${results.workspaces.length}`);
  console.log(`   📅 Recent files: ${results.recentFiles.length}`);
  console.log(`   🎮 Game files: ${gameFiles.length}`);

  // Detailed summary
  console.log('\n📊 STORAGE SUMMARY:');
  console.log('=' * 20);

  results.storageLocations.forEach(location => {
    if (location.exists) {
      console.log(`✅ ${location.path}: ${location.itemCount} items`);
      if (location.files.length > 0) {
        console.log(`   └─ Files: ${location.files.join(', ')}`);
      }
    } else {
      console.log(`❌ ${location.path}: Not found`);
    }
  });

  if (results.builds.length > 0) {
    console.log('\n🏗️ BUILD DIRECTORIES:');
    results.builds.forEach(build => {
      console.log(`   📦 ${build.name}: ${build.files.length} files`);
    });
  }

  return {
    success: true,
    summary: results,
    gameFiles: gameFiles,
    recommendations: [
      'Check storage/workspaces for active PlayCanvas projects',
      'Look for test_build_* directories for generated builds',
      'Monitor storage/app/public for downloadable games',
      'Recent files indicate active game generation'
    ]
  };
}

export { testStorageVerification };
