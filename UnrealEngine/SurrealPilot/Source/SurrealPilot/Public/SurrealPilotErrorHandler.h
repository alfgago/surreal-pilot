#pragma once

#include "CoreMinimal.h"
#include "Engine/Engine.h"
#include "Framework/Notifications/NotificationManager.h"
#include "Widgets/Notifications/SNotificationList.h"

/**
 * Error handler for SurrealPilot operations
 */
class SURREALPILOT_API FSurrealPilotErrorHandler
{
public:
    /**
     * Handle HTTP errors from API requests
     * @param StatusCode HTTP status code
     * @param Response Response body
     */
    static void HandleHttpError(int32 StatusCode, const FString& Response);
    
    /**
     * Handle patch application errors
     * @param PatchJson The patch that failed to apply
     * @param Error Error message
     */
    static void HandlePatchError(const FString& PatchJson, const FString& Error);
    
    /**
     * Handle context export errors
     * @param ContextType Type of context being exported
     * @param Error Error message
     */
    static void HandleContextExportError(const FString& ContextType, const FString& Error);
    
    /**
     * Show user notification in the editor
     * @param Message Message to display
     * @param Duration Duration in seconds (0 for persistent)
     * @param Type Notification type (Info, Warning, Error)
     */
    static void ShowUserNotification(const FString& Message, float Duration = 5.0f, const FString& Type = TEXT("Info"));
    
    /**
     * Log error with appropriate severity
     * @param Error Error message
     * @param Severity Log severity level
     */
    static void LogError(const FString& Error, const FString& Severity = TEXT("Error"));
    
    /**
     * Handle insufficient credits error
     * @param CreditsAvailable Number of credits available
     * @param CreditsRequired Number of credits required
     */
    static void HandleInsufficientCreditsError(int32 CreditsAvailable, int32 CreditsRequired);
    
    /**
     * Handle provider unavailable error
     * @param Provider Provider that is unavailable
     * @param FallbackProviders List of available fallback providers
     */
    static void HandleProviderUnavailableError(const FString& Provider, const TArray<FString>& FallbackProviders);

private:
    /**
     * Get notification icon based on type
     * @param Type Notification type
     * @return Icon brush
     */
    static const FSlateBrush* GetNotificationIcon(const FString& Type);
    
    /**
     * Get notification color based on type
     * @param Type Notification type
     * @return Color
     */
    static FLinearColor GetNotificationColor(const FString& Type);
};