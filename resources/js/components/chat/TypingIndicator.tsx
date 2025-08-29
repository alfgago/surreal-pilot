import React, { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { TypingUser } from '@/services/realtimeChatService';
import { Loader2 } from 'lucide-react';

interface TypingIndicatorProps {
    conversationId: number;
    className?: string;
}

export default function TypingIndicator({ conversationId, className = '' }: TypingIndicatorProps) {
    const [typingUsers, setTypingUsers] = useState<TypingUser[]>([]);
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        const handleTypingStart = (event: CustomEvent) => {
            const { user, conversation_id } = event.detail;
            if (conversation_id === conversationId) {
                setTypingUsers(prev => {
                    const exists = prev.find(u => u.id === user.id);
                    if (!exists) {
                        return [...prev, user];
                    }
                    return prev;
                });
                setIsVisible(true);
            }
        };

        const handleTypingStop = (event: CustomEvent) => {
            const { user, conversation_id } = event.detail;
            if (conversation_id === conversationId) {
                setTypingUsers(prev => prev.filter(u => u.id !== user.id));
            }
        };

        // Listen for typing events
        window.addEventListener('chat:typing-start', handleTypingStart as EventListener);
        window.addEventListener('chat:typing-stop', handleTypingStop as EventListener);

        return () => {
            window.removeEventListener('chat:typing-start', handleTypingStart as EventListener);
            window.removeEventListener('chat:typing-stop', handleTypingStop as EventListener);
        };
    }, [conversationId]);

    // Hide indicator when no users are typing
    useEffect(() => {
        if (typingUsers.length === 0) {
            const timer = setTimeout(() => setIsVisible(false), 300);
            return () => clearTimeout(timer);
        } else {
            setIsVisible(true);
        }
    }, [typingUsers]);

    if (!isVisible || typingUsers.length === 0) {
        return null;
    }

    const getTypingText = () => {
        if (typingUsers.length === 1) {
            return `${typingUsers[0].name} is typing...`;
        } else if (typingUsers.length === 2) {
            return `${typingUsers[0].name} and ${typingUsers[1].name} are typing...`;
        } else {
            return `${typingUsers[0].name} and ${typingUsers.length - 1} others are typing...`;
        }
    };

    return (
        <div className={`flex items-center space-x-2 animate-in fade-in-0 slide-in-from-bottom-2 duration-300 ${className}`}>
            <Badge variant="secondary" className="flex items-center space-x-2 px-3 py-1">
                <Loader2 className="w-3 h-3 animate-spin" />
                <span className="text-xs">{getTypingText()}</span>
            </Badge>
        </div>
    );
}

// AI Assistant typing indicator component
interface AITypingIndicatorProps {
    isTyping: boolean;
    className?: string;
}

export function AITypingIndicator({ isTyping, className = '' }: AITypingIndicatorProps) {
    if (!isTyping) {
        return null;
    }

    return (
        <div className={`flex items-center space-x-2 animate-in fade-in-0 slide-in-from-bottom-2 duration-300 ${className}`}>
            <div className="flex items-center space-x-2 bg-muted rounded-lg p-3">
                <div className="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                    <Loader2 className="w-3 h-3 animate-spin text-primary" />
                </div>
                <div className="flex flex-col">
                    <span className="text-sm font-medium">AI Assistant</span>
                    <div className="flex items-center space-x-1">
                        <span className="text-xs text-muted-foreground">Thinking</span>
                        <div className="flex space-x-1">
                            <div className="w-1 h-1 bg-muted-foreground rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                            <div className="w-1 h-1 bg-muted-foreground rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                            <div className="w-1 h-1 bg-muted-foreground rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}