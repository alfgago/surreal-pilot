import { SSEService } from './sseService';
import { getWebSocketService, WebSocketService } from './websocketService';
import axios from 'axios';

export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
    metadata?: any;
    isStreaming?: boolean;
}

export interface TypingUser {
    id: number;
    name: string;
}

export interface ConnectionStatus {
    user: {
        id: number;
        name: string;
    };
    status: 'connected' | 'disconnected' | 'reconnecting';
    timestamp: string;
    metadata?: any;
}

export interface StreamingChatOptions {
    onChunk?: (content: string, tokens: number) => void;
    onComplete?: (messageId: string, totalTokens: number) => void;
    onError?: (error: string) => void;
    onTypingStart?: () => void;
    onTypingStop?: () => void;
    onUserMessage?: (messageId: string, content: string) => void;
}

export class RealtimeChatService {
    private sseService: SSEService | null = null;
    private wsService: WebSocketService;
    private currentWorkspaceId: number | null = null;
    private currentConversationId: number | null = null;
    private typingTimer: NodeJS.Timeout | null = null;

    constructor() {
        this.wsService = getWebSocketService();
        this.setupWebSocketListeners();
    }

    /**
     * Initialize real-time chat for a workspace and conversation
     */
    initialize(workspaceId: number, conversationId?: number): void {
        this.currentWorkspaceId = workspaceId;
        this.currentConversationId = conversationId || null;

        // WebSocket connection disabled for now - no WebSocket server configured
        // TODO: Enable when WebSocket server is set up
        // if (!this.wsService.getConnectionStatus()) {
        //     this.wsService.connect();
        // }

        // Subscribe to workspace channel for connection status
        // this.wsService.subscribe(`workspace.${workspaceId}`);

        // Subscribe to conversation channel if available
        // if (conversationId) {
        //     this.wsService.subscribe(`conversation.${conversationId}`);
        // }

        // Update connection status
        this.updateConnectionStatus('connected');
    }

    /**
     * Start streaming chat with Server-Sent Events
     */
    startStreamingChat(
        message: string,
        conversationId: number,
        workspaceId: number,
        options: StreamingChatOptions = {}
    ): void {
        if (this.sseService) {
            this.sseService.disconnect();
        }

        // Create SSE connection for streaming
        const sseUrl = `/api/chat/stream?conversation_id=${conversationId}&workspace_id=${workspaceId}&message=${encodeURIComponent(message)}`;
        
        this.sseService = new SSEService(sseUrl, {
            onOpen: () => {
                console.log('Streaming chat started');
            },
            onError: (error) => {
                console.error('Streaming chat error:', error);
                options.onError?.('Connection error occurred');
            },
            onClose: () => {
                console.log('Streaming chat ended');
            },
        });

        // Set up event listeners
        this.sseService.on('connected', (data) => {
            console.log('Streaming chat connected:', data);
        });

        this.sseService.on('user_message', (data) => {
            options.onUserMessage?.(data.id, data.content);
        });

        this.sseService.on('typing_start', () => {
            options.onTypingStart?.();
        });

        this.sseService.on('typing_stop', () => {
            options.onTypingStop?.();
        });

        this.sseService.on('chunk', (data) => {
            options.onChunk?.(data.content, data.tokens);
        });

        this.sseService.on('completed', (data) => {
            options.onComplete?.(data.message_id, data.total_tokens);
            this.sseService?.disconnect();
        });

        this.sseService.on('error', (data) => {
            options.onError?.(data.message);
            this.sseService?.disconnect();
        });

        // Start the connection
        this.sseService.connect();
    }

    /**
     * Send typing indicator
     */
    async sendTypingIndicator(conversationId: number, isTyping: boolean): Promise<void> {
        try {
            await axios.post(`/api/conversations/${conversationId}/typing`, {
                is_typing: isTyping,
            });
        } catch (error) {
            console.error('Failed to send typing indicator:', error);
        }
    }

    /**
     * Start typing (with auto-stop after delay)
     */
    startTyping(conversationId: number): void {
        // Clear existing timer
        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
        }

        // Send typing start
        this.sendTypingIndicator(conversationId, true);

