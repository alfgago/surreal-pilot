<?php

namespace App\Exceptions;

class GameException extends ApiException
{
    public static function gameNotFound(int $gameId): self
    {
        return new self(
            'The specified game was not found',
            'GAME_NOT_FOUND',
            'The game you\'re trying to access doesn\'t exist or you don\'t have permission to access it.',
            [
                'game_id' => $gameId,
                'actions' => [
                    'view_games' => '/api/games',
                    'create_game' => 'Create a new game',
                ],
            ],
            404
        );
    }

    public static function gameAccessDenied(int $gameId): self
    {
        return new self(
            'You do not have permission to access this game',
            'GAME_ACCESS_DENIED',
            'This game belongs to a different workspace or company.',
            [
                'game_id' => $gameId,
                'actions' => [
                    'view_your_games' => '/api/games',
                    'contact_admin' => 'Contact your company administrator',
                ],
            ],
            403
        );
    }

    public static function gameCreationFailed(string $reason): self
    {
        return new self(
            "Failed to create game: {$reason}",
            'GAME_CREATION_FAILED',
            'There was a problem creating your game. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try creating the game again',
                    'check_workspace' => 'Verify your workspace settings',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
            500
        );
    }

    public static function gameUpdateFailed(string $reason): self
    {
        return new self(
            "Failed to update game: {$reason}",
            'GAME_UPDATE_FAILED',
            'There was a problem updating the game. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try updating again',
                    'refresh_game' => 'Refresh the game details',
                ],
            ],
            500
        );
    }

    public static function gameDeleteFailed(string $reason): self
    {
        return new self(
            "Failed to delete game: {$reason}",
            'GAME_DELETE_FAILED',
            'There was a problem deleting the game. Please try again.',
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

    public static function gameStorageFull(): self
    {
        return new self(
            'Game storage quota exceeded',
            'GAME_STORAGE_FULL',
            'You have reached your game storage limit. Please delete some games or upgrade your plan.',
            [
                'actions' => [
                    'delete_old_games' => 'Delete unused games',
                    'upgrade_plan' => '/dashboard/billing/plans',
                    'view_usage' => '/dashboard/usage',
                ],
            ],
            422
        );
    }

    public static function gamePublishFailed(string $reason): self
    {
        return new self(
            "Failed to publish game: {$reason}",
            'GAME_PUBLISH_FAILED',
            'There was a problem publishing your game. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try publishing again',
                    'check_game_build' => 'Verify your game build',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
            500
        );
    }

    public static function invalidGameFormat(string $format): self
    {
        return new self(
            "Invalid game format: {$format}",
            'INVALID_GAME_FORMAT',
            'The game format is not supported. Please use a supported format.',
            [
                'provided_format' => $format,
                'supported_formats' => ['playcanvas', 'html5', 'webgl'],
                'actions' => [
                    'convert_format' => 'Convert to a supported format',
                    'check_documentation' => 'Review supported formats',
                ],
            ],
            422
        );
    }

    public static function gameStorageFailed(string $reason): self
    {
        return new self(
            "Failed to store game files: {$reason}",
            'GAME_STORAGE_FAILED',
            'There was a problem storing your game files. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try saving the game again',
                    'check_storage' => 'Check available storage space',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
            500
        );
    }
}