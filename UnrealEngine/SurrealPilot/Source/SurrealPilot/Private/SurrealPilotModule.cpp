#include "SurrealPilotModule.h"
#include "SurrealPilotStyle.h"
#include "SurrealPilotSettings.h"
#include "HttpClient.h"
#include "ContextExporter.h"
#include "BuildErrorCapture.h"
#include "PatchApplier.h"
#include "SurrealPilotErrorHandler.h"
#include "RemoteControlIntegration.h"
#include "LevelEditor.h"
#include "Widgets/Docking/SDockTab.h"
#include "Framework/MultiBox/MultiBoxBuilder.h"
#include "ToolMenus.h"
#include "Engine/Blueprint.h"
#include "Editor.h"
#include "HAL/PlatformApplicationMisc.h"

static const FName SurrealPilotTabName("SurrealPilot");

#define LOCTEXT_NAMESPACE "FSurrealPilotModule"

void FSurrealPilotModule::StartupModule()
{
	// Initialize the plugin style
	FSurrealPilotStyle::Initialize();
	FSurrealPilotStyle::ReloadTextures();

	// Register commands
	FSurrealPilotCommands::Register();

	// Register menus
	RegisterMenus();

	// Initialize HTTP client
	FHttpClient::Initialize();

	UE_LOG(LogTemp, Log, TEXT("SurrealPilot plugin started"));
}

void FSurrealPilotModule::ShutdownModule()
{
	// Unregister menus
	UnregisterMenus();

	// Unregister commands
	FSurrealPilotCommands::Unregister();

	// Shutdown style
	FSurrealPilotStyle::Shutdown();

	// Shutdown HTTP client
	FHttpClient::Shutdown();

	UE_LOG(LogTemp, Log, TEXT("SurrealPilot plugin shutdown"));
}

void FSurrealPilotModule::RegisterMenus()
{
	// Owner will be used for cleanup in call to UToolMenus::UnregisterOwner
	FToolMenuOwnerScoped OwnerScoped(this);

	{
		UToolMenu* Menu = UToolMenus::Get()->ExtendMenu("LevelEditor.MainMenu.Window");
		{
			FToolMenuSection& Section = Menu->FindOrAddSection("WindowLayout");
			Section.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().OpenChatWindow, nullptr);
		}
	}

	{
		UToolMenu* Menu = UToolMenus::Get()->ExtendMenu("LevelEditor.MainMenu.Tools");
		{
			FToolMenuSection& Section = Menu->FindOrAddSection("Programming");
			Section.AddSubMenu(
				"SurrealPilot",
				LOCTEXT("SurrealPilotSubMenu", "SurrealPilot"),
				LOCTEXT("SurrealPilotSubMenuTooltip", "SurrealPilot AI Assistant"),
				FNewToolMenuDelegate::CreateLambda([](UToolMenu* SubMenu)
				{
					FToolMenuSection& ContextSection = SubMenu->AddSection("SurrealPilotContext", LOCTEXT("SurrealPilotContext", "Context Export"));
					ContextSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().ExportBlueprintContext, nullptr);
					ContextSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().ExportSelectionContext, nullptr);
					
					FToolMenuSection& BuildSection = SubMenu->AddSection("SurrealPilotBuild", LOCTEXT("SurrealPilotBuild", "Build Error Capture"));
					BuildSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().StartBuildErrorCapture, nullptr);
					BuildSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().StopBuildErrorCapture, nullptr);
					BuildSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().ExportBuildErrors, nullptr);
					
					FToolMenuSection& PatchSection = SubMenu->AddSection("SurrealPilotPatch", LOCTEXT("SurrealPilotPatch", "Patch Application"));
					PatchSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().ApplyPatch, nullptr);
					PatchSection.AddMenuEntryWithCommandList(FSurrealPilotCommands::Get().TestPatch, nullptr);
				})
			);
		}
	}

	{
		UToolMenu* ToolbarMenu = UToolMenus::Get()->ExtendMenu("LevelEditor.LevelEditorToolBar");
		{
			FToolMenuSection& Section = ToolbarMenu->FindOrAddSection("Settings");
			{
				FToolMenuEntry& Entry = Section.AddEntry(FToolMenuEntry::InitToolBarButton(FSurrealPilotCommands::Get().OpenChatWindow));
				Entry.SetCommandList(nullptr);
			}
		}
	}
}

