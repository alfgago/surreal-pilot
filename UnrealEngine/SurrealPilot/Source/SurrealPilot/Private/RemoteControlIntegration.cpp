#include "RemoteControlIntegration.h"
#include "HttpClient.h"
#include "ContextExporter.h"
#include "BuildErrorCapture.h"
#include "PatchApplier.h"
#include "SurrealPilotErrorHandler.h"
#include "Engine/World.h"
#include "Engine/Level.h"
#include "Engine/Blueprint.h"
#include "Editor.h"
#include "LevelEditor.h"
#include "Serialization/JsonSerializer.h"
#include "Serialization/JsonWriter.h"
#include "Dom/JsonObject.h"

// Remote Control includes
#include "IRemoteControlModule.h"
#include "RemoteControlPreset.h"
#include "RemoteControlBinding.h"

void URemoteControlIntegration::Initialize(FSubsystemCollectionBase& Collection)
{
    Super::Initialize(Collection);
    
    bDesktopChatConnected = false;
    
    // Register Remote Control endpoints
    RegisterRemoteControlEndpoints();
    
    // Test desktop chat connection
    TestDesktopChatConnection();
    
    UE_LOG(LogTemp, Log, TEXT("RemoteControlIntegration initialized"));
}

void URemoteControlIntegration::Deinitialize()
{
    Super::Deinitialize();
    UE_LOG(LogTemp, Log, TEXT("RemoteControlIntegration deinitialized"));
}

URemoteControlIntegration* URemoteControlIntegration::Get()
{
    if (GEditor)
    {
        return GEditor->GetEditorSubsystem<URemoteControlIntegration>();
    }
    return nullptr;
}

void URemoteControlIntegration::RegisterRemoteControlEndpoints()
{
    CreateRemoteControlPreset();
    
    if (!SurrealPilotPreset)
    {
        UE_LOG(LogTemp, Warning, TEXT("Failed to create Remote Control preset"));
        return;
    }
    
    // Register functions for remote access
    // These will be accessible via HTTP API at /remote/object/call
    
    UE_LOG(LogTemp, Log, TEXT("Remote Control endpoints registered for SurrealPilot"));
}

FString URemoteControlIntegration::HandleChatRequest(const FString& Message, const FString& Provider)
{
    UE_LOG(LogTemp, Log, TEXT("Handling chat request via Remote Control: %s"), *Message);
    
    // Send chat request to desktop application
    FHttpClient& HttpClient = FHttpClient::Get();
    
    // Build message array
    TArray<TSharedPtr<FJsonObject>> Messages;
    TSharedPtr<FJsonObject> UserMessage = MakeShareable(new FJsonObject);
    UserMessage->SetStringField(TEXT("role"), TEXT("user"));
    UserMessage->SetStringField(TEXT("content"), Message);
    Messages.Add(UserMessage);
    
    // Build context
    TSharedPtr<FJsonObject> Context = MakeShareable(new FJsonObject);
    Context->SetStringField(TEXT("source"), TEXT("ue_remote_control"));
    Context->SetStringField(TEXT("timestamp"), FDateTime::Now().ToIso8601());
    
    // Add current UE context
    UContextExporter* ContextExporter = UContextExporter::Get();
    if (ContextExporter)
    {
        FString CurrentContext = ContextExporter->ExportSelectionContext();
        Context->SetStringField(TEXT("ue_context"), CurrentContext);
    }
    
    FString ResponseContent;
    
    // Send request with callbacks
    HttpClient.SendChatRequest(
        Messages,
        Provider,
        Context,
        FHttpClient::FOnStreamingChunk::CreateLambda([&ResponseContent](const FString& Chunk)
        {
            ResponseContent += Chunk;
        }),
        FHttpClient::FOnHttpError::CreateLambda([](const FString& Error)
        {
            UE_LOG(LogTemp, Error, TEXT("Chat request failed: %s"), *Error);
        })
    );
    
    return ResponseContent;
}

