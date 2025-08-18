#include "PatchApplier.h"
#include "SurrealPilotErrorHandler.h"
#include "Engine/Blueprint.h"
#include "Editor.h"

/**
 * Simple test functions for PatchApplier functionality
 * These can be called from the UE console or Blueprint for testing
 */
class SURREALPILOT_API FPatchApplierTest
{
public:
    /**
     * Test basic patch validation
     */
    static bool TestPatchValidation()
    {
        UPatchApplier* PatchApplier = UPatchApplier::Get();
        if (!PatchApplier)
        {
            UE_LOG(LogTemp, Error, TEXT("PatchApplier not available for testing"));
            return false;
        }
        
        // Test valid patch JSON
        FString ValidPatch = TEXT(R"({
            "operations": [
                {
                    "type": "variable_rename",
                    "blueprint": "TestBlueprint",
                    "old_name": "OldVariable",
                    "new_name": "NewVariable"
                }
            ]
        })");
        
        // Test invalid patch JSON
        FString InvalidPatch = TEXT("{ invalid json }");
        
        bool ValidResult = PatchApplier->CanApplyPatch(ValidPatch);
        bool InvalidResult = PatchApplier->CanApplyPatch(InvalidPatch);
        
        UE_LOG(LogTemp, Log, TEXT("Patch validation test - Valid: %s, Invalid: %s"), 
               ValidResult ? TEXT("PASS") : TEXT("FAIL"),
               !InvalidResult ? TEXT("PASS") : TEXT("FAIL"));
        
        return !InvalidResult; // Should fail for invalid JSON
    }
    
    /**
     * Test patch JSON parsing
     */
    static bool TestPatchParsing()
    {
        UPatchApplier* PatchApplier = UPatchApplier::Get();
        if (!PatchApplier)
        {
            return false;
        }
        
        // Test single operation
        FString SingleOpPatch = TEXT(R"({
            "type": "variable_rename",
            "blueprint": "TestBlueprint",
            "old_name": "OldVar",
            "new_name": "NewVar"
        })");
        
        // Test multiple operations
        FString MultiOpPatch = TEXT(R"({
            "operations": [
                {
                    "type": "variable_rename",
                    "blueprint": "TestBlueprint",
                    "old_name": "Var1",
                    "new_name": "NewVar1"
                },
                {
                    "type": "node_add",
                    "blueprint": "TestBlueprint",
                    "node_type": "VariableGet",
                    "variable_name": "TestVar"
                }
            ]
        })");
        
        bool SingleResult = PatchApplier->CanApplyPatch(SingleOpPatch);
        bool MultiResult = PatchApplier->CanApplyPatch(MultiOpPatch);
        
        UE_LOG(LogTemp, Log, TEXT("Patch parsing test - Single: %s, Multi: %s"), 
               SingleResult ? TEXT("PARSED") : TEXT("FAILED"),
               MultiResult ? TEXT("PARSED") : TEXT("FAILED"));
        
        return true;
    }
    
    /**
     * Generate sample patch JSON for testing
     */
    static FString GenerateSamplePatch()
    {
        return TEXT(R"({
            "operations": [
                {
                    "type": "variable_rename",
                    "blueprint": "/Game/TestBlueprint",
                    "old_name": "PlayerHealth",
                    "new_name": "CurrentHealth",
                    "description": "Rename variable for clarity"
                },
                {
                    "type": "node_add",
                    "blueprint": "/Game/TestBlueprint",
                    "graph": "EventGraph",
                    "node_type": "VariableGet",
                    "variable_name": "CurrentHealth",
                    "position": {
                        "x": 100,
                        "y": 200
                    },
                    "description": "Add getter for renamed variable"
                }
            ],
            "metadata": {
                "generated_by": "SurrealPilot AI",
                "timestamp": "2024-01-01T00:00:00Z",
                "description": "Rename PlayerHealth variable and add getter node"
            }
        })");
    }
    
    /**
     * Test error handling
     */
    static void TestErrorHandling()
    {
        // Test various error scenarios
        FSurrealPilotErrorHandler::HandleHttpError(401, TEXT("Unauthorized"));
        FSurrealPilotErrorHandler::HandleHttpError(402, TEXT("Insufficient credits"));
        FSurrealPilotErrorHandler::HandlePatchError(TEXT("{}"), TEXT("Invalid patch format"));
        FSurrealPilotErrorHandler::HandleContextExportError(TEXT("Blueprint"), TEXT("No blueprint selected"));
        FSurrealPilotErrorHandler::HandleInsufficientCreditsError(50, 100);
        
        TArray<FString> FallbackProviders = {TEXT("OpenAI"), TEXT("Anthropic")};
        FSurrealPilotErrorHandler::HandleProviderUnavailableError(TEXT("Gemini"), FallbackProviders);
        
        UE_LOG(LogTemp, Log, TEXT("Error handling test completed - check notifications"));
    }
};

// Console command to run tests
static FAutoConsoleCommand TestPatchApplierCommand(
    TEXT("SurrealPilot.TestPatchApplier"),
    TEXT("Run PatchApplier tests"),
    FConsoleCommandDelegate::CreateLambda([]()
    {
        UE_LOG(LogTemp, Log, TEXT("Running SurrealPilot PatchApplier tests..."));
        
        FPatchApplierTest::TestPatchValidation();
        FPatchApplierTest::TestPatchParsing();
        FPatchApplierTest::TestErrorHandling();
        
        // Generate and log sample patch
        FString SamplePatch = FPatchApplierTest::GenerateSamplePatch();
        UE_LOG(LogTemp, Log, TEXT("Sample patch JSON:\n%s"), *SamplePatch);
        
        UE_LOG(LogTemp, Log, TEXT("SurrealPilot PatchApplier tests completed"));
    })
);