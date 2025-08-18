# Implementation Plan

-   [x] 1. Set up database schema and core models

    -   Create migration for workspaces table with engine_type, mcp_port, mcp_pid, preview_url, published_url, status, and metadata fields
    -   Create migration for demo_templates table with engine_type, repository_url, preview_image, tags, and difficulty_level fields
    -   Create migration for multiplayer_sessions table with workspace_id, fargate_task_arn, ngrok_url, session_url, and expires_at fields
    -   Create Workspace model with relationships and engine detection methods
    -   Create DemoTemplate model with PlayCanvas-specific methods
    -   Create MultiplayerSession model with status management
    -   _Requirements: 1.1, 2.1, 9.1_

-

-   [x] 2. Implement PlayCanvas MCP server integration

    -   Create PlayCanvasMcpManager service class with startServer, stopServer, sendCommand, and getServerStatus methods
    -   Add git submodule for PlayCanvas editor-mcp-server to vendor/pc_mcp directory
    -   Create Dockerfile for PlayCanvas MCP server with Node.js 18 Alpine base
    -   Implement MCP server port management and process tracking
    -   Create MCP server health check and restart mechanisms
    -   Write unit tests for PlayCanvasMcpManager with mocked server responses
    -   _Requirements: 1.1, 1.2, 1.3, 1.4_

-   [x] 3. Create workspace management service

    -   Implement WorkspaceService class with createFromTemplate, startMcpServer, stopMcpServer, and cleanup methods

    -   Add workspace creation logic that clones templates and initializes MCP servers
    -   Implement workspace status tracking (initializing, ready, building, published, error)
    -   Create workspace cleanup job for removing old workspaces and associated resources
    -   Add workspace filtering by engine type and company
    -   Write unit tests for WorkspaceService covering all CRUD operations and status transitions
    -   _Requirements: 3.1, 3.2, 3.3, 10.1, 10.2_

-   [x] 4. Implement template registry system

    -   Create TemplateRegistry service with getAvailableTemplates, cloneTemplate, and validateTemplate methods
    -   Seed demo_templates table with PlayCanvas starter templates (FPS, Third-Person, 2D Platformer, Tower Defense)
    -   Implement git clone functionality for template repositories
    -   Add template validation to ensure proper PlayCanvas project structure
    -   Create template preview image handling and storage

    -   Write unit tests for TemplateRegistry with mocked git operations
    -   _Requirements: 2.1, 2.2, 2.3, 2.4_

-   [x] 5. Create prototype workflow API endpoints

    -   Add POST /api/demos route to return available PlayCanvas templates
    -   Add POST /api/prototype route that accepts demo_id and company_id, returns workspace_id and preview_url
    -   Implement prototype creation logic with 15-second timeout requirement
    -   Add workspace status polling endpoint GET /api/workspace/{id}/status
    -   Create error handling for prototype creation failures
    -   Write integration tests for prototype workflow API with database transactions
    -   _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

-   [x] 6. Extend AssistController for PlayCanvas routing

    -   Modify buildSystemMessage method to detect engine type and provide PlayCanvas-specific context
    -   Add routeToMcpServer method that routes commands to appropriate MCP server based on workspace engine type
    -   Implement PlayCanvas context handling for scene, entities, and component data
    -   Add engine type detection from workspace or context parameters
    -   Ensure existing Unreal Engine functionality remains completely unchanged
    -   Write unit tests verifying correct routing for both engine types
    -   _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 12.1, 12.2, 12.3, 12.4_

-   [x] 7. Implement credit system integration with MCP surcharge

    -   Add MCP action tracking middleware that counts PlayCanvas MCP operations
    -   Implement 0.1 credit surcharge per MCP action in addition to token costs
    -   Modify credit deduction logic to include MCP surcharge in transaction metadata
    -   Add real-time credit balance updates for PlayCanvas operations
    -   Create credit usage analytics for PlayCanvas vs Unreal Engine operations
    -   Write unit tests for credit calculations including MCP surcharges
    -   _Requirements: 5.1, 5.2, 5.3, 5.4_

-   [x] 8. Create publishing system for static deployment

    -   Implement PublishService class with publishToStatic, buildProject, and invalidateCloudFront methods
    -   Add npm build execution for PlayCanvas projects with error handling
    -   Implement cloudflare or github pages upload with gzip and Brotli compression for mobile optimization if makes sense, can be all projects in same repo subfolders for now.
    -   Create distribution configuration for fast mobile load times
    -   Add published URL generation and storage in workspace model
    -   Write integration tests using cloudflare and github pages HTTP fakes
    -   _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

