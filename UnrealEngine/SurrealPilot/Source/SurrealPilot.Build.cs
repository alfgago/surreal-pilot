using UnrealBuildTool;

public class SurrealPilot : ModuleRules
{
	public SurrealPilot(ReadOnlyTargetRules Target) : base(Target)
	{
		PCHUsage = ModuleRules.PCHUsageMode.UseExplicitOrSharedPCHs;
		
		PublicIncludePaths.AddRange(
			new string[] {
			}
		);
				
		PrivateIncludePaths.AddRange(
			new string[] {
			}
		);
			
		PublicDependencyModuleNames.AddRange(
			new string[]
			{
				"Core",
				"CoreUObject",
				"Engine",
				"UnrealEd",
				"EditorStyle",
				"EditorWidgets",
				"ToolMenus",
				"Slate",
				"SlateCore",
				"HTTP",
				"Json",
				"JsonObjectConverter"
			}
		);
			
		PrivateDependencyModuleNames.AddRange(
			new string[]
			{
				"EditorScriptingUtilities",
				"BlueprintGraph",
				"KismetCompiler",
				"ToolMenus",
				"Projects",
				"InputCore",
				"EditorSubsystem",
				"LevelEditor",
				"RemoteControl",
				"RemoteControlUI",
				"AssetRegistry",
				"MaterialEditor",
				"AnimGraph",
				"Persona"
			}
		);
		
		DynamicallyLoadedModuleNames.AddRange(
			new string[]
			{
			}
		);
	}
}