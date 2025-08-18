#include "SurrealPilotErrorHandler.h"
#include "Framework/Notifications/NotificationManager.h"
#include "Widgets/Notifications/SNotificationList.h"
#include "EditorStyleSet.h"
#include "Engine/Engine.h"

void FSurrealPilotErrorHandler::HandleHttpError(int32 StatusCode, const FString& Response)
{
    FString ErrorMessage;
    FString NotificationType = TEXT("Error");
    
    switch (StatusCode)
    {
        case 401:
            ErrorMessage = TEXT("Authentication failed. Please check your API key.");
            break;
        case 402:
            ErrorMessage = TEXT("Insufficient credits. Please purchase more credits to continue.");
            NotificationType = TEXT("Warning");
            break;
        case 403:
            ErrorMessage = TEXT("Access denied. You don't have permission to use this feature.");
            break;
        case 429:
            ErrorMessage = TEXT("Rate limit exceeded. Please wait before making another request.");
            NotificationType = TEXT("Warning");
            break;
        case 500:
            ErrorMessage = TEXT("Server error. Please try again later.");
            break;
        case 503:
            ErrorMessage = TEXT("Service unavailable. The AI provider may be temporarily down.");
            NotificationType = TEXT("Warning");
            break;
        default:
            ErrorMessage = FString::Printf(TEXT("HTTP Error %d: %s"), StatusCode, *Response);
            break;
    }
    
    LogError(ErrorMessage, TEXT("Error"));
    ShowUserNotification(ErrorMessage, 10.0f, NotificationType);
}

void FSurrealPilotErrorHandler::HandlePatchError(const FString& PatchJson, const FString& Error)
{
    FString ErrorMessage = FString::Printf(TEXT("Failed to apply AI patch: %s"), *Error);
    
    LogError(ErrorMessage, TEXT("Error"));
    LogError(FString::Printf(TEXT("Patch JSON: %s"), *PatchJson), TEXT("Verbose"));
    
    ShowUserNotification(ErrorMessage, 15.0f, TEXT("Error"));
    
    // Also show a more detailed notification with suggestions
    FString DetailedMessage = FString::Printf(
        TEXT("Patch application failed: %s\n\nSuggestions:\n• Check if the target Blueprint is open\n• Verify the Blueprint hasn't been modified\n• Try exporting fresh context from UE"),
        *Error
    );
    
    ShowUserNotification(DetailedMessage, 20.0f, TEXT("Warning"));
}

void FSurrealPilotErrorHandler::HandleContextExportError(const FString& ContextType, const FString& Error)
{
    FString ErrorMessage = FString::Printf(TEXT("Failed to export %s context: %s"), *ContextType, *Error);
    
    LogError(ErrorMessage, TEXT("Warning"));
    ShowUserNotification(ErrorMessage, 8.0f, TEXT("Warning"));
}

void FSurrealPilotErrorHandler::ShowUserNotification(const FString& Message, float Duration, const FString& Type)
{
    if (!GEditor)
    {
        return;
    }
    
    FNotificationInfo Info(FText::FromString(Message));
    Info.bFireAndForget = Duration > 0;
    Info.FadeOutDuration = 1.0f;
    Info.ExpireDuration = Duration;
    
    // Set icon and color based on type
    if (Type == TEXT("Error"))
    {
        Info.Image = FEditorStyle::GetBrush(TEXT("MessageLog.Error"));
    }
    else if (Type == TEXT("Warning"))
    {
        Info.Image = FEditorStyle::GetBrush(TEXT("MessageLog.Warning"));
    }
    else
    {
        Info.Image = FEditorStyle::GetBrush(TEXT("MessageLog.Note"));
    }
    
    // Add action buttons for certain error types
    if (Type == TEXT("Error") && Message.Contains(TEXT("credits")))
    {
        Info.ButtonDetails.Add(FNotificationButtonInfo(
            FText::FromString(TEXT("Purchase Credits")),
            FText::FromString(TEXT("Open billing page to purchase more credits")),
            FSimpleDelegate::CreateLambda([]()
            {
                // TODO: Open billing URL in browser
                FPlatformProcess::LaunchURL(TEXT("https://surrealpilot.com/billing"), nullptr, nullptr);
            })
        ));
    }
    
    FSlateNotificationManager::Get().AddNotification(Info);
}

void FSurrealPilotErrorHandler::LogError(const FString& Error, const FString& Severity)
{
    if (Severity == TEXT("Error"))
    {
        UE_LOG(LogTemp, Error, TEXT("SurrealPilot: %s"), *Error);
    }
    else if (Severity == TEXT("Warning"))
    {
        UE_LOG(LogTemp, Warning, TEXT("SurrealPilot: %s"), *Error);
    }
    else if (Severity == TEXT("Verbose"))
    {
        UE_LOG(LogTemp, Verbose, TEXT("SurrealPilot: %s"), *Error);
    }
    else
    {
        UE_LOG(LogTemp, Log, TEXT("SurrealPilot: %s"), *Error);
    }
}

void FSurrealPilotErrorHandler::HandleInsufficientCreditsError(int32 CreditsAvailable, int32 CreditsRequired)
{
    FString ErrorMessage = FString::Printf(
        TEXT("Insufficient credits: %d available, %d required. Please purchase more credits to continue."),
        CreditsAvailable,
        CreditsRequired
    );
    
    LogError(ErrorMessage, TEXT("Warning"));
    ShowUserNotification(ErrorMessage, 15.0f, TEXT("Warning"));
}

void FSurrealPilotErrorHandler::HandleProviderUnavailableError(const FString& Provider, const TArray<FString>& FallbackProviders)
{
    FString ErrorMessage = FString::Printf(TEXT("AI provider '%s' is currently unavailable."), *Provider);
    
    if (FallbackProviders.Num() > 0)
    {
        FString FallbackList = FString::Join(FallbackProviders, TEXT(", "));
        ErrorMessage += FString::Printf(TEXT(" Available alternatives: %s"), *FallbackList);
    }
    
    LogError(ErrorMessage, TEXT("Warning"));
    ShowUserNotification(ErrorMessage, 10.0f, TEXT("Warning"));
}

const FSlateBrush* FSurrealPilotErrorHandler::GetNotificationIcon(const FString& Type)
{
    if (Type == TEXT("Error"))
    {
        return FEditorStyle::GetBrush(TEXT("MessageLog.Error"));
    }
    else if (Type == TEXT("Warning"))
    {
        return FEditorStyle::GetBrush(TEXT("MessageLog.Warning"));
    }
    else
    {
        return FEditorStyle::GetBrush(TEXT("MessageLog.Note"));
    }
}

FLinearColor FSurrealPilotErrorHandler::GetNotificationColor(const FString& Type)
{
    if (Type == TEXT("Error"))
    {
        return FLinearColor::Red;
    }
    else if (Type == TEXT("Warning"))
    {
        return FLinearColor::Yellow;
    }
    else
    {
        return FLinearColor::White;
    }
}