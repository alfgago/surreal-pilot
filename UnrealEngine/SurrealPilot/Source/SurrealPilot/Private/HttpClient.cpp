#include "HttpClient.h"
#include "SurrealPilotSettings.h"
#include "SurrealPilotErrorHandler.h"
#include "Engine/Engine.h"
#include "Misc/FileHelper.h"
#include "Misc/Paths.h"
#include "Dom/JsonValue.h"
#include "Serialization/JsonSerializer.h"
#include "Serialization/JsonWriter.h"

TUniquePtr<FHttpClient> FHttpClient::Instance = nullptr;

void FHttpClient::Initialize()
{
	if (!Instance.IsValid())
	{
		Instance = TUniquePtr<FHttpClient>(new FHttpClient());
		Instance->HttpModule = &FHttpModule::Get();
		UE_LOG(LogTemp, Log, TEXT("SurrealPilot HTTP client initialized"));
	}
}

void FHttpClient::Shutdown()
{
	if (Instance.IsValid())
	{
		Instance.Reset();
		UE_LOG(LogTemp, Log, TEXT("SurrealPilot HTTP client shutdown"));
	}
}

FHttpClient& FHttpClient::Get()
{
	check(Instance.IsValid());
	return *Instance;
}

void FHttpClient::SendChatRequest(
	const TArray<TSharedPtr<FJsonObject>>& Messages,
	const FString& Provider,
	const TSharedPtr<FJsonObject>& Context,
	FOnStreamingChunk OnChunk,
	FOnHttpError OnError)
{
	FHttpRequestPtr Request = CreateRequest(TEXT("POST"), TEXT("/api/chat"));
	
	// Build request body
	TSharedPtr<FJsonObject> RequestBody = MakeShareable(new FJsonObject);
	RequestBody->SetStringField(TEXT("provider"), Provider);
	
	// Convert messages array to JSON
	TArray<TSharedPtr<FJsonValue>> MessagesArray;
	for (const auto& Message : Messages)
	{
		MessagesArray.Add(MakeShareable(new FJsonValueObject(Message)));
	}
	RequestBody->SetArrayField(TEXT("messages"), MessagesArray);
	
	// Add context if provided
	if (Context.IsValid())
	{
		RequestBody->SetObjectField(TEXT("context"), Context);
	}
	
	// Serialize to JSON string
	FString RequestBodyString;
	TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&RequestBodyString);
	FJsonSerializer::Serialize(RequestBody.ToSharedRef(), Writer);
	
	Request->SetContentAsString(RequestBodyString);
	Request->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
	
	// Handle streaming response
	Request->OnProcessRequestComplete().BindLambda([this, OnChunk, OnError](FHttpRequestPtr Request, FHttpResponsePtr Response, bool bWasSuccessful)
	{
		HandleStreamingResponse(Request, Response, bWasSuccessful, OnChunk, OnError);
	});
	
	Request->ProcessRequest();
}

void FHttpClient::SendContextRequest(
	const FString& ContextType,
	const TSharedPtr<FJsonObject>& ContextData,
	FOnHttpResponse OnResponse,
	FOnHttpError OnError)
{
	FHttpRequestPtr Request = CreateRequest(TEXT("POST"), TEXT("/api/context"));
	
	// Build request body
	TSharedPtr<FJsonObject> RequestBody = MakeShareable(new FJsonObject);
	RequestBody->SetStringField(TEXT("type"), ContextType);
	RequestBody->SetObjectField(TEXT("data"), ContextData);
	
	// Serialize to JSON string
	FString RequestBodyString;
	TSharedRef<TJsonWriter<>> Writer = TJsonWriterFactory<>::Create(&RequestBodyString);
	FJsonSerializer::Serialize(RequestBody.ToSharedRef(), Writer);
	
	Request->SetContentAsString(RequestBodyString);
	Request->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
	
	Request->OnProcessRequestComplete().BindLambda([OnResponse, OnError](FHttpRequestPtr Request, FHttpResponsePtr Response, bool bWasSuccessful)
	{
		if (bWasSuccessful && Response.IsValid())
		{
			FString ResponseString = Response->GetContentAsString();
			TSharedPtr<FJsonObject> JsonResponse;
			TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(ResponseString);
			
			if (FJsonSerializer::Deserialize(Reader, JsonResponse) && JsonResponse.IsValid())
			{
				OnResponse.ExecuteIfBound(JsonResponse);
			}
			else
			{
				OnError.ExecuteIfBound(TEXT("Failed to parse JSON response"));
			}
		}
		else
		{
			FString ErrorMessage = Response.IsValid() ? 
				FString::Printf(TEXT("HTTP Error %d: %s"), Response->GetResponseCode(), *Response->GetContentAsString()) :
				TEXT("Request failed");
			OnError.ExecuteIfBound(ErrorMessage);
		}
	});
	
	Request->ProcessRequest();
}

void FHttpClient::TestConnection(FOnHttpResponse OnResponse, FOnHttpError OnError)
{
	FHttpRequestPtr Request = CreateRequest(TEXT("GET"), TEXT("/api/health"));
	
	Request->OnProcessRequestComplete().BindLambda([OnResponse, OnError](FHttpRequestPtr Request, FHttpResponsePtr Response, bool bWasSuccessful)
	{
		if (bWasSuccessful && Response.IsValid() && Response->GetResponseCode() == 200)
		{
			TSharedPtr<FJsonObject> JsonResponse = MakeShareable(new FJsonObject);
			JsonResponse->SetStringField(TEXT("status"), TEXT("connected"));
			OnResponse.ExecuteIfBound(JsonResponse);
		}
		else
		{
			FString ErrorMessage = Response.IsValid() ? 
				FString::Printf(TEXT("Connection test failed: %d"), Response->GetResponseCode()) :
				TEXT("Connection test failed: No response");
			OnError.ExecuteIfBound(ErrorMessage);
		}
	});
	
	Request->ProcessRequest();
}

