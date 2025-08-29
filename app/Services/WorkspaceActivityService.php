<?php

namespace App\Services;

use App\Events\WorkspaceActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Game;
use App\Models\ChatConversation;
use App\Models\DemoTemplate;
use Illuminate\Support\Facades\Log;

class WorkspaceActivityService
{
    /**
     * Broadcast game creation activity
     */
    public function broadcastGameCreated(User $user, Workspace $workspace, Game $game): void
    {
        try {
            broadcast(new WorkspaceActivity(
                $user,
                $workspace,
                'game_created',
                [
                    'game_id' => $game->id,
                    'game_title' => $game->title,
                    'game_engine' => $workspace->engine_type,
                ]
            ));

            Log::info('Game creation activity broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'game_id' => $game->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast game creation activity', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast game update activity
     */
    public function broadcastGameUpdated(User $user, Workspace $workspace, Game $game, array $changes = []): void
    {
        try {
            broadcast(new WorkspaceActivity(
                $user,
                $workspace,
                'game_updated',
                [
                    'game_id' => $game->id,
                    'game_title' => $game->title,
                    'changes' => $changes,
                ]
            ));

            Log::info('Game update activity broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'game_id' => $game->id,
                'changes' => $changes,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast game update activity', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast chat conversation started activity
     */
    public function broadcastChatStarted(User $user, Workspace $workspace, ChatConversation $conversation): void
    {
        try {
            broadcast(new WorkspaceActivity(
                $user,
                $workspace,
                'chat_started',
                [
                    'conversation_id' => $conversation->id,
                    'conversation_title' => $conversation->title,
                ]
            ));

            Log::info('Chat started activity broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast chat started activity', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast template usage activity
     */
    public function broadcastTemplateUsed(User $user, Workspace $workspace, DemoTemplate $template): void
    {
        try {
            broadcast(new WorkspaceActivity(
                $user,
                $workspace,
                'template_used',
                [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'template_engine' => $template->engine_type,
                ]
            ));

            Log::info('Template usage activity broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'template_id' => $template->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast template usage activity', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast multiplayer session activity
     */
    public function broadcastMultiplayerActivity(
        User $user, 
        Workspace $workspace, 
        string $activity, 
        array $metadata = []
    ): void {
        try {
            broadcast(new WorkspaceActivity(
                $user,
                $workspace,
                "multiplayer_{$activity}",
                $metadata
            ));

            Log::info('Multiplayer activity broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'activity' => $activity,
                'metadata' => $metadata,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast multiplayer activity', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'activity' => $activity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast workspace collaboration activity
     */
    public function broadcastCollaborationActivity(
        User $user, 
        Workspace $workspace, 
        string $activity, 
        array $metadata = []
    ): void {
        try {
            broadcast(new WorkspaceActivity(
                $user,
                $workspace,
                "collaboration_{$activity}",
                $metadata
            ));

            Log::debug('Collaboration activity broadcasted', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'activity' => $activity,
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to broadcast collaboration activity', [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'activity' => $activity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get recent workspace activities
     */
    public function getRecentActivities(Workspace $workspace, int $limit = 20): array
    {
        // This would typically come from a dedicated activities table
        // For now, we'll return a placeholder structure
        return [
            'activities' => [],
            'total' => 0,
            'workspace_id' => $workspace->id,
        ];
    }
}