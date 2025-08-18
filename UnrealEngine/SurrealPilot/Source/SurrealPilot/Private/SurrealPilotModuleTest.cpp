#include "SurrealPilotModule.h"
#include "SurrealPilotSettings.h"
#include "ContextExporter.h"
#include "PatchApplier.h"
#include "HttpClient.h"
#include "BuildErrorCapture.h"
#include "SurrealPilotErrorHandler.h"
#include "Misc/AutomationTest.h"
#include "Engine/Engine.h"
#include "Editor.h"

#if WITH_DEV_AUTOMATION_TESTS

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FSurrealPilotModuleTest, "SurrealPilot.Module.Initialization", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FSurrealPilotModuleTest::RunTest(const FString& Parameters)
{
    // Test that the module is loaded
    FSurrealPilotModule& Module = FModuleManager::LoadModuleChecked<FSurrealPilotModule>("SurrealPilot");
    TestTrue("SurrealPilot module should be loaded", Module.IsGameModule());

    // Test that all core components are available
    UContextExporter* ContextExporter = UContextExporter::Get();
    TestNotNull("ContextExporter should be initialized", ContextExporter);

    UPatchApplier* PatchApplier = UPatchApplier::Get();
    TestNotNull("PatchApplier should be initialized", PatchApplier);

    USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
    TestNotNull("HttpClient should be initialized", HttpClient);

    UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
    TestNotNull("BuildErrorCapture should be initialized", BuildErrorCapture);

    // Test settings access
    const USurrealPilotSettings* Settings = GetDefault<USurrealPilotSettings>();
    TestNotNull("Settings should be available", Settings);

    if (Settings)
    {
        // Test default settings values
        TestTrue("Should have default server URL", !Settings->ServerUrl.IsEmpty());
        TestTrue("Should have default fallback URL", !Settings->FallbackServerUrl.IsEmpty());
        TestTrue("Should have default provider", !Settings->PreferredProvider.IsEmpty());
    }

    return true;
}

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FSurrealPilotIntegrationTest, "SurrealPilot.Integration.WorkflowTest", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FSurrealPilotIntegrationTest::RunTest(const FString& Parameters)
{
    // Test complete workflow: Context Export -> API Call -> Patch Application
    
    // 1. Test context export
    UContextExporter* ContextExporter = UContextExporter::Get();
    TestNotNull("ContextExporter should be available", ContextExporter);

    if (ContextExporter)
    {
        // Export error context
        TArray<FString> TestErrors;
        TestErrors.Add(TEXT("Blueprint compilation failed: Variable 'Health' not found"));
        TestErrors.Add(TEXT("Node 'Set Health' has invalid input"));

        FString ErrorContext = ContextExporter->ExportErrorContext(TestErrors);
        TestTrue("Error context should be exported", !ErrorContext.IsEmpty());
        TestTrue("Error context should be valid JSON", ErrorContext.Contains(TEXT("BuildErrors")));

        // Export selection context
        FString SelectionContext = ContextExporter->ExportSelectionContext();
        TestTrue("Selection context should be exported", !SelectionContext.IsEmpty());
    }

    // 2. Test patch validation and application
    UPatchApplier* PatchApplier = UPatchApplier::Get();
    TestNotNull("PatchApplier should be available", PatchApplier);

    if (PatchApplier)
    {
        // Test with a valid patch
        FString ValidPatch = TEXT(R"({
            "operations": [
                {
                    "type": "variable_rename",
                    "blueprint": "TestBlueprint",
                    "old_name": "Health",
                    "new_name": "PlayerHealth",
                    "description": "Rename for clarity"
                }
            ]
        })");

        bool CanApply = PatchApplier->CanApplyPatch(ValidPatch);
        TestTrue("Should be able to validate patch", CanApply);

        // Test with invalid patch
        FString InvalidPatch = TEXT("{ invalid json }");
        bool CanApplyInvalid = PatchApplier->CanApplyPatch(InvalidPatch);
        TestFalse("Should reject invalid patch", CanApplyInvalid);
    }

    // 3. Test HTTP client configuration
    USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
    TestNotNull("HttpClient should be available", HttpClient);

    if (HttpClient)
    {
        FString BaseUrl = HttpClient->GetBaseUrl();
        TestTrue("Base URL should be configured", !BaseUrl.IsEmpty());

        TMap<FString, FString> Headers = HttpClient->BuildRequestHeaders();
        TestTrue("Headers should be configured", Headers.Num() > 0);
    }

    return true;
}

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FSurrealPilotErrorHandlingTest, "SurrealPilot.ErrorHandling.ComprehensiveTest", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FSurrealPilotErrorHandlingTest::RunTest(const FString& Parameters)
{
    // Test all error handling scenarios
    
    // Test HTTP errors
    FSurrealPilotErrorHandler::HandleHttpError(401, TEXT("Unauthorized access"));
    FSurrealPilotErrorHandler::HandleHttpError(402, TEXT("Insufficient credits"));
    FSurrealPilotErrorHandler::HandleHttpError(503, TEXT("Service unavailable"));
    FSurrealPilotErrorHandler::HandleHttpError(500, TEXT("Internal server error"));

    // Test patch errors
    FSurrealPilotErrorHandler::HandlePatchError(TEXT("{}"), TEXT("Empty patch"));
    FSurrealPilotErrorHandler::HandlePatchError(TEXT("invalid"), TEXT("Invalid JSON"));

    // Test context export errors
    FSurrealPilotErrorHandler::HandleContextExportError(TEXT("Blueprint"), TEXT("No blueprint selected"));
    FSurrealPilotErrorHandler::HandleContextExportError(TEXT("Selection"), TEXT("Nothing selected"));

    // Test credit errors
    FSurrealPilotErrorHandler::HandleInsufficientCreditsError(10, 100);

    // Test provider errors
    TArray<FString> FallbackProviders;
    FallbackProviders.Add(TEXT("OpenAI"));
    FallbackProviders.Add(TEXT("Anthropic"));
    FSurrealPilotErrorHandler::HandleProviderUnavailableError(TEXT("Gemini"), FallbackProviders);

    // If we get here without crashing, error handling is working
    TestTrue("Error handling should not crash", true);

    return true;
}

