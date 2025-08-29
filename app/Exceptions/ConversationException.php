<?php

namespace App\Exceptions;

class ConversationException extends ApiException
{
    public static function conversationNotFound(int $conversationId): self
    {
        return new self(
            'The specified conversation was not found',
            'CONVERSATION_NOT_FOUND',
            'The conversation you\'re trying to access doesn\'t exist or you don\'t have permission to access it.',
            [
                'conversation_id' => $conversationId,
                'actions' => [
                    'view_conversations' => '/api/conversations',
                    'create_conversation' => 'Start a new conversation',
                ],
            ],
            404
        );
    }

    public static function conversationAccessDenied(int $conversationId): self
    {
        return new self(
            'You do not have permission to access this conversation',
            'CONVERSATION_ACCESS_DENIED',
            'This conversation belongs to a different workspace or company.',
            [
                'conversation_id' => $conversationId,
                'actions' => [
                    'view_your_conversations' => '/api/conversations',
                    'contact_admin' => 'Contact your company administrator',
                ],
            ],
            403
        );
    }

    public static function conversationCreationFailed(string $reason): self
    {
        return new self(
            "Failed to create conversation: {$reason}",
            'CONVERSATION_CREATION_FAILED',
            'There was a problem creating your conversation. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try creating the conversation again',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
            500
        );
    }

    public static function messageAddFailed(string $reason): self
    {
        return new self(
            "Failed to add message to conversation: {$reason}",
            'MESSAGE_ADD_FAILED',
            'There was a problem adding your message. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try sending the message again',
                    'refresh_conversation' => 'Refresh the conversation',
                ],
            ],
            500
        );
    }

    public static function conversationUpdateFailed(string $reason): self
    {
        return new self(
            "Failed to update conversation: {$reason}",
            'CONVERSATION_UPDATE_FAILED',
            'There was a problem updating the conversation. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try updating again',
                    'refresh_conversation' => 'Refresh the conversation',
                ],
            ],
            500
        );
    }

    public static function conversationDeleteFailed(string $reason): self
    {
        return new self(
            "Failed to delete conversation: {$reason}",
            'CONVERSATION_DELETE_FAILED',
            'There was a problem deleting the conversation. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try deleting again',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
            500
        );
    }

    public static function tooManyMessages(int $limit): self
    {
        return new self(
            "Conversation has reached the maximum number of messages ({$limit})",
            'TOO_MANY_MESSAGES',
            'This conversation has reached the maximum number of messages. Please start a new conversation.',
            [
                'message_limit' => $limit,
                'actions' => [
                    'create_new_conversation' => 'Start a new conversation',
                    'archive_conversation' => 'Archive this conversation',
                ],
            ],
            422
        );
    }
}