-   [x] 9. Add optional PlayCanvas cloud publishing

    -   Implement PlayCanvas API integration for cloud publishing
    -   Add user credential management for PlayCanvas API keys and Project IDs
    -   Create publishToPlayCanvasCloud method in PublishService
    -   Add cloud publishing option in UI with credential input
    -   Implement credit deduction for cloud publishing (build tokens only)
    -   Write unit tests for PlayCanvas cloud API integration with mocked responses
    -   _Requirements: 7.1, 7.2, 7.3, 7.4_

-   [x] 10. Create mobile-optimized UI components

    -   Design and implement mobile-first demo chooser modal with large touch targets
    -   Add responsive "Preview" button that works in portrait and landscape orientations
    -   Create mobile-optimized "Publish" command in toolbar with thumb-friendly positioning
    -   Implement smart suggestions and auto-complete for common PlayCanvas commands
    -   Add touch-optimized chat interface with appropriate button sizes and spacing
    -   Write Playwright tests for mobile UI interactions and responsive design
    -   _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

-   [ ] 11. Implement PWA optimization for mobile

    -   Configure service worker for offline capability and fast loading, if offline store the changes in the local laraveln storage. When connection is recovered upload to the web-based laravel.
    -   Optimize bundle size and implement code splitting for mobile performance
    -   Add PWA manifest with appropriate icons and theme colors
    -   Implement lazy loading for non-critical components
    -   Optimize images and assets for mobile bandwidth
    -   Run Lighthouse audits and achieve PWA score of 90+ on mobile
    -   _Requirements: 8.4, 8.7_

-   [x] 12. Create multiplayer testing infrastructure

    -   Implement MultiplayerService class with startSession, stopSession, and getSessionStatus methods
    -   Create task definition for PlayCanvas multiplayer server. Allow easily toggleable multiplayer functionality where a player can host a server and store files in their pc for database progress.

    -   When generated, compile and store in our laravel server or wherever laravel define the storage to be, and make sure these are public routes with accurate project-based folders for project separation
    -   Implement POST /api/multiplayer/start endpoint that spawns Fargate tasks
    -   Add session management with automatic cleanup after TTL expiration
    -   Write integration tests for multiplayer session lifecycle with AWS service mocks

-   [x] 13. Implement resource cleanup and garbage collection

    -   Create scheduled job for cleaning up workspaces older than 24 hours
    -   Add CloudFront path cleanup when workspaces are deleted
    -   Implement Fargate task and ngrok tunnel termination for expired multiplayer sessions
    -   Create git repo for all sample projects or analyze if there is a better, affordable option like S3 or Supabase or CloudFlare, bucket cleanup to remove stale build artifacts
    -   Add ECS task cleanup to prevent resource leaks
    -   Write unit tests for cleanup operations with mocked AWS services
    -   _Requirements: 10.1, 10.2, 10.3, 10.4_

-   [x] 14. Create documentation and onboarding

    -   Write /docs/05-playcanvas.md with step-by-step mobile and PC instructions
    -   Document built-in template usage with simple chat command examples
    -   Add custom demo repository setup instructions for advanced users
    -   Create interactive mobile tutorials for new users
    -   Document common chat prompt examples for game modifications

    -   Ensure documentation enables 5-minute game publishing workflow
    -   _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6_

-   [x] 15. Implement comprehensive testing suite

    -   Write Pest tests for template cloning, assistance operations, and diff assertions
    -   Create integration tests with HTTP fakes for static build services
    -   Add Jest tests for PlayCanvas MCP server DemoLoader scripts
    -   Implement mobile browser testing with Playwright for touch interactions
    -   Create performance tests for mobile load times and concurrent user handling
    -   Verify Unreal Engine functionality remains unaffected with regression tests
    -   _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

-   [x] 16. Add cross-engine compatibility safeguards


    -   Implement engine type validation in all PlayCanvas-specific endpoints
    -   Add workspace engine type checks before routing MCP commands
    -   Create UI indicators to clearly show which engine type each workspace uses
    -   Implement command validation to prevent cross-engine command execution
    -   Add database constraints to ensure data integrity between engine types
    -   Write integration tests verifying complete isolation between engine types
    -   _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_
