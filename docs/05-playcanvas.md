# PlayCanvas Integration Guide

## Quick Start: From Idea to Published Game in 5 Minutes

Welcome to Surreal Pilot's PlayCanvas integration! This guide will help you create, modify, and publish games using only chat commands - no complex setup required.

> 📋 **Quick Reference:** Use the [Onboarding Checklist](./playcanvas-onboarding-checklist.md) to ensure you can consistently publish games in under 5 minutes.

### 🚀 The 5-Minute Workflow

1. **Choose a Template** (30 seconds)
2. **Create Your Prototype** (1 minute)
3. **Modify with Chat** (2-3 minutes)
4. **Publish & Share** (30 seconds)

---

## Getting Started

### For Mobile Users 📱

#### Step 1: Access Surreal Pilot
1. Open your mobile browser (Chrome, Safari, Firefox)
2. Navigate to your Surreal Pilot workspace
3. The interface automatically optimizes for your screen size

#### Step 2: Create Your First Game
1. Tap the **"New Prototype"** button (large, thumb-friendly)
2. Choose from available templates:
   - **Starter FPS** - First-person shooter basics
   - **Third-Person** - Character controller with camera
   - **2D Platformer** - Side-scrolling jump mechanics
   - **Tower Defense** - Strategy game foundation

3. Tap your preferred template
4. Wait 15 seconds for your workspace to initialize
5. Tap **"Preview"** to see your game running

#### Step 3: Modify Your Game with Chat
Use the mobile-optimized chat interface with these example commands:

**Basic Modifications:**
```
"Make the player jump higher"
"Change the background color to blue"
"Add more enemies"
"Make the game faster"
```

**Advanced Modifications:**
```
"Add a health bar to the player"
"Create a power-up that doubles jump height"
"Add sound effects when enemies are defeated"
"Change the camera to follow the player more smoothly"
```

#### Step 4: Publish Your Game
1. Tap the **"Publish"** button in the mobile toolbar
2. Wait 30 seconds for build completion
3. Share the generated link with friends
4. Your game loads in under 1 second on mobile devices

### For PC Users 💻

#### Step 1: Access Your Workspace
1. Open Surreal Pilot in your browser or desktop app
2. Navigate to the PlayCanvas section
3. The interface provides full desktop functionality

#### Step 2: Create a Prototype
1. Click **"New Prototype"**
2. Browse available templates with preview images
3. Select your template and click **"Create"**
4. Your workspace initializes with a live preview

#### Step 3: Iterate with Natural Language
Type commands in the chat interface:

**Gameplay Modifications:**
```
"Add a scoring system that increases when enemies are defeated"
"Create a main menu with start and quit buttons"
"Add particle effects when the player lands from a jump"
"Implement a simple inventory system"
```

**Visual Enhancements:**
```
"Add fog to create depth in the scene"
"Change the lighting to create a sunset atmosphere"
"Add animated textures to the environment"
"Create a skybox with moving clouds"
```

#### Step 4: Publish and Share
1. Click **"Publish"** in the toolbar
2. Choose publishing method:
   - **Static Hosting** (S3 + CloudFront) - Free, fast
   - **PlayCanvas Cloud** (requires API key) - Advanced features
3. Receive your shareable game URL

---

## Built-in Templates

### 🎯 Starter FPS
**Best for:** Action games, shooting mechanics
**Includes:** Player controller, weapon system, basic enemies
**Difficulty:** Beginner

**Quick Modifications:**
```
"Add a crosshair to the center of the screen"
"Make bullets travel faster"
"Add a reload mechanic with 30 bullets per magazine"
"Create different weapon types"
```

### 🏃 Third-Person
**Best for:** Adventure games, character-based gameplay
**Includes:** Character controller, third-person camera, basic animations
**Difficulty:** Beginner

**Quick Modifications:**
```
"Add a double jump ability"
"Create collectible coins scattered around the level"
"Add a sprint function when holding shift"
"Change the character model to a robot"
```

### 🦘 2D Platformer
**Best for:** Classic side-scrolling games
**Includes:** 2D physics, jumping mechanics, simple enemies
**Difficulty:** Beginner

**Quick Modifications:**
```
"Add moving platforms that go up and down"
"Create checkpoints that save player progress"
"Add a wall-jump mechanic"
"Include power-ups that change player size"
```

### 🏰 Tower Defense
**Best for:** Strategy games, resource management
**Includes:** Tower placement, enemy waves, basic economy
**Difficulty:** Intermediate

**Quick Modifications:**
```
"Add a new tower type that shoots ice to slow enemies"
"Create flying enemies that take a different path"
"Add an upgrade system for existing towers"
"Include boss enemies with more health"
```

---

## Common Chat Commands

> 📚 **Complete Reference:** For a comprehensive list of commands with advanced examples, see [PlayCanvas Chat Commands Reference](./playcanvas-chat-commands.md)

### Quick Reference

### Movement & Controls
```
"Make the player move faster"
"Add WASD controls for movement"
"Create smooth camera following"
"Add mouse look controls"
"Enable touch controls for mobile"
```

### Visual Effects
```
"Add particle effects when jumping"
"Create a glowing effect around collectibles"
"Add screen shake when taking damage"
"Change the fog color to create atmosphere"
"Add animated water with reflections"
```

### Game Mechanics
```
"Add a health system with 3 lives"
"Create a timer that counts down from 60 seconds"
"Add sound effects for all player actions"
"Implement a simple dialogue system"
"Create a pause menu"
```

