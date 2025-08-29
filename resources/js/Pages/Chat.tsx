import { useState, useEffect, useRef } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout';
import { PageProps } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

import ChatSettingsModal from '@/components/chat/ChatSettingsModal';
import EngineContext from '@/components/engine/EngineContext';
import UnrealConnectionModal from '@/components/engine/UnrealConnectionModal';
import PlayCanvasPreview from '@/components/engine/PlayCanvasPreview';
import TypingIndicator, { AITypingIndicator } from '@/components/chat/TypingIndicator';
import ConnectionStatus, { SimpleConnectionIndicator } from '@/components/chat/ConnectionStatus';
import StreamingMessage, { StreamingProgress } from '@/components/chat/StreamingMessage';
import { engineService, EngineStatus, UnrealConnectionStatus } from '@/services/engineService';
import { getRealtimeChatService, RealtimeChatService } from '@/services/realtimeChatService';
import { useToast } from '@/components/ui/use-toast';
import { 
    Settings, 
    Bot,
    User,
    Loader2,
    Send,
    MessageSquare,
    Zap,
    Square
} from 'lucide-react';

interface Workspace {
    id: number;
    name: string;
    engine_type: 'playcanvas' | 'unreal';
    status: string;
    preview_url?: string;
    published_url?: string;
    mcp_port?: number;
    mcp_pid?: number;
}

interface Conversation {
    id: number;
    title: string;
    updated_at: string;
}

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
    metadata?: any;
    isStreaming?: boolean;
}

interface ChatSettings {
    provider: string;
    model: string;
    temperature: number;
    max_tokens: number;
    system_prompt?: string;
    api_keys: {
        openai?: string;
        anthropic?: string;
        gemini?: string;
    };
    preferences: {
        auto_save_conversations: boolean;
        show_token_usage: boolean;
        enable_context_memory: boolean;
        stream_responses: boolean;
    };
}

interface ChatPageProps extends PageProps {
    workspace: Workspace;
    conversations: Conversation[];
    providers: Record<string, any>;
    currentConversation?: Conversation;
    messages?: Message[];
    chatSettings?: ChatSettings;
}

