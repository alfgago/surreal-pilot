# Requirements Document

## Introduction

SurrealPilot is an AI copilot system for Unreal Engine developers that provides intelligent assistance through both web SaaS and desktop applications. The system integrates with Unreal Engine through a plugin, processes developer context through Laravel APIs, leverages multiple LLM providers via Prism-PHP, and applies intelligent fixes back to the Unreal Engine environment. The bootstrap phase establishes the foundational infrastructure including multi-tenancy, billing, AI provider integration, and the core API endpoints.

## Requirements

### Requirement 1: Multi-Provider AI Integration

**User Story:** As a developer, I want to choose from multiple AI providers (OpenAI, Anthropic, Gemini, Ollama) so that I can use my preferred AI service or switch based on availability and cost.

#### Acceptance Criteria

1. WHEN the system is configured THEN it SHALL support OpenAI, Anthropic, Gemini, and Ollama providers through Prism-PHP
2. WHEN a user makes an API request THEN the system SHALL allow provider selection via request parameter
3. WHEN Prism-PHP is installed THEN the system SHALL have a complete configuration file mapping environment variables to provider settings
4. IF Ollama is selected AND the local service is unavailable THEN the system SHALL fallback to cloud providers
5. WHEN provider credentials are invalid THEN the system SHALL return appropriate error messages without exposing sensitive information

### Requirement 2: Company-Based Credit System

**User Story:** As a company administrator, I want to manage AI usage credits for my team so that I can control costs and track usage across different subscription plans.

#### Acceptance Criteria

1. WHEN a company is created THEN it SHALL have credits and plan fields in the database
2. WHEN an AI request is processed THEN the system SHALL decrement credits based on token usage
3. WHEN credits reach zero THEN the system SHALL prevent further AI requests until credits are replenished
4. WHEN subscription plans are seeded THEN the system SHALL create Starter, Pro, and Enterprise plans with different credit allocations
5. IF a company has insufficient credits THEN the system SHALL return a clear error message indicating credit shortage

### Requirement 3: Streaming Chat API

**User Story:** As an Unreal Engine developer, I want to send context and receive streaming AI responses so that I can get real-time assistance with my development tasks.

#### Acceptance Criteria

1. WHEN a POST request is made to /api/chat THEN the system SHALL authenticate using Sanctum tokens
2. WHEN valid input is provided THEN the system SHALL accept provider, messages array, and optional context parameters
3. WHEN processing a chat request THEN the system SHALL stream responses using Prism-PHP streaming capabilities
4. WHEN streaming responses THEN the system SHALL count tokens and decrement company credits in real-time
5. IF authentication fails THEN the system SHALL return 401 unauthorized status

### Requirement 4: Filament Dashboard Integration

**User Story:** As a company administrator, I want to view credit usage and purchase additional credits through the Filament dashboard so that I can manage my team's AI usage efficiently.

#### Acceptance Criteria

1. WHEN accessing the dashboard THEN the system SHALL display current credit balance in a widget
2. WHEN viewing usage analytics THEN the system SHALL show recent API usage logs with timestamps and token counts
3. WHEN credits are low THEN the system SHALL provide a prominent top-up purchase option
4. WHEN purchasing credits THEN the system SHALL integrate with Cashier Billing Provider for payment processing
5. WHEN payment is successful THEN the system SHALL immediately update the company's credit balance

### Requirement 5: NativePHP Desktop Application

**User Story:** As a developer, I want to run SurrealPilot as a desktop application so that I can use it offline and have better integration with my local development environment.

#### Acceptance Criteria

1. WHEN NativePHP is installed THEN the system SHALL create an Electron-based desktop application
2. WHEN the desktop app starts THEN it SHALL expose the same API routes on localhost:8000
3. WHEN using the desktop app THEN users SHALL be able to store API keys locally in ~/.surrealpilot/config.json
4. WHEN the desktop UI is accessed THEN it SHALL provide a chat interface with streaming responses
5. WHEN offline mode is available THEN the system SHALL use local Ollama instance if configured

### Requirement 6: Unreal Engine Plugin Foundation

**User Story:** As an Unreal Engine developer, I want a plugin that can export context and apply AI-generated fixes so that I can seamlessly integrate AI assistance into my UE workflow.

#### Acceptance Criteria

1. WHEN the UE plugin is installed THEN it SHALL provide Blueprint JSON export functionality
2. WHEN build errors occur THEN the plugin SHALL capture and format error logs for AI processing
3. WHEN AI responses are received THEN the plugin SHALL provide ApplyPatch helpers using FScopedTransaction
4. WHEN making API calls THEN the plugin SHALL POST to localhost:8000/api/assist with fallback to SaaS URL
5. WHEN applying changes THEN the plugin SHALL support basic operations like variable renaming and node addition

### Requirement 7: Role-Based Access Control

**User Story:** As a company owner, I want to control which team members can spend AI credits so that I can manage costs and permissions appropriately.

#### Acceptance Criteria

1. WHEN Filament Companies is configured THEN the system SHALL use existing role and permission structures
2. WHEN a user attempts to use AI features THEN the system SHALL verify they have 'developer' role or equivalent permissions
3. WHEN roles are assigned THEN only authorized users SHALL be able to make credit-consuming API calls
4. WHEN permissions are revoked THEN users SHALL immediately lose access to AI features
5. IF unauthorized access is attempted THEN the system SHALL log the attempt and return appropriate error responses

### Requirement 8: Billing Integration

**User Story:** As a company administrator, I want automated billing and subscription management so that credits are automatically replenished and usage is properly tracked.

#### Acceptance Criteria

1. WHEN Cashier Billing Provider is installed THEN the system SHALL handle Stripe webhook events
2. WHEN subscription status changes THEN the system SHALL update company plan and credit allocations accordingly
3. WHEN payments are processed THEN the system SHALL automatically add purchased credits to company balance
4. WHEN subscriptions expire THEN the system SHALL prevent new AI requests until renewal
5. WHEN billing events occur THEN the system SHALL maintain audit logs for financial tracking