### UI Elements
```
"Add a score display in the top-left corner"
"Create a minimap showing player position"
"Add a progress bar for level completion"
"Include on-screen instructions for new players"
"Create a game over screen with restart button"
```

---

## Advanced: Custom Demo Repositories

### Setting Up Your Own Templates

For advanced users who want to create custom starting templates:

#### Repository Structure
```
my-custom-template/
├── package.json
├── src/
│   ├── index.html
│   ├── scripts/
│   └── assets/
├── playcanvas.json
└── README.md
```

#### Required Files

**package.json**
```json
{
  "name": "my-playcanvas-template",
  "version": "1.0.0",
  "scripts": {
    "build": "playcanvas-build",
    "dev": "playcanvas-dev"
  },
  "dependencies": {
    "playcanvas": "^1.60.0"
  }
}
```

**playcanvas.json**
```json
{
  "name": "My Custom Template",
  "description": "A custom starting point for games",
  "tags": ["custom", "advanced"],
  "difficulty": "intermediate",
  "estimatedSetupTime": 300
}
```

#### Adding Your Template
1. Create a public GitHub repository with your template
2. Contact support to add it to the template registry
3. Include preview images and documentation
4. Test with the validation system

---

## Interactive Mobile Tutorials

Access interactive tutorials at `/mobile/tutorials` for hands-on learning with step-by-step guidance.

### Tutorial 1: Your First Game (2 minutes)
**Goal:** Create and publish a simple jumping game

1. **Start:** Tap "New Prototype" → Select "2D Platformer"
2. **Modify:** Type "make the player jump twice as high"
3. **Test:** Tap "Preview" and try the game
4. **Publish:** Tap "Publish" and share your link
5. **Success:** You've created your first game!

### Tutorial 2: Adding Visual Effects (3 minutes)
**Goal:** Make your game more visually appealing

1. **Start:** Open your existing prototype or create new one
2. **Add Effects:** Type "add particle effects when the player jumps"
3. **Change Colors:** Type "change the background to a sunset gradient"
4. **Add Polish:** Type "add screen shake when enemies are defeated"
5. **Preview:** See your enhanced game in action

### Tutorial 3: Game Mechanics (4 minutes)
**Goal:** Add gameplay depth with scoring and power-ups

1. **Scoring:** Type "add a score that increases by 10 for each enemy defeated"
2. **Power-ups:** Type "create a power-up that makes the player invincible for 5 seconds"
3. **UI:** Type "add a health bar at the top of the screen"
4. **Challenge:** Type "spawn enemies every 3 seconds"
5. **Test:** Play your enhanced game

### Tutorial 4: Mobile Optimization (3 minutes)
**Goal:** Optimize your games for mobile devices

1. **Touch Controls:** Type "add on-screen touch buttons for mobile"
2. **Performance:** Type "optimize graphics for mobile devices"
3. **UI Scaling:** Type "make UI elements larger for mobile"
4. **Test:** Preview on your mobile device

---

## Troubleshooting

### Common Issues

**"My game won't load on mobile"**
- Ensure you're using a modern mobile browser
- Check your internet connection
- Try refreshing the page
- Clear browser cache if needed

**"The preview is taking too long"**
- Wait up to 30 seconds for initial load
- Check if your workspace is still initializing
- Try creating a new prototype if issues persist

**"My modifications aren't working"**
- Be specific in your commands
- Try simpler modifications first
- Check the chat for error messages
- Use the example commands as templates

**"Publishing failed"**
- Ensure your game builds without errors
- Check your company's credit balance
- Try publishing again after a few minutes
- Contact support if issues persist

### Getting Help

**In-App Support:**
- Type "help" in the chat for quick assistance
- Use the "?" button for context-sensitive help

**Community:**
- Join our Discord for community support
- Check the FAQ section for common questions
- Browse example projects for inspiration

---

## Performance Tips

### Mobile Optimization
- Keep asset sizes small for faster loading
- Use compressed textures when possible
- Limit particle effects for better performance
- Test on various mobile devices
- Ensure interface works in both portrait and landscape orientations
- Achieve Lighthouse PWA score of 90+ for optimal mobile experience

### Best Practices
- Start with simple modifications
- Test frequently during development
- Use descriptive names for game objects
- Keep your game scope manageable

---

## What's Next?

### Multiplayer Testing
Once you're comfortable with single-player games, try:
```
"Enable multiplayer for up to 4 players"
"Add player names above characters"
"Create a lobby system for joining games"
```

### Advanced Publishing
- Set up PlayCanvas Cloud credentials for advanced hosting
- Configure custom domains for your games
- Implement analytics to track player behavior

### Integration with Other Tools
- Export your games for mobile app stores
- Integrate with social media for sharing
- Add monetization features

---

## Success Stories

**"I created my first game in 3 minutes!"** - Sarah, Mobile User
*"I just typed 'make a jumping game with coins to collect' and had a playable prototype instantly. The mobile interface made it so easy to test and share with friends."*

**"From prototype to published in one lunch break"** - Mike, Indie Developer
*"I used the FPS template and added my own mechanics through chat. Published it and got feedback from players the same day. This is game development at the speed of thought."*

**"Perfect for rapid prototyping"** - Jessica, Game Designer
*"I can test game ideas immediately without setting up complex development environments. The 5-minute workflow is real - I've done it multiple times."*

---

Ready to create your first game? Tap "New Prototype" and start building! 🎮