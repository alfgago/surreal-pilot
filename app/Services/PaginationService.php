<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Game;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class PaginationService
{
    // Default pagination sizes
    const DEFAULT_CONVERSATIONS_PER_PAGE = 20;
    const DEFAULT_MESSAGES_PER_PAGE = 50;
    const DEFAULT_GAMES_PER_PAGE = 12;
    const DEFAULT_RECENT_ITEMS = 10;

    /**
     * Paginate workspace conversations.
     */
    public function paginateWorkspaceConversations(
        Workspace $workspace, 
        int $page = 1, 
        int $perPage = self::DEFAULT_CONVERSATIONS_PER_PAGE
    ): LengthAwarePaginator {
        try {
            $query = $workspace->conversations()
                ->select(['id', 'workspace_id', 'title', 'description', 'created_at', 'updated_at'])
                ->orderBy('updated_at', 'desc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            
            $conversations = $query->offset($offset)->limit($perPage)->get();

            return new LengthAwarePaginator(
                $conversations,
                $total,
                $perPage,
                $page,
                [
                    'path' => Request::url(),
                    'pageName' => 'page',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to paginate workspace conversations', [
                'workspace_id' => $workspace->id,
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage(),
            ]);

            // Return empty paginator on error
            return new LengthAwarePaginator(
                new Collection(),
                0,
                $perPage,
                $page,
                ['path' => Request::url(), 'pageName' => 'page']
            );
        }
    }

    /**
     * Paginate workspace games.
     */
    public function paginateWorkspaceGames(
        Workspace $workspace, 
        int $page = 1, 
        int $perPage = self::DEFAULT_GAMES_PER_PAGE
    ): LengthAwarePaginator {
        try {
            $query = $workspace->games()
                ->select(['id', 'workspace_id', 'conversation_id', 'title', 'description', 
                         'preview_url', 'published_url', 'thumbnail_url', 'created_at', 'updated_at'])
                ->orderBy('updated_at', 'desc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            
            $games = $query->offset($offset)->limit($perPage)->get();

            return new LengthAwarePaginator(
                $games,
                $total,
                $perPage,
                $page,
                [
                    'path' => Request::url(),
                    'pageName' => 'page',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to paginate workspace games', [
                'workspace_id' => $workspace->id,
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage(),
            ]);

            // Return empty paginator on error
            return new LengthAwarePaginator(
                new Collection(),
                0,
                $perPage,
                $page,
                ['path' => Request::url(), 'pageName' => 'page']
            );
        }
    }

    /**
     * Paginate conversation messages.
     */
    public function paginateConversationMessages(
        ChatConversation $conversation, 
        int $page = 1, 
        int $perPage = self::DEFAULT_MESSAGES_PER_PAGE
    ): LengthAwarePaginator {
        try {
            $query = $conversation->messages()
                ->select(['id', 'conversation_id', 'role', 'content', 'metadata', 'created_at'])
                ->orderBy('created_at', 'asc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            
            $messages = $query->offset($offset)->limit($perPage)->get();

            return new LengthAwarePaginator(
                $messages,
                $total,
                $perPage,
                $page,
                [
                    'path' => Request::url(),
                    'pageName' => 'page',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to paginate conversation messages', [
                'conversation_id' => $conversation->id,
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage(),
            ]);

            // Return empty paginator on error
            return new LengthAwarePaginator(
                new Collection(),
                0,
                $perPage,
                $page,
                ['path' => Request::url(), 'pageName' => 'page']
            );
        }
    }

    /**
     * Paginate recent conversations across all workspaces for a company.
     */
    public function paginateRecentConversations(
        int $companyId, 
        int $page = 1, 
        int $perPage = self::DEFAULT_CONVERSATIONS_PER_PAGE
    ): LengthAwarePaginator {
        try {
            $query = ChatConversation::select([
                'chat_conversations.id',
                'chat_conversations.workspace_id',
                'chat_conversations.title',
                'chat_conversations.description',
                'chat_conversations.created_at',
                'chat_conversations.updated_at'
            ])
            ->whereHas('workspace', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->with(['workspace:id,company_id,name,engine_type'])
            ->orderBy('updated_at', 'desc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            
            $conversations = $query->offset($offset)->limit($perPage)->get();

            return new LengthAwarePaginator(
                $conversations,
                $total,
                $perPage,
                $page,
                [
                    'path' => Request::url(),
                    'pageName' => 'page',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to paginate recent conversations', [
                'company_id' => $companyId,
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage(),
            ]);

            // Return empty paginator on error
            return new LengthAwarePaginator(
                new Collection(),
                0,
                $perPage,
                $page,
                ['path' => Request::url(), 'pageName' => 'page']
            );
        }
    }

    /**
     * Paginate recent games across all workspaces for a company.
     */
    public function paginateRecentGames(
        int $companyId, 
        int $page = 1, 
        int $perPage = self::DEFAULT_GAMES_PER_PAGE
    ): LengthAwarePaginator {
        try {
            $query = Game::select([
                'games.id',
                'games.workspace_id',
                'games.conversation_id',
                'games.title',
                'games.description',
                'games.preview_url',
                'games.published_url',
                'games.thumbnail_url',
                'games.created_at',
                'games.updated_at'
            ])
            ->whereHas('workspace', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->with([
                'workspace:id,company_id,name,engine_type',
                'conversation:id,workspace_id,title'
            ])
            ->orderBy('updated_at', 'desc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;
            
            $games = $query->offset($offset)->limit($perPage)->get();

            return new LengthAwarePaginator(
                $games,
                $total,
                $perPage,
                $page,
                [
                    'path' => Request::url(),
                    'pageName' => 'page',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to paginate recent games', [
                'company_id' => $companyId,
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage(),
            ]);

            // Return empty paginator on error
            return new LengthAwarePaginator(
                new Collection(),
                0,
                $perPage,
                $page,
                ['path' => Request::url(), 'pageName' => 'page']
            );
        }
    }

    /**
     * Create a paginated response array.
     */
    public function createPaginatedResponse(
        Collection $items,
        int $total,
        int $page,
        int $perPage,
        ?string $path = null
    ): array {
        $lastPage = ceil($total / $perPage);
        $from = ($page - 1) * $perPage + 1;
        $to = min($page * $perPage, $total);

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more_pages' => $page < $lastPage,
                'from' => $total > 0 ? $from : 0,
                'to' => $total > 0 ? $to : 0,
                'path' => $path ?? Request::url(),
            ]
        ];
    }

    /**
     * Get pagination metadata from a LengthAwarePaginator.
     */
    public function getPaginationMetadata(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'path' => $paginator->path(),
        ];
    }

    /**
     * Calculate optimal page size based on content type and user preferences.
     */
    public function getOptimalPageSize(string $contentType, ?int $userPreference = null): int
    {
        if ($userPreference && $userPreference > 0 && $userPreference <= 100) {
            return $userPreference;
        }

        return match ($contentType) {
            'conversations' => self::DEFAULT_CONVERSATIONS_PER_PAGE,
            'messages' => self::DEFAULT_MESSAGES_PER_PAGE,
            'games' => self::DEFAULT_GAMES_PER_PAGE,
            'recent' => self::DEFAULT_RECENT_ITEMS,
            default => 20,
        };
    }

    /**
     * Validate pagination parameters.
     */
    public function validatePaginationParams(int $page, int $perPage): array
    {
        $validatedPage = max(1, $page);
        $validatedPerPage = max(1, min(100, $perPage)); // Limit to 100 items per page

        return [$validatedPage, $validatedPerPage];
    }

    /**
     * Create cursor-based pagination for large datasets.
     */
    public function createCursorPagination(
        $query,
        string $cursorColumn = 'id',
        ?string $cursor = null,
        int $limit = 20,
        string $direction = 'desc'
    ): array {
        try {
            $operator = $direction === 'desc' ? '<' : '>';
            
            if ($cursor) {
                $query->where($cursorColumn, $operator, $cursor);
            }
            
            $items = $query->orderBy($cursorColumn, $direction)
                          ->limit($limit + 1) // Get one extra to check if there are more
                          ->get();
            
            $hasMore = $items->count() > $limit;
            
            if ($hasMore) {
                $items = $items->take($limit);
            }
            
            $nextCursor = $hasMore && $items->isNotEmpty() 
                ? $items->last()->{$cursorColumn}
                : null;

            return [
                'data' => $items,
                'pagination' => [
                    'has_more' => $hasMore,
                    'next_cursor' => $nextCursor,
                    'limit' => $limit,
                ]
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create cursor pagination', [
                'cursor_column' => $cursorColumn,
                'cursor' => $cursor,
                'limit' => $limit,
                'direction' => $direction,
                'error' => $e->getMessage(),
            ]);

            return [
                'data' => new Collection(),
                'pagination' => [
                    'has_more' => false,
                    'next_cursor' => null,
                    'limit' => $limit,
                ]
            ];
        }
    }
}