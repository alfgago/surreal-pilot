import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    User, 
    Bot, 
    Copy, 
    ThumbsUp, 
    ThumbsDown, 
    RefreshCw,
    Loader2
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
    metadata?: any;
    isStreaming?: boolean;
}

interface MessageListProps {
    messages: Message[];
    isLoading?: boolean;
    onCopyMessage?: (content: string) => void;
    onRegenerateResponse?: (messageId: string) => void;
    onFeedback?: (messageId: string, feedback: 'positive' | 'negative') => void;
}

export default function MessageList({
    messages,
    isLoading = false,
    onCopyMessage,
    onRegenerateResponse,
    onFeedback
}: MessageListProps) {
    const { toast } = useToast();

    const handleCopyMessage = async (content: string) => {
        try {
            await navigator.clipboard.writeText(content);
            toast({
                title: "Copied",
                description: "Message copied to clipboard.",
            });
            if (onCopyMessage) {
                onCopyMessage(content);
            }
        } catch (error) {
            toast({
                title: "Copy Failed",
                description: "Failed to copy message to clipboard.",
                variant: "destructive",
            });
        }
    };

    const handleFeedback = (messageId: string, feedback: 'positive' | 'negative') => {
        if (onFeedback) {
            onFeedback(messageId, feedback);
        }
        toast({
            title: "Feedback Sent",
            description: `Thank you for your ${feedback} feedback!`,
        });
    };

    const formatTimestamp = (timestamp: string) => {
        return new Date(timestamp).toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    };

    const renderMessageContent = (content: string) => {
        // Simple markdown-like rendering for code blocks
        const parts = content.split(/(```[\s\S]*?```|`[^`]+`)/);
        
        return parts.map((part, index) => {
            if (part.startsWith('```') && part.endsWith('```')) {
                // Code block
                const code = part.slice(3, -3).trim();
                const lines = code.split('\n');
                const language = lines[0].match(/^[a-zA-Z]+$/) ? lines.shift() : '';
                const codeContent = lines.join('\n');
                
                return (
                    <div key={index} className="my-3">
                        {language && (
                            <div className="text-xs text-muted-foreground mb-1 font-mono">
                                {language}
                            </div>
                        )}
                        <pre className="bg-muted p-3 rounded-md overflow-x-auto text-sm">
                            <code>{codeContent}</code>
                        </pre>
                    </div>
                );
            } else if (part.startsWith('`') && part.endsWith('`')) {
                // Inline code
                return (
                    <code key={index} className="bg-muted px-1.5 py-0.5 rounded text-sm font-mono">
                        {part.slice(1, -1)}
                    </code>
                );
            } else {
                // Regular text with line breaks
                return part.split('\n').map((line, lineIndex) => (
                    <React.Fragment key={`${index}-${lineIndex}`}>
                        {line}
                        {lineIndex < part.split('\n').length - 1 && <br />}
                    </React.Fragment>
                ));
            }
        });
    };

    if (messages.length === 0 && !isLoading) {
        return (
            <div className="flex items-center justify-center h-full text-center">
                <div className="space-y-4">
                    <Bot className="w-12 h-12 mx-auto text-muted-foreground" />
                    <div>
                        <h3 className="text-lg font-medium">Start a conversation</h3>
                        <p className="text-muted-foreground">
                            Ask me anything about your game development project.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 justify-center">
                        <Badge variant="outline" className="cursor-pointer hover:bg-accent">
                            Create a platformer game
                        </Badge>
                        <Badge variant="outline" className="cursor-pointer hover:bg-accent">
                            Help with character movement
                        </Badge>
                        <Badge variant="outline" className="cursor-pointer hover:bg-accent">
                            Add physics to my game
                        </Badge>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6 p-4">
            {messages.map((message) => (
                <div 
                    key={message.id} 
                    className={`flex gap-4 ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                    {message.role === 'assistant' && (
                        <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center flex-shrink-0">
                            <Bot className="w-4 h-4 text-primary-foreground" />
                        </div>
                    )}

                    <div className={`max-w-[80%] space-y-2 ${message.role === 'user' ? 'items-end' : 'items-start'}`}>
                        <Card className={`${
                            message.role === 'user' 
                                ? 'bg-primary text-primary-foreground ml-auto' 
                                : 'bg-card'
                        }`}>
                            <CardContent className="p-4">
                                <div className="prose prose-sm max-w-none">
                                    <div className={`text-sm leading-relaxed ${
                                        message.role === 'user' 
                                            ? 'text-primary-foreground' 
                                            : 'text-card-foreground'
                                    }`}>
                                        {renderMessageContent(message.content)}
                                        {message.isStreaming && (
                                            <span className="inline-block w-2 h-4 bg-current ml-1 animate-pulse" />
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div className={`flex items-center gap-2 text-xs text-muted-foreground ${
                            message.role === 'user' ? 'justify-end' : 'justify-start'
                        }`}>
                            <span>{formatTimestamp(message.timestamp)}</span>

                            {message.role === 'assistant' && !message.isStreaming && (
                                <div className="flex items-center gap-1">
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        className="h-6 w-6 p-0"
                                        onClick={() => handleCopyMessage(message.content)}
                                        title="Copy message"
                                    >
                                        <Copy className="w-3 h-3" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        className="h-6 w-6 p-0"
                                        onClick={() => handleFeedback(message.id, 'positive')}
                                        title="Good response"
                                    >
                                        <ThumbsUp className="w-3 h-3" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        className="h-6 w-6 p-0"
                                        onClick={() => handleFeedback(message.id, 'negative')}
                                        title="Poor response"
                                    >
                                        <ThumbsDown className="w-3 h-3" />
                                    </Button>
                                    {onRegenerateResponse && (
                                        <Button 
                                            variant="ghost" 
                                            size="sm" 
                                            className="h-6 w-6 p-0"
                                            onClick={() => onRegenerateResponse(message.id)}
                                            title="Regenerate response"
                                        >
                                            <RefreshCw className="w-3 h-3" />
                                        </Button>
                                    )}
                                </div>
                            )}

                            {message.metadata?.token_count && (
                                <Badge variant="outline" className="text-xs">
                                    {message.metadata.token_count} tokens
                                </Badge>
                            )}
                        </div>
                    </div>

                    {message.role === 'user' && (
                        <div className="w-8 h-8 bg-secondary rounded-full flex items-center justify-center flex-shrink-0">
                            <User className="w-4 h-4 text-secondary-foreground" />
                        </div>
                    )}
                </div>
            ))}

            {isLoading && (
                <div className="flex justify-start">
                    <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center flex-shrink-0 mr-4">
                        <Bot className="w-4 h-4 text-primary-foreground" />
                    </div>
                    <Card className="bg-card">
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-2">
                                <Loader2 className="w-4 h-4 animate-spin" />
                                <span className="text-sm text-muted-foreground">AI is thinking...</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
}