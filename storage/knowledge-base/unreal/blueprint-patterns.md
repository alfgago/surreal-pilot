# Unreal Engine Blueprint Patterns & Best Practices

## Blueprint Node Categories

### Essential Nodes
- **Event BeginPlay**: Fired when actor starts play
- **Event Tick**: Called every frame (use sparingly!)
- **Cast To**: Type conversion with null checking
- **IsValid**: Check if object reference is valid
- **Branch**: If/then logic flow
- **For Each Loop**: Iterate through arrays
- **Delay**: Wait for specified time
- **Timeline**: Smooth value interpolation over time

### Common Execution Flow
```
Event BeginPlay
├── Get Player Controller
├── Cast to MyPlayerController
├── Branch (Cast was successful?)
│   ├── True: Store reference and continue setup
│   └── False: Print error and destroy self
```

## Variable Management

### Naming Conventions
- **Booleans**: `bIsJumping`, `bCanFire`, `bGameActive`
- **Floats**: `MovementSpeed`, `JumpHeight`, `CurrentHealth`
- **Objects**: `PlayerRef`, `WeaponMesh`, `TargetActor`
- **Arrays**: `EnemyList`, `SpawnPoints`, `InventoryItems`

### Variable Categories
```
Instance Variables (per object):
- CurrentHealth (float)
- PlayerName (string)
- InventoryItems (array)

Class Defaults (shared by all instances):
- MaxHealth (float, default 100)
- MovementSpeed (float, default 600)
- WeaponDamage (float, default 25)
```

## Event Dispatchers for Decoupling

### Creating Event Dispatcher
```
// In Player Blueprint
Event Dispatcher: OnPlayerDeath
├── Parameters: 
│   ├── DeadPlayer (Actor Reference)
│   └── Killer (Actor Reference)

// Usage in Player Blueprint
On Take Damage
├── Subtract from CurrentHealth
├── Branch (Health <= 0?)
│   └── True: Call OnPlayerDeath dispatcher
```

### Binding to Event Dispatcher
```
// In Game Mode Blueprint
Event BeginPlay
├── Get All Actors of Class (Player)
├── For Each Actor
│   └── Bind Event to OnPlayerDeath
│       └── Connected to: Handle Player Death function
```

## Component Communication

### Blueprint Interface Method
```
// Create Blueprint Interface: BPI_Damageable
Function: TakeDamage
├── Inputs:
│   ├── Damage Amount (float)
│   ├── Damage Type (enum)
│   └── Instigator (Actor)
├── Outputs:
│   └── Was Killed (bool)

// Implementation in Actor Blueprint
Override TakeDamage
├── Subtract damage from health
├── Play damage effects
├── Branch (Health <= 0)
│   ├── True: Play death animation, return true
│   └── False: Return false
```

### Component Reference Pattern
```
// In Character Blueprint (BeginPlay)
Get Component by Class (Health Component)
├── Is Valid?
│   ├── True: Store as HealthComponentRef
│   └── False: Add Health Component, then store reference

// Later usage
Call Function on HealthComponentRef
├── Function: TakeDamage
├── Input: DamageAmount
```

## Performance Optimization Patterns

### Tick Alternatives
```
// AVOID: Constant Tick checking
Event Tick
├── Branch (bIsPlayerNearby?)
│   └── True: Update UI elements (expensive!)

// BETTER: Use Timers
Event BeginPlay
├── Set Timer by Event (UpdateUI)
│   ├── Time: 0.1 (10 times per second)
│   └── Looping: True

Custom Event: UpdateUI
├── Get Player Distance
├── Branch (Distance < 500?)
│   └── True: Update UI elements
```

### Caching References
```
// GOOD: Cache in BeginPlay
Event BeginPlay
├── Get Player Controller → Store as PlayerControllerRef
├── Get Player Pawn → Store as PlayerPawnRef
├── Get Component by Class (Audio) → Store as AudioComponentRef

// Use cached references everywhere else
Play Sound Function
├── Call Play on AudioComponentRef (fast!)

// BAD: Don't repeatedly get references
Play Sound Function
├── Get Player Controller (slow!)
├── Get Controlled Pawn (slow!)
├── Get Component by Class (slow!)
├── Play Sound
```

## Input Handling Patterns

### Enhanced Input System (UE5)
```
// Input Action Setup
Create Input Action: IA_Move
├── Value Type: Vector2D (for WASD/analog stick)

Create Input Action: IA_Jump  
├── Value Type: Boolean (for space/button)

// Binding in Blueprint
Event BeginPlay
├── Get Enhanced Input Local Player Subsystem
├── Add Mapping Context (IMC_Player)

Enhanced Input Action (IA_Move)
├── Get Action Value (Vector2D)
├── Add Movement Input
│   ├── World Direction: Get Actor Forward Vector
│   └── Scale Value: Action Value.X

Enhanced Input Action (IA_Jump)
├── Branch (Action Value > 0?)
│   └── True: Jump
```

## Animation Blueprint Integration

### State Machine Structure
```
Locomotion State Machine:
├── Idle State
│   ├── Transition to Walk: Speed > 0
├── Walk State  
│   ├── Transition to Idle: Speed ≤ 0
│   ├── Transition to Run: Speed > WalkThreshold
├── Run State
│   ├── Transition to Walk: Speed ≤ WalkThreshold
├── Jump State
│   ├── Entry: bIsJumping = true
│   ├── Transition to Fall: Velocity.Z < 0
├── Fall State
│   ├── Transition to Land: bIsOnGround = true
```

### Variable Updates from Character
```
// In Character Blueprint (Tick or Timer)
Update Animation Variables
├── Get Velocity → Calculate Speed
├── Get Movement Component → Is Falling?
├── Store in Animation Blueprint variables:
│   ├── MovementSpeed (float)
│   ├── bIsInAir (bool)
│   ├── bIsCrouching (bool)
│   └── AimYaw (float)
```

## Widget Blueprint (UI) Patterns

### Responsive Anchoring
```
// Main Menu Widget Setup
Canvas Panel
├── Background Image
│   ├── Anchors: Full Screen (0,0,1,1)
│   └── Size To Content: False
├── Title Text
│   ├── Anchors: Top Center (0.5,0,0.5,0)
│   └── Position: (0, 100)
├── Button Panel
│   ├── Anchors: Center (0.5,0.5,0.5,0.5)
│   └── Vertical Box: Play, Settings, Quit buttons
```

### Data Binding Pattern
```
// Health Bar Widget
Event Pre Construct
├── Get Owning Player Pawn
├── Cast to MyCharacter
├── Bind to OnHealthChanged event dispatcher

Custom Event: Update Health Display
├── Input: New Health Percentage (float)
├── Set Progress Bar Percent
├── Update Health Text
├── Play damage effect if health decreased
```
