# Requirements Document

## Introduction

The PlayCanvas Integration feature will enable Surreal Pilot users to create, edit, test, and publish PlayCanvas games entirely through chat prompts from their mobile phone or PC without any complex setup or GUI editor requirements. This mobile-first integration leverages the open-source PlayCanvas Engine and Editor Frontend, providing an effortless prototyping experience where users can go from idea to playable game in minutes using only natural language commands, curated demo templates, instant one-click publishing, and disposable multiplayer testing capabilities.

## Requirements

### Requirement 1

**User Story:** As a developer, I want to integrate the PlayCanvas engine and MCP server into Surreal Pilot, so that I can manipulate PlayCanvas projects through chat prompts.

#### Acceptance Criteria

1. WHEN the system starts THEN it SHALL initialize the PlayCanvas MCP server on a random port
2. WHEN the MCP server is running THEN it SHALL respond to /v1/context requests
3. WHEN a preview is requested THEN the system SHALL return an HTML page with the game running
4. WHEN the MCP server is integrated THEN it SHALL capture the process ID and preview URL for management

### Requirement 2

**User Story:** As a user, I want access to curated demo templates, so that I can quickly start prototyping with proven game foundations.

#### Acceptance Criteria

1. WHEN the system loads THEN it SHALL provide a registry of available demo templates
2. WHEN templates are requested via /api/demos THEN the system SHALL return a JSON list of available demos
3. WHEN a user selects a demo THEN the system SHALL clone the template into a new workspace
4. WHEN templates are provided THEN they SHALL include Starter FPS, Third-Person, and 2D Platformer options

### Requirement 3

**User Story:** As a mobile or PC user, I want to create game prototypes instantly with zero setup, so that I can test game ideas immediately from any device.

#### Acceptance Criteria

1. WHEN a user requests a prototype via POST /api/prototype THEN the system SHALL return workspace_id and preview_url within 15 seconds
2. WHEN a prototype is created THEN the preview link SHALL load perfectly on mobile devices without errors or performance issues
3. WHEN a prototype workspace is created THEN it SHALL be associated with the requesting company
4. WHEN accessed from mobile THEN the prototype SHALL be touch-optimized and responsive
5. WHEN users start prototyping THEN they SHALL require zero technical knowledge or setup steps

### Requirement 4

**User Story:** As a mobile or PC user, I want to modify PlayCanvas projects through simple chat prompts, so that I can iterate on game mechanics effortlessly without any technical knowledge or visual editors.

#### Acceptance Criteria

1. WHEN a user sends a PlayCanvas-related prompt from mobile or PC THEN the AssistController SHALL route the request through the MCP server
2. WHEN a modification prompt is processed THEN the system SHALL apply changes and return an updated preview optimized for the user's device
3. WHEN changes are made THEN the preview SHALL reflect the modifications immediately on both mobile and desktop
4. WHEN simple prompts like "double the jump height" or "make enemies faster" are given THEN the system SHALL modify relevant game parameters accordingly
5. WHEN users interact via mobile chat THEN the interface SHALL provide intuitive touch-friendly controls and suggestions

### Requirement 5

**User Story:** As a company administrator, I want PlayCanvas operations to be tracked and billed appropriately, so that I can manage costs and usage.

#### Acceptance Criteria

1. WHEN PlayCanvas operations are performed THEN the system SHALL calculate prompt tokens plus MCP action surcharge
2. WHEN credits are consumed THEN the company's credit balance SHALL be decremented accordingly
3. WHEN credit usage occurs THEN the UI badge SHALL update in real-time
4. WHEN MCP actions are performed THEN they SHALL incur a 0.1 credit surcharge per action

### Requirement 6

**User Story:** As a mobile or PC user, I want to publish my PlayCanvas games with one tap/click, so that I can instantly share playable prototypes with others from any device.

#### Acceptance Criteria

1. WHEN a user requests publishing from mobile or PC THEN the system SHALL build the project and upload to S3 with CloudFront distribution
2. WHEN a build is published THEN the system SHALL return a public link that loads in under 1 second on mobile devices
3. WHEN files are uploaded THEN they SHALL be compressed with gzip and Brotli headers for optimal mobile performance
4. WHEN publishing completes THEN CloudFront invalidation SHALL be triggered for immediate availability
5. WHEN shared links are accessed THEN they SHALL work perfectly on mobile browsers without any additional setup or downloads

### Requirement 7

**User Story:** As a user with PlayCanvas cloud credentials, I want to optionally publish to PlayCanvas cloud, so that I can leverage their hosting infrastructure.

#### Acceptance Criteria