        // Auto-stop typing after 3 seconds
        this.typingTimer = setTimeout(() => {
            this.stopTyping(conversationId);
        }, 3000);
    }

    /**
     * Stop typing
     */
    stopTyping(conversationId: number): void {
        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
            this.typingTimer = null;
        }

        this.sendTypingIndicator(conversationId, false);
    }

    /**
     * Update connection status
     */
    async updateConnectionStatus(
        status: 'connected' | 'disconnected' | 'reconnecting',
        metadata?: any
    ): Promise<void> {
        if (!this.currentWorkspaceId) return;

        try {
            await axios.post(`/api/workspaces/${this.currentWorkspaceId}/connection`, {
                status,
                metadata,
            });
        } catch (error) {
            console.error('Failed to update connection status:', error);
        }
    }

    /**
     * Get typing users for current conversation
     */
    async getTypingUsers(conversationId: number): Promise<TypingUser[]> {
        try {
            const response = await axios.get(`/api/conversations/${conversationId}/typing`);
            return response.data.typing_users || [];
        } catch (error) {
            console.error('Failed to get typing users:', error);
            return [];
        }
    }

    /**
     * Get connection statuses for current workspace
     */
    async getConnectionStatuses(workspaceId: number): Promise<ConnectionStatus[]> {
        try {
            const response = await axios.get(`/api/workspaces/${workspaceId}/connections`);
            return response.data.connections || [];
        } catch (error) {
            console.error('Failed to get connection statuses:', error);
            return [];
        }
    }

    /**
     * Set up WebSocket event listeners
     */
    private setupWebSocketListeners(): void {
        // Listen for new messages
        this.wsService.on('message.received', (data) => {
            console.log('New message received:', data);
            // Emit custom event for components to listen to
            window.dispatchEvent(new CustomEvent('chat:message-received', { detail: data }));
        });

        // Listen for typing events
        this.wsService.on('user.typing.start', (data) => {
            console.log('User started typing:', data);
            window.dispatchEvent(new CustomEvent('chat:typing-start', { detail: data }));
        });

        this.wsService.on('user.typing.stop', (data) => {
            console.log('User stopped typing:', data);
            window.dispatchEvent(new CustomEvent('chat:typing-stop', { detail: data }));
        });

        // Listen for connection status changes
        this.wsService.on('connection.status', (data) => {
            console.log('Connection status changed:', data);
            window.dispatchEvent(new CustomEvent('chat:connection-status', { detail: data }));
        });
    }

    /**
     * Subscribe to conversation updates
     */
    subscribeToConversation(conversationId: number): void {
        this.currentConversationId = conversationId;
        this.wsService.subscribe(`conversation.${conversationId}`);
    }

    /**
     * Unsubscribe from conversation updates
     */
    unsubscribeFromConversation(conversationId: number): void {
        this.wsService.unsubscribe(`conversation.${conversationId}`);
        if (this.currentConversationId === conversationId) {
            this.currentConversationId = null;
        }
    }

    /**
     * Clean up resources
     */
    cleanup(): void {
        if (this.sseService) {
            this.sseService.disconnect();
            this.sseService = null;
        }

        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
            this.typingTimer = null;
        }

        if (this.currentWorkspaceId) {
            this.updateConnectionStatus('disconnected');
        }

        // Unsubscribe from all channels
        const channels = this.wsService.getSubscribedChannels();
        channels.forEach(channel => {
            this.wsService.unsubscribe(channel);
        });
    }

    /**
     * Get connection status
     */
    getConnectionStatus(): {
        sse: boolean;
        websocket: boolean;
        reconnectAttempts: number;
    } {
        return {
            sse: this.sseService?.getConnectionStatus() || false,
            websocket: false, // WebSocket disabled for now
            reconnectAttempts: 0, // No reconnection attempts when disabled
        };
    }
}

// Create singleton instance
let realtimeChatService: RealtimeChatService | null = null;

export function getRealtimeChatService(): RealtimeChatService {
    if (!realtimeChatService) {
        realtimeChatService = new RealtimeChatService();
    }
    return realtimeChatService;
}