FString URemoteControlIntegration::ExportCurrentContext()
{
    UContextExporter* ContextExporter = UContextExporter::Get();
    if (!ContextExporter)
    {
        return TEXT("{}");
    }
    
    // Export comprehensive context
    TSharedPtr<FJsonObject> FullContext = MakeShareable(new FJsonObject);
    
    // Selection context
    FString SelectionContext = ContextExporter->ExportSelectionContext();
    TSharedPtr<FJsonObject> SelectionJson;
    TSharedRef<TJsonReader<>> SelectionReader = TJsonReaderFactory<>::Create(SelectionContext);
    if (FJsonSerializer::Deserialize(SelectionReader, SelectionJson))
    {
        FullContext->SetObjectField(TEXT("selection"), SelectionJson);
    }
    
    // Scene context
    FString SceneContext = GetSceneInfo();
    TSharedPtr<FJsonObject> SceneJson;
    TSharedRef<TJsonReader<>> SceneReader = TJsonReaderFactory<>::Create(SceneContext);
    if (FJsonSerializer::Deserialize(SceneReader, SceneJson))
    {
        FullContext->SetObjectField(TEXT("scene"), SceneJson);
    }
    
    // Build errors
    FString BuildErrors = GetBuildErrors();
    TSharedPtr<FJsonObject> BuildJson;
    TSharedRef<TJsonReader<>> BuildReader = TJsonReaderFactory<>::Create(BuildErrors);
    if (FJsonSerializer::Deserialize(BuildReader, BuildJson))
    {
        FullContext->SetObjectField(TEXT("build_errors"), BuildJson);
    }
    
    // C++ project info
    FString CppInfo = GetCppProjectInfo();
    TSharedPtr<FJsonObject> CppJson;
    TSharedRef<TJsonReader<>> CppReader = TJsonReaderFactory<>::Create(CppInfo);
    if (FJsonSerializer::Deserialize(CppReader, CppJson))
    {
        FullContext->SetObjectField(TEXT("cpp_project"), CppJson);
    }
    
    // Serialize to string
    FString ContextString;
    TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&ContextString);
    FJsonSerializer::Serialize(FullContext.ToSharedRef(), Writer);
    
    // Send to desktop chat if available
    SendContextToDesktopChat(TEXT("full_context"), FullContext);
    
    return ContextString;
}

bool URemoteControlIntegration::ApplyPatchFromRemote(const FString& PatchJson)
{
    UPatchApplier* PatchApplier = UPatchApplier::Get();
    if (!PatchApplier)
    {
        FSurrealPilotErrorHandler::HandlePatchError(PatchJson, TEXT("PatchApplier not available"));
        return false;
    }
    
    bool bSuccess = PatchApplier->ApplyJsonPatch(PatchJson);
    
    if (bSuccess)
    {
        UE_LOG(LogTemp, Log, TEXT("Patch applied successfully via Remote Control"));
        
        // Send success notification to desktop chat
        TSharedPtr<FJsonObject> Notification = MakeShareable(new FJsonObject);
        Notification->SetStringField(TEXT("type"), TEXT("patch_applied"));
        Notification->SetBoolField(TEXT("success"), true);
        Notification->SetStringField(TEXT("message"), TEXT("Patch applied successfully"));
        SendContextToDesktopChat(TEXT("notification"), Notification);
    }
    else
    {
        FString Error = PatchApplier->GetLastError();
        FSurrealPilotErrorHandler::HandlePatchError(PatchJson, Error);
        
        // Send error notification to desktop chat
        TSharedPtr<FJsonObject> Notification = MakeShareable(new FJsonObject);
        Notification->SetStringField(TEXT("type"), TEXT("patch_failed"));
        Notification->SetBoolField(TEXT("success"), false);
        Notification->SetStringField(TEXT("error"), Error);
        SendContextToDesktopChat(TEXT("notification"), Notification);
    }
    
    return bSuccess;
}

FString URemoteControlIntegration::GetBuildErrors()
{
    UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
    if (!BuildErrorCapture)
    {
        return TEXT("{}");
    }
    
    return BuildErrorCapture->ExportBuildErrorsAsJson();
}

