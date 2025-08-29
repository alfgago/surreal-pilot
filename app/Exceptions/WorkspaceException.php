<?php

namespace App\Exceptions;

class WorkspaceException extends ApiException
{
    public static function workspaceNotFound(int $workspaceId): self
    {
        return new self(
            'The specified workspace was not found',
            'WORKSPACE_NOT_FOUND',
            'The workspace you\'re trying to access doesn\'t exist or you don\'t have permission to access it.',
            [
                'workspace_id' => $workspaceId,
                'actions' => [
                    'view_workspaces' => '/api/workspaces',
                    'create_workspace' => '/api/prototype',
                ],
            ],
            404
        );
    }

    public static function workspaceEngineMismatch(string $workspaceEngine, string $userEngine, string $workspaceName): self
    {
        return new self(
            "Workspace engine type ({$workspaceEngine}) doesn't match your selected engine ({$userEngine})",
            'WORKSPACE_ENGINE_MISMATCH',
            "This workspace is configured for {$workspaceEngine}, but you have {$userEngine} selected. Please switch your engine preference or select a different workspace.",
            [
                'workspace_engine' => $workspaceEngine,
                'user_engine' => $userEngine,
                'workspace_name' => $workspaceName,
                'actions' => [
                    'change_engine_preference' => '/api/user/engine-preference',
                    'select_different_workspace' => '/api/workspaces',
                ],
            ],
            409
        );
    }

    public static function workspaceNameTaken(string $name): self
    {
        return new self(
            'A workspace with this name already exists in your company',
            'WORKSPACE_NAME_TAKEN',
            'Please choose a different name for your workspace.',
            [
                'attempted_name' => $name,
                'actions' => [
                    'try_different_name' => 'Choose a different workspace name',
                    'view_existing_workspaces' => '/api/workspaces',
                ],
            ],
            422
        );
    }

    public static function workspaceCreationFailed(string $reason): self
    {
        return new self(
            "Failed to create workspace: {$reason}",
            'WORKSPACE_CREATION_FAILED',
            'There was a problem creating your workspace. Please try again.',
            [
                'reason' => $reason,
                'actions' => [
                    'retry' => 'Try creating the workspace again',
                    'contact_support' => 'Contact support if the problem persists',
                ],
            ],
            500
        );
    }

    public static function workspaceAccessDenied(int $workspaceId): self
    {
        return new self(
            'You do not have permission to access this workspace',
            'WORKSPACE_ACCESS_DENIED',
            'This workspace belongs to a different company or you don\'t have the required permissions.',
            [
                'workspace_id' => $workspaceId,
                'actions' => [
                    'view_your_workspaces' => '/api/workspaces',
                    'contact_admin' => 'Contact your company administrator',
                ],
            ],
            403
        );
    }
}