export default function Chat() {
    const { 
        workspace, 
        conversations, 
        currentConversation,
        messages: initialMessages = [],
        chatSettings
    } = usePage<ChatPageProps>().props;
    
    const [messages, setMessages] = useState<Message[]>(initialMessages);
    const [currentMessage, setCurrentMessage] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [engineStatus, setEngineStatus] = useState<EngineStatus>({ status: 'disconnected' });
    const [unrealConnectionStatus, setUnrealConnectionStatus] = useState<UnrealConnectionStatus>({ connected: false });
    const [isUnrealModalOpen, setIsUnrealModalOpen] = useState(false);
    const [isSettingsModalOpen, setIsSettingsModalOpen] = useState(false);
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const [isLiveUpdatesEnabled, setIsLiveUpdatesEnabled] = useState(true);
    const [settings, setSettings] = useState<ChatSettings>(chatSettings || {
        provider: 'anthropic',
        model: 'claude-3-5-sonnet-20241022',
        temperature: 0.7,
        max_tokens: 1024,
        api_keys: {},
        preferences: {
            auto_save_conversations: true,
            show_token_usage: false,
            enable_context_memory: true,
            stream_responses: true,
        }
    });
    
    // Real-time chat state
    const [isStreaming, setIsStreaming] = useState(false);
    const [streamingContent, setStreamingContent] = useState('');
    const [streamingTokens, setStreamingTokens] = useState(0);
    const [isAITyping, setIsAITyping] = useState(false);
    const [connectionStatus, setConnectionStatus] = useState({
        sse: false,
        websocket: false,
        reconnectAttempts: 0,
    });
    
    const { toast } = useToast();
    const realtimeChatService = useRef<RealtimeChatService | null>(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    // Initialize real-time chat service
    useEffect(() => {
        if (workspace) {
            realtimeChatService.current = getRealtimeChatService();
            realtimeChatService.current.initialize(workspace.id, currentConversation?.id);
            
            // Update connection status periodically with proper comparison
            const statusInterval = setInterval(() => {
                if (realtimeChatService.current) {
                    const newStatus = realtimeChatService.current.getConnectionStatus();
                    setConnectionStatus(prevStatus => {
                        // Only update if status actually changed
                        if (
                            prevStatus.sse !== newStatus.sse ||
                            prevStatus.websocket !== newStatus.websocket ||
                            prevStatus.reconnectAttempts !== newStatus.reconnectAttempts
                        ) {
                            return newStatus;
                        }
                        return prevStatus;
                    });
                }
            }, 1000);

            return () => {
                clearInterval(statusInterval);
                realtimeChatService.current?.cleanup();
            };
        }
    }, [workspace, currentConversation?.id]);

    // Load initial engine status
    useEffect(() => {
        if (workspace) {
            loadEngineStatus();
            if (workspace.engine_type === 'unreal') {
                loadUnrealConnectionStatus();
            }
        }
    }, [workspace]);

    // Note: We initialize messages with initialMessages in useState above
    // No need for useEffect to sync them since they're set on component mount

    // Auto-scroll to bottom when messages change
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, streamingContent]);

    // Handle typing indicator
    const handleTyping = () => {
        if (currentConversation?.id && realtimeChatService.current) {
            realtimeChatService.current.startTyping(currentConversation.id);
            
            // Clear existing timeout
            if (typingTimeoutRef.current) {
                clearTimeout(typingTimeoutRef.current);
            }
            
            // Stop typing after 3 seconds of inactivity
            typingTimeoutRef.current = setTimeout(() => {
                if (currentConversation?.id && realtimeChatService.current) {
                    realtimeChatService.current.stopTyping(currentConversation.id);
                }
            }, 3000);
        }
    };

    const loadEngineStatus = async () => {
        try {
            const status = await engineService.getEngineStatus(workspace.id);
            setEngineStatus(status);
        } catch (error) {
            console.error('Failed to load engine status:', error);
            setEngineStatus({
                status: 'error',
                message: 'Failed to load engine status'
            });
        }
    };

    const loadUnrealConnectionStatus = async () => {
        try {
            const status = await engineService.getUnrealConnectionStatus(workspace.id);
            setUnrealConnectionStatus(status);
        } catch (error) {
            console.error('Failed to load Unreal connection status:', error);
            setUnrealConnectionStatus({
                connected: false,
                error: 'Failed to load connection status'
            });
        }
    };

    const handleConversationSelect = (conversationId: number) => {
        // Navigate to the conversation
        router.get(`/chat?conversation=${conversationId}`, {}, {
            preserveState: true,
        });
    };

    const handleSendMessage = async () => {
        if (!currentMessage.trim() || isLoading || isStreaming) return;
        if (!currentConversation?.id) {
            toast({
                title: "No Conversation",
                description: "Please select or create a conversation first.",
                variant: "destructive",
            });
            return;
        }

        const messageText = currentMessage.trim();
        setCurrentMessage('');
        setIsLoading(true);

        // Stop typing indicator
        if (realtimeChatService.current) {
            realtimeChatService.current.stopTyping(currentConversation.id);
        }

        try {
            // Add user message immediately
            const userMessage: Message = {
                id: Date.now().toString(),
                role: 'user',
                content: messageText,
                timestamp: new Date().toISOString(),
            };
            setMessages(prev => [...prev, userMessage]);

            // Use streaming if enabled
            if (settings.preferences.stream_responses && realtimeChatService.current) {
                setIsStreaming(true);
                setStreamingContent('');
                setStreamingTokens(0);
                
                realtimeChatService.current.startStreamingChat(
                    messageText,
                    currentConversation.id,
                    workspace.id,
                    {
                        onUserMessage: (messageId, content) => {
                            console.log('User message confirmed:', messageId);
                        },
                        onTypingStart: () => {
                            setIsAITyping(true);
                        },
                        onTypingStop: () => {
                            setIsAITyping(false);
                        },
                        onChunk: (content, tokens) => {
                            setStreamingContent(prev => prev + content);
                            setStreamingTokens(prev => prev + tokens);
                        },
                        onComplete: (messageId, totalTokens) => {
                            // Add complete assistant message
                            const assistantMessage: Message = {
                                id: messageId,
                                role: 'assistant',
                                content: streamingContent,
                                timestamp: new Date().toISOString(),
                                metadata: { tokens_used: totalTokens },
                            };
                            setMessages(prev => [...prev, assistantMessage]);
                            
                            // Reset streaming state
                            setIsStreaming(false);
                            setStreamingContent('');
                            setStreamingTokens(0);
                            setIsAITyping(false);
                            setIsLoading(false);
                            
                            toast({
                                title: "Message Sent",
                                description: `Response completed (${totalTokens} tokens used)`,
                            });
                        },
                        onError: (error) => {
                            setIsStreaming(false);
                            setStreamingContent('');
                            setStreamingTokens(0);
                            setIsAITyping(false);
                            setIsLoading(false);
                            
                            toast({
                                title: "Error",
                                description: error || "Failed to send message. Please try again.",
                                variant: "destructive",
                            });
                        },
                    }
                );
            } else {
                // Fallback to regular API call using axios
                try {
                    const response = await axios.post('/api/chat', {
                        message: messageText,
                        workspace_id: workspace.id,
                        conversation_id: currentConversation.id,
                    });

                    // Handle successful response
                    if (response.data) {
                        // Add the AI response to messages
                        const aiMessage: Message = {
                            id: Date.now() + 1,
                            content: response.data.response || response.data.content,
                            role: 'assistant',
                            timestamp: new Date().toISOString(),
                        };
                        
                        setMessages(prev => [...prev, aiMessage]);
                        
                        toast({
                            title: "Success",
                            description: "Message sent successfully!",
                        });
                    }
                } catch (error) {
                    console.error('Failed to send message:', error);
                    toast({
                        title: "Error",
                        description: "Failed to send message. Please try again.",
                        variant: "destructive",
                    });
                } finally {
                    setIsLoading(false);
                }
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            setIsLoading(false);
            setIsStreaming(false);
            setStreamingContent('');
            setIsAITyping(false);
            
            toast({
                title: "Error",
                description: "Failed to send message. Please try again.",
                variant: "destructive",
            });
        }
    };

    const handleStopStreaming = () => {
        setIsStreaming(false);
        setStreamingContent('');
        setStreamingTokens(0);
        setIsAITyping(false);
        setIsLoading(false);
        
        // In a real implementation, you would cancel the SSE connection here
        if (realtimeChatService.current) {
            // Add method to stop streaming in the service
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        } else {
            // Send typing indicator
            handleTyping();
        }
    };

    const handleSettingsUpdate = (newSettings: ChatSettings) => {
        setSettings(newSettings);
    };

    const handleRefreshEngineStatus = async () => {
        await loadEngineStatus();
        if (workspace.engine_type === 'unreal') {
            await loadUnrealConnectionStatus();
        }
    };

    const handleOpenUnrealConnection = () => {
        setIsUnrealModalOpen(true);
    };

    const handleOpenPlayCanvasPreview = () => {
        if (workspace.preview_url) {
            window.open(workspace.preview_url, '_blank');
        }
    };

    const handleTestUnrealConnection = async (): Promise<void> => {
        try {
            const result = await engineService.testUnrealConnection(workspace.id);
            setUnrealConnectionStatus(result);
        } catch (error) {
            console.error('Failed to test Unreal connection:', error);
            throw error;
        }
    };

    const handleRefreshPlayCanvasPreview = async () => {
        try {
            await engineService.refreshPlayCanvasPreview(workspace.id);
            await loadEngineStatus();
        } catch (error) {
            console.error('Failed to refresh PlayCanvas preview:', error);
            throw error;
        }
    };

    const getEngineDisplayName = () => {
        return workspace.engine_type === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine';
    };

    return (
        <MainLayout title={`Chat - ${workspace.name}`}>
            <Head title={`Chat - ${workspace.name}`} />
            
            <div className="flex h-[calc(100vh-4rem)]">
                {/* Main Chat Area */}
                <div className="flex-1 flex flex-col">
                    {/* Chat Header */}
                    <div className="border-b bg-background p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-xl font-semibold">{workspace.name}</h1>
                                <div className="flex items-center space-x-2 text-sm text-muted-foreground">
                                    <Badge variant="outline">
                                        {getEngineDisplayName()}
                                    </Badge>
                                    <span>•</span>
                                    <SimpleConnectionIndicator 
                                        isConnected={connectionStatus.websocket}
                                        isReconnecting={connectionStatus.reconnectAttempts > 0}
                                    />
                                </div>
                            </div>
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => setIsSettingsModalOpen(true)}
                            >
                                <Settings className="w-4 h-4 mr-2" />
                                Settings
                            </Button>
                        </div>
                    </div>

                    {/* Messages Area */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-4">
                        {messages.length === 0 ? (
                            <div className="flex items-center justify-center h-full text-center">
                                <div className="space-y-4">
                                    <Bot className="w-12 h-12 mx-auto text-muted-foreground" />
                                    <div>
                                        <h3 className="text-lg font-medium">Welcome to {getEngineDisplayName()} AI Assistant</h3>
                                        <p className="text-muted-foreground">
                                            Start a conversation to get help with your game development project.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2 justify-center">
                                        <Button variant="outline" size="sm" onClick={() => setCurrentMessage("Create a simple platformer game")}>
                                            Create a platformer
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => setCurrentMessage("Help me with character movement")}>
                                            Character movement
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => setCurrentMessage("Add physics to my game")}>
                                            Add physics
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            messages.map((message) => (
                                <div
                                    key={message.id}
                                    className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                                >
                                    <div
                                        className={`max-w-[80%] rounded-lg p-3 ${
                                            message.role === 'user'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted'
                                        }`}
                                    >
                                        <div className="flex items-start space-x-2">
                                            {message.role === 'assistant' && (
                                                <Bot className="w-5 h-5 mt-0.5 flex-shrink-0" />
                                            )}
                                            {message.role === 'user' && (
                                                <User className="w-5 h-5 mt-0.5 flex-shrink-0" />
                                            )}
                                            <div className="flex-1">
                                                <div className="whitespace-pre-wrap">{message.content}</div>
                                                <div className="text-xs opacity-70 mt-1">
                                                    {new Date(message.timestamp).toLocaleTimeString()}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                        
                        {/* Streaming Message */}
                        {isStreaming && streamingContent && (
                            <StreamingMessage
                                content={streamingContent}
                                isStreaming={isStreaming}
                                totalTokens={streamingTokens}
                                onStop={handleStopStreaming}
                            />
                        )}
                        
                        {/* AI Typing Indicator */}
                        <AITypingIndicator isTyping={isAITyping || (isLoading && !isStreaming)} />
                        
                        {/* User Typing Indicators */}
                        {currentConversation?.id && (
                            <TypingIndicator conversationId={currentConversation.id} />
                        )}
                        
                        {/* Streaming Progress */}
                        {isStreaming && (
                            <StreamingProgress
                                isActive={isStreaming}
                                tokensUsed={streamingTokens}
                                className="mb-4"
                            />
                        )}
                        
                        <div ref={messagesEndRef} />
                    </div>

                    {/* Message Input */}
                    <div className="border-t bg-background p-4">
                        <div className="flex space-x-2">
                            <Textarea
                                value={currentMessage}
                                onChange={(e) => {
                                    setCurrentMessage(e.target.value);
                                    handleTyping();
                                }}
                                onKeyDown={handleKeyDown}
                                placeholder={`Ask your ${getEngineDisplayName()} AI assistant anything...`}
                                className="flex-1 min-h-[60px] max-h-[120px] resize-none"
                                disabled={isLoading || isStreaming}
                            />
                            {isStreaming ? (
                                <Button
                                    onClick={handleStopStreaming}
                                    variant="destructive"
                                    size="lg"
                                >
                                    <Square className="w-4 h-4" />
                                </Button>
                            ) : (
                                <Button
                                    onClick={handleSendMessage}
                                    disabled={!currentMessage.trim() || isLoading}
                                    size="lg"
                                >
                                    {isLoading ? (
                                        <Loader2 className="w-4 h-4 animate-spin" />
                                    ) : (
                                        <Send className="w-4 h-4" />
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Right Sidebar */}
                <div className="w-80 border-l bg-muted/30 p-4 space-y-4 overflow-y-auto">
                    {/* Engine Context */}
                    <EngineContext
                        workspace={workspace}
                        engineStatus={engineStatus}
                        onRefreshStatus={handleRefreshEngineStatus}
                        onOpenConnection={handleOpenUnrealConnection}
                        onOpenPreview={handleOpenPlayCanvasPreview}
                    />

                    {/* PlayCanvas Preview */}
                    {workspace.engine_type === 'playcanvas' && (
                        <PlayCanvasPreview
                            workspaceId={workspace.id}
                            previewUrl={workspace.preview_url}
                            isLiveUpdatesEnabled={isLiveUpdatesEnabled}
                            onToggleLiveUpdates={setIsLiveUpdatesEnabled}
                            onRefreshPreview={handleRefreshPlayCanvasPreview}
                            onOpenFullscreen={handleOpenPlayCanvasPreview}
                        />
                    )}

                    {/* Game Actions */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center space-x-2">
                                <span>Game Actions</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    className="w-full justify-start"
                                    onClick={() => window.open(workspace.preview_url || '#', '_blank')}
                                    disabled={!workspace.preview_url}
                                >
                                    Preview Game
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    className="w-full justify-start"
                                    onClick={() => {
                                        if (workspace.published_url) {
                                            navigator.clipboard.writeText(workspace.published_url);
                                            toast({
                                                title: "Link Copied",
                                                description: "Share link copied to clipboard",
                                            });
                                        }
                                    }}
                                    disabled={!workspace.published_url}
                                >
                                    Share Game
                                </Button>
                                <Button 
                                    variant="default" 
                                    size="sm" 
                                    className="w-full justify-start"
                                    onClick={() => router.get('/games')}
                                >
                                    Publish Game
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recent Conversations */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center justify-between">
                                <div className="flex items-center space-x-2">
                                    <MessageSquare className="w-5 h-5" />
                                    <span>Conversations</span>
                                </div>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    onClick={() => router.get('/chat')}
                                >
                                    New Chat
                                </Button>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {conversations.length > 0 ? (
                                <div className="space-y-2">
                                    {conversations.slice(0, 5).map((conversation) => (
                                        <div
                                            key={conversation.id}
                                            className={`p-2 rounded hover:bg-muted cursor-pointer ${
                                                currentConversation?.id === conversation.id ? 'bg-muted' : ''
                                            }`}
                                            onClick={() => handleConversationSelect(conversation.id)}
                                        >
                                            <div className="font-medium text-sm truncate">
                                                {conversation.title}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {new Date(conversation.updated_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center text-muted-foreground text-sm">
                                    No conversations yet
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Connection Status */}
                    <ConnectionStatus workspaceId={workspace.id} />

                    {/* AI Provider Info */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center space-x-2">
                                <Zap className="w-5 h-5" />
                                <span>AI Provider</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-sm space-y-2">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Engine:</span>
                                    <span>{getEngineDisplayName()}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Model:</span>
                                    <span>{settings.model}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Streaming:</span>
                                    <Badge variant={settings.preferences.stream_responses ? "default" : "secondary"}>
                                        {settings.preferences.stream_responses ? "Enabled" : "Disabled"}
                                    </Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Status:</span>
                                    <Badge 
                                        variant="outline" 
                                        className={
                                            connectionStatus.websocket 
                                                ? "bg-green-100 text-green-800 border-green-200" 
                                                : "bg-red-100 text-red-800 border-red-200"
                                        }
                                    >
                                        {connectionStatus.websocket ? "Connected" : "Disconnected"}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Chat Settings Modal */}
            <ChatSettingsModal
                isOpen={isSettingsModalOpen}
                onClose={() => setIsSettingsModalOpen(false)}
                engineType={workspace.engine_type}
                currentSettings={settings}
                onSettingsUpdate={handleSettingsUpdate}
            />

            {/* Unreal Connection Modal */}
            {workspace.engine_type === 'unreal' && (
                <UnrealConnectionModal
                    isOpen={isUnrealModalOpen}
                    onClose={() => setIsUnrealModalOpen(false)}
                    workspaceId={workspace.id}
                    connectionStatus={unrealConnectionStatus}
                    onTestConnection={handleTestUnrealConnection}
                    onRefreshStatus={loadUnrealConnectionStatus}
                />
            )}
        </MainLayout>
    );
}