FString URemoteControlIntegration::GetSceneInfo()
{
    TSharedPtr<FJsonObject> SceneInfo = MakeShareable(new FJsonObject);
    
    if (GEditor && GEditor->GetEditorWorldContext().World())
    {
        UWorld* World = GEditor->GetEditorWorldContext().World();
        
        // Basic world info
        SceneInfo->SetStringField(TEXT("world_name"), World->GetName());
        SceneInfo->SetStringField(TEXT("world_type"), UEnum::GetValueAsString(World->WorldType));
        
        // Level info
        TArray<TSharedPtr<FJsonValue>> LevelsArray;
        for (ULevel* Level : World->GetLevels())
        {
            if (Level)
            {
                TSharedPtr<FJsonObject> LevelInfo = MakeShareable(new FJsonObject);
                LevelInfo->SetStringField(TEXT("name"), Level->GetName());
                LevelInfo->SetNumberField(TEXT("actor_count"), Level->Actors.Num());
                
                // Count different actor types
                int32 StaticMeshCount = 0;
                int32 LightCount = 0;
                int32 BlueprintCount = 0;
                
                for (AActor* Actor : Level->Actors)
                {
                    if (Actor)
                    {
                        if (Actor->GetClass()->GetName().Contains(TEXT("StaticMesh")))
                            StaticMeshCount++;
                        else if (Actor->GetClass()->GetName().Contains(TEXT("Light")))
                            LightCount++;
                        else if (Actor->GetClass()->GetName().Contains(TEXT("Blueprint")))
                            BlueprintCount++;
                    }
                }
                
                LevelInfo->SetNumberField(TEXT("static_mesh_count"), StaticMeshCount);
                LevelInfo->SetNumberField(TEXT("light_count"), LightCount);
                LevelInfo->SetNumberField(TEXT("blueprint_count"), BlueprintCount);
                
                LevelsArray.Add(MakeShareable(new FJsonValueObject(LevelInfo)));
            }
        }
        SceneInfo->SetArrayField(TEXT("levels"), LevelsArray);
    }
    
    // Serialize to string
    FString SceneString;
    TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&SceneString);
    FJsonSerializer::Serialize(SceneInfo.ToSharedRef(), Writer);
    
    return SceneString;
}

FString URemoteControlIntegration::GetCppProjectInfo()
{
    TSharedPtr<FJsonObject> CppInfo = MakeShareable(new FJsonObject);
    
    // Get project information
    FString ProjectName = FApp::GetProjectName();
    FString ProjectDir = FPaths::GetProjectFilePath();
    FString SourceDir = FPaths::GameSourceDir();
    
    CppInfo->SetStringField(TEXT("project_name"), ProjectName);
    CppInfo->SetStringField(TEXT("project_dir"), ProjectDir);
    CppInfo->SetStringField(TEXT("source_dir"), SourceDir);
    
    // Get module information
    TArray<TSharedPtr<FJsonValue>> ModulesArray;
    
    // This is a simplified version - in a full implementation,
    // you'd scan the source directory for .cpp/.h files
    TSharedPtr<FJsonObject> MainModule = MakeShareable(new FJsonObject);
    MainModule->SetStringField(TEXT("name"), ProjectName);
    MainModule->SetStringField(TEXT("type"), TEXT("Game"));
    MainModule->SetStringField(TEXT("path"), SourceDir);
    ModulesArray.Add(MakeShareable(new FJsonValueObject(MainModule)));
    
    CppInfo->SetArrayField(TEXT("modules"), ModulesArray);
    
    // Engine version
    CppInfo->SetStringField(TEXT("engine_version"), ENGINE_VERSION_STRING);
    
    // Serialize to string
    FString CppString;
    TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&CppString);
    FJsonSerializer::Serialize(CppInfo.ToSharedRef(), Writer);
    
    return CppString;
}

