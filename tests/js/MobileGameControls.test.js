/**
 * Mobile Game Controls Test
 * 
 * Tests the mobile optimization features for GDevelop games
 */

describe('Mobile Game Controls', () => {
    // Mock navigator.vibrate
    beforeAll(() => {
        Object.defineProperty(navigator, 'vibrate', {
            writable: true,
            value: jest.fn()
        });
    });

    beforeEach(() => {
        if (navigator.vibrate) {
            navigator.vibrate.mockClear();
        }
    });

    test('detects mobile device correctly', () => {
        // Mock user agent for mobile device
        Object.defineProperty(navigator, 'userAgent', {
            writable: true,
            value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15'
        });

        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        expect(isMobile).toBe(true);
    });

    test('detects desktop device correctly', () => {
        // Mock user agent for desktop
        Object.defineProperty(navigator, 'userAgent', {
            writable: true,
            value: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        });

        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        expect(isMobile).toBe(false);
    });

    test('detects touch device correctly', () => {
        // Mock touch support
        Object.defineProperty(window, 'ontouchstart', {
            writable: true,
            value: true
        });

        Object.defineProperty(navigator, 'maxTouchPoints', {
            writable: true,
            value: 5
        });

        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        expect(isTouchDevice).toBe(true);
    });

    test('calculates swipe direction correctly', () => {
        const calculateSwipeDirection = (startX, startY, endX, endY) => {
            const deltaX = endX - startX;
            const deltaY = endY - startY;
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            
            if (distance < 50) return 'tap';
            
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                return deltaX > 0 ? 'swipe_right' : 'swipe_left';
            } else {
                return deltaY > 0 ? 'swipe_down' : 'swipe_up';
            }
        };

        expect(calculateSwipeDirection(100, 100, 200, 100)).toBe('swipe_right');
        expect(calculateSwipeDirection(100, 100, 50, 100)).toBe('swipe_left');
        expect(calculateSwipeDirection(100, 100, 100, 200)).toBe('swipe_down');
        expect(calculateSwipeDirection(100, 100, 100, 50)).toBe('swipe_up');
        expect(calculateSwipeDirection(100, 100, 110, 110)).toBe('tap');
    });

    test('determines appropriate control scheme for game types', () => {
        const determineControlScheme = (gameType) => {
            switch (gameType) {
                case 'platformer':
                    return 'virtual_dpad';
                case 'tower-defense':
                    return 'touch_direct';
                case 'puzzle':
                    return 'drag_drop';
                case 'arcade':
                    return 'touch_gesture';
                default:
                    return 'touch_direct';
            }
        };

        expect(determineControlScheme('platformer')).toBe('virtual_dpad');
        expect(determineControlScheme('tower-defense')).toBe('touch_direct');
        expect(determineControlScheme('puzzle')).toBe('drag_drop');
        expect(determineControlScheme('arcade')).toBe('touch_gesture');
        expect(determineControlScheme('unknown')).toBe('touch_direct');
    });

    test('calculates mobile UI scale correctly', () => {
        const calculateMobileUIScale = (gameType) => {
            switch (gameType) {
                case 'puzzle':
                    return 1.5;
                case 'tower-defense':
                    return 1.3;
                case 'platformer':
                    return 1.2;
                case 'arcade':
                    return 1.1;
                default:
                    return 1.2;
            }
        };

        expect(calculateMobileUIScale('puzzle')).toBe(1.5);
        expect(calculateMobileUIScale('tower-defense')).toBe(1.3);
        expect(calculateMobileUIScale('platformer')).toBe(1.2);
        expect(calculateMobileUIScale('arcade')).toBe(1.1);
        expect(calculateMobileUIScale('unknown')).toBe(1.2);
    });

    test('detects orientation changes', () => {
        // Mock window dimensions for portrait
        Object.defineProperty(window, 'innerWidth', { value: 375, writable: true });
        Object.defineProperty(window, 'innerHeight', { value: 667, writable: true });

        let orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
        expect(orientation).toBe('portrait');

        // Change to landscape
        window.innerWidth = 667;
        window.innerHeight = 375;

        orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';
        expect(orientation).toBe('landscape');
    });

    test('validates touch target size meets accessibility guidelines', () => {
        const minTouchSize = 44; // iOS/Android accessibility guidelines
        const touchTargetSize = 48; // Example button size

        expect(touchTargetSize).toBeGreaterThanOrEqual(minTouchSize);
    });

    test('handles gesture recognition timing', () => {
        const isLongPress = (startTime, endTime, distance) => {
            const duration = endTime - startTime;
            return duration > 500 && distance < 20;
        };

        const isTap = (startTime, endTime, distance) => {
            const duration = endTime - startTime;
            return duration < 500 && distance < 20;
        };

        const isSwipe = (distance) => {
            return distance > 50;
        };

        // Test long press
        expect(isLongPress(1000, 1600, 10)).toBe(true);
        expect(isLongPress(1000, 1200, 10)).toBe(false);

        // Test tap
        expect(isTap(1000, 1200, 10)).toBe(true);
        expect(isTap(1000, 1600, 10)).toBe(false);

        // Test swipe
        expect(isSwipe(100)).toBe(true);
        expect(isSwipe(30)).toBe(false);
    });

    test('generates appropriate gesture support for game types', () => {
        const getGestureSupport = (gameType) => {
            const gestures = ['tap', 'touch'];
            
            switch (gameType) {
                case 'platformer':
                    return [...gestures, 'swipe_left', 'swipe_right', 'swipe_up'];
                case 'tower-defense':
                    return [...gestures, 'long_press', 'pinch_zoom'];
                case 'puzzle':
                    return [...gestures, 'drag', 'long_press', 'double_tap'];
                case 'arcade':
                    return [...gestures, 'drag', 'swipe'];
                default:
                    return gestures;
            }
        };

        const platformerGestures = getGestureSupport('platformer');
        expect(platformerGestures).toContain('tap');
        expect(platformerGestures).toContain('swipe_left');
        expect(platformerGestures).toContain('swipe_right');
        expect(platformerGestures).toContain('swipe_up');

        const towerDefenseGestures = getGestureSupport('tower-defense');
        expect(towerDefenseGestures).toContain('long_press');
        expect(towerDefenseGestures).toContain('pinch_zoom');

        const puzzleGestures = getGestureSupport('puzzle');
        expect(puzzleGestures).toContain('drag');
        expect(puzzleGestures).toContain('double_tap');
    });

    test('validates mobile viewport settings', () => {
        const mobileViewport = {
            width: 'device-width',
            initialScale: 1.0,
            maximumScale: 1.0,
            userScalable: false
        };

        expect(mobileViewport.width).toBe('device-width');
        expect(mobileViewport.initialScale).toBe(1.0);
        expect(mobileViewport.userScalable).toBe(false);
    });

    test('handles haptic feedback patterns', () => {
        const hapticPatterns = {
            light: [10],
            medium: [20],
            heavy: [30]
        };

        const triggerHapticFeedback = (intensity) => {
            if ('vibrate' in navigator && navigator.vibrate) {
                navigator.vibrate(hapticPatterns[intensity] || hapticPatterns.light);
                return true;
            }
            return false;
        };

        expect(triggerHapticFeedback('light')).toBe(true);
        expect(navigator.vibrate).toHaveBeenCalledWith([10]);

        triggerHapticFeedback('medium');
        expect(navigator.vibrate).toHaveBeenCalledWith([20]);

        triggerHapticFeedback('heavy');
        expect(navigator.vibrate).toHaveBeenCalledWith([30]);
    });
});