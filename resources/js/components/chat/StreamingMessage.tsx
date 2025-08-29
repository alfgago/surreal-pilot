import React, { useState, useEffect, useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Bot, Square, Zap } from 'lucide-react';

interface StreamingMessageProps {
    content: string;
    isStreaming: boolean;
    totalTokens?: number;
    onStop?: () => void;
    className?: string;
}

export default function StreamingMessage({
    content,
    isStreaming,
    totalTokens = 0,
    onStop,
    className = ''
}: StreamingMessageProps) {
    const [displayedContent, setDisplayedContent] = useState('');
    const [currentTokens, setCurrentTokens] = useState(0);
    const contentRef = useRef<HTMLDivElement>(null);
    const lastContentRef = useRef('');

    // Update displayed content with typewriter effect when streaming
    useEffect(() => {
        if (isStreaming && content !== lastContentRef.current) {
            const newContent = content.slice(lastContentRef.current.length);
            lastContentRef.current = content;

            // Add new content character by character for smooth streaming effect
            let index = 0;
            const interval = setInterval(() => {
                if (index < newContent.length) {
                    setDisplayedContent(prev => prev + newContent[index]);
                    index++;
                } else {
                    clearInterval(interval);
                }
            }, 20); // Adjust speed as needed

            return () => clearInterval(interval);
        } else if (!isStreaming) {
            // Show complete content immediately when not streaming
            setDisplayedContent(content);
            lastContentRef.current = content;
        }
    }, [content, isStreaming]);

    // Update token count
    useEffect(() => {
        setCurrentTokens(totalTokens);
    }, [totalTokens]);

    // Auto-scroll to bottom when content updates
    useEffect(() => {
        if (contentRef.current) {
            contentRef.current.scrollTop = contentRef.current.scrollHeight;
        }
    }, [displayedContent]);

    return (
        <div className={`flex justify-start ${className}`}>
            <div className="max-w-[80%] bg-muted rounded-lg p-3">
                <div className="flex items-start space-x-2">
                    <Bot className="w-5 h-5 mt-0.5 flex-shrink-0" />
                    <div className="flex-1">
                        {/* Message Header */}
                        <div className="flex items-center justify-between mb-2">
                            <div className="flex items-center space-x-2">
                                <span className="text-sm font-medium">AI Assistant</span>
                                {isStreaming && (
                                    <Badge variant="secondary" className="text-xs animate-pulse">
                                        Streaming...
                                    </Badge>
                                )}
                            </div>
                            
                            {/* Stop Button */}
                            {isStreaming && onStop && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={onStop}
                                    className="h-6 px-2 text-xs"
                                >
                                    <Square className="w-3 h-3 mr-1" />
                                    Stop
                                </Button>
                            )}
                        </div>

                        {/* Message Content */}
                        <div 
                            ref={contentRef}
                            className="whitespace-pre-wrap text-sm leading-relaxed"
                        >
                            {displayedContent}
                            {isStreaming && (
                                <span className="inline-block w-2 h-4 bg-current ml-1 animate-pulse" />
                            )}
                        </div>

                        {/* Message Footer */}
                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-border/50">
                            <div className="flex items-center space-x-2 text-xs text-muted-foreground">
                                {currentTokens > 0 && (
                                    <div className="flex items-center space-x-1">
                                        <Zap className="w-3 h-3" />
                                        <span>{currentTokens} tokens</span>
                                    </div>
                                )}
                                <span>{new Date().toLocaleTimeString()}</span>
                            </div>
                            
                            {!isStreaming && (
                                <Badge variant="outline" className="text-xs">
                                    Complete
                                </Badge>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Streaming progress indicator component
interface StreamingProgressProps {
    isActive: boolean;
    tokensUsed: number;
    estimatedTotal?: number;
    className?: string;
}

export function StreamingProgress({
    isActive,
    tokensUsed,
    estimatedTotal,
    className = ''
}: StreamingProgressProps) {
    if (!isActive) {
        return null;
    }

    const progress = estimatedTotal ? (tokensUsed / estimatedTotal) * 100 : 0;

    return (
        <div className={`bg-muted/50 rounded-lg p-3 ${className}`}>
            <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium">Streaming Response</span>
                <div className="flex items-center space-x-2">
                    <div className="flex space-x-1">
                        <div className="w-1 h-1 bg-primary rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                        <div className="w-1 h-1 bg-primary rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                        <div className="w-1 h-1 bg-primary rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                    </div>
                    <span className="text-xs text-muted-foreground">{tokensUsed} tokens</span>
                </div>
            </div>
            
            {estimatedTotal && (
                <div className="w-full bg-background rounded-full h-1.5">
                    <div 
                        className="bg-primary h-1.5 rounded-full transition-all duration-300 ease-out"
                        style={{ width: `${Math.min(progress, 100)}%` }}
                    />
                </div>
            )}
        </div>
    );
}