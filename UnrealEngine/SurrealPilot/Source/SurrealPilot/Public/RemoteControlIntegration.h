#pragma once

#include "CoreMinimal.h"
#include "EditorSubsystem.h"
#include "Dom/JsonObject.h"
#include "RemoteControlIntegration.generated.h"

/**
 * Integration with UE Remote Control API for external communication
 */
UCLASS()
class SURREALPILOT_API URemoteControlIntegration : public UEditorSubsystem
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
    static URemoteControlIntegration* Get();

    /**
     * Register Remote Control endpoints for SurrealPilot
     */
    void RegisterRemoteControlEndpoints();

    /**
     * Handle incoming chat request from external application
     */
    UFUNCTION(CallInEditor = true, Category = "SurrealPilot")
    FString HandleChatRequest(const FString& Message, const FString& Provider = TEXT("openai"));

    /**
     * Export current context via Remote Control
     */
    UFUNCTION(CallInEditor = true, Category = "SurrealPilot")
    FString ExportCurrentContext();

    /**
     * Apply patch via Remote Control
     */
    UFUNCTION(CallInEditor = true, Category = "SurrealPilot")
    bool ApplyPatchFromRemote(const FString& PatchJson);

    /**
     * Get build errors via Remote Control
     */
    UFUNCTION(CallInEditor = true, Category = "SurrealPilot")
    FString GetBuildErrors();

    /**
     * Get scene information via Remote Control
     */
    UFUNCTION(CallInEditor = true, Category = "SurrealPilot")
    FString GetSceneInfo();

    /**
     * Get C++ project information via Remote Control
     */
    UFUNCTION(CallInEditor = true, Category = "SurrealPilot")
    FString GetCppProjectInfo();

    /**
     * Send context to desktop chat automatically
     */
    void SendContextToDesktopChat(const FString& ContextType, const TSharedPtr<FJsonObject>& ContextData);

    /**
     * Check if desktop chat is available
     */
    bool IsDesktopChatAvailable() const;

private:
    /** Remote Control preset for SurrealPilot */
    class URemoteControlPreset* SurrealPilotPreset;

    /** Desktop chat connection status */
    bool bDesktopChatConnected;

    /**
     * Create Remote Control preset
     */
    void CreateRemoteControlPreset();

    /**
     * Test connection to desktop chat
     */
    void TestDesktopChatConnection();

    /**
     * Handle Remote Control property change
     */
    void OnRemoteControlPropertyChange(const FString& PropertyPath, const FString& NewValue);
};