FString FHttpClient::GetApiBaseUrl() const
{
	if (!CachedApiUrl.IsEmpty())
	{
		return CachedApiUrl;
	}
	
	// Try to read local config first (desktop mode)
	FString ConfigPath = FPaths::Combine(FPlatformMisc::GetEnvironmentVariable(TEXT("USERPROFILE")), TEXT(".surrealpilot"), TEXT("config.json"));
	FString ConfigContent;
	
	if (FFileHelper::LoadFileToString(ConfigContent, *ConfigPath))
	{
		TSharedPtr<FJsonObject> ConfigJson;
		TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(ConfigContent);
		
		if (FJsonSerializer::Deserialize(Reader, ConfigJson) && ConfigJson.IsValid())
		{
			int32 Port = ConfigJson->GetIntegerField(TEXT("port"));
			CachedApiUrl = FString::Printf(TEXT("http://127.0.0.1:%d"), Port);
			return CachedApiUrl;
		}
	}
	
	// Fallback to default localhost
	CachedApiUrl = TEXT("http://127.0.0.1:8000");
	
	// TODO: Add SaaS fallback URL from settings
	// const USurrealPilotSettings* Settings = GetDefault<USurrealPilotSettings>();
	// if (Settings && !Settings->SaaSApiUrl.IsEmpty())
	// {
	//     CachedApiUrl = Settings->SaaSApiUrl;
	// }
	
	return CachedApiUrl;
}

TMap<FString, FString> FHttpClient::GetAuthHeaders() const
{
	TMap<FString, FString> Headers;
	
	// Try to get API key from local config
	FString ConfigPath = FPaths::Combine(FPlatformMisc::GetEnvironmentVariable(TEXT("USERPROFILE")), TEXT(".surrealpilot"), TEXT("config.json"));
	FString ConfigContent;
	
	if (FFileHelper::LoadFileToString(ConfigContent, *ConfigPath))
	{
		TSharedPtr<FJsonObject> ConfigJson;
		TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(ConfigContent);
		
		if (FJsonSerializer::Deserialize(Reader, ConfigJson) && ConfigJson.IsValid())
		{
			FString ApiKey;
			if (ConfigJson->TryGetStringField(TEXT("api_key"), ApiKey) && !ApiKey.IsEmpty())
			{
				Headers.Add(TEXT("Authorization"), FString::Printf(TEXT("Bearer %s"), *ApiKey));
			}
		}
	}
	
	return Headers;
}

void FHttpClient::HandleStreamingResponse(FHttpRequestPtr Request, FHttpResponsePtr Response, bool bWasSuccessful, FOnStreamingChunk OnChunk, FOnHttpError OnError)
{
	if (!bWasSuccessful || !Response.IsValid())
	{
		FSurrealPilotErrorHandler::HandleHttpError(0, TEXT("Request failed"));
		OnError.ExecuteIfBound(TEXT("Request failed"));
		return;
	}
	
	if (Response->GetResponseCode() != 200)
	{
		FSurrealPilotErrorHandler::HandleHttpError(Response->GetResponseCode(), Response->GetContentAsString());
		FString ErrorMessage = FString::Printf(TEXT("HTTP Error %d: %s"), Response->GetResponseCode(), *Response->GetContentAsString());
		OnError.ExecuteIfBound(ErrorMessage);
		return;
	}
	
	// Parse Server-Sent Events
	FString ResponseData = Response->GetContentAsString();
	TArray<FString> Chunks = ParseSSEData(ResponseData);
	
	for (const FString& Chunk : Chunks)
	{
		OnChunk.ExecuteIfBound(Chunk);
	}
}

TArray<FString> FHttpClient::ParseSSEData(const FString& ResponseData) const
{
	TArray<FString> Chunks;
	TArray<FString> Lines;
	ResponseData.ParseIntoArrayLines(Lines);
	
	for (const FString& Line : Lines)
	{
		if (Line.StartsWith(TEXT("data: ")))
		{
			FString Data = Line.Mid(6); // Remove "data: " prefix
			if (!Data.IsEmpty() && Data != TEXT("[DONE]"))
			{
				Chunks.Add(Data);
			}
		}
	}
	
	return Chunks;
}

FHttpRequestPtr FHttpClient::CreateRequest(const FString& Verb, const FString& Endpoint) const
{
	FHttpRequestPtr Request = HttpModule->CreateRequest();
	Request->SetVerb(Verb);
	Request->SetURL(GetApiBaseUrl() + Endpoint);
	
	// Add authentication headers
	TMap<FString, FString> AuthHeaders = GetAuthHeaders();
	for (const auto& Header : AuthHeaders)
	{
		Request->SetHeader(Header.Key, Header.Value);
	}
	
	// Add common headers
	Request->SetHeader(TEXT("User-Agent"), TEXT("SurrealPilot-UE-Plugin/1.0"));
	Request->SetHeader(TEXT("Accept"), TEXT("text/event-stream, application/json"));
	
	return Request;
}