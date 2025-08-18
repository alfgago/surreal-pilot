#include "HttpClient.h"
#include "SurrealPilotErrorHandler.h"
#include "SurrealPilotSettings.h"
#include "Misc/AutomationTest.h"
#include "Engine/Engine.h"

#if WITH_DEV_AUTOMATION_TESTS

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FHttpClientTest, "SurrealPilot.HttpClient.BasicFunctionality", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FHttpClientTest::RunTest(const FString& Parameters)
{
    // Test HttpClient creation
    USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
    TestNotNull("HttpClient should be available", HttpClient);

    if (HttpClient)
    {
        // Test URL construction
        FString BaseUrl = HttpClient->GetBaseUrl();
        TestTrue("Base URL should not be empty", !BaseUrl.IsEmpty());
        TestTrue("Base URL should be valid format", BaseUrl.StartsWith(TEXT("http")));

        // Test endpoint construction
        FString ChatEndpoint = HttpClient->BuildEndpointUrl(TEXT("chat"));
        TestTrue("Chat endpoint should contain base URL", ChatEndpoint.Contains(BaseUrl));
        TestTrue("Chat endpoint should contain 'chat'", ChatEndpoint.Contains(TEXT("chat")));

        FString AssistEndpoint = HttpClient->BuildEndpointUrl(TEXT("assist"));
        TestTrue("Assist endpoint should contain 'assist'", AssistEndpoint.Contains(TEXT("assist")));

        // Test header construction
        TMap<FString, FString> Headers = HttpClient->BuildRequestHeaders();
        TestTrue("Headers should contain Content-Type", Headers.Contains(TEXT("Content-Type")));
        TestTrue("Headers should contain Accept", Headers.Contains(TEXT("Accept")));
        TestEqual("Content-Type should be JSON", Headers[TEXT("Content-Type")], TEXT("application/json"));
    }

    return true;
}

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FHttpClientRequestTest, "SurrealPilot.HttpClient.RequestConstruction", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FHttpClientRequestTest::RunTest(const FString& Parameters)
{
    USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
    TestNotNull("HttpClient should be available", HttpClient);

    if (HttpClient)
    {
        // Test chat request construction
        TArray<FString> Messages;
        Messages.Add(TEXT("Hello, I need help with my Blueprint"));
        Messages.Add(TEXT("Can you help me fix this error?"));

        FString ContextJson = TEXT(R"({
            "blueprint": "TestBlueprint",
            "errors": ["Error 1", "Error 2"],
            "selection": "VariableNode"
        })");

        FString RequestJson = HttpClient->BuildChatRequest(Messages, TEXT("openai"), ContextJson);
        
        TestTrue("Request JSON should not be empty", !RequestJson.IsEmpty());
        TestTrue("Request should contain messages", RequestJson.Contains(TEXT("messages")));
        TestTrue("Request should contain provider", RequestJson.Contains(TEXT("openai")));
        TestTrue("Request should contain context", RequestJson.Contains(TEXT("context")));
        TestTrue("Request should be valid JSON", RequestJson.StartsWith(TEXT("{")));

        // Test assist request construction
        FString AssistRequestJson = HttpClient->BuildAssistRequest(TEXT("anthropic"));
        TestTrue("Assist request should not be empty", !AssistRequestJson.IsEmpty());
        TestTrue("Assist request should contain provider", AssistRequestJson.Contains(TEXT("anthropic")));
    }

    return true;
}

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FHttpClientErrorHandlingTest, "SurrealPilot.HttpClient.ErrorHandling", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FHttpClientErrorHandlingTest::RunTest(const FString& Parameters)
{
    USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
    TestNotNull("HttpClient should be available", HttpClient);

    if (HttpClient)
    {
        // Test error response parsing
        FString ErrorResponse401 = TEXT(R"({
            "error": "authentication_required",
            "message": "Invalid or missing API token",
            "error_code": "AUTHENTICATION_REQUIRED"
        })");

        FString ErrorResponse402 = TEXT(R"({
            "error": "insufficient_credits",
            "message": "Company has insufficient credits",
            "credits_available": 50,
            "estimated_tokens_needed": 100
        })");

        FString ErrorResponse503 = TEXT(R"({
            "error": "provider_unavailable",
            "message": "OpenAI is temporarily unavailable",
            "available_providers": ["anthropic", "gemini"]
        })");

        // Test that error responses can be parsed without crashing
        bool CanParse401 = HttpClient->IsValidJsonResponse(ErrorResponse401);
        bool CanParse402 = HttpClient->IsValidJsonResponse(ErrorResponse402);
        bool CanParse503 = HttpClient->IsValidJsonResponse(ErrorResponse503);

        TestTrue("Should parse 401 error response", CanParse401);
        TestTrue("Should parse 402 error response", CanParse402);
        TestTrue("Should parse 503 error response", CanParse503);

        // Test invalid JSON handling
        FString InvalidJson = TEXT("{ invalid json }");
        bool CanParseInvalid = HttpClient->IsValidJsonResponse(InvalidJson);
        TestFalse("Should not parse invalid JSON", CanParseInvalid);
    }

    return true;
}

#endif // WITH_DEV_AUTOMATION_TESTS

/**
 * Console commands for manual testing
 */
