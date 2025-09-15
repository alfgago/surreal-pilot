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
import GDevelopChatInterface from '@/components/gdevelop/GDevelopChatInterface';
import GDevelopPreview from '@/components/gdevelop/GDevelopPreview';
import TypingIndicator, { AITypingIndicator } from '@/components/chat/TypingIndicator';
import ConnectionStatus, { SimpleConnectionIndicator } from '@/components/chat/ConnectionStatus';
import StreamingMessage, { StreamingProgress } from '@/components/chat/StreamingMessage';
import StreamingChat from '@/components/chat/StreamingChat';
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
    engine_type: 'playcanvas' | 'unreal' | 'gdevelop';
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
    allWorkspaces: Workspace[];
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
    const [gdevelopGameData, setGDevelopGameData] = useState<any>(null);
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
    const streamingContentRef = useRef<string>('');

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

    // Load initial engine status and auto-start PlayCanvas MCP
    useEffect(() => {
        if (workspace) {
            loadEngineStatus();
            if (workspace.engine_type === 'unreal') {
                loadUnrealConnectionStatus();
            } else if (workspace.engine_type === 'playcanvas') {
                // Auto-start PlayCanvas MCP server if not running
                autoStartPlayCanvasMcp();
            } else if (workspace.engine_type === 'gdevelop') {
                // GDevelop doesn't need MCP auto-start, just set ready status
                setEngineStatus({
                    status: 'connected',
                    message: 'Ready for game development'
                });
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

    const autoStartPlayCanvasMcp = async () => {
        try {
            // Check if MCP is already running
            const status = await engineService.getPlayCanvasStatus(workspace.id);

            if (!status.mcp_running) {
                // Start MCP server automatically
                setEngineStatus({ status: 'connecting', message: 'Initializing workspace...' });

                const result = await engineService.startPlayCanvasMcp(workspace.id);

                if (result.success) {
                    setEngineStatus({
                        status: 'connected',
                        message: 'Ready for game development'
                    });

                    // Refresh engine status to get updated info
                    await loadEngineStatus();
                } else {
                    setEngineStatus({
                        status: 'error',
                        message: 'Unable to initialize workspace'
                    });
                }
            } else {
                setEngineStatus({
                    status: 'connected',
                    message: 'Ready for game development'
                });
            }
        } catch (error) {
            console.error('Failed to auto-start PlayCanvas MCP:', error);
            setEngineStatus({
                status: 'error',
                message: 'Unable to initialize workspace'
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

        console.log('Sending message:', currentMessage.trim());
        console.log('Current conversation:', currentConversation);
        console.log('Workspace:', workspace);

        // If no conversation exists, create one automatically
        let conversationToUse = currentConversation;
        if (!conversationToUse?.id) {
            console.log('No conversation found, creating new one...');
            try {
                const response = await axios.post(`/api/workspaces/${workspace.id}/conversations`, {
                    title: 'New Chat'
                });

                if (response.data.success) {
                    conversationToUse = response.data.conversation;
                    // Update the page to include the new conversation
                    window.location.href = `/chat?workspace=${workspace.id}&conversation=${conversationToUse.id}`;
                    return;
                } else {
                    toast({
                        title: "Error",
                        description: "Failed to create conversation. Please try again.",
                        variant: "destructive",
                    });
                    return;
                }
            } catch (error) {
                console.error('Failed to create conversation:', error);
                toast({
                    title: "Error",
                    description: "Failed to create conversation. Please try again.",
                    variant: "destructive",
                });
                return;
            }
        }

        const messageText = currentMessage.trim();
        setCurrentMessage('');
        setIsLoading(true);

        // Stop typing indicator
        if (realtimeChatService.current) {
            realtimeChatService.current.stopTyping(conversationToUse.id);
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
                console.log('Starting streaming chat with conversation:', conversationToUse.id);
                setIsStreaming(true);
                setStreamingContent('');
                setStreamingTokens(0);
                streamingContentRef.current = '';

                realtimeChatService.current.startStreamingChat(
                    messageText,
                    conversationToUse.id,
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
                            setStreamingContent(prev => {
                                const newContent = prev + content;
                                streamingContentRef.current = newContent;
                                return newContent;
                            });
                            setStreamingTokens(prev => prev + tokens);
                        },
                        onComplete: (messageId, totalTokens) => {
                            // Use the ref to get the current streaming content
                            const finalContent = streamingContentRef.current;

                            const assistantMessage: Message = {
                                id: messageId,
                                role: 'assistant',
                                content: finalContent,
                                timestamp: new Date().toISOString(),
                                metadata: { tokens_used: totalTokens },
                            };

                            setMessages(prev => [...prev, assistantMessage]);

                            // Reset streaming state
                            setIsStreaming(false);
                            setStreamingContent('');
                            setStreamingTokens(0);
                            streamingContentRef.current = '';
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
                            streamingContentRef.current = '';
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
                        conversation_id: conversationToUse.id,
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
            streamingContentRef.current = '';
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
        streamingContentRef.current = '';
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
        switch (workspace.engine_type) {
            case 'playcanvas':
                return 'PlayCanvas';
            case 'gdevelop':
                return 'GDevelop';
            default:
                return 'Unreal Engine';
        }
    };

    return (
        <MainLayout
            title={`Chat - ${workspace.name}`}
            currentWorkspace={workspace}
            workspaces={usePage<ChatPageProps>().props.allWorkspaces || []}
        >
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

                    {/* Streaming Chat Component */}
                    {currentConversation?.id ? (
                        <StreamingChat
                            workspaceId={workspace.id}
                            conversationId={currentConversation.id}
                            initialMessages={messages}
                            onMessageAdded={(message) => {
                                setMessages(prev => [...prev, message]);
                            }}
                        />
                    ) : (
                        <div className="flex-1 flex items-center justify-center text-center p-4">
                            <div className="space-y-4">
                                <Bot className="w-12 h-12 mx-auto text-muted-foreground" />
                                <div>
                                    <h3 className="text-lg font-medium">Welcome to {getEngineDisplayName()} AI Assistant</h3>
                                    <p className="text-muted-foreground">
                                        Please create or select a conversation to start chatting.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
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

                    {/* GDevelop Chat Interface */}
                    {workspace.engine_type === 'gdevelop' && currentConversation?.id && (
                        <GDevelopChatInterface
                            workspaceId={workspace.id.toString()}
                            sessionId={currentConversation.id.toString()}
                            onGameGenerated={(gameData) => {
                                setGDevelopGameData(gameData);
                            }}
                            onPreviewReady={(previewUrl) => {
                                // Update workspace preview URL if needed
                                console.log('GDevelop preview ready:', previewUrl);
                            }}
                        />
                    )}

                    {/* GDevelop Game Preview */}
                    {workspace.engine_type === 'gdevelop' && gdevelopGameData?.previewUrl && (
                        <GDevelopPreview
                            gameData={gdevelopGameData}
                            previewUrl={gdevelopGameData.previewUrl}
                            onExport={() => {
                                // Export functionality is handled within GDevelopChatInterface
                                console.log('Export requested for GDevelop game');
                            }}
                            onRefresh={() => {
                                // Refresh preview
                                console.log('Refresh requested for GDevelop preview');
                            }}
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
