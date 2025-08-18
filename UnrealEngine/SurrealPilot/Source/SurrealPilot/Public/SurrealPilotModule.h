#pragma once

#include "CoreMinimal.h"
#include "Modules/ModuleManager.h"
#include "Framework/Commands/Commands.h"

class FSurrealPilotModule : public IModuleInterface
{
public:
	/** IModuleInterface implementation */
	virtual void StartupModule() override;
	virtual void ShutdownModule() override;

private:
	void RegisterMenus();
	void UnregisterMenus();
	
	/** Creates the SurrealPilot menu entries */
	void CreateSurrealPilotMenu();
	
	/** Handles the chat window action */
	void OnChatWindowClicked();
	
	/** Handles the settings action */
	void OnSettingsClicked();
	
	/** Handles the export blueprint context action */
	void OnExportBlueprintContext();
	
	/** Handles the export selection context action */
	void OnExportSelectionContext();
	
	/** Handles the start build error capture action */
	void OnStartBuildErrorCapture();
	
	/** Handles the stop build error capture action */
	void OnStopBuildErrorCapture();
	
	/** Handles the export build errors action */
	void OnExportBuildErrors();
	
	/** Handles the apply patch action */
	void OnApplyPatch();
	
	/** Handles the test patch action */
	void OnTestPatch();
};

class FSurrealPilotCommands : public TCommands<FSurrealPilotCommands>
{
public:
	FSurrealPilotCommands()
		: TCommands<FSurrealPilotCommands>(TEXT("SurrealPilot"), NSLOCTEXT("Contexts", "SurrealPilot", "SurrealPilot Plugin"), NAME_None, TEXT("SurrealPilotStyle"))
	{
	}

	// TCommands<> interface
	virtual void RegisterCommands() override;

public:
	TSharedPtr<FUICommandInfo> OpenChatWindow;
	TSharedPtr<FUICommandInfo> OpenSettings;
	TSharedPtr<FUICommandInfo> ExportBlueprintContext;
	TSharedPtr<FUICommandInfo> ExportSelectionContext;
	TSharedPtr<FUICommandInfo> StartBuildErrorCapture;
	TSharedPtr<FUICommandInfo> StopBuildErrorCapture;
	TSharedPtr<FUICommandInfo> ExportBuildErrors;
	TSharedPtr<FUICommandInfo> ApplyPatch;
	TSharedPtr<FUICommandInfo> TestPatch;
};