1. WHEN a user provides PlayCanvas API key and Project ID THEN the system SHALL support cloud publishing
2. WHEN cloud publishing is requested THEN the system SHALL call the PlayCanvas publish API
3. WHEN cloud publishing completes THEN the system SHALL return the launch URL
4. WHEN cloud publishing is used THEN only build tokens SHALL be deducted from credits

### Requirement 8

**User Story:** As a mobile-first user, I want a perfectly optimized chat interface for PlayCanvas operations, so that prototyping games on my phone is as easy as sending text messages.

#### Acceptance Criteria

1. WHEN using mobile devices THEN the interface SHALL provide a touch-friendly demo chooser modal with large, easy-to-tap options
2. WHEN a workspace has a published URL THEN a prominent "Preview" button SHALL be available and optimized for thumb navigation
3. WHEN mobile users access the interface THEN a "Publish" command SHALL be easily accessible in the mobile-optimized toolbar
4. WHEN tested on mobile browsers THEN the interface SHALL achieve a Lighthouse PWA score of 90 or higher
5. WHEN typing on mobile THEN the chat interface SHALL provide smart suggestions and auto-complete for common game modification commands
6. WHEN using the interface THEN it SHALL work seamlessly in both portrait and landscape orientations
7. WHEN accessing from mobile THEN all interactions SHALL be optimized for touch with appropriate button sizes and spacing

### Requirement 9

**User Story:** As a developer testing multiplayer features, I want temporary multiplayer servers, so that I can test real-time gameplay without permanent infrastructure.

#### Acceptance Criteria

1. WHEN multiplayer testing is requested THEN the system SHALL spawn an AWS Fargate task with PlayCanvas real-time server
2. WHEN a multiplayer server starts THEN it SHALL be accessible via ngrok tunnel with 40-minute TTL
3. WHEN two mobile devices join the same session THEN latency SHALL be under 100ms in US-East region
4. WHEN the TTL expires THEN the server and tunnel SHALL be automatically terminated

### Requirement 10

**User Story:** As a system administrator, I want automatic cleanup of temporary resources, so that costs are controlled and storage is managed efficiently.

#### Acceptance Criteria

1. WHEN workspaces are older than 24 hours THEN they SHALL be automatically deleted
2. WHEN workspaces are deleted THEN associated CloudFront paths SHALL be cleaned up
3. WHEN multiplayer sessions expire THEN Fargate tasks and ngrok tunnels SHALL be terminated
4. WHEN cleanup runs THEN S3 buckets and ECS lists SHALL show no stale artifacts

### Requirement 11

**User Story:** As a new mobile or PC user with no game development experience, I want simple documentation and onboarding, so that I can create and publish my first game prototype in minutes without any technical knowledge.

#### Acceptance Criteria

1. WHEN documentation is accessed THEN it SHALL include step-by-step instructions for spinning up prototypes from mobile or PC
2. WHEN users read the docs THEN they SHALL understand how to use built-in templates with simple chat commands
3. WHEN advanced users need customization THEN documentation SHALL explain adding custom demo repositories
4. WHEN following the documentation THEN internal QA SHALL be able to publish a game in under 5 minutes from a mobile device
5. WHEN new users start THEN they SHALL have access to interactive tutorials that work on mobile
6. WHEN users need help THEN the documentation SHALL include common chat prompt examples for game modifications

### Requirement 12

**User Story:** As a user of both Unreal Engine and PlayCanvas features, I want both integrations to work seamlessly together, so that I can choose the right engine for each project without conflicts.

#### Acceptance Criteria

1. WHEN both Unreal Engine and PlayCanvas workspaces exist THEN they SHALL operate independently without interference
2. WHEN switching between engine types THEN the assistant SHALL correctly route requests to the appropriate engine's MCP server
3. WHEN Unreal Engine functionality is used THEN existing features SHALL continue to work exactly as before
4. WHEN PlayCanvas features are added THEN they SHALL not modify or break existing Unreal Engine code paths
5. WHEN users have projects in both engines THEN the UI SHALL clearly indicate which engine type each workspace uses
6. WHEN engine-specific commands are used THEN the system SHALL prevent cross-engine command execution

### Requirement 13

**User Story:** As a developer, I want comprehensive test coverage for PlayCanvas features, so that the integration is reliable and maintainable.

#### Acceptance Criteria

1. WHEN tests run THEN they SHALL cover template cloning, assistance operations, and diff assertions
2. WHEN integration tests execute THEN they SHALL use HTTP fakes for S3 and CloudFront operations
3. WHEN Node.js components are tested THEN Jest tests SHALL cover DemoLoader scripts
4. WHEN CI runs THEN it SHALL pass with at least 85% code coverage for new modules
5. WHEN tests run THEN they SHALL verify that Unreal Engine functionality remains unaffected