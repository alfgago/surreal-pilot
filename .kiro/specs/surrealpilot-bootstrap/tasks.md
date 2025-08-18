# Implementation Plan

-   [x] 1. Install and configure core dependencies

    -   Install Prism-PHP package via composer
    -   Install Cashier Billing Provider for Filament
    -   Install NativePHP packages
    -   _Requirements: 1.1, 1.3_

-   [x] 2. Create Prism-PHP configuration and provider setup

    -   Create config/prism.php with all provider configurations
    -   Set up environment variables for API keys
    -   Implement provider fallback logic
    -   _Requirements: 1.1, 1.2, 1.4_

-

-   [x] 3. Extend Company model with credits and billing fields

    -   Create migration to add credits, plan, and monthly_credit_limit to companies table
    -   Update Company model with new fillable fields and relationships
    -   Add credit-related methods to Company model
    -   _Requirements: 2.1, 2.2_

-   [x] 4. Create credit transaction system

    -   Create CreditTransaction model and migration

    -   Implement CreditManager service class
    -   Add credit transaction relationship to Company model
    -   Write unit tests for credit operations
    -   _Requirements: 2.2, 2.3, 2.5_

-   [x] 5. Create subscription plans seeder

    -   Create SubscriptionPlan model and migration
    -   Implement seeder for Starter, Pro, and Enterprise plans
    -   Link subscription plans to credit allocations
    -   _Requirements: 2.1, 2.4_

-   [x] 6. Implement AI provider resolution middleware

    -   Create ResolveAiDriver middleware class
    -   Implement provider selection

and fallback logic - Add middleware to API routes - Write tests for provider resolution - _Requirements: 1.2, 1.4, 1.5_

-   [x] 7. Create streaming chat API endpoint

    -   Create AssistController with chat method
    -   Implement ChatRequest validation class
    -   Set up streaming response with Server-Sent Events
    -   Integrate Prism-PHP streaming capabilities
    -   _Requirements: 3.1, 3.2, 3.3_

-

-   [x] 8.  Integrate credit deduction with streaming API

            -   Add real-time token counting to streaming responses
            -   Implement credit deduction during streaming
            -   Add credit validation before processing requests
            -   Handle insufficient credits error re

        sponses - _Requirements: 3.4, 2.3, 2.5_

-

-   [x] 9. Create Filament dashboard widgets

        - Create CreditBalanceWidget for current credit display
        - Create UsageAnalyticsWidget with chart
          functionality
        - Implement credit top-up purchase component
          -- Add widgets to Filament dashboard conf

iguration - _Requirements: 4.1, 4.2, 4.3_

-   [x] 10. Integrate Cashier Billing Provider

    -   Configure Cashier Billing Provider in Filament
    -   Set up Stripe webhook handling for credit purchases
    -   Implement automatic credit addition on successful payments
    -   Create billing history view in dashboard

    -   _Requirements: 4.4, 4.5, 8.2, 8.3_

-   [x] 11. Implement role-based access control

    -   Create middleware to check developer role permissions
    -   Integrate with Filament Companies role system
    -   Add permission checks to API endpoints
    -   Implement access denied error handling
    -   _Requirements: 7.1, 7.2, 7.3, 7.5_

-   [x] 12. Set up NativePHP desktop application

    -   Configure NativePHP (https://nativephp.com/docs/desktop/1/getting-started/introduction), I have already run composer require nativephp/electron and php artisan native:install
    -   Create desktop-specific routes and controllers
    -   Implement local API server on port 8000
    -   Implement port collision fallback — if 8000 is busy, choose 8001 and write to config.json; UE plugin should read that.
    -   Create basic desktop UI with chat interface that will later be used to communicate with unreal engine.
    -   _Requirements: 5.1, 5.2, 5.4_

-   [x] 13. Create local configuration management for desktop

    -   Implement LocalConfigManager class
    -   Create ~/.surrealpilot/config.json handling
    -   Add API key storage and retrieval functionality
    -   Implement provider preference management
    -   _Requirements: 5.3, 5.5_

-   [x] 14. Create Unreal Engine plugin foundation

    -   Set up UE plugin directory structure and .uplugin file
    -   Create basic plugin module with initialization
    -   Implement HTTP client for API communication
    -   Add plugin configuration and settings
    -   _Requirements: 6.1, 6.4_

-   [x] 15. Implement UE plugin context export functionality

    -   Create ContextExporter class for Blueprint JSON export
    -   Implement build error log capture and formatting
    -   Add selection context export capabilities
    -   Write C++ interfaces for context extraction
    -   _Requirements: 6.1, 6.2_

-   [x] 16. Implement UE plugin patch application system

    -   Create PatchApplier class with FScopedTransaction support
    -   Implement JSON patch parsing and validation
    -   Add basic operations like variable renaming and node addition
    -   Improve the laravel app chat UI with history, prompt toolbar, credit balance, undo option, ai provider selection, plan it to make sure it works with ue and help me tell it what to leverage from filament and prism.
    -   Create error handling for patch application failures

    -   _Requirements: 6.3, 6.5_

-   [x] 17. Create comprehensive API error handling

    -   Implement ApiErrorHandler class
    -   Add specific error responses for credit and provider issues
    -   Create error logging and monitoring
    -   Add user-friendly error messages
    -   _Requirements: 1.5, 2.5, 3.5, 7.5_

-   [x] 18. Implement billing webhook processing

    -   Create Stripe webhook controller
    -   Handle subscription status change events
    -   Implement automatic credit allocation on plan changes
    -   Add webhook security validation
    -   _Requirements: 8.1, 8.2, 8.4_

-   [x] 19. Create comprehensive test suite

    -   Write unit tests for all service classes
    -   Create integration tests for API endpoints
    -   Add feature tests for credit system workflows
    -   Implement UE plugin unit tests
    -   _Requirements: All requirements validation_

-   [x] 20. Set up development and deployment configuration


    -   Perform final tests
    -   Set up environment configuration files
    -   Create deployment scripts and documentation, this will run on Laragon locally for development but later on a Forge server.
    -   Verify if the Forge setup makes sense when this will deploy later in Electron with NativePHP. Database should be in the Forge even for NativePHP/Electron downloads.
    -   Configure CI/CD pipeline for testing
    -   Update the README.md with everything done so far in this list.
    -   _Requirements: System deployment and maintenance_
