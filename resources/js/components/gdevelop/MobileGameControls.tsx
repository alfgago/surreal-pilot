import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowUp, 
    ArrowDown, 
    ArrowLeft, 
    ArrowRight,
    Circle,
    Square,
    Triangle,
    Gamepad2,
    Settings,
    RotateCcw,
    Maximize,
    Volume2,
    VolumeX,
    Smartphone,
    Tablet
} from 'lucide-react';

interface MobileGameControlsProps {
    gameType: 'platformer' | 'tower-defense' | 'puzzle' | 'arcade' | 'basic';
    controlScheme: 'virtual_dpad' | 'touch_direct' | 'drag_drop' | 'touch_gesture';
    onControlInput: (input: ControlInput) => void;
    isVisible?: boolean;
    className?: string;
}

interface ControlInput {
    type: 'button' | 'gesture' | 'touch';
    action: string;
    position?: { x: number; y: number };
    pressure?: number;
    duration?: number;
}

interface TouchState {
    isActive: boolean;
    startPosition: { x: number; y: number };
    currentPosition: { x: number; y: number };
    startTime: number;
    pressure: number;
}

export default function MobileGameControls({
    gameType,
    controlScheme,
    onControlInput,
    isVisible = true,
    className = ''
}: MobileGameControlsProps) {
    const [touchState, setTouchState] = useState<TouchState>({
        isActive: false,
        startPosition: { x: 0, y: 0 },
        currentPosition: { x: 0, y: 0 },
        startTime: 0,
        pressure: 0
    });

    const [activeButtons, setActiveButtons] = useState<Set<string>>(new Set());
    const [controlsVisible, setControlsVisible] = useState(isVisible);
    const [deviceOrientation, setDeviceOrientation] = useState<'portrait' | 'landscape'>('portrait');
    const [hapticFeedback, setHapticFeedback] = useState(true);

    const controlsRef = useRef<HTMLDivElement>(null);
    const gestureAreaRef = useRef<HTMLDivElement>(null);

    // Detect device orientation
    useEffect(() => {
        const handleOrientationChange = () => {
            const orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
            setDeviceOrientation(orientation);
        };

        handleOrientationChange();
        window.addEventListener('resize', handleOrientationChange);
        window.addEventListener('orientationchange', handleOrientationChange);

        return () => {
            window.removeEventListener('resize', handleOrientationChange);
            window.removeEventListener('orientationchange', handleOrientationChange);
        };
    }, []);

    // Haptic feedback function
    const triggerHapticFeedback = useCallback((intensity: 'light' | 'medium' | 'heavy' = 'light') => {
        if (!hapticFeedback) return;
        
        if ('vibrate' in navigator) {
            const patterns = {
                light: [10],
                medium: [20],
                heavy: [30]
            };
            navigator.vibrate(patterns[intensity]);
        }
    }, [hapticFeedback]);

    // Handle button press
    const handleButtonPress = useCallback((action: string, pressed: boolean) => {
        if (pressed) {
            setActiveButtons(prev => new Set(prev).add(action));
            triggerHapticFeedback('light');
        } else {
            setActiveButtons(prev => {
                const newSet = new Set(prev);
                newSet.delete(action);
                return newSet;
            });
        }

        onControlInput({
            type: 'button',
            action: pressed ? `${action}_press` : `${action}_release`
        });
    }, [onControlInput, triggerHapticFeedback]);

    // Handle touch gestures
    const handleTouchStart = useCallback((event: React.TouchEvent) => {
        const touch = event.touches[0];
        const rect = gestureAreaRef.current?.getBoundingClientRect();
        
        if (!rect) return;

        const x = touch.clientX - rect.left;
        const y = touch.clientY - rect.top;

        setTouchState({
            isActive: true,
            startPosition: { x, y },
            currentPosition: { x, y },
            startTime: Date.now(),
            pressure: touch.force || 0.5
        });

        onControlInput({
            type: 'touch',
            action: 'touch_start',
            position: { x, y },
            pressure: touch.force || 0.5
        });

        triggerHapticFeedback('light');
    }, [onControlInput, triggerHapticFeedback]);

    const handleTouchMove = useCallback((event: React.TouchEvent) => {
        if (!touchState.isActive) return;

        const touch = event.touches[0];
        const rect = gestureAreaRef.current?.getBoundingClientRect();
        
        if (!rect) return;

        const x = touch.clientX - rect.left;
        const y = touch.clientY - rect.top;

        setTouchState(prev => ({
            ...prev,
            currentPosition: { x, y }
        }));

        onControlInput({
            type: 'gesture',
            action: 'drag',
            position: { x, y },
            pressure: touch.force || 0.5
        });
    }, [touchState.isActive, onControlInput]);

    const handleTouchEnd = useCallback((event: React.TouchEvent) => {
        if (!touchState.isActive) return;

        const duration = Date.now() - touchState.startTime;
        const deltaX = touchState.currentPosition.x - touchState.startPosition.x;
        const deltaY = touchState.currentPosition.y - touchState.startPosition.y;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);

        // Determine gesture type
        let gestureType = 'tap';
        
        if (duration > 500 && distance < 20) {
            gestureType = 'long_press';
            triggerHapticFeedback('heavy');
        } else if (distance > 50) {
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                gestureType = deltaX > 0 ? 'swipe_right' : 'swipe_left';
            } else {
                gestureType = deltaY > 0 ? 'swipe_down' : 'swipe_up';
            }
            triggerHapticFeedback('medium');
        } else {
            triggerHapticFeedback('light');
        }

        onControlInput({
            type: 'gesture',
            action: gestureType,
            position: touchState.currentPosition,
            duration
        });

        setTouchState({
            isActive: false,
            startPosition: { x: 0, y: 0 },
            currentPosition: { x: 0, y: 0 },
            startTime: 0,
            pressure: 0
        });
    }, [touchState, onControlInput, triggerHapticFeedback]);

    // Render virtual D-pad for platformer games
    const renderVirtualDPad = () => (
        <div className="grid grid-cols-3 gap-2 w-32 h-32">
            <div></div>
            <Button
                variant={activeButtons.has('up') ? 'default' : 'outline'}
                size="sm"
                className="h-10 w-10 p-0 touch-manipulation"
                onTouchStart={() => handleButtonPress('up', true)}
                onTouchEnd={() => handleButtonPress('up', false)}
                onMouseDown={() => handleButtonPress('up', true)}
                onMouseUp={() => handleButtonPress('up', false)}
            >
                <ArrowUp className="w-4 h-4" />
            </Button>
            <div></div>
            
            <Button
                variant={activeButtons.has('left') ? 'default' : 'outline'}
                size="sm"
                className="h-10 w-10 p-0 touch-manipulation"
                onTouchStart={() => handleButtonPress('left', true)}
                onTouchEnd={() => handleButtonPress('left', false)}
                onMouseDown={() => handleButtonPress('left', true)}
                onMouseUp={() => handleButtonPress('left', false)}
            >
                <ArrowLeft className="w-4 h-4" />
            </Button>
            <div></div>
            <Button
                variant={activeButtons.has('right') ? 'default' : 'outline'}
                size="sm"
                className="h-10 w-10 p-0 touch-manipulation"
                onTouchStart={() => handleButtonPress('right', true)}
                onTouchEnd={() => handleButtonPress('right', false)}
                onMouseDown={() => handleButtonPress('right', true)}
                onMouseUp={() => handleButtonPress('right', false)}
            >
                <ArrowRight className="w-4 h-4" />
            </Button>
            
            <div></div>
            <Button
                variant={activeButtons.has('down') ? 'default' : 'outline'}
                size="sm"
                className="h-10 w-10 p-0 touch-manipulation"
                onTouchStart={() => handleButtonPress('down', true)}
                onTouchEnd={() => handleButtonPress('down', false)}
                onMouseDown={() => handleButtonPress('down', true)}
                onMouseUp={() => handleButtonPress('down', false)}
            >
                <ArrowDown className="w-4 h-4" />
            </Button>
            <div></div>
        </div>
    );

    // Render action buttons
    const renderActionButtons = () => (
        <div className="flex space-x-3">
            <Button
                variant={activeButtons.has('action1') ? 'default' : 'outline'}
                size="lg"
                className="h-14 w-14 rounded-full p-0 touch-manipulation"
                onTouchStart={() => handleButtonPress('action1', true)}
                onTouchEnd={() => handleButtonPress('action1', false)}
                onMouseDown={() => handleButtonPress('action1', true)}
                onMouseUp={() => handleButtonPress('action1', false)}
            >
                <Circle className="w-6 h-6" />
            </Button>
            <Button
                variant={activeButtons.has('action2') ? 'default' : 'outline'}
                size="lg"
                className="h-14 w-14 rounded-full p-0 touch-manipulation"
                onTouchStart={() => handleButtonPress('action2', true)}
                onTouchEnd={() => handleButtonPress('action2', false)}
                onMouseDown={() => handleButtonPress('action2', true)}
                onMouseUp={() => handleButtonPress('action2', false)}
            >
                <Square className="w-6 h-6" />
            </Button>
        </div>
    );

    // Render gesture area for touch-direct games
    const renderGestureArea = () => (
        <div
            ref={gestureAreaRef}
            className="w-full h-48 bg-muted/20 border-2 border-dashed border-muted rounded-lg flex items-center justify-center touch-manipulation"
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
            style={{ touchAction: 'none' }}
        >
            <div className="text-center text-muted-foreground">
                <Smartphone className="w-8 h-8 mx-auto mb-2" />
                <p className="text-sm">Touch & Gesture Area</p>
                <p className="text-xs">Tap, drag, swipe, or long press</p>
            </div>
        </div>
    );

    // Render controls based on scheme
    const renderControls = () => {
        switch (controlScheme) {
            case 'virtual_dpad':
                return (
                    <div className="flex justify-between items-end">
                        {renderVirtualDPad()}
                        {renderActionButtons()}
                    </div>
                );
            
            case 'touch_direct':
            case 'drag_drop':
            case 'touch_gesture':
                return renderGestureArea();
            
            default:
                return renderGestureArea();
        }
    };

    if (!controlsVisible) {
        return (
            <Button
                variant="outline"
                size="sm"
                className="fixed bottom-4 right-4 z-50 touch-manipulation"
                onClick={() => setControlsVisible(true)}
            >
                <Gamepad2 className="w-4 h-4" />
            </Button>
        );
    }

    return (
        <Card className={`fixed bottom-4 left-4 right-4 z-40 ${className}`}>
            <CardContent className="p-4 space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Gamepad2 className="w-4 h-4" />
                        <span className="text-sm font-medium">Mobile Controls</span>
                        <Badge variant="outline" className="text-xs">
                            {gameType}
                        </Badge>
                        <Badge variant="outline" className="text-xs">
                            {deviceOrientation === 'portrait' ? <Smartphone className="w-3 h-3" /> : <Tablet className="w-3 h-3" />}
                        </Badge>
                    </div>
                    
                    <div className="flex items-center space-x-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setHapticFeedback(!hapticFeedback)}
                            title={`${hapticFeedback ? 'Disable' : 'Enable'} haptic feedback`}
                        >
                            {hapticFeedback ? <Volume2 className="w-3 h-3" /> : <VolumeX className="w-3 h-3" />}
                        </Button>
                        
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setControlsVisible(false)}
                            title="Hide controls"
                        >
                            <Maximize className="w-3 h-3" />
                        </Button>
                    </div>
                </div>

                {/* Controls */}
                <div ref={controlsRef}>
                    {renderControls()}
                </div>

                {/* Instructions */}
                <div className="text-xs text-muted-foreground space-y-1">
                    {controlScheme === 'virtual_dpad' && (
                        <div>
                            <p>• Use D-pad for movement</p>
                            <p>• Circle button to jump/action</p>
                            <p>• Square button for secondary action</p>
                        </div>
                    )}
                    {controlScheme === 'touch_direct' && (
                        <div>
                            <p>• Tap to interact with game elements</p>
                            <p>• Long press for context actions</p>
                            <p>• Drag to move objects</p>
                        </div>
                    )}
                    {controlScheme === 'drag_drop' && (
                        <div>
                            <p>• Tap to select pieces</p>
                            <p>• Drag to move selected items</p>
                            <p>• Double tap for quick actions</p>
                        </div>
                    )}
                    {controlScheme === 'touch_gesture' && (
                        <div>
                            <p>• Swipe in any direction to move</p>
                            <p>• Tap to shoot/interact</p>
                            <p>• Pinch to zoom (if supported)</p>
                        </div>
                    )}
                </div>

                {/* Touch feedback indicator */}
                {touchState.isActive && (
                    <div className="text-xs text-muted-foreground">
                        <div className="flex justify-between">
                            <span>Touch Active:</span>
                            <span>
                                {Math.round(touchState.currentPosition.x)}, {Math.round(touchState.currentPosition.y)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span>Pressure:</span>
                            <span>{Math.round(touchState.pressure * 100)}%</span>
                        </div>
                        <div className="flex justify-between">
                            <span>Duration:</span>
                            <span>{Date.now() - touchState.startTime}ms</span>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}