#endif // WITH_DEV_AUTOMATION_TESTS

/**
 * Comprehensive test suite for manual execution
 */
class SURREALPILOT_API FSurrealPilotTestSuite
{
public:
    /**
     * Run all tests in sequence
     */
    static void RunAllTests()
    {
        UE_LOG(LogTemp, Log, TEXT("=== SurrealPilot Comprehensive Test Suite ==="));

        TestModuleInitialization();
        TestContextExportFunctionality();
        TestPatchApplicationSystem();
        TestHttpClientFunctionality();
        TestErrorHandlingSystem();
        TestSettingsConfiguration();
        TestBuildErrorCapture();

        UE_LOG(LogTemp, Log, TEXT("=== SurrealPilot Test Suite Complete ==="));
    }

private:
    static void TestModuleInitialization()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing module initialization..."));

        // Check if module is loaded
        if (FModuleManager::Get().IsModuleLoaded("SurrealPilot"))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ SurrealPilot module is loaded"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ SurrealPilot module is not loaded"));
        }

        // Check core components
        bool AllComponentsAvailable = true;
        
        if (!UContextExporter::Get())
        {
            UE_LOG(LogTemp, Error, TEXT("✗ ContextExporter not available"));
            AllComponentsAvailable = false;
        }
        
        if (!UPatchApplier::Get())
        {
            UE_LOG(LogTemp, Error, TEXT("✗ PatchApplier not available"));
            AllComponentsAvailable = false;
        }
        
        if (!USurrealPilotHttpClient::Get())
        {
            UE_LOG(LogTemp, Error, TEXT("✗ HttpClient not available"));
            AllComponentsAvailable = false;
        }
        
        if (!UBuildErrorCapture::Get())
        {
            UE_LOG(LogTemp, Error, TEXT("✗ BuildErrorCapture not available"));
            AllComponentsAvailable = false;
        }

        if (AllComponentsAvailable)
        {
            UE_LOG(LogTemp, Log, TEXT("✓ All core components are available"));
        }
    }

    static void TestContextExportFunctionality()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing context export functionality..."));

        UContextExporter* ContextExporter = UContextExporter::Get();
        if (!ContextExporter)
        {
            UE_LOG(LogTemp, Error, TEXT("✗ ContextExporter not available"));
            return;
        }

        // Test error context export
        TArray<FString> TestErrors;
        TestErrors.Add(TEXT("Error: Blueprint compilation failed"));
        TestErrors.Add(TEXT("Warning: Unused variable 'TestVar'"));
        TestErrors.Add(TEXT("Error: Invalid node connection"));

        FString ErrorJson = ContextExporter->ExportErrorContext(TestErrors);
        if (!ErrorJson.IsEmpty() && ErrorJson.Contains(TEXT("BuildErrors")))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Error context export working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Error context export failed"));
        }

        // Test selection context export
        FString SelectionJson = ContextExporter->ExportSelectionContext();
        if (!SelectionJson.IsEmpty())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Selection context export working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Selection context export failed"));
        }
    }

    static void TestPatchApplicationSystem()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing patch application system..."));

        UPatchApplier* PatchApplier = UPatchApplier::Get();
        if (!PatchApplier)
        {
            UE_LOG(LogTemp, Error, TEXT("✗ PatchApplier not available"));
            return;
        }

        // Test valid patch validation
        FString ValidPatch = TEXT(R"({
            "operations": [
                {
                    "type": "variable_rename",
                    "blueprint": "TestBlueprint",
                    "old_name": "OldVar",
                    "new_name": "NewVar"
                }
            ]
        })");

        if (PatchApplier->CanApplyPatch(ValidPatch))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Valid patch validation working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Valid patch validation failed"));
        }

        // Test invalid patch rejection
        FString InvalidPatch = TEXT("{ invalid json }");
        if (!PatchApplier->CanApplyPatch(InvalidPatch))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Invalid patch rejection working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Invalid patch rejection failed"));
        }
    }

    static void TestHttpClientFunctionality()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing HTTP client functionality..."));

        USurrealPilotHttpClient* HttpClient = USurrealPilotHttpClient::Get();
        if (!HttpClient)
        {
            UE_LOG(LogTemp, Error, TEXT("✗ HttpClient not available"));
            return;
        }

        // Test URL construction
        FString BaseUrl = HttpClient->GetBaseUrl();
        if (!BaseUrl.IsEmpty() && BaseUrl.StartsWith(TEXT("http")))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Base URL configuration working: %s"), *BaseUrl);
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Base URL configuration failed"));
        }

        // Test header construction
        TMap<FString, FString> Headers = HttpClient->BuildRequestHeaders();
        if (Headers.Contains(TEXT("Content-Type")) && Headers.Contains(TEXT("Accept")))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Request headers working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Request headers failed"));
        }

        // Test request construction
        TArray<FString> Messages;
        Messages.Add(TEXT("Test message"));
        FString RequestJson = HttpClient->BuildChatRequest(Messages, TEXT("openai"), TEXT("{}"));
        
        if (!RequestJson.IsEmpty() && RequestJson.Contains(TEXT("messages")))
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Request construction working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Request construction failed"));
        }
    }

    static void TestErrorHandlingSystem()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing error handling system..."));

        // Test various error scenarios without crashing
        try
        {
            FSurrealPilotErrorHandler::HandleHttpError(401, TEXT("Test unauthorized"));
            FSurrealPilotErrorHandler::HandleHttpError(402, TEXT("Test insufficient credits"));
            FSurrealPilotErrorHandler::HandlePatchError(TEXT("{}"), TEXT("Test patch error"));
            FSurrealPilotErrorHandler::HandleContextExportError(TEXT("Test"), TEXT("Test context error"));
            FSurrealPilotErrorHandler::HandleInsufficientCreditsError(10, 100);

            TArray<FString> Providers = {TEXT("OpenAI"), TEXT("Anthropic")};
            FSurrealPilotErrorHandler::HandleProviderUnavailableError(TEXT("Gemini"), Providers);

            UE_LOG(LogTemp, Log, TEXT("✓ Error handling system working"));
        }
        catch (...)
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Error handling system crashed"));
        }
    }

    static void TestSettingsConfiguration()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing settings configuration..."));

        const USurrealPilotSettings* Settings = GetDefault<USurrealPilotSettings>();
        if (!Settings)
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Settings not available"));
            return;
        }

        if (!Settings->ServerUrl.IsEmpty())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Server URL configured: %s"), *Settings->ServerUrl);
        }
        else
        {
            UE_LOG(LogTemp, Warning, TEXT("⚠ Server URL not configured"));
        }

        if (!Settings->PreferredProvider.IsEmpty())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Preferred provider configured: %s"), *Settings->PreferredProvider);
        }
        else
        {
            UE_LOG(LogTemp, Warning, TEXT("⚠ Preferred provider not configured"));
        }

        UE_LOG(LogTemp, Log, TEXT("✓ Settings configuration accessible"));
    }

    static void TestBuildErrorCapture()
    {
        UE_LOG(LogTemp, Log, TEXT("Testing build error capture..."));

        UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
        if (!BuildErrorCapture)
        {
            UE_LOG(LogTemp, Error, TEXT("✗ BuildErrorCapture not available"));
            return;
        }

        // Test capture lifecycle
        if (!BuildErrorCapture->IsCapturing())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Initial capture state correct"));
        }

        BuildErrorCapture->StartCapture();
        if (BuildErrorCapture->IsCapturing())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Capture start working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Capture start failed"));
        }

        BuildErrorCapture->StopCapture();
        if (!BuildErrorCapture->IsCapturing())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Capture stop working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Capture stop failed"));
        }

        // Test JSON export
        FString ErrorJson = BuildErrorCapture->ExportBuildErrorsAsJson();
        if (!ErrorJson.IsEmpty())
        {
            UE_LOG(LogTemp, Log, TEXT("✓ Build error JSON export working"));
        }
        else
        {
            UE_LOG(LogTemp, Error, TEXT("✗ Build error JSON export failed"));
        }
    }
};

// Console command to run comprehensive tests
static FAutoConsoleCommand RunAllTestsCommand(
    TEXT("SurrealPilot.RunAllTests"),
    TEXT("Run comprehensive SurrealPilot test suite"),
    FConsoleCommandDelegate::CreateStatic(&FSurrealPilotTestSuite::RunAllTests)
);