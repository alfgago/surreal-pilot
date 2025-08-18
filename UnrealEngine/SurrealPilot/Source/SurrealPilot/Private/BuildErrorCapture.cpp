#include "BuildErrorCapture.h"
#include "ContextExporter.h"
#include "Editor.h"
#include "Logging/LogMacros.h"
#include "KismetCompiler.h"
#include "BlueprintGraph/Classes/K2Node.h"
#include "Engine/Blueprint.h"
#include "Misc/DateTime.h"

DEFINE_LOG_CATEGORY(LogSurrealPilotBuild);

FSurrealPilotOutputDevice::FSurrealPilotOutputDevice()
    : bIsCapturing(false)
{
}

FSurrealPilotOutputDevice::~FSurrealPilotOutputDevice()
{
    if (bIsCapturing)
    {
        StopCapture();
    }
}

void FSurrealPilotOutputDevice::Serialize(const TCHAR* V, ELogVerbosity::Type Verbosity, const class FName& Category)
{
    if (!bIsCapturing)
    {
        return;
    }

    FString Message(V);
    
    // Check if this is a build-related message
    if (!IsBuildMessage(Category, Message))
    {
        return;
    }

    // Categorize the message based on verbosity
    if (Verbosity == ELogVerbosity::Error || Verbosity == ELogVerbosity::Fatal)
    {
        FString FormattedError = FString::Printf(TEXT("[%s] %s: %s"), 
            *FDateTime::Now().ToString(), 
            *Category.ToString(), 
            *Message);
        CapturedErrors.Add(FormattedError);
        
        UE_LOG(LogSurrealPilotBuild, Log, TEXT("Captured build error: %s"), *FormattedError);
    }
    else if (Verbosity == ELogVerbosity::Warning)
    {
        FString FormattedWarning = FString::Printf(TEXT("[%s] %s: %s"), 
            *FDateTime::Now().ToString(), 
            *Category.ToString(), 
            *Message);
        CapturedWarnings.Add(FormattedWarning);
        
        UE_LOG(LogSurrealPilotBuild, Log, TEXT("Captured build warning: %s"), *FormattedWarning);
    }
}

void FSurrealPilotOutputDevice::ClearCaptured()
{
    CapturedErrors.Empty();
    CapturedWarnings.Empty();
}

void FSurrealPilotOutputDevice::StartCapture()
{
    if (!bIsCapturing)
    {
        bIsCapturing = true;
        ClearCaptured();
        GLog->AddOutputDevice(this);
        UE_LOG(LogSurrealPilotBuild, Log, TEXT("Started capturing build messages"));
    }
}

void FSurrealPilotOutputDevice::StopCapture()
{
    if (bIsCapturing)
    {
        bIsCapturing = false;
        GLog->RemoveOutputDevice(this);
        UE_LOG(LogSurrealPilotBuild, Log, TEXT("Stopped capturing build messages. Captured %d errors and %d warnings"), 
            CapturedErrors.Num(), CapturedWarnings.Num());
    }
}

bool FSurrealPilotOutputDevice::IsBuildMessage(const FName& Category, const FString& Message) const
{
    // List of log categories that are related to building/compilation
    static const TArray<FString> BuildCategories = {
        TEXT("LogBlueprint"),
        TEXT("LogBlueprintCompile"),
        TEXT("LogKismetCompiler"),
        TEXT("LogCompile"),
        TEXT("LogBlueprintDebug"),
        TEXT("LogK2Compiler"),
        TEXT("LogEditorBuildPromotionTests"),
        TEXT("LogCook"),
        TEXT("LogUObjectGlobals"),
        TEXT("LogLinker"),
        TEXT("LogStreaming"),
        TEXT("LogPackageName")
    };

    FString CategoryString = Category.ToString();
    
    // Check if the category is in our build-related categories
    for (const FString& BuildCategory : BuildCategories)
    {
        if (CategoryString.Contains(BuildCategory))
        {
            return true;
        }
    }

    // Also check message content for common build error patterns
    if (Message.Contains(TEXT("Error:")) || 
        Message.Contains(TEXT("Warning:")) ||
        Message.Contains(TEXT("failed to compile")) ||
        Message.Contains(TEXT("compilation error")) ||
        Message.Contains(TEXT("blueprint compile")) ||
        Message.Contains(TEXT("node") && Message.Contains(TEXT("error"))) ||
        Message.Contains(TEXT("pin") && Message.Contains(TEXT("error"))))
    {
        return true;
    }

    return false;
}

void UBuildErrorCapture::Initialize(FSubsystemCollectionBase& Collection)
{
    Super::Initialize(Collection);
    
    OutputDevice = MakeUnique<FSurrealPilotOutputDevice>();
    BindCompilationEvents();
    
    UE_LOG(LogSurrealPilotBuild, Log, TEXT("BuildErrorCapture subsystem initialized"));
}