void URemoteControlIntegration::SendContextToDesktopChat(const FString& ContextType, const TSharedPtr<FJsonObject>& ContextData)
{
    if (!IsDesktopChatAvailable())
    {
        return;
    }
    
    FHttpClient& HttpClient = FHttpClient::Get();
    
    HttpClient.SendContextRequest(
        ContextType,
        ContextData,
        FHttpClient::FOnHttpResponse::CreateLambda([](TSharedPtr<FJsonObject> Response)
        {
            UE_LOG(LogTemp, Log, TEXT("Context sent to desktop chat successfully"));
        }),
        FHttpClient::FOnHttpError::CreateLambda([](const FString& Error)
        {
            UE_LOG(LogTemp, Warning, TEXT("Failed to send context to desktop chat: %s"), *Error);
        })
    );
}

bool URemoteControlIntegration::IsDesktopChatAvailable() const
{
    return bDesktopChatConnected;
}

void URemoteControlIntegration::CreateRemoteControlPreset()
{
    IRemoteControlModule& RemoteControlModule = IRemoteControlModule::Get();
    
    // Create or get existing preset
    SurrealPilotPreset = RemoteControlModule.CreatePreset(TEXT("SurrealPilot"), TEXT("SurrealPilot AI Assistant Remote Control"));
    
    if (SurrealPilotPreset)
    {
        // Expose this subsystem to Remote Control
        SurrealPilotPreset->ExposeFunction(
            this,
            URemoteControlIntegration::StaticClass()->FindFunctionByName(TEXT("HandleChatRequest")),
            TEXT("HandleChatRequest")
        );
        
        SurrealPilotPreset->ExposeFunction(
            this,
            URemoteControlIntegration::StaticClass()->FindFunctionByName(TEXT("ExportCurrentContext")),
            TEXT("ExportCurrentContext")
        );
        
        SurrealPilotPreset->ExposeFunction(
            this,
            URemoteControlIntegration::StaticClass()->FindFunctionByName(TEXT("ApplyPatchFromRemote")),
            TEXT("ApplyPatchFromRemote")
        );
        
        SurrealPilotPreset->ExposeFunction(
            this,
            URemoteControlIntegration::StaticClass()->FindFunctionByName(TEXT("GetBuildErrors")),
            TEXT("GetBuildErrors")
        );
        
        SurrealPilotPreset->ExposeFunction(
            this,
            URemoteControlIntegration::StaticClass()->FindFunctionByName(TEXT("GetSceneInfo")),
            TEXT("GetSceneInfo")
        );
        
        SurrealPilotPreset->ExposeFunction(
            this,
            URemoteControlIntegration::StaticClass()->FindFunctionByName(TEXT("GetCppProjectInfo")),
            TEXT("GetCppProjectInfo")
        );
        
        UE_LOG(LogTemp, Log, TEXT("Remote Control preset created for SurrealPilot"));
    }
}

void URemoteControlIntegration::TestDesktopChatConnection()
{
    FHttpClient& HttpClient = FHttpClient::Get();
    
    HttpClient.TestConnection(
        FHttpClient::FOnHttpResponse::CreateLambda([this](TSharedPtr<FJsonObject> Response)
        {
            bDesktopChatConnected = true;
            UE_LOG(LogTemp, Log, TEXT("Desktop chat connection established"));
        }),
        FHttpClient::FOnHttpError::CreateLambda([this](const FString& Error)
        {
            bDesktopChatConnected = false;
            UE_LOG(LogTemp, Warning, TEXT("Desktop chat not available: %s"), *Error);
        })
    );
}

void URemoteControlIntegration::OnRemoteControlPropertyChange(const FString& PropertyPath, const FString& NewValue)
{
    // Handle property changes from Remote Control
    UE_LOG(LogTemp, Log, TEXT("Remote Control property changed: %s = %s"), *PropertyPath, *NewValue);
    
    // Send notification to desktop chat
    TSharedPtr<FJsonObject> Notification = MakeShareable(new FJsonObject);
    Notification->SetStringField(TEXT("type"), TEXT("property_changed"));
    Notification->SetStringField(TEXT("property_path"), PropertyPath);
    Notification->SetStringField(TEXT("new_value"), NewValue);
    
    SendContextToDesktopChat(TEXT("notification"), Notification);
}