#pragma once

#include "CoreMinimal.h"
#include "EditorSubsystem.h"
#include "Logging/LogMacros.h"
#include "Misc/OutputDevice.h"
#include "BuildErrorCapture.generated.h"

DECLARE_LOG_CATEGORY_EXTERN(LogSurrealPilotBuild, Log, All);

/**
 * Custom output device to capture build errors and warnings
 */
class SURREALPILOT_API FSurrealPilotOutputDevice : public FOutputDevice
{
public:
    FSurrealPilotOutputDevice();
    virtual ~FSurrealPilotOutputDevice();

    // FOutputDevice interface
    virtual void Serialize(const TCHAR* V, ELogVerbosity::Type Verbosity, const class FName& Category) override;

    /**
     * Get captured build errors
     */
    TArray<FString> GetCapturedErrors() const { return CapturedErrors; }

    /**
     * Get captured build warnings
     */
    TArray<FString> GetCapturedWarnings() const { return CapturedWarnings; }

    /**
     * Clear captured messages
     */
    void ClearCaptured();

    /**
     * Start capturing build messages
     */
    void StartCapture();

    /**
     * Stop capturing build messages
     */
    void StopCapture();

    /**
     * Check if currently capturing
     */
    bool IsCapturing() const { return bIsCapturing; }

private:
    TArray<FString> CapturedErrors;
    TArray<FString> CapturedWarnings;
    bool bIsCapturing;

    /**
     * Check if a log message is a build error or warning
     */
    bool IsBuildMessage(const FName& Category, const FString& Message) const;
};

/**
 * Subsystem for capturing and managing build errors
 */
UCLASS()
class SURREALPILOT_API UBuildErrorCapture : public UEditorSubsystem
{
    GENERATED_BODY()

public:
    // USubsystem interface
    virtual void Initialize(FSubsystemCollectionBase& Collection) override;
    virtual void Deinitialize() override;

    /**
     * Get the singleton instance
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    static UBuildErrorCapture* Get();

    /**
     * Start capturing build errors
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    void StartCapture();

    /**
     * Stop capturing build errors
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    void StopCapture();

    /**
     * Get all captured build errors and warnings
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    TArray<FString> GetCapturedBuildMessages();

    /**
     * Get captured build errors only
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    TArray<FString> GetCapturedErrors();

    /**
     * Get captured build warnings only
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    TArray<FString> GetCapturedWarnings();

    /**
     * Clear all captured messages
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    void ClearCaptured();

    /**
     * Export captured build errors as JSON using ContextExporter
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    FString ExportBuildErrorsAsJson();

    /**
     * Check if currently capturing
     */
    UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
    bool IsCapturing() const;

private:
    TUniquePtr<FSurrealPilotOutputDevice> OutputDevice;

    /**
     * Handle compilation started
     */
    void OnCompilationStarted();

    /**
     * Handle compilation finished
     */
    void OnCompilationFinished(bool bSucceeded);

    /**
     * Bind to compilation events
     */
    void BindCompilationEvents();

    /**
     * Unbind from compilation events
     */
    void UnbindCompilationEvents();

    // Delegate handles
    FDelegateHandle CompilationStartedHandle;
    FDelegateHandle CompilationFinishedHandle;
};