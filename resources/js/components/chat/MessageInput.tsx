import React, { useState, useRef, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { 
    Send, 
    Loader2, 
    Paperclip, 
    Mic,
    Square
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

interface MessageInputProps {
    workspaceId: number;
    conversationId?: number;
    onMessageSent?: (message: any) => void;
    onMessageStart?: () => void;
    disabled?: boolean;
    placeholder?: string;
    engineType?: 'playcanvas' | 'unreal';
}

export default function MessageInput({
    workspaceId,
    conversationId,
    onMessageSent,
    onMessageStart,
    disabled = false,
    placeholder = "Ask your AI assistant anything...",
    engineType = 'playcanvas'
}: MessageInputProps) {
    const [isStreaming, setIsStreaming] = useState(false);
    const [streamingContent, setStreamingContent] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const { toast } = useToast();

    const { data, setData, post, processing, errors, reset } = useForm({
        message: '',
        conversation_id: conversationId,
        include_context: true,
    });

    // Auto-resize textarea
    useEffect(() => {
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
            textareaRef.current.style.height = `${textareaRef.current.scrollHeight}px`;
        }
    }, [data.message]);

    // Update conversation_id when it changes
    useEffect(() => {
        setData('conversation_id', conversationId);
    }, [conversationId]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!data.message.trim() || processing || disabled) {
            return;
        }

        if (!conversationId) {
            toast({
                title: "No Conversation",
                description: "Please select or create a conversation first.",
                variant: "destructive",
            });
            return;
        }

        const messageContent = data.message.trim();
        
        // Notify parent that message is starting
        if (onMessageStart) {
            onMessageStart();
        }

        // Clear input immediately for better UX
        reset('message');

        try {
            // Send message via Inertia
            post(`/api/workspaces/${workspaceId}/chat`, {
                onSuccess: (response) => {
                    if (onMessageSent) {
                        onMessageSent({
                            user_message: messageContent,
                            assistant_response: response.props.flash?.message || 'Response received'
                        });
                    }
                    
                    toast({
                        title: "Message Sent",
                        description: "Your message has been processed.",
                    });
                },
                onError: (errors) => {
                    console.error('Message send error:', errors);
                    toast({
                        title: "Send Failed",
                        description: "Failed to send message. Please try again.",
                        variant: "destructive",
                    });
                },
                preserveScroll: true,
            });
        } catch (error) {
            console.error('Failed to send message:', error);
            toast({
                title: "Send Failed",
                description: "An unexpected error occurred. Please try again.",
                variant: "destructive",
            });
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    const handleStopStreaming = () => {
        setIsStreaming(false);
        setStreamingContent('');
        // In a real implementation, you would cancel the streaming request here
    };

    const isDisabled = disabled || processing || !data.message.trim();

    return (
        <div className="border-t bg-background p-4">
            <form onSubmit={handleSubmit} className="space-y-3">
                {/* Message Input */}
                <div className="flex space-x-2">
                    <div className="flex-1 relative">
                        <Textarea
                            ref={textareaRef}
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            onKeyPress={handleKeyPress}
                            placeholder={placeholder}
                            className="min-h-[60px] max-h-[120px] resize-none pr-12"
                            disabled={disabled || processing}
                            rows={1}
                        />
                        
                        {/* Character count */}
                        {data.message.length > 0 && (
                            <div className="absolute bottom-2 right-2 text-xs text-muted-foreground">
                                {data.message.length}
                            </div>
                        )}
                    </div>

                    {/* Send Button */}
                    {isStreaming ? (
                        <Button
                            type="button"
                            variant="destructive"
                            size="lg"
                            onClick={handleStopStreaming}
                        >
                            <Square className="w-4 h-4" />
                        </Button>
                    ) : (
                        <Button
                            type="submit"
                            disabled={isDisabled}
                            size="lg"
                        >
                            {processing ? (
                                <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                                <Send className="w-4 h-4" />
                            )}
                        </Button>
                    )}
                </div>

                {/* Error Display */}
                {errors.message && (
                    <div className="text-sm text-destructive">
                        {errors.message}
                    </div>
                )}

                {/* Input Options */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={disabled}
                            title="Attach file (coming soon)"
                        >
                            <Paperclip className="w-4 h-4" />
                        </Button>
                        
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={disabled}
                            title="Voice input (coming soon)"
                        >
                            <Mic className="w-4 h-4" />
                        </Button>

                        <div className="flex items-center space-x-1">
                            <input
                                type="checkbox"
                                id="include_context"
                                checked={data.include_context}
                                onChange={(e) => setData('include_context', e.target.checked)}
                                className="w-4 h-4"
                            />
                            <label htmlFor="include_context" className="text-sm text-muted-foreground">
                                Include {engineType === 'playcanvas' ? 'PlayCanvas' : 'Unreal'} context
                            </label>
                        </div>
                    </div>

                    <div className="flex items-center space-x-2">
                        <Badge variant="outline" className="text-xs">
                            {engineType === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine'}
                        </Badge>
                        
                        {processing && (
                            <Badge variant="secondary" className="text-xs">
                                Sending...
                            </Badge>
                        )}
                    </div>
                </div>

                {/* Streaming Content Display */}
                {isStreaming && streamingContent && (
                    <div className="bg-muted p-3 rounded-md">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-medium">AI Response</span>
                            <Badge variant="secondary" className="text-xs">
                                Streaming...
                            </Badge>
                        </div>
                        <div className="text-sm whitespace-pre-wrap">
                            {streamingContent}
                            <span className="inline-block w-2 h-4 bg-current ml-1 animate-pulse" />
                        </div>
                    </div>
                )}
            </form>
        </div>
    );
}