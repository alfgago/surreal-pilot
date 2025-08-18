# PlayCanvas Core Concepts

## Entity-Component-System (ECS) Architecture

PlayCanvas uses an Entity-Component-System architecture where:

- **Entities** are containers for components
- **Components** provide functionality (Model, Script, Camera, etc.)
- **Systems** process components each frame

### Creating Entities
```javascript
// Create a new entity
const entity = new pc.Entity('MyEntity');
this.app.root.addChild(entity);

// Add components
entity.addComponent('model', {
    type: 'box'
});
entity.addComponent('script');
```

## Script Component Lifecycle

### Core Methods
```javascript
// Called once after all resources are loaded
initialize: function() {
    // Cache references here
    this.player = this.app.root.findByName('Player');
},

// Called after initialize on all scripts
postInitialize: function() {
    // Setup that depends on other scripts being initialized
},

// Called every frame
update: function(dt) {
    // Game logic here - dt is delta time
},

// Called after update on all scripts  
postUpdate: function(dt) {
    // Actions that need to happen after all updates
},

// Called when entity is destroyed
onDestroy: function() {
    // Cleanup event listeners, timers, etc.
}
```

## Performance Best Practices

### Caching References
```javascript
// GOOD: Cache in initialize
initialize: function() {
    this.camera = this.app.root.findByName('Camera');
    this.transform = this.entity.getComponent('model');
},

update: function(dt) {
    // Use cached references
    this.camera.lookAt(this.entity.getPosition());
}

// BAD: Don't lookup every frame
update: function(dt) {
    const camera = this.app.root.findByName('Camera'); // Expensive!
    camera.lookAt(this.entity.getPosition());
}
```

### Object Pooling
```javascript
// Bullet pool example
initialize: function() {
    this.bulletPool = [];
    this.activeBullets = [];
    
    // Pre-create bullets
    for (let i = 0; i < 50; i++) {
        const bullet = this.createBullet();
        bullet.enabled = false;
        this.bulletPool.push(bullet);
    }
},

getBullet: function() {
    return this.bulletPool.pop() || this.createBullet();
},

returnBullet: function(bullet) {
    bullet.enabled = false;
    bullet.setPosition(0, -1000, 0); // Move offscreen
    this.bulletPool.push(bullet);
}
```

## Input Handling

### Mouse Input
```javascript
initialize: function() {
    this.app.mouse.on(pc.EVENT_MOUSEDOWN, this.onMouseDown, this);
    this.app.mouse.on(pc.EVENT_MOUSEMOVE, this.onMouseMove, this);
},

onMouseDown: function(event) {
    if (event.button === pc.MOUSEBUTTON_LEFT) {
        // Left click
    }
},

onMouseMove: function(event) {
    // event.dx, event.dy for movement delta
    // event.x, event.y for absolute position
}
```

### Touch Input
```javascript
initialize: function() {
    this.app.touch.on(pc.EVENT_TOUCHSTART, this.onTouchStart, this);
    this.app.touch.on(pc.EVENT_TOUCHMOVE, this.onTouchMove, this);
},

onTouchStart: function(event) {
    if (event.touches.length === 1) {
        // Single touch
        const touch = event.touches[0];
        // touch.x, touch.y for position
    }
}
```

### Keyboard Input
```javascript
update: function(dt) {
    if (this.app.keyboard.isPressed(pc.KEY_W)) {
        // Move forward
        this.entity.translateLocal(0, 0, -this.speed * dt);
    }
    
    if (this.app.keyboard.wasPressed(pc.KEY_SPACE)) {
        // Jump (one-time press)
        this.jump();
    }
}
```

## Mobile Optimization

### Responsive UI
```javascript
// Screen component setup for responsive UI
initialize: function() {
    const screen = this.entity.screen;
    screen.scaleMode = pc.SCALEMODE_BLEND;
    screen.referenceResolution = new pc.Vec2(1920, 1080);
    screen.scaleBlend = 0.5; // Balance between width and height scaling
},

// Element anchoring
this.entity.element.anchor = new pc.Vec4(0.5, 0.5, 0.5, 0.5); // Center
this.entity.element.pivot = new pc.Vec2(0.5, 0.5);
```

### Performance Monitoring
```javascript
// Monitor FPS and performance
update: function(dt) {
    this.frameTime += dt;
    this.frameCount++;
    
    if (this.frameTime >= 1.0) {
        const fps = this.frameCount / this.frameTime;
        console.log('FPS:', Math.round(fps));
        
        // Auto-adjust quality
        if (fps < 30) {
            this.reduceQuality();
        }
        
        this.frameTime = 0;
        this.frameCount = 0;
    }
}
```
