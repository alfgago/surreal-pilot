import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import MobileGameControls from '../../resources/js/components/gdevelop/MobileGameControls';

// Mock navigator.vibrate
Object.defineProperty(navigator, 'vibrate', {
    writable: true,
    value: jest.fn()
});

// Mock window dimensions
Object.defineProperty(window, 'innerWidth', {
    writable: true,
    configurable: true,
    value: 375
});

Object.defineProperty(window, 'innerHeight', {
    writable: true,
    configurable: true,
    value: 667
});

describe('MobileGameControls', () => {
    const mockOnControlInput = jest.fn();

    beforeEach(() => {
        mockOnControlInput.mockClear();
        (navigator.vibrate as jest.Mock).mockClear();
    });

    const defaultProps = {
        gameType: 'platformer' as const,
        controlScheme: 'virtual_dpad' as const,
        onControlInput: mockOnControlInput,
        isVisible: true
    };

    it('renders mobile controls with correct game type badge', () => {
        render(<MobileGameControls {...defaultProps} />);
        
        expect(screen.getByText('Mobile Controls')).toBeInTheDocument();
        expect(screen.getByText('platformer')).toBeInTheDocument();
    });

    it('renders virtual D-pad for platformer games', () => {
        render(<MobileGameControls {...defaultProps} />);
        
        // Should have directional buttons
        expect(screen.getByRole('button', { name: /up/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /down/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /left/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /right/i })).toBeInTheDocument();
        
        // Should have action buttons
        expect(screen.getAllByRole('button')).toHaveLength(7); // 4 directions + 2 actions + 1 hide button
    });

    it('renders gesture area for touch-direct control scheme', () => {
        render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_direct"
            />
        );
        
        expect(screen.getByText('Touch & Gesture Area')).toBeInTheDocument();
        expect(screen.getByText('Tap, drag, swipe, or long press')).toBeInTheDocument();
    });

    it('handles button press events correctly', async () => {
        render(<MobileGameControls {...defaultProps} />);
        
        const upButton = screen.getByRole('button', { name: /up/i });
        
        // Test button press
        fireEvent.mouseDown(upButton);
        expect(mockOnControlInput).toHaveBeenCalledWith({
            type: 'button',
            action: 'up_press'
        });
        
        // Test button release
        fireEvent.mouseUp(upButton);
        expect(mockOnControlInput).toHaveBeenCalledWith({
            type: 'button',
            action: 'up_release'
        });
    });

    it('handles touch events on gesture area', async () => {
        render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_direct"
            />
        );
        
        const gestureArea = screen.getByText('Touch & Gesture Area').closest('div');
        
        // Mock touch event
        const touchEvent = {
            touches: [{
                clientX: 100,
                clientY: 150,
                force: 0.5
            }]
        };
        
        fireEvent.touchStart(gestureArea!, touchEvent);
        
        expect(mockOnControlInput).toHaveBeenCalledWith({
            type: 'touch',
            action: 'touch_start',
            position: { x: expect.any(Number), y: expect.any(Number) },
            pressure: 0.5
        });
    });

    it('detects swipe gestures correctly', async () => {
        render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_gesture"
            />
        );
        
        const gestureArea = screen.getByText('Touch & Gesture Area').closest('div');
        
        // Start touch
        fireEvent.touchStart(gestureArea!, {
            touches: [{ clientX: 100, clientY: 100, force: 0.5 }]
        });
        
        // Move touch (swipe right)
        fireEvent.touchMove(gestureArea!, {
            touches: [{ clientX: 200, clientY: 100, force: 0.5 }]
        });
        
        // End touch
        fireEvent.touchEnd(gestureArea!, {
            touches: []
        });
        
        await waitFor(() => {
            expect(mockOnControlInput).toHaveBeenCalledWith({
                type: 'gesture',
                action: 'swipe_right',
                position: { x: expect.any(Number), y: expect.any(Number) },
                duration: expect.any(Number)
            });
        });
    });

    it('detects long press gestures', async () => {
        jest.useFakeTimers();
        
        render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_direct"
            />
        );
        
        const gestureArea = screen.getByText('Touch & Gesture Area').closest('div');
        
        // Start touch
        fireEvent.touchStart(gestureArea!, {
            touches: [{ clientX: 100, clientY: 100, force: 0.5 }]
        });
        
        // Advance time to simulate long press
        jest.advanceTimersByTime(600);
        
        // End touch
        fireEvent.touchEnd(gestureArea!, {
            touches: []
        });
        
        await waitFor(() => {
            expect(mockOnControlInput).toHaveBeenCalledWith({
                type: 'gesture',
                action: 'long_press',
                position: { x: expect.any(Number), y: expect.any(Number) },
                duration: expect.any(Number)
            });
        });
        
        jest.useRealTimers();
    });

    it('triggers haptic feedback on interactions', () => {
        render(<MobileGameControls {...defaultProps} />);
        
        const upButton = screen.getByRole('button', { name: /up/i });
        fireEvent.mouseDown(upButton);
        
        expect(navigator.vibrate).toHaveBeenCalledWith([10]);
    });

    it('can disable haptic feedback', () => {
        render(<MobileGameControls {...defaultProps} />);
        
        // Click haptic feedback toggle
        const hapticButton = screen.getByTitle(/disable haptic feedback/i);
        fireEvent.click(hapticButton);
        
        // Now button presses shouldn't trigger vibration
        const upButton = screen.getByRole('button', { name: /up/i });
        fireEvent.mouseDown(upButton);
        
        expect(navigator.vibrate).not.toHaveBeenCalled();
    });

    it('shows appropriate instructions for different control schemes', () => {
        const { rerender } = render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="virtual_dpad"
            />
        );
        
        expect(screen.getByText('• Use D-pad for movement')).toBeInTheDocument();
        
        rerender(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_direct"
            />
        );
        
        expect(screen.getByText('• Tap to interact with game elements')).toBeInTheDocument();
        
        rerender(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="drag_drop"
            />
        );
        
        expect(screen.getByText('• Tap to select pieces')).toBeInTheDocument();
    });

    it('detects device orientation changes', () => {
        render(<MobileGameControls {...defaultProps} />);
        
        // Initially portrait (height > width)
        expect(screen.getByTitle(/smartphone/i)).toBeInTheDocument();
        
        // Change to landscape
        Object.defineProperty(window, 'innerWidth', { value: 667 });
        Object.defineProperty(window, 'innerHeight', { value: 375 });
        
        fireEvent(window, new Event('resize'));
        
        // Should show tablet icon for landscape
        expect(screen.getByTitle(/tablet/i)).toBeInTheDocument();
    });

    it('can be hidden and shown', () => {
        render(<MobileGameControls {...defaultProps} isVisible={false} />);
        
        // Should show only the show button
        expect(screen.getByRole('button')).toBeInTheDocument();
        expect(screen.queryByText('Mobile Controls')).not.toBeInTheDocument();
        
        // Click to show controls
        fireEvent.click(screen.getByRole('button'));
        
        // Controls should now be visible
        expect(screen.getByText('Mobile Controls')).toBeInTheDocument();
    });

    it('shows touch feedback when active', () => {
        render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_direct"
            />
        );
        
        const gestureArea = screen.getByText('Touch & Gesture Area').closest('div');
        
        // Start touch
        fireEvent.touchStart(gestureArea!, {
            touches: [{ clientX: 100, clientY: 150, force: 0.8 }]
        });
        
        // Should show touch feedback
        expect(screen.getByText('Touch Active:')).toBeInTheDocument();
        expect(screen.getByText('100, 150')).toBeInTheDocument();
        expect(screen.getByText('80%')).toBeInTheDocument(); // Pressure
    });

    it('adapts to different game types', () => {
        const gameTypes = ['platformer', 'tower-defense', 'puzzle', 'arcade'] as const;
        
        gameTypes.forEach(gameType => {
            const { rerender } = render(
                <MobileGameControls 
                    {...defaultProps} 
                    gameType={gameType}
                />
            );
            
            expect(screen.getByText(gameType)).toBeInTheDocument();
            
            // Each game type should have appropriate controls
            if (gameType === 'platformer') {
                expect(screen.getByRole('button', { name: /up/i })).toBeInTheDocument();
            }
        });
    });

    it('handles touch events with proper touch action prevention', () => {
        render(
            <MobileGameControls 
                {...defaultProps} 
                controlScheme="touch_direct"
            />
        );
        
        const gestureArea = screen.getByText('Touch & Gesture Area').closest('div');
        
        // Should have touch-action: none style
        expect(gestureArea).toHaveStyle({ touchAction: 'none' });
    });
});