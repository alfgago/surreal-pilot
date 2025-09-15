import { useState, useCallback, useRef } from 'react';
import axios from 'axios';

interface StreamOptions {
    onError?: (error: any) => void;
    onComplete?: () => void;
}

interface UseStreamReturn {
    data: string;
    send: (data: any) => Promise<void>;
    isStreaming: boolean;
    error: string | null;
}

export function useStream(url: string, options: StreamOptions = {}): UseStreamReturn {
    const [data, setData] = useState<string>('');
    const [isStreaming, setIsStreaming] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const abortControllerRef = useRef<AbortController | null>(null);

    const send = useCallback(async (requestData: any) => {
        // Reset state
        setData('');
        setError(null);
        setIsStreaming(true);

        // Create abort controller for cancellation
        abortControllerRef.current = new AbortController();

        try {
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/plain',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(requestData),
                signal: abortControllerRef.current.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Check if response body is available
            if (!response.body) {
                throw new Error('Response body is not available');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            try {
                while (true) {
                    const { done, value } = await reader.read();

                    if (done) {
                        break;
                    }

                    const chunk = decoder.decode(value, { stream: true });
                    setData(prev => prev + chunk);
                }
            } finally {
                reader.releaseLock();
            }

            options.onComplete?.();
        } catch (err: any) {
            if (err.name !== 'AbortError') {
                console.error('Stream error:', err);
                setError(err.message || 'An error occurred during streaming');
                options.onError?.(err);
            }
        } finally {
            setIsStreaming(false);
            abortControllerRef.current = null;
        }
    }, [url, options]);

    return {
        data,
        send,
        isStreaming,
        error,
    };
}