class SURREALPILOT_API FHttpClientTestCommands
{
public:
    /**
     * Test connection to local server
     */
    static void TestLocalConnection()
    {
        USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
        if (!HttpClient)
        {
            UE_LOG(LogTemp, Error, TEXT("HttpClient not available"));
            return;
        }

        UE_LOG(LogTemp, Log, TEXT("Testing connection to local server..."));
        
        // Test providers endpoint
        HttpClient->GetProviders(FOnHttpResponse::CreateLambda([](bool bSuccess, const FString& Response, int32 StatusCode)
        {
            if (bSuccess)
            {
                UE_LOG(LogTemp, Log, TEXT("Providers endpoint test - SUCCESS: %s"), *Response);
            }
            else
            {
                UE_LOG(LogTemp, Warning, TEXT("Providers endpoint test - FAILED: Status %d, Response: %s"), StatusCode, *Response);
            }
        }));
    }

    /**
     * Test chat request with sample data
     */
    static void TestChatRequest()
    {
        USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
        if (!HttpClient)
        {
            UE_LOG(LogTemp, Error, TEXT("HttpClient not available"));
            return;
        }

        UE_LOG(LogTemp, Log, TEXT("Testing chat request..."));

        TArray<FString> Messages;
        Messages.Add(TEXT("Hello, I'm working on a Blueprint and need help"));
        Messages.Add(TEXT("Can you help me create a simple health system?"));

        FString Context = TEXT(R"({
            "blueprint": "/Game/Characters/PlayerCharacter",
            "selection": "HealthVariable",
            "errors": []
        })");

        HttpClient->SendChatRequest(
            Messages,
            TEXT("openai"),
            Context,
            false, // Non-streaming for test
            FOnHttpResponse::CreateLambda([](bool bSuccess, const FString& Response, int32 StatusCode)
            {
                if (bSuccess)
                {
                    UE_LOG(LogTemp, Log, TEXT("Chat request test - SUCCESS: %s"), *Response);
                }
                else
                {
                    UE_LOG(LogTemp, Warning, TEXT("Chat request test - FAILED: Status %d, Response: %s"), StatusCode, *Response);
                    
                    // Handle specific error cases
                    if (StatusCode == 401)
                    {
                        FSurrealPilotErrorHandler::HandleHttpError(401, Response);
                    }
                    else if (StatusCode == 402)
                    {
                        FSurrealPilotErrorHandler::HandleHttpError(402, Response);
                    }
                    else if (StatusCode == 503)
                    {
                        FSurrealPilotErrorHandler::HandleHttpError(503, Response);
                    }
                }
            })
        );
    }

    /**
     * Test streaming chat request
     */
    static void TestStreamingRequest()
    {
        USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
        if (!HttpClient)
        {
            UE_LOG(LogTemp, Error, TEXT("HttpClient not available"));
            return;
        }

        UE_LOG(LogTemp, Log, TEXT("Testing streaming chat request..."));

        TArray<FString> Messages;
        Messages.Add(TEXT("Explain how to create a Blueprint function that calculates damage"));

        HttpClient->SendChatRequest(
            Messages,
            TEXT("openai"),
            TEXT("{}"),
            true, // Streaming enabled
            FOnHttpResponse::CreateLambda([](bool bSuccess, const FString& Response, int32 StatusCode)
            {
                if (bSuccess)
                {
                    UE_LOG(LogTemp, Log, TEXT("Streaming chunk received: %s"), *Response);
                }
                else
                {
                    UE_LOG(LogTemp, Warning, TEXT("Streaming request failed: Status %d, Response: %s"), StatusCode, *Response);
                }
            })
        );
    }

    /**
     * Test assist endpoint
     */
    static void TestAssistRequest()
    {
        USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
        if (!HttpClient)
        {
            UE_LOG(LogTemp, Error, TEXT("HttpClient not available"));
            return;
        }

        UE_LOG(LogTemp, Log, TEXT("Testing assist request..."));

        HttpClient->SendAssistRequest(
            TEXT("anthropic"),
            FOnHttpResponse::CreateLambda([](bool bSuccess, const FString& Response, int32 StatusCode)
            {
                if (bSuccess)
                {
                    UE_LOG(LogTemp, Log, TEXT("Assist request test - SUCCESS: %s"), *Response);
                }
                else
                {
                    UE_LOG(LogTemp, Warning, TEXT("Assist request test - FAILED: Status %d, Response: %s"), StatusCode, *Response);
                }
            })
        );
    }
};

// Console commands
static FAutoConsoleCommand TestLocalConnectionCommand(
    TEXT("SurrealPilot.TestConnection"),
    TEXT("Test connection to local SurrealPilot server"),
    FConsoleCommandDelegate::CreateStatic(&FHttpClientTestCommands::TestLocalConnection)
);

static FAutoConsoleCommand TestChatRequestCommand(
    TEXT("SurrealPilot.TestChat"),
    TEXT("Test chat request to SurrealPilot API"),
    FConsoleCommandDelegate::CreateStatic(&FHttpClientTestCommands::TestChatRequest)
);

static FAutoConsoleCommand TestStreamingRequestCommand(
    TEXT("SurrealPilot.TestStreaming"),
    TEXT("Test streaming chat request"),
    FConsoleCommandDelegate::CreateStatic(&FHttpClientTestCommands::TestStreamingRequest)
);

static FAutoConsoleCommand TestAssistRequestCommand(
    TEXT("SurrealPilot.TestAssist"),
    TEXT("Test assist request"),
    FConsoleCommandDelegate::CreateStatic(&FHttpClientTestCommands::TestAssistRequest)
);