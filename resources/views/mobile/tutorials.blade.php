@extends('mobile.layout')

@section('title', 'PlayCanvas Tutorials')

@section('content')
<div class="tutorial-container">
    <div class="tutorial-header">
        <h1>Interactive Tutorials</h1>
        <p>Learn PlayCanvas game development step-by-step</p>
    </div>

    <div class="tutorial-grid">
        <!-- Tutorial 1: First Game -->
        <div class="tutorial-card" data-tutorial="first-game">
            <div class="tutorial-icon">🎮</div>
            <h3>Your First Game</h3>
            <p>Create and publish a jumping game in 2 minutes</p>
            <div class="tutorial-meta">
                <span class="duration">⏱️ 2 min</span>
                <span class="difficulty beginner">Beginner</span>
            </div>
            <button class="tutorial-start-btn" onclick="startTutorial('first-game')">
                Start Tutorial
            </button>
        </div>

        <!-- Tutorial 2: Visual Effects -->
        <div class="tutorial-card" data-tutorial="visual-effects">
            <div class="tutorial-icon">✨</div>
            <h3>Adding Visual Effects</h3>
            <p>Make your game visually appealing with particles and colors</p>
            <div class="tutorial-meta">
                <span class="duration">⏱️ 3 min</span>
                <span class="difficulty beginner">Beginner</span>
            </div>
            <button class="tutorial-start-btn" onclick="startTutorial('visual-effects')">
                Start Tutorial
            </button>
        </div>

        <!-- Tutorial 3: Game Mechanics -->
        <div class="tutorial-card" data-tutorial="game-mechanics">
            <div class="tutorial-icon">⚙️</div>
            <h3>Game Mechanics</h3>
            <p>Add scoring, power-ups, and gameplay depth</p>
            <div class="tutorial-meta">
                <span class="duration">⏱️ 4 min</span>
                <span class="difficulty intermediate">Intermediate</span>
            </div>
            <button class="tutorial-start-btn" onclick="startTutorial('game-mechanics')">
                Start Tutorial
            </button>
        </div>

        <!-- Tutorial 4: Mobile Optimization -->
        <div class="tutorial-card" data-tutorial="mobile-optimization">
            <div class="tutorial-icon">📱</div>
            <h3>Mobile Optimization</h3>
            <p>Optimize your games for mobile devices</p>
            <div class="tutorial-meta">
                <span class="duration">⏱️ 3 min</span>
                <span class="difficulty intermediate">Intermediate</span>
            </div>
            <button class="tutorial-start-btn" onclick="startTutorial('mobile-optimization')">
                Start Tutorial
            </button>
        </div>
    </div>
</div>

<!-- Tutorial Modal -->
<div id="tutorial-modal" class="tutorial-modal hidden">
    <div class="tutorial-modal-content">
        <div class="tutorial-modal-header">
            <h2 id="tutorial-title"></h2>
            <button class="tutorial-close" onclick="closeTutorial()">×</button>
        </div>
        
        <div class="tutorial-progress">
            <div class="progress-bar">
                <div class="progress-fill" id="tutorial-progress"></div>
            </div>
            <span class="progress-text" id="progress-text">Step 1 of 5</span>
        </div>

        <div class="tutorial-content">
            <div class="tutorial-step" id="tutorial-step-content">
                <!-- Dynamic content loaded here -->
            </div>
        </div>

        <div class="tutorial-actions">
            <button id="tutorial-prev" class="tutorial-btn secondary" onclick="previousStep()" disabled>
                Previous
            </button>
            <button id="tutorial-next" class="tutorial-btn primary" onclick="nextStep()">
                Next
            </button>
            <button id="tutorial-complete" class="tutorial-btn success hidden" onclick="completeTutorial()">
                Complete Tutorial
            </button>
        </div>
    </div>
</div>

