import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AIThinkingDisplay, { ThinkingIndicator } from './AIThinkingDisplay';
import { ThinkingProcess } from '@/types';

export default function AIThinkingDisplayTest() {
    const [isThinkingVisible, setIsThinkingVisible] = useState(false);
    const [currentThinking, setCurrentThinking] = useState<ThinkingProcess | null>(null);
    const [isProcessing, setIsProcessing] = useState(false);

    const sampleThinkingProcess: ThinkingProcess = {
        step: "Analyzing Tower Defense Game Request",
        reasoning: "The user wants to create a tower defense game. I need to break this down into core components: towers, enemies, waves, currency system, and win/lose conditions. Let me think through the architecture and implementation approach.",
        decisions: [
            "Use PlayCanvas engine for web-based deployment and mobile compatibility",
            "Implement a grid-based tower placement system for strategic gameplay",
            "Create a wave-based enemy spawning system with increasing difficulty",
            "Design a currency system that rewards strategic tower placement"
        ],
        implementation: "I'll start by creating the basic game structure with a grid system, then implement tower placement mechanics, followed by enemy pathfinding and wave management. The currency system will be integrated throughout to provide upgrade paths.",
        timestamp: new Date().toISOString()
    };

    const simulateThinking = () => {
        setIsProcessing(true);
        setIsThinkingVisible(true);
        
        // Simulate progressive thinking steps
        setTimeout(() => {
            setCurrentThinking({
                step: "Initial Analysis",
                reasoning: "Starting to analyze the user's request for a tower defense game...",
                decisions: [],
                implementation: "",
                timestamp: new Date().toISOString()
            });
        }, 500);

        setTimeout(() => {
            setCurrentThinking({
                step: "Game Design Planning",
                reasoning: "The user wants to create a tower defense game. I need to consider the core mechanics: tower placement, enemy waves, resource management, and victory conditions.",
                decisions: [
                    "Use a grid-based system for tower placement",
                    "Implement wave-based enemy spawning"
                ],
                implementation: "",
                timestamp: new Date().toISOString()
            });
        }, 2000);

        setTimeout(() => {
            setCurrentThinking(sampleThinkingProcess);
            setIsProcessing(false);
        }, 4000);
    };

    const clearThinking = () => {
        setCurrentThinking(null);
        setIsThinkingVisible(false);
        setIsProcessing(false);
    };

    return (
        <div className="max-w-4xl mx-auto p-6 space-y-6" data-testid="ai-thinking-test">
            <Card>
                <CardHeader>
                    <CardTitle>AI Thinking Display Test</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex space-x-2">
                        <Button onClick={simulateThinking} disabled={isProcessing}>
                            {isProcessing ? 'Processing...' : 'Simulate AI Thinking'}
                        </Button>
                        <Button variant="outline" onClick={clearThinking}>
                            Clear Thinking
                        </Button>
                        <Button 
                            variant="outline" 
                            onClick={() => setIsThinkingVisible(!isThinkingVisible)}
                            disabled={!currentThinking}
                        >
                            {isThinkingVisible ? 'Hide' : 'Show'} Thinking
                        </Button>
                    </div>

                    {/* Thinking Indicator (shows when thinking but display is hidden) */}
                    {isProcessing && !isThinkingVisible && (
                        <ThinkingIndicator
                            isThinking={isProcessing}
                            onShow={() => setIsThinkingVisible(true)}
                        />
                    )}

                    {/* AI Thinking Display */}
                    {currentThinking && (
                        <div data-testid="thinking-display">
                            <AIThinkingDisplay
                                thinking={currentThinking}
                                isVisible={isThinkingVisible}
                                onToggle={() => setIsThinkingVisible(!isThinkingVisible)}
                            />
                        </div>
                    )}

                    {/* Sample Chat Message */}
                    <Card className="bg-muted">
                        <CardContent className="p-4">
                            <div className="flex items-start space-x-2">
                                <div className="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-primary-foreground text-sm font-medium">
                                    U
                                </div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium mb-1">User</p>
                                    <p className="text-sm">Create a tower defense game with multiple tower types and enemy waves.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {currentThinking && !isProcessing && (
                        <Card className="bg-muted">
                            <CardContent className="p-4">
                                <div className="flex items-start space-x-2">
                                    <div className="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                                        AI
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-medium mb-1">AI Assistant</p>
                                        <p className="text-sm">I'll help you create a tower defense game! Let me start by setting up the basic game structure with a grid system for tower placement...</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}