void FSurrealPilotModule::UnregisterMenus()
{
	UToolMenus::UnregisterOwner(this);
}

void FSurrealPilotModule::OnChatWindowClicked()
{
	// TODO: Open chat window - will be implemented in future tasks
	UE_LOG(LogTemp, Log, TEXT("SurrealPilot chat window requested"));
}

void FSurrealPilotModule::OnSettingsClicked()
{
	// TODO: Open settings window - will be implemented in future tasks
	UE_LOG(LogTemp, Log, TEXT("SurrealPilot settings requested"));
}

void FSurrealPilotModule::OnExportBlueprintContext()
{
	UContextExporter* ContextExporter = UContextExporter::Get();
	if (!ContextExporter)
	{
		UE_LOG(LogTemp, Error, TEXT("ContextExporter not available"));
		return;
	}

	// Get the currently selected blueprint
	UBlueprint* SelectedBlueprint = nullptr;
	if (GEditor)
	{
		USelection* Selection = GEditor->GetSelectedObjects();
		if (Selection)
		{
			for (FSelectionIterator It(*Selection); It; ++It)
			{
				if (UBlueprint* Blueprint = Cast<UBlueprint>(*It))
				{
					SelectedBlueprint = Blueprint;
					break;
				}
			}
		}
	}

	if (!SelectedBlueprint)
	{
		UE_LOG(LogTemp, Warning, TEXT("No blueprint selected for context export"));
		return;
	}

	FString ContextJson = ContextExporter->ExportBlueprintContext(SelectedBlueprint);
	
	// Copy to clipboard for now - in future tasks this will be sent to API
	FPlatformApplicationMisc::ClipboardCopy(*ContextJson);
	
	UE_LOG(LogTemp, Log, TEXT("Blueprint context exported to clipboard: %s"), *SelectedBlueprint->GetName());
}

void FSurrealPilotModule::OnExportSelectionContext()
{
	UContextExporter* ContextExporter = UContextExporter::Get();
	if (!ContextExporter)
	{
		UE_LOG(LogTemp, Error, TEXT("ContextExporter not available"));
		return;
	}

	FString ContextJson = ContextExporter->ExportSelectionContext();
	
	// Copy to clipboard for now - in future tasks this will be sent to API
	FPlatformApplicationMisc::ClipboardCopy(*ContextJson);
	
	UE_LOG(LogTemp, Log, TEXT("Selection context exported to clipboard"));
}

void FSurrealPilotModule::OnStartBuildErrorCapture()
{
	UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
	if (!BuildErrorCapture)
	{
		UE_LOG(LogTemp, Error, TEXT("BuildErrorCapture not available"));
		return;
	}

	BuildErrorCapture->StartCapture();
	UE_LOG(LogTemp, Log, TEXT("Started capturing build errors"));
}

void FSurrealPilotModule::OnStopBuildErrorCapture()
{
	UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
	if (!BuildErrorCapture)
	{
		UE_LOG(LogTemp, Error, TEXT("BuildErrorCapture not available"));
		return;
	}

	BuildErrorCapture->StopCapture();
	UE_LOG(LogTemp, Log, TEXT("Stopped capturing build errors"));
}

