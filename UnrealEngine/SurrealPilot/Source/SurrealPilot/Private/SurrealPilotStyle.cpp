#include "SurrealPilotStyle.h"
#include "Styling/SlateStyleRegistry.h"
#include "Framework/Application/SlateApplication.h"
#include "Slate/SlateGameResources.h"
#include "Interfaces/IPluginManager.h"
#include "Styling/SlateStyleMacros.h"

#define RootToContentDir Style->RootToContentDir

TSharedPtr<FSlateStyleSet> FSurrealPilotStyle::StyleInstance = nullptr;

void FSurrealPilotStyle::Initialize()
{
	if (!StyleInstance.IsValid())
	{
		StyleInstance = Create();
		FSlateStyleRegistry::RegisterSlateStyle(*StyleInstance);
	}
}

void FSurrealPilotStyle::Shutdown()
{
	FSlateStyleRegistry::UnRegisterSlateStyle(*StyleInstance);
	ensure(StyleInstance.IsUnique());
	StyleInstance.Reset();
}

FName FSurrealPilotStyle::GetStyleSetName()
{
	static FName StyleSetName(TEXT("SurrealPilotStyle"));
	return StyleSetName;
}

const ISlateStyle& FSurrealPilotStyle::Get()
{
	return *StyleInstance;
}

void FSurrealPilotStyle::ReloadTextures()
{
	if (FSlateApplication::IsInitialized())
	{
		FSlateApplication::Get().GetRenderer()->ReloadTextureResources();
	}
}

TSharedRef<FSlateStyleSet> FSurrealPilotStyle::Create()
{
	TSharedRef<FSlateStyleSet> Style = MakeShareable(new FSlateStyleSet("SurrealPilotStyle"));
	Style->SetContentRoot(IPluginManager::Get().FindPlugin("SurrealPilot")->GetBaseDir() / TEXT("Resources"));

	// Define icon sizes
	const FVector2D Icon16x16(16.0f, 16.0f);
	const FVector2D Icon20x20(20.0f, 20.0f);
	const FVector2D Icon40x40(40.0f, 40.0f);

	// Set default icons (using engine defaults for now)
	Style->Set("SurrealPilot.OpenChatWindow", new IMAGE_BRUSH_SVG(TEXT("Slate/Starship/Common/chat"), Icon40x40));
	Style->Set("SurrealPilot.OpenChatWindow.Small", new IMAGE_BRUSH_SVG(TEXT("Slate/Starship/Common/chat"), Icon20x20));
	Style->Set("SurrealPilot.OpenSettings", new IMAGE_BRUSH_SVG(TEXT("Slate/Starship/Common/settings"), Icon40x40));
	Style->Set("SurrealPilot.OpenSettings.Small", new IMAGE_BRUSH_SVG(TEXT("Slate/Starship/Common/settings"), Icon20x20));

	// Chat window styles
	Style->Set("SurrealPilot.ChatWindow.Background", FSlateColorBrush(FLinearColor(0.02f, 0.02f, 0.02f, 1.0f)));
	Style->Set("SurrealPilot.ChatWindow.MessageUser", FSlateColorBrush(FLinearColor(0.1f, 0.3f, 0.6f, 1.0f)));
	Style->Set("SurrealPilot.ChatWindow.MessageAI", FSlateColorBrush(FLinearColor(0.2f, 0.2f, 0.2f, 1.0f)));

	return Style;
}