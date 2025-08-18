#include "ContextExporter.h"
#include "BuildErrorCapture.h"
#include "Engine/Blueprint.h"
#include "Misc/AutomationTest.h"

#if WITH_DEV_AUTOMATION_TESTS

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FContextExporterTest, "SurrealPilot.ContextExporter.BasicFunctionality", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FContextExporterTest::RunTest(const FString& Parameters)
{
    // Test ContextExporter creation
    UContextExporter* ContextExporter = UContextExporter::Get();
    TestNotNull("ContextExporter should be available", ContextExporter);

    if (ContextExporter)
    {
        // Test error context export with sample errors
        TArray<FString> TestErrors;
        TestErrors.Add(TEXT("Error: Blueprint compilation failed"));
        TestErrors.Add(TEXT("Warning: Variable 'TestVar' is not used"));
        TestErrors.Add(TEXT("Error: Node 'TestNode' has invalid connections"));

        FString ErrorJson = ContextExporter->ExportErrorContext(TestErrors);
        TestTrue("Error JSON should not be empty", !ErrorJson.IsEmpty());
        TestTrue("Error JSON should contain error information", ErrorJson.Contains(TEXT("BuildErrors")));
        TestTrue("Error JSON should contain error count", ErrorJson.Contains(TEXT("errorCount")));

        // Test selection context export (even if nothing is selected)
        FString SelectionJson = ContextExporter->ExportSelectionContext();
        TestTrue("Selection JSON should not be empty", !SelectionJson.IsEmpty());
        TestTrue("Selection JSON should contain selection type", SelectionJson.Contains(TEXT("Selection")));
    }

    return true;
}

IMPLEMENT_SIMPLE_AUTOMATION_TEST(FBuildErrorCaptureTest, "SurrealPilot.BuildErrorCapture.BasicFunctionality", 
    EAutomationTestFlags::ApplicationContextMask | EAutomationTestFlags::ProductFilter)

bool FBuildErrorCaptureTest::RunTest(const FString& Parameters)
{
    // Test BuildErrorCapture creation
    UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
    TestNotNull("BuildErrorCapture should be available", BuildErrorCapture);

    if (BuildErrorCapture)
    {
        // Test capture start/stop
        TestFalse("Should not be capturing initially", BuildErrorCapture->IsCapturing());
        
        BuildErrorCapture->StartCapture();
        TestTrue("Should be capturing after start", BuildErrorCapture->IsCapturing());
        
        BuildErrorCapture->StopCapture();
        TestFalse("Should not be capturing after stop", BuildErrorCapture->IsCapturing());

        // Test JSON export
        FString ErrorJson = BuildErrorCapture->ExportBuildErrorsAsJson();
        TestTrue("Error JSON should not be empty", !ErrorJson.IsEmpty());
        TestTrue("Error JSON should be valid JSON format", ErrorJson.Contains(TEXT("{")));
    }

    return true;
}

#endif // WITH_DEV_AUTOMATION_TESTS