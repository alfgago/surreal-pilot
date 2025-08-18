#pragma once

#include "CoreMinimal.h"
#include "Engine/DeveloperSettings.h"
#include "SurrealPilotSettings.generated.h"

UENUM(BlueprintType)
enum class EAIProvider : uint8
{
	OpenAI		UMETA(DisplayName = "OpenAI"),
	Anthropic	UMETA(DisplayName = "Anthropic"),
	Gemini		UMETA(DisplayName = "Google Gemini"),
	Ollama		UMETA(DisplayName = "Local Ollama")
};

/**
 * Settings for SurrealPilot plugin
 */
UCLASS(config=EditorPerProjectUserSettings, meta=(DisplayName="SurrealPilot"))
class SURREALPILOT_API USurrealPilotSettings : public UDeveloperSettings
{
	GENERATED_BODY()

public:
	USurrealPilotSettings();

	// UDeveloperSettings interface
	virtual FName GetCategoryName() const override;
	virtual FText GetSectionText() const override;

public:
	/** Preferred AI provider for requests */
	UPROPERTY(config, EditAnywhere, Category = "AI Provider", meta = (DisplayName = "Preferred AI Provider"))
	EAIProvider PreferredProvider = EAIProvider::OpenAI;

	/** SaaS API URL (fallback when desktop app is not available) */
	UPROPERTY(config, EditAnywhere, Category = "Connection", meta = (DisplayName = "SaaS API URL"))
	FString SaaSApiUrl = TEXT("https://api.surrealpilot.com");

	/** Desktop API port (for local desktop app) */
	UPROPERTY(config, EditAnywhere, Category = "Connection", meta = (DisplayName = "Desktop API Port", ClampMin = "1024", ClampMax = "65535"))
	int32 DesktopApiPort = 8000;

	/** Enable automatic context export on Blueprint compilation errors */
	UPROPERTY(config, EditAnywhere, Category = "Context Export", meta = (DisplayName = "Auto Export on Compile Errors"))
	bool bAutoExportOnCompileErrors = true;

	/** Enable automatic context export on Blueprint selection changes */
	UPROPERTY(config, EditAnywhere, Category = "Context Export", meta = (DisplayName = "Auto Export on Selection Change"))
	bool bAutoExportOnSelectionChange = false;

	/** Maximum number of error lines to include in context */
	UPROPERTY(config, EditAnywhere, Category = "Context Export", meta = (DisplayName = "Max Error Lines", ClampMin = "10", ClampMax = "1000"))
	int32 MaxErrorLines = 100;

	/** Enable debug logging for HTTP requests */
	UPROPERTY(config, EditAnywhere, Category = "Debug", meta = (DisplayName = "Enable HTTP Debug Logging"))
	bool bEnableHttpDebugLogging = false;

	/** Enable debug logging for context export */
	UPROPERTY(config, EditAnywhere, Category = "Debug", meta = (DisplayName = "Enable Context Debug Logging"))
	bool bEnableContextDebugLogging = false;

	/** Timeout for HTTP requests in seconds */
	UPROPERTY(config, EditAnywhere, Category = "Advanced", meta = (DisplayName = "HTTP Request Timeout", ClampMin = "5", ClampMax = "300"))
	int32 HttpTimeoutSeconds = 30;

	/** Enable streaming responses */
	UPROPERTY(config, EditAnywhere, Category = "Advanced", meta = (DisplayName = "Enable Streaming Responses"))
	bool bEnableStreamingResponses = true;

	/** API key for SaaS authentication (stored in local config, not in project settings) */
	UPROPERTY(Transient, meta = (DisplayName = "API Key (Local Only)"))
	FString ApiKey;

public:
	/** Get the current API key from local configuration */
	UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
	FString GetApiKey() const;

	/** Set the API key in local configuration */
	UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
	void SetApiKey(const FString& NewApiKey);

	/** Get the effective API URL (desktop or SaaS) */
	UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
	FString GetEffectiveApiUrl() const;

	/** Test connection to the API */
	UFUNCTION(BlueprintCallable, Category = "SurrealPilot")
	void TestApiConnection();

private:
	/** Load API key from local configuration file */
	void LoadLocalConfig();

	/** Save API key to local configuration file */
	void SaveLocalConfig() const;

	/** Get the local configuration file path */
	FString GetLocalConfigPath() const;
};