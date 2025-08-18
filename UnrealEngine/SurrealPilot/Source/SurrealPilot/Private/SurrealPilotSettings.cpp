#include "SurrealPilotSettings.h"
#include "HttpClient.h"
#include "Misc/FileHelper.h"
#include "Misc/Paths.h"
#include "Dom/JsonObject.h"
#include "Serialization/JsonSerializer.h"
#include "Serialization/JsonWriter.h"
#include "HAL/PlatformFilemanager.h"

USurrealPilotSettings::USurrealPilotSettings()
{
	LoadLocalConfig();
}

FName USurrealPilotSettings::GetCategoryName() const
{
	return TEXT("Plugins");
}

FText USurrealPilotSettings::GetSectionText() const
{
	return NSLOCTEXT("SurrealPilotSettings", "SectionText", "SurrealPilot");
}

FString USurrealPilotSettings::GetApiKey() const
{
	return ApiKey;
}

void USurrealPilotSettings::SetApiKey(const FString& NewApiKey)
{
	ApiKey = NewApiKey;
	SaveLocalConfig();
}

FString USurrealPilotSettings::GetEffectiveApiUrl() const
{
	// Try desktop first
	FString DesktopUrl = FString::Printf(TEXT("http://127.0.0.1:%d"), DesktopApiPort);
	
	// TODO: Add actual connectivity test here
	// For now, return desktop URL if port is valid, otherwise SaaS
	if (DesktopApiPort > 0 && DesktopApiPort <= 65535)
	{
		return DesktopUrl;
	}
	
	return SaaSApiUrl;
}

void USurrealPilotSettings::TestApiConnection()
{
	FHttpClient::Get().TestConnection(
		FOnHttpResponse::CreateLambda([](TSharedPtr<FJsonObject> Response)
		{
			UE_LOG(LogTemp, Log, TEXT("SurrealPilot API connection test successful"));
		}),
		FOnHttpError::CreateLambda([](const FString& Error)
		{
			UE_LOG(LogTemp, Warning, TEXT("SurrealPilot API connection test failed: %s"), *Error);
		})
	);
}

void USurrealPilotSettings::LoadLocalConfig()
{
	FString ConfigPath = GetLocalConfigPath();
	FString ConfigContent;
	
	if (FFileHelper::LoadFileToString(ConfigContent, *ConfigPath))
	{
		TSharedPtr<FJsonObject> ConfigJson;
		TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(ConfigContent);
		
		if (FJsonSerializer::Deserialize(Reader, ConfigJson) && ConfigJson.IsValid())
		{
			// Load API key
			FString LoadedApiKey;
			if (ConfigJson->TryGetStringField(TEXT("api_key"), LoadedApiKey))
			{
				ApiKey = LoadedApiKey;
			}
			
			// Load preferred provider
			FString ProviderString;
			if (ConfigJson->TryGetStringField(TEXT("preferred_provider"), ProviderString))
			{
				if (ProviderString == TEXT("openai"))
				{
					PreferredProvider = EAIProvider::OpenAI;
				}
				else if (ProviderString == TEXT("anthropic"))
				{
					PreferredProvider = EAIProvider::Anthropic;
				}
				else if (ProviderString == TEXT("gemini"))
				{
					PreferredProvider = EAIProvider::Gemini;
				}
				else if (ProviderString == TEXT("ollama"))
				{
					PreferredProvider = EAIProvider::Ollama;
				}
			}
			
			// Load port
			int32 LoadedPort;
			if (ConfigJson->TryGetNumberField(TEXT("port"), LoadedPort))
			{
				DesktopApiPort = LoadedPort;
			}
		}
	}
}

void USurrealPilotSettings::SaveLocalConfig() const
{
	FString ConfigPath = GetLocalConfigPath();
	
	// Ensure directory exists
	FString ConfigDir = FPaths::GetPath(ConfigPath);
	IPlatformFile& PlatformFile = FPlatformFileManager::Get().GetPlatformFile();
	if (!PlatformFile.DirectoryExists(*ConfigDir))
	{
		PlatformFile.CreateDirectoryTree(*ConfigDir);
	}
	
	// Create JSON object
	TSharedPtr<FJsonObject> ConfigJson = MakeShareable(new FJsonObject);
	ConfigJson->SetStringField(TEXT("api_key"), ApiKey);
	ConfigJson->SetNumberField(TEXT("port"), DesktopApiPort);
	
	// Convert provider enum to string
	FString ProviderString;
	switch (PreferredProvider)
	{
		case EAIProvider::OpenAI:
			ProviderString = TEXT("openai");
			break;
		case EAIProvider::Anthropic:
			ProviderString = TEXT("anthropic");
			break;
		case EAIProvider::Gemini:
			ProviderString = TEXT("gemini");
			break;
		case EAIProvider::Ollama:
			ProviderString = TEXT("ollama");
			break;
	}
	ConfigJson->SetStringField(TEXT("preferred_provider"), ProviderString);
	
	// Serialize to string
	FString ConfigContent;
	TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&ConfigContent);
	FJsonSerializer::Serialize(ConfigJson.ToSharedRef(), Writer);
	
	// Save to file
	FFileHelper::SaveStringToFile(ConfigContent, *ConfigPath);
}

FString USurrealPilotSettings::GetLocalConfigPath() const
{
	FString UserProfile = FPlatformMisc::GetEnvironmentVariable(TEXT("USERPROFILE"));
	if (UserProfile.IsEmpty())
	{
		UserProfile = FPlatformMisc::GetEnvironmentVariable(TEXT("HOME"));
	}
	
	return FPaths::Combine(UserProfile, TEXT(".surrealpilot"), TEXT("config.json"));
}