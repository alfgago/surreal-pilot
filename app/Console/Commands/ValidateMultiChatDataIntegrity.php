<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Game;
use App\Models\Workspace;
use Illuminate\Console\Command;

class ValidateMultiChatDataIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'validate:multichat-data-integrity';

    /**
     * The console command description.
     */
    protected $description = 'Validate data integrity for multi-chat functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Validating multi-chat data integrity...');

        $issues = [];

        // Check 1: All workspaces should have at least one conversation
        $workspacesWithoutConversations = Workspace::doesntHave('conversations')->get();
        if ($workspacesWithoutConversations->isNotEmpty()) {
            $issues[] = "Found {$workspacesWithoutConversations->count()} workspaces without conversations:";
            foreach ($workspacesWithoutConversations as $workspace) {
                $issues[] = "  - Workspace ID {$workspace->id}: {$workspace->name}";
            }
        }

        // Check 2: All conversations should belong to valid workspaces
        $orphanedConversations = ChatConversation::whereDoesntHave('workspace')->get();
        if ($orphanedConversations->isNotEmpty()) {
            $issues[] = "Found {$orphanedConversations->count()} orphaned conversations:";
            foreach ($orphanedConversations as $conversation) {
                $issues[] = "  - Conversation ID {$conversation->id}: {$conversation->title}";
            }
        }

        // Check 3: All chat messages should belong to valid conversations
        $orphanedMessages = ChatMessage::whereDoesntHave('conversation')->get();
        if ($orphanedMessages->isNotEmpty()) {
            $issues[] = "Found {$orphanedMessages->count()} orphaned chat messages:";
            foreach ($orphanedMessages as $message) {
                $issues[] = "  - Message ID {$message->id} (conversation_id: {$message->conversation_id})";
            }
        }

        // Check 4: All games with conversation_id should reference valid conversations
        $gamesWithInvalidConversations = Game::whereNotNull('conversation_id')
            ->whereDoesntHave('conversation')
            ->get();
        if ($gamesWithInvalidConversations->isNotEmpty()) {
            $issues[] = "Found {$gamesWithInvalidConversations->count()} games with invalid conversation references:";
            foreach ($gamesWithInvalidConversations as $game) {
                $issues[] = "  - Game ID {$game->id}: {$game->title} (conversation_id: {$game->conversation_id})";
            }
        }

        // Check 5: Validate conversation message counts
        $conversationsWithIncorrectCounts = [];
        $conversations = ChatConversation::all();
        foreach ($conversations as $conversation) {
            $actualCount = $conversation->messages()->count();
            $reportedCount = $conversation->getMessageCount();
            if ($actualCount !== $reportedCount) {
                $conversationsWithIncorrectCounts[] = "  - Conversation ID {$conversation->id}: actual={$actualCount}, reported={$reportedCount}";
            }
        }
        if (!empty($conversationsWithIncorrectCounts)) {
            $issues[] = "Found conversations with incorrect message counts:";
            $issues = array_merge($issues, $conversationsWithIncorrectCounts);
        }

        // Report results
        if (empty($issues)) {
            $this->info('✓ All data integrity checks passed!');
            
            // Show summary statistics
            $this->info('');
            $this->info('Summary Statistics:');
            $this->info('  - Total workspaces: ' . Workspace::count());
            $this->info('  - Total conversations: ' . ChatConversation::count());
            $this->info('  - Total chat messages: ' . ChatMessage::count());
            $this->info('  - Total games: ' . Game::count());
            $this->info('  - Games with conversations: ' . Game::whereNotNull('conversation_id')->count());
            
            return 0;
        } else {
            $this->error('❌ Data integrity issues found:');
            $this->error('');
            foreach ($issues as $issue) {
                $this->error($issue);
            }
            $this->error('');
            $this->error('Please fix these issues before proceeding.');
            return 1;
        }
    }
}