void FSurrealPilotModule::OnExportBuildErrors()
{
	UBuildErrorCapture* BuildErrorCapture = UBuildErrorCapture::Get();
	if (!BuildErrorCapture)
	{
		UE_LOG(LogTemp, Error, TEXT("BuildErrorCapture not available"));
		return;
	}

	FString ErrorJson = BuildErrorCapture->ExportBuildErrorsAsJson();
	
	// Copy to clipboard for now - in future tasks this will be sent to API
	FPlatformApplicationMisc::ClipboardCopy(*ErrorJson);
	
	TArray<FString> Errors = BuildErrorCapture->GetCapturedErrors();
	TArray<FString> Warnings = BuildErrorCapture->GetCapturedWarnings();
	
	UE_LOG(LogTemp, Log, TEXT("Build errors exported to clipboard (%d errors, %d warnings)"), 
		Errors.Num(), Warnings.Num());
}

void FSurrealPilotModule::OnApplyPatch()
{
	UPatchApplier* PatchApplier = UPatchApplier::Get();
	if (!PatchApplier)
	{
		UE_LOG(LogTemp, Error, TEXT("PatchApplier not available"));
		return;
	}

	// Get patch JSON from clipboard
	FString PatchJson;
	FPlatformApplicationMisc::ClipboardPaste(PatchJson);
	
	if (PatchJson.IsEmpty())
	{
		UE_LOG(LogTemp, Warning, TEXT("No patch data found in clipboard"));
		return;
	}

	// Apply the patch
	bool bSuccess = PatchApplier->ApplyJsonPatch(PatchJson);
	
	if (bSuccess)
	{
		UE_LOG(LogTemp, Log, TEXT("Patch applied successfully"));
	}
	else
	{
		FString ErrorMessage = PatchApplier->GetLastError();
		UE_LOG(LogTemp, Error, TEXT("Failed to apply patch: %s"), *ErrorMessage);
	}
}

void FSurrealPilotModule::OnTestPatch()
{
	UPatchApplier* PatchApplier = UPatchApplier::Get();
	if (!PatchApplier)
	{
		UE_LOG(LogTemp, Error, TEXT("PatchApplier not available"));
		return;
	}

	// Get patch JSON from clipboard
	FString PatchJson;
	FPlatformApplicationMisc::ClipboardPaste(PatchJson);
	
	if (PatchJson.IsEmpty())
	{
		UE_LOG(LogTemp, Warning, TEXT("No patch data found in clipboard"));
		return;
	}

	// Test if the patch can be applied
	bool bCanApply = PatchApplier->CanApplyPatch(PatchJson);
	
	if (bCanApply)
	{
		UE_LOG(LogTemp, Log, TEXT("Patch validation successful - patch can be applied"));
	}
	else
	{
		FString ErrorMessage = PatchApplier->GetLastError();
		UE_LOG(LogTemp, Warning, TEXT("Patch validation failed: %s"), *ErrorMessage);
	}
}

void FSurrealPilotCommands::RegisterCommands()
{
	UI_COMMAND(OpenChatWindow, "SurrealPilot Chat", "Open SurrealPilot AI chat window", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(OpenSettings, "SurrealPilot Settings", "Open SurrealPilot settings", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(ExportBlueprintContext, "Export Blueprint Context", "Export selected blueprint context as JSON", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(ExportSelectionContext, "Export Selection Context", "Export current selection context as JSON", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(StartBuildErrorCapture, "Start Error Capture", "Start capturing build errors and warnings", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(StopBuildErrorCapture, "Stop Error Capture", "Stop capturing build errors and warnings", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(ExportBuildErrors, "Export Build Errors", "Export captured build errors as JSON", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(ApplyPatch, "Apply Patch", "Apply AI-generated patch from clipboard", EUserInterfaceActionType::Button, FInputChord());
	UI_COMMAND(TestPatch, "Test Patch", "Test if AI-generated patch can be applied", EUserInterfaceActionType::Button, FInputChord());
}

#undef LOCTEXT_NAMESPACE
	
IMPLEMENT_MODULE(FSurrealPilotModule, SurrealPilot)