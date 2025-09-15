import React, { useState, useRef, useEffect } from 'react';
import { useStream } from '@/hooks/useStream';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Bot, User, Send, Square } from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
    metadata?: any;
}

interface StreamingChatProps {
    workspaceId: number;
    conversationId: number;
    initialMessages?: Message[];
    onMessageAdded?: (message: Message) => void;
}

export default function StreamingChat({
    workspaceId,
    conversationId,
    initialMessages = [],
    onMessageAdded
}: StreamingChatProps) {
    const [messages, setMessages] = useState<Message[]>(initialMessages);
    const [currentMessage, setCurrentMessage] = useState('');
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const { toast } = useToast();

    // Use custom Stream hook
    const { data, send, isStreaming, error } = useStream('/api/chat/stream', {
        onError: (err) => {
            console.error('Streaming error:', err);
            toast({
                title: "Streaming Error",
                description: "Failed to stream response. Please try again.",
                variant: "destructive",
            });
        },
        onComplete: () => {
            console.log('Streaming completed');
        }
    });

    // Auto-scroll to bottom when new messages arrive
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, data]);

    // Handle completed streaming responses
    useEffect(() => {
        if (data && !isStreaming) {
            // Add the complete assistant message
            const assistantMessage: Message = {
                id: Date.now().toString(),
                role: 'assistant',
                content: data,
                timestamp: new Date().toISOString(),
            };

            setMessages(prev => [...prev, assistantMessage]);
            onMessageAdded?.(assistantMessage);
        }
    }, [data, isStreaming, onMessageAdded]);

    const handleSendMessage = async () => {
        if (!currentMessage.trim() || isStreaming) return;

        const messageText = currentMessage.trim();
        setCurrentMessage('');

        // Add user message immediately
        const userMessage: Message = {
            id: Date.now().toString(),
            role: 'user',
            content: messageText,
            timestamp: new Date().toISOString(),
        };

        setMessages(prev => [...prev, userMessage]);
        onMessageAdded?.(userMessage);

        // Send to stream endpoint
        try {
            const allMessages = [...messages, userMessage];

            await send({
                messages: allMessages.map(msg => ({
                    role: msg.role,
                    content: msg.content
                })),
                conversation_id: conversationId,
                workspace_id: workspaceId,
            });
        } catch (error) {
            console.error('Failed to send message:', error);
            toast({
                title: "Error",
                description: "Failed to send message. Please try again.",
                variant: "destructive",
            });
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    };

    const stopStreaming = () => {
        // The useStream hook should handle this automatically
        window.location.reload(); // Fallback for now
    };

    return (
        <div className="flex flex-col h-full">
            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.map((message) => (
                    <div
                        key={message.id}
                        className={`flex gap-4 ${
                            message.role === 'user' ? 'justify-end' : 'justify-start'
                        }`}
                    >
                        {message.role === 'assistant' && (
                            <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center flex-shrink-0">
                                <Bot className="w-4 h-4 text-primary-foreground" />
                            </div>
                        )}

                        <Card className={`max-w-[80%] ${
                            message.role === 'user'
                                ? 'bg-primary text-primary-foreground ml-auto'
                                : 'bg-card'
                        }`}>
                            <CardContent className="p-4">
                                <div className="prose prose-sm max-w-none">
                                    <div className={`text-sm leading-relaxed whitespace-pre-wrap ${
                                        message.role === 'user'
                                            ? 'text-primary-foreground'
                                            : 'text-card-foreground'
                                    }`}>
                                        {message.content}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {message.role === 'user' && (
                            <div className="w-8 h-8 bg-secondary rounded-full flex items-center justify-center flex-shrink-0">
                                <User className="w-4 h-4 text-secondary-foreground" />
                            </div>
                        )}
                    </div>
                ))}

                {/* Streaming message */}
                {isStreaming && data && (
                    <div className="flex gap-4 justify-start">
                        <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center flex-shrink-0">
                            <Bot className="w-4 h-4 text-primary-foreground" />
                        </div>

                        <Card className="max-w-[80%] bg-card">
                            <CardContent className="p-4">
                                <div className="prose prose-sm max-w-none">
                                    <div className="text-sm leading-relaxed whitespace-pre-wrap text-card-foreground">
                                        {data}
                                        <span className="inline-block w-2 h-4 bg-current ml-1 animate-pulse" />
                                    </div>
                                </div>
                                <div className="flex items-center justify-between mt-2 pt-2 border-t border-border/50">
                                    <Badge variant="outline" className="text-xs">
                                        Streaming...
                                    </Badge>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={stopStreaming}
                                        className="text-xs"
                                    >
                                        <Square className="w-3 h-3 mr-1" />
                                        Stop
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <div ref={messagesEndRef} />
            </div>

            {/* Input area */}
            <div className="border-t p-4">
                <div className="flex gap-2">
                    <Textarea
                        value={currentMessage}
                        onChange={(e) => setCurrentMessage(e.target.value)}
                        onKeyPress={handleKeyPress}
                        placeholder="Type your message here..."
                        className="flex-1 min-h-[60px] resize-none"
                        disabled={isStreaming}
                    />
                    <Button
                        onClick={handleSendMessage}
                        disabled={!currentMessage.trim() || isStreaming}
                        size="lg"
                        className="px-6"
                    >
                        <Send className="w-4 h-4" />
                    </Button>
                </div>

                {isStreaming && (
                    <div className="flex items-center justify-center mt-2">
                        <Badge variant="secondary" className="text-xs">
                            AI is responding...
                        </Badge>
                    </div>
                )}
            </div>
        </div>
    );
}