void UBuildErrorCapture::Deinitialize()
{
    UnbindCompilationEvents();
    
    if (OutputDevice.IsValid())
    {
        OutputDevice->StopCapture();
        OutputDevice.Reset();
    }
    
    Super::Deinitialize();
    UE_LOG(LogSurrealPilotBuild, Log, TEXT("BuildErrorCapture subsystem deinitialized"));
}

UBuildErrorCapture* UBuildErrorCapture::Get()
{
    if (GEditor)
    {
        return GEditor->GetEditorSubsystem<UBuildErrorCapture>();
    }
    return nullptr;
}

void UBuildErrorCapture::StartCapture()
{
    if (OutputDevice.IsValid())
    {
        OutputDevice->StartCapture();
    }
}

void UBuildErrorCapture::StopCapture()
{
    if (OutputDevice.IsValid())
    {
        OutputDevice->StopCapture();
    }
}

TArray<FString> UBuildErrorCapture::GetCapturedBuildMessages()
{
    TArray<FString> AllMessages;
    
    if (OutputDevice.IsValid())
    {
        TArray<FString> Errors = OutputDevice->GetCapturedErrors();
        TArray<FString> Warnings = OutputDevice->GetCapturedWarnings();
        
        AllMessages.Append(Errors);
        AllMessages.Append(Warnings);
    }
    
    return AllMessages;
}

TArray<FString> UBuildErrorCapture::GetCapturedErrors()
{
    if (OutputDevice.IsValid())
    {
        return OutputDevice->GetCapturedErrors();
    }
    return TArray<FString>();
}

TArray<FString> UBuildErrorCapture::GetCapturedWarnings()
{
    if (OutputDevice.IsValid())
    {
        return OutputDevice->GetCapturedWarnings();
    }
    return TArray<FString>();
}

void UBuildErrorCapture::ClearCaptured()
{
    if (OutputDevice.IsValid())
    {
        OutputDevice->ClearCaptured();
    }
}

FString UBuildErrorCapture::ExportBuildErrorsAsJson()
{
    UContextExporter* ContextExporter = UContextExporter::Get();
    if (!ContextExporter)
    {
        UE_LOG(LogSurrealPilotBuild, Error, TEXT("ContextExporter not available for build error export"));
        return TEXT("{}");
    }

    TArray<FString> AllMessages = GetCapturedBuildMessages();
    return ContextExporter->ExportErrorContext(AllMessages);
}

bool UBuildErrorCapture::IsCapturing() const
{
    if (OutputDevice.IsValid())
    {
        return OutputDevice->IsCapturing();
    }
    return false;
}

void UBuildErrorCapture::OnCompilationStarted()
{
    UE_LOG(LogSurrealPilotBuild, Log, TEXT("Blueprint compilation started - beginning error capture"));
    StartCapture();
}

void UBuildErrorCapture::OnCompilationFinished(bool bSucceeded)
{
    StopCapture();
    
    TArray<FString> Errors = GetCapturedErrors();
    TArray<FString> Warnings = GetCapturedWarnings();
    
    UE_LOG(LogSurrealPilotBuild, Log, TEXT("Blueprint compilation finished (Success: %s) - captured %d errors and %d warnings"), 
        bSucceeded ? TEXT("true") : TEXT("false"), Errors.Num(), Warnings.Num());
    
    // If there were errors, log them for debugging
    if (Errors.Num() > 0)
    {
        UE_LOG(LogSurrealPilotBuild, Log, TEXT("Build errors captured:"));
        for (int32 i = 0; i < Errors.Num(); i++)
        {
            UE_LOG(LogSurrealPilotBuild, Log, TEXT("  %d: %s"), i + 1, *Errors[i]);
        }
    }
}

void UBuildErrorCapture::BindCompilationEvents()
{
    if (GEditor)
    {
        // Bind to blueprint compilation events
        // Note: These delegates might need to be adjusted based on the specific UE version
        // For now, we'll provide manual start/stop methods and automatic capture can be added later
        
        UE_LOG(LogSurrealPilotBuild, Log, TEXT("Build error capture events bound"));
    }
}

void UBuildErrorCapture::UnbindCompilationEvents()
{
    if (CompilationStartedHandle.IsValid())
    {
        // Unbind compilation started delegate
        CompilationStartedHandle.Reset();
    }
    
    if (CompilationFinishedHandle.IsValid())
    {
        // Unbind compilation finished delegate
        CompilationFinishedHandle.Reset();
    }
    
    UE_LOG(LogSurrealPilotBuild, Log, TEXT("Build error capture events unbound"));
}