<style>
.tutorial-container {
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.tutorial-header {
    text-align: center;
    margin-bottom: 30px;
}

.tutorial-header h1 {
    font-size: 2rem;
    color: #333;
    margin-bottom: 10px;
}

.tutorial-header p {
    color: #666;
    font-size: 1.1rem;
}

.tutorial-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.tutorial-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.tutorial-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.tutorial-icon {
    font-size: 3rem;
    text-align: center;
    margin-bottom: 16px;
}

.tutorial-card h3 {
    font-size: 1.3rem;
    color: #333;
    margin-bottom: 8px;
}

.tutorial-card p {
    color: #666;
    line-height: 1.5;
    margin-bottom: 16px;
}

.tutorial-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.duration {
    color: #666;
}

.difficulty {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.difficulty.beginner {
    background: #e8f5e8;
    color: #2d5a2d;
}

.difficulty.intermediate {
    background: #fff3cd;
    color: #856404;
}

.tutorial-start-btn {
    width: 100%;
    padding: 12px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.tutorial-start-btn:hover {
    background: #0056b3;
}

/* Tutorial Modal Styles */
.tutorial-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.tutorial-modal.hidden {
    display: none;
}

.tutorial-modal-content {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}

.tutorial-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #eee;
}

.tutorial-modal-header h2 {
    margin: 0;
    color: #333;
}

.tutorial-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tutorial-progress {
    padding: 20px 24px;
    border-bottom: 1px solid #eee;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: #007bff;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.9rem;
    color: #666;
}

.tutorial-content {
    padding: 24px;
    min-height: 200px;
}

.tutorial-actions {
    display: flex;
    justify-content: space-between;
    padding: 20px 24px;
    border-top: 1px solid #eee;
}

.tutorial-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.tutorial-btn.primary {
    background: #007bff;
    color: white;
}

.tutorial-btn.primary:hover {
    background: #0056b3;
}

.tutorial-btn.secondary {
    background: #6c757d;
    color: white;
}

.tutorial-btn.secondary:hover {
    background: #545b62;
}

.tutorial-btn.success {
    background: #28a745;
    color: white;
}

.tutorial-btn.success:hover {
    background: #1e7e34;
}

.tutorial-btn:disabled {
    background: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}

.tutorial-btn.hidden {
    display: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .tutorial-container {
        padding: 15px;
    }
    
    .tutorial-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .tutorial-modal {
        padding: 10px;
    }
    
    .tutorial-modal-content {
        max-height: 90vh;
    }
    
    .tutorial-actions {
        flex-direction: column;
        gap: 10px;
    }
    
    .tutorial-btn {
        width: 100%;
    }
}
</style>

<script>
// Tutorial data
const tutorials = {
    'first-game': {
        title: 'Your First Game',
        steps: [
            {
                title: 'Welcome!',
                content: `
                    <h3>🎮 Create Your First Game</h3>
                    <p>In this tutorial, you'll learn how to create and publish a simple jumping game in just 2 minutes!</p>
                    <p><strong>What you'll learn:</strong></p>
                    <ul>
                        <li>How to select a template</li>
                        <li>Basic game modifications</li>
                        <li>Testing your game</li>
                        <li>Publishing and sharing</li>
                    </ul>
                    <p>Let's get started!</p>
                `
            },
            {
                title: 'Step 1: Choose Template',
                content: `
                    <h3>📋 Select a Template</h3>
                    <p>First, let's create a new prototype:</p>
                    <ol>
                        <li>Tap the <strong>"New Prototype"</strong> button</li>
                        <li>Select <strong>"2D Platformer"</strong> template</li>
                        <li>Wait for initialization (about 15 seconds)</li>
                    </ol>
                    <div class="tutorial-tip">
                        💡 <strong>Tip:</strong> The 2D Platformer template includes basic jumping mechanics and simple enemies - perfect for beginners!
                    </div>
                `
            },
            {
                title: 'Step 2: Modify Your Game',
                content: `
                    <h3>✏️ Make It Your Own</h3>
                    <p>Now let's modify the game using chat commands:</p>
                    <div class="chat-example">
                        <strong>Type this command:</strong>
                        <code>"make the player jump twice as high"</code>
                    </div>
                    <p>Watch as the AI modifies your game in real-time!</p>
                    <div class="tutorial-tip">
                        💡 <strong>Try these too:</strong>
                        <ul>
                            <li>"change the background color to blue"</li>
                            <li>"add more enemies"</li>
                            <li>"make the player move faster"</li>
                        </ul>
                    </div>
                `
            },
            {
                title: 'Step 3: Test Your Game',
                content: `
                    <h3>🎯 Test Your Creation</h3>
                    <p>Time to see your game in action:</p>
                    <ol>
                        <li>Tap the <strong>"Preview"</strong> button</li>
                        <li>Your game opens in a new tab</li>
                        <li>Try the controls and see your modifications</li>
                        <li>Use arrow keys or touch controls to play</li>
                    </ol>
                    <div class="tutorial-tip">
                        💡 <strong>Mobile Tip:</strong> The game automatically adapts to touch controls on mobile devices!
                    </div>
                `
            },
            {
                title: 'Step 4: Publish & Share',
                content: `
                    <h3>🚀 Share Your Game</h3>
                    <p>Ready to show the world your creation?</p>
                    <ol>
                        <li>Tap the <strong>"Publish"</strong> button</li>
                        <li>Wait 30 seconds for the build to complete</li>
                        <li>Copy the generated link</li>
                        <li>Share with friends and family!</li>
                    </ol>
                    <div class="tutorial-success">
                        🎉 <strong>Congratulations!</strong> You've just created and published your first game!
                    </div>
                `
            }
        ]
    },
    'visual-effects': {
        title: 'Adding Visual Effects',
        steps: [
            {
                title: 'Visual Polish',
                content: `
                    <h3>✨ Make Your Game Beautiful</h3>
                    <p>Learn how to add visual effects that make your game stand out!</p>
                    <p><strong>In this tutorial:</strong></p>
                    <ul>
                        <li>Add particle effects</li>
                        <li>Change colors and atmosphere</li>
                        <li>Create screen effects</li>
                        <li>Polish your game's look</li>
                    </ul>
                `
            },
            {
                title: 'Particle Effects',
                content: `
                    <h3>💫 Add Particle Magic</h3>
                    <p>Let's add some sparkle to your game:</p>
                    <div class="chat-example">
                        <strong>Try these commands:</strong>
                        <code>"add particle effects when the player jumps"</code>
                        <code>"create sparkles around collectible items"</code>
                    </div>
                    <p>Particles make actions feel more impactful and fun!</p>
                `
            },
            {
                title: 'Colors & Atmosphere',
                content: `
                    <h3>🎨 Set the Mood</h3>
                    <p>Change the visual atmosphere:</p>
                    <div class="chat-example">
                        <strong>Atmosphere commands:</strong>
                        <code>"change the background to a sunset gradient"</code>
                        <code>"add fog to create depth in the scene"</code>
                        <code>"make the lighting warmer and more golden"</code>
                    </div>
                `
            },
            {
                title: 'Screen Effects',
                content: `
                    <h3>📺 Dynamic Effects</h3>
                    <p>Add effects that respond to gameplay:</p>
                    <div class="chat-example">
                        <strong>Dynamic effects:</strong>
                        <code>"add screen shake when enemies are defeated"</code>
                        <code>"create a flash effect when taking damage"</code>
                        <code>"add a glow effect around the player"</code>
                    </div>
                `
            }
        ]
    },
    'game-mechanics': {
        title: 'Game Mechanics',
        steps: [
            {
                title: 'Gameplay Depth',
                content: `
                    <h3>⚙️ Add Game Mechanics</h3>
                    <p>Transform your simple game into something engaging!</p>
                    <p><strong>You'll learn to add:</strong></p>
                    <ul>
                        <li>Scoring systems</li>
                        <li>Power-ups and abilities</li>
                        <li>Health and lives</li>
                        <li>User interface elements</li>
                    </ul>
                `
            },
            {
                title: 'Scoring System',
                content: `
                    <h3>🏆 Track Player Progress</h3>
                    <div class="chat-example">
                        <strong>Add scoring:</strong>
                        <code>"add a score that increases by 10 for each enemy defeated"</code>
                        <code>"display the score in the top-left corner"</code>
                        <code>"save the high score between games"</code>
                    </div>
                `
            },
            {
                title: 'Power-ups',
                content: `
                    <h3>⚡ Special Abilities</h3>
                    <div class="chat-example">
                        <strong>Create power-ups:</strong>
                        <code>"create a power-up that makes the player invincible for 5 seconds"</code>
                        <code>"add a speed boost power-up"</code>
                        <code>"make power-ups spawn randomly every 10 seconds"</code>
                    </div>
                `
            },
            {
                title: 'Health System',
                content: `
                    <h3>❤️ Player Health</h3>
                    <div class="chat-example">
                        <strong>Add health mechanics:</strong>
                        <code>"add a health bar at the top of the screen"</code>
                        <code>"player loses health when touching enemies"</code>
                        <code>"add health pickups that restore 1 heart"</code>
                    </div>
                `
            }
        ]
    },
    'mobile-optimization': {
        title: 'Mobile Optimization',
        steps: [
            {
                title: 'Mobile-First Gaming',
                content: `
                    <h3>📱 Optimize for Mobile</h3>
                    <p>Learn how to make your games perfect for mobile devices!</p>
                    <p><strong>Topics covered:</strong></p>
                    <ul>
                        <li>Touch controls</li>
                        <li>Performance optimization</li>
                        <li>Screen size adaptation</li>
                        <li>Mobile-specific features</li>
                    </ul>
                `
            },
            {
                title: 'Touch Controls',
                content: `
                    <h3>👆 Perfect Touch Experience</h3>
                    <div class="chat-example">
                        <strong>Touch optimization:</strong>
                        <code>"add on-screen touch buttons for mobile"</code>
                        <code>"make touch areas larger for easier tapping"</code>
                        <code>"add swipe gestures for movement"</code>
                    </div>
                `
            },
            {
                title: 'Performance',
                content: `
                    <h3>⚡ Fast Loading</h3>
                    <div class="chat-example">
                        <strong>Performance commands:</strong>
                        <code>"optimize graphics for mobile devices"</code>
                        <code>"reduce particle count for better performance"</code>
                        <code>"compress textures for faster loading"</code>
                    </div>
                `
            },
            {
                title: 'Screen Adaptation',
                content: `
                    <h3>📐 Responsive Design</h3>
                    <div class="chat-example">
                        <strong>Screen adaptation:</strong>
                        <code>"make the UI scale properly on different screen sizes"</code>
                        <code>"adjust camera view for portrait mode"</code>
                        <code>"ensure buttons are thumb-friendly"</code>
                    </div>
                `
            }
        ]
    }
};

let currentTutorial = null;
let currentStep = 0;

function startTutorial(tutorialId) {
    currentTutorial = tutorials[tutorialId];
    currentStep = 0;
    
    document.getElementById('tutorial-title').textContent = currentTutorial.title;
    document.getElementById('tutorial-modal').classList.remove('hidden');
    
    updateTutorialStep();
}

function closeTutorial() {
    document.getElementById('tutorial-modal').classList.add('hidden');
    currentTutorial = null;
    currentStep = 0;
}

function updateTutorialStep() {
    if (!currentTutorial) return;
    
    const step = currentTutorial.steps[currentStep];
    const totalSteps = currentTutorial.steps.length;
    
    // Update progress
    const progressPercent = ((currentStep + 1) / totalSteps) * 100;
    document.getElementById('tutorial-progress').style.width = progressPercent + '%';
    document.getElementById('progress-text').textContent = `Step ${currentStep + 1} of ${totalSteps}`;
    
    // Update content
    document.getElementById('tutorial-step-content').innerHTML = step.content;
    
    // Update buttons
    document.getElementById('tutorial-prev').disabled = currentStep === 0;
    
    if (currentStep === totalSteps - 1) {
        document.getElementById('tutorial-next').classList.add('hidden');
        document.getElementById('tutorial-complete').classList.remove('hidden');
    } else {
        document.getElementById('tutorial-next').classList.remove('hidden');
        document.getElementById('tutorial-complete').classList.add('hidden');
    }
}

function nextStep() {
    if (currentTutorial && currentStep < currentTutorial.steps.length - 1) {
        currentStep++;
        updateTutorialStep();
    }
}

function previousStep() {
    if (currentStep > 0) {
        currentStep--;
        updateTutorialStep();
    }
}

function completeTutorial() {
    // Show completion message
    alert('🎉 Tutorial completed! You\'re ready to create amazing games!');
    closeTutorial();
}

// Add tutorial-specific styles
const tutorialStyles = `
.chat-example {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin: 15px 0;
    border-radius: 4px;
}

.chat-example code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    display: block;
    margin: 5px 0;
}

.tutorial-tip {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 6px;
    padding: 12px;
    margin: 15px 0;
}

.tutorial-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 6px;
    padding: 12px;
    margin: 15px 0;
}
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = tutorialStyles;
document.head.appendChild(styleSheet);
</script>
@endsection