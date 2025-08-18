#pragma once

#include "CoreMinimal.h"
#include "Http.h"
#include "Dom/JsonObject.h"

DECLARE_DELEGATE_OneParam(FOnHttpResponse, TSharedPtr<FJsonObject>);
DECLARE_DELEGATE_OneParam(FOnHttpError, const FString&);
DECLARE_DELEGATE_OneParam(FOnStreamingChunk, const FString&);

/**
 * HTTP client for communicating with SurrealPilot API
 * Handles both desktop (localhost:8000) and SaaS endpoints
 */
class SURREALPILOT_API FHttpClient
{
public:
	/** Initialize the HTTP client */
	static void Initialize();
	
	/** Shutdown the HTTP client */
	static void Shutdown();
	
	/** Get the singleton instance */
	static FHttpClient& Get();

	/** Send a chat request to the API */
	void SendChatRequest(
		const TArray<TSharedPtr<FJsonObject>>& Messages,
		const FString& Provider = TEXT("openai"),
		const TSharedPtr<FJsonObject>& Context = nullptr,
		FOnStreamingChunk OnChunk = FOnStreamingChunk(),
		FOnHttpError OnError = FOnHttpError()
	);
	
	/** Send a context export request */
	void SendContextRequest(
		const FString& ContextType,
		const TSharedPtr<FJsonObject>& ContextData,
		FOnHttpResponse OnResponse,
		FOnHttpError OnError
	);
	
	/** Test API connectivity */
	void TestConnection(FOnHttpResponse OnResponse, FOnHttpError OnError);

private:
	FHttpClient() = default;
	~FHttpClient() = default;
	
	/** Get the base API URL (localhost or SaaS) */
	FString GetApiBaseUrl() const;
	
	/** Get authentication headers */
	TMap<FString, FString> GetAuthHeaders() const;
	
	/** Handle streaming response */
	void HandleStreamingResponse(FHttpRequestPtr Request, FHttpResponsePtr Response, bool bWasSuccessful, FOnStreamingChunk OnChunk, FOnHttpError OnError);
	
	/** Parse Server-Sent Events data */
	TArray<FString> ParseSSEData(const FString& ResponseData) const;
	
	/** Create HTTP request with common headers */
	FHttpRequestPtr CreateRequest(const FString& Verb, const FString& Endpoint) const;

private:
	static TUniquePtr<FHttpClient> Instance;
	
	/** Cached API base URL */
	mutable FString CachedApiUrl;
	
	/** HTTP module reference */
	FHttpModule* HttpModule;
};