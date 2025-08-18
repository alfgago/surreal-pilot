# Prism-PHP Configuration Guide

## Overview

This document explains how to configure and use the Prism-PHP integration in SurrealPilot. The system supports multiple AI providers with automatic fallback logic.

## Supported Providers

- **OpenAI** - GPT-4, GPT-3.5-turbo, and other OpenAI models
- **Anthropic** - Claude 3 models (Opus, Sonnet, Haiku)
- **Google Gemini** - Gemini 1.5 Pro and Flash models
- **Ollama** - Local AI models (requires local Ollama installation)

## Environment Configuration

Copy the following variables to your `.env` file and configure with your API keys:

```env
# Prism-PHP AI Provider Configuration
PRISM_DEFAULT_PROVIDER=openai
PRISM_TOKEN_COUNTING=true
PRISM_STREAMING=true
PRISM_LOGGING=true

# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_DEFAULT_MODEL=gpt-4

# Anthropic Configuration
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key-here
ANTHROPIC_DEFAULT_MODEL=claude-3-sonnet-20240229

# Google Gemini Configuration
GEMINI_API_KEY=your-gemini-key-here
GEMINI_DEFAULT_MODEL=gemini-1.5-pro

# Ollama Configuration (Local)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_DEFAULT_MODEL=llama3.1
```

## Provider Fallback Logic

The system automatically falls back to available providers in this order:

1. **Requested Provider** - The provider specified in the API request
2. **Fallback Chain** - Configured in `config/prism.php`:
   - OpenAI
   - Anthropic
   - Gemini
   - Ollama

If all providers are unavailable, the system returns an error.

## Testing Configuration

Use the built-in command to test your provider configuration:

```bash
# Test all providers
php artisan prism:test-providers

# Test a specific provider
php artisan prism:test-providers --provider=openai
```

## Usage in Code

### Using the Provider Manager

```php
use App\Services\PrismProviderManager;

$manager = app(PrismProviderManager::class);

// Get available providers
$available = $manager->getAvailableProviders();

// Resolve best provider
$provider = $manager->resolveProvider('openai');

// Create Prism instance
$prism = $manager->createPrismInstance('anthropic');
```

### Using the Facade

```php
use App\Facades\PrismManager;

// Check if provider is available
if (PrismManager::isProviderAvailable('openai')) {
    $prism = PrismManager::createPrismInstance('openai');
}

// Get provider statistics
$stats = PrismManager::getProviderStats();
```

### Using the Helper Service

```php
use App\Services\PrismHelper;

$helper = app(PrismHelper::class);

// Stream chat completion
foreach ($helper->streamChat($messages, 'openai') as $chunk) {
    echo $chunk->content;
}

// Simple chat completion
$response = $helper->chat($messages, 'anthropic');

// Estimate tokens
$tokenCount = $helper->estimateTokens($messages);
```

## Middleware Integration

The `ResolveAiDriver` middleware automatically resolves the best available provider for API requests:

```php
// In your routes
Route::middleware(['auth:sanctum', 'resolve-ai-driver'])
    ->post('/api/chat', [ChatController::class, 'stream']);
```

The middleware adds these to the request:
- `resolved_provider` - The actual provider that will be used
- `original_provider` - The provider originally requested

## Configuration Options

### Token Counting
- `PRISM_TOKEN_COUNTING=true` - Enable token counting for credit deduction
- `PRISM_TOKEN_COUNT_METHOD=tiktoken` - Method for counting tokens

### Streaming
- `PRISM_STREAMING=true` - Enable streaming responses
- `PRISM_STREAM_TIMEOUT=120` - Streaming timeout in seconds

### Rate Limiting
- `PRISM_RATE_LIMITING=true` - Enable rate limiting
- `OPENAI_RPM=60` - Requests per minute for OpenAI
- `OPENAI_TPM=90000` - Tokens per minute for OpenAI

### Logging
- `PRISM_LOGGING=true` - Enable request/response logging
- `PRISM_LOG_LEVEL=info` - Log level for Prism operations

## Ollama Setup

For local Ollama usage:

1. Install Ollama: https://ollama.ai/
2. Pull models: `ollama pull llama3.1`
3. Start Ollama service
4. Configure `OLLAMA_BASE_URL=http://localhost:11434`

## Troubleshooting

### Provider Not Available
- Check API keys are correctly set
- Verify network connectivity
- Check provider service status
- Review logs for specific error messages

### Fallback Not Working
- Ensure fallback providers are configured with valid API keys
- Check the fallback chain in `config/prism.php`
- Verify provider availability with test command

### Streaming Issues
- Check `PRISM_STREAMING=true` is set
- Verify timeout settings
- Ensure proper middleware configuration