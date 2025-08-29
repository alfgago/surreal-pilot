import React, { useState, useEffect, useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { 
    ChevronDown, 
    ChevronRight, 
    Brain, 
    Search, 
    CheckCircle, 
    Cog, 
    Clock,
    Lightbulb,
    Code,
    Target
} from 'lucide-react';
import { ThinkingProcess, ThinkingStep } from '@/types';

interface AIThinkingDisplayProps {
    thinking: ThinkingProcess;
    isVisible: boolean;
    onToggle: () => void;
    className?: string;
}

interface ThinkingStepProps {
    step: ThinkingStep;
    isExpanded: boolean;
    onToggle: () => void;
    isActive?: boolean;
}

// Icon mapping for different thinking step types
const getStepIcon = (type: ThinkingStep['type']) => {
    switch (type) {
        case 'analysis':
            return Search;
        case 'decision':
            return Lightbulb;
        case 'implementation':
            return Code;
        case 'validation':
            return Target;
        default:
            return Brain;
    }
};

// Color mapping for different thinking step types
const getStepColor = (type: ThinkingStep['type']) => {
    switch (type) {
        case 'analysis':
            return 'text-blue-600 bg-blue-50 border-blue-200';
        case 'decision':
            return 'text-yellow-600 bg-yellow-50 border-yellow-200';
        case 'implementation':
            return 'text-green-600 bg-green-50 border-green-200';
        case 'validation':
            return 'text-purple-600 bg-purple-50 border-purple-200';
        default:
            return 'text-gray-600 bg-gray-50 border-gray-200';
    }
};

function ThinkingStepComponent({ step, isExpanded, onToggle, isActive = false }: ThinkingStepProps) {
    const Icon = getStepIcon(step.type);
    const colorClass = getStepColor(step.type);
    
    return (
        <div className={`border rounded-lg transition-all duration-200 ${colorClass} ${isActive ? 'ring-2 ring-primary/20' : ''}`}>
            <Button
                variant="ghost"
                onClick={onToggle}
                className="w-full justify-between p-3 h-auto hover:bg-transparent"
            >
                <div className="flex items-center space-x-2">
                    <Icon className="w-4 h-4" />
                    <span className="font-medium text-sm">{step.title}</span>
                    {step.duration > 0 && (
                        <Badge variant="outline" className="text-xs">
                            <Clock className="w-3 h-3 mr-1" />
                            {step.duration}ms
                        </Badge>
                    )}
                </div>
                {isExpanded ? (
                    <ChevronDown className="w-4 h-4" />
                ) : (
                    <ChevronRight className="w-4 h-4" />
                )}
            </Button>
            
            {isExpanded && (
                <div className="px-3 pb-3 space-y-2">
                    <div className="text-sm leading-relaxed whitespace-pre-wrap">
                        {step.content}
                    </div>
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span className="capitalize">{step.type} step</span>
                        <span>{new Date(step.timestamp).toLocaleTimeString()}</span>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AIThinkingDisplay({ 
    thinking, 
    isVisible, 
    onToggle, 
    className = '' 
}: AIThinkingDisplayProps) {
    const [expandedSteps, setExpandedSteps] = useState<Set<string>>(new Set());
    const [steps, setSteps] = useState<ThinkingStep[]>([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    // Parse thinking process into steps
    useEffect(() => {
        if (!thinking) return;

        const parsedSteps: ThinkingStep[] = [];
        
        // Parse the main thinking process
        if (thinking.step) {
            parsedSteps.push({
                title: thinking.step,
                content: thinking.reasoning,
                type: 'analysis',
                duration: 0,
                timestamp: thinking.timestamp
            });
        }

        // Parse decisions as decision steps
        if (thinking.decisions && thinking.decisions.length > 0) {
            thinking.decisions.forEach((decision, index) => {
                parsedSteps.push({
                    title: `Decision ${index + 1}`,
                    content: decision,
                    type: 'decision',
                    duration: 0,
                    timestamp: thinking.timestamp
                });
            });
        }

        // Parse implementation as implementation step
        if (thinking.implementation) {
            parsedSteps.push({
                title: 'Implementation Plan',
                content: thinking.implementation,
                type: 'implementation',
                duration: 0,
                timestamp: thinking.timestamp
            });
        }

        setSteps(parsedSteps);
        setIsProcessing(parsedSteps.length > 0);
    }, [thinking]);

    // Auto-expand first step when thinking starts
    useEffect(() => {
        if (steps.length > 0 && expandedSteps.size === 0) {
            setExpandedSteps(new Set([steps[0].title]));
        }
    }, [steps]);

    // Auto-scroll to bottom when new steps are added
    useEffect(() => {
        if (containerRef.current && isVisible) {
            containerRef.current.scrollTop = containerRef.current.scrollHeight;
        }
    }, [steps, isVisible]);

    const toggleStep = (stepTitle: string) => {
        const newExpanded = new Set(expandedSteps);
        if (newExpanded.has(stepTitle)) {
            newExpanded.delete(stepTitle);
        } else {
            newExpanded.add(stepTitle);
        }
        setExpandedSteps(newExpanded);
    };

    const expandAll = () => {
        setExpandedSteps(new Set(steps.map(step => step.title)));
    };

    const collapseAll = () => {
        setExpandedSteps(new Set());
    };

    if (!isVisible) {
        return null;
    }

    return (
        <Card className={`bg-muted/30 border-dashed ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between p-3 border-b border-border/50">
                <div className="flex items-center space-x-2">
                    <Brain className="w-4 h-4 text-primary" />
                    <span className="font-medium text-sm">AI Thinking Process</span>
                    {isProcessing && (
                        <Badge variant="secondary" className="text-xs animate-pulse">
                            <div className="flex items-center space-x-1">
                                <div className="w-1 h-1 bg-current rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                                <div className="w-1 h-1 bg-current rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                                <div className="w-1 h-1 bg-current rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                            </div>
                        </Badge>
                    )}
                </div>
                
                <div className="flex items-center space-x-1">
                    {steps.length > 1 && (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={expandAll}
                                className="h-6 px-2 text-xs"
                            >
                                Expand All
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={collapseAll}
                                className="h-6 px-2 text-xs"
                            >
                                Collapse All
                            </Button>
                        </>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onToggle}
                        className="h-6 px-2 text-xs"
                    >
                        Hide
                    </Button>
                </div>
            </div>

            {/* Content */}
            <div 
                ref={containerRef}
                className="p-3 space-y-2 max-h-96 overflow-y-auto"
            >
                {steps.length === 0 ? (
                    <div className="text-center py-8 text-muted-foreground">
                        <Brain className="w-8 h-8 mx-auto mb-2 opacity-50" />
                        <p className="text-sm">AI thinking process will appear here...</p>
                    </div>
                ) : (
                    steps.map((step, index) => (
                        <ThinkingStepComponent
                            key={`${step.title}-${index}`}
                            step={step}
                            isExpanded={expandedSteps.has(step.title)}
                            onToggle={() => toggleStep(step.title)}
                            isActive={index === steps.length - 1 && isProcessing}
                        />
                    ))
                )}
            </div>

            {/* Footer */}
            {steps.length > 0 && (
                <div className="px-3 py-2 border-t border-border/50 bg-muted/20">
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{steps.length} thinking step{steps.length !== 1 ? 's' : ''}</span>
                        <span>
                            {isProcessing ? 'Processing...' : 'Complete'}
                            {!isProcessing && <CheckCircle className="w-3 h-3 ml-1 inline" />}
                        </span>
                    </div>
                </div>
            )}
        </Card>
    );
}

// Compact thinking indicator for when the full display is hidden
interface ThinkingIndicatorProps {
    isThinking: boolean;
    onShow: () => void;
    className?: string;
}

export function ThinkingIndicator({ isThinking, onShow, className = '' }: ThinkingIndicatorProps) {
    if (!isThinking) {
        return null;
    }

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={onShow}
            className={`animate-pulse ${className}`}
        >
            <Brain className="w-4 h-4 mr-2" />
            <span className="text-sm">AI is thinking...</span>
            <div className="flex space-x-1 ml-2">
                <div className="w-1 h-1 bg-current rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                <div className="w-1 h-1 bg-current rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                <div className="w-1 h-1 bg-current rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
            </div>
        </Button>
    );
}