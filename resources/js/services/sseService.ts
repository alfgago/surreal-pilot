export interface SSEEvent {
    event: string;
    data: any;
    timestamp: string;
}

export interface SSEOptions {
    onOpen?: () => void;
    onError?: (error: Event) => void;
    onClose?: () => void;
    reconnectInterval?: number;
    maxReconnectAttempts?: number;
}

export class SSEService {
    private eventSource: EventSource | null = null;
    private url: string;
    private options: SSEOptions;
    private reconnectAttempts = 0;
    private eventListeners: Map<string, ((data: any) => void)[]> = new Map();
    private isConnected = false;

    constructor(url: string, options: SSEOptions = {}) {
        this.url = url;
        this.options = {
            reconnectInterval: 3000,
            maxReconnectAttempts: 5,
            ...options,
        };
    }

    connect(): void {
        if (this.eventSource) {
            this.disconnect();
        }

        try {
            this.eventSource = new EventSource(this.url);

            this.eventSource.onopen = () => {
                console.log('SSE connection opened');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.options.onOpen?.();
            };

            this.eventSource.onerror = (error) => {
                console.error('SSE connection error:', error);
                this.isConnected = false;
                this.options.onError?.(error);
                this.handleReconnect();
            };

            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleEvent('message', data);
                } catch (e) {
                    console.error('Failed to parse SSE message:', e);
                }
            };

            // Set up custom event listeners
            this.eventListeners.forEach((listeners, eventType) => {
                if (this.eventSource) {
                    this.eventSource.addEventListener(eventType, (event: any) => {
                        try {
                            const data = JSON.parse(event.data);
                            this.handleEvent(eventType, data);
                        } catch (e) {
                            console.error(`Failed to parse SSE event ${eventType}:`, e);
                        }
                    });
                }
            });

        } catch (error) {
            console.error('Failed to create SSE connection:', error);
            this.handleReconnect();
        }
    }

    disconnect(): void {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.isConnected = false;
            this.options.onClose?.();
        }
    }

    on(eventType: string, callback: (data: any) => void): void {
        if (!this.eventListeners.has(eventType)) {
            this.eventListeners.set(eventType, []);
        }
        this.eventListeners.get(eventType)!.push(callback);

        // If already connected, add the event listener to the existing EventSource
        if (this.eventSource && this.isConnected) {
            this.eventSource.addEventListener(eventType, (event: any) => {
                try {
                    const data = JSON.parse(event.data);
                    callback(data);
                } catch (e) {
                    console.error(`Failed to parse SSE event ${eventType}:`, e);
                }
            });
        }
    }

    off(eventType: string, callback?: (data: any) => void): void {
        if (!this.eventListeners.has(eventType)) {
            return;
        }

        if (callback) {
            const listeners = this.eventListeners.get(eventType)!;
            const index = listeners.indexOf(callback);
            if (index > -1) {
                listeners.splice(index, 1);
            }
        } else {
            this.eventListeners.delete(eventType);
        }
    }

    private handleEvent(eventType: string, data: any): void {
        const listeners = this.eventListeners.get(eventType) || [];
        listeners.forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error(`Error in SSE event handler for ${eventType}:`, error);
            }
        });
    }

    private handleReconnect(): void {
        if (this.reconnectAttempts >= (this.options.maxReconnectAttempts || 5)) {
            console.error('Max reconnection attempts reached');
            return;
        }

        this.reconnectAttempts++;
        console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.options.maxReconnectAttempts})...`);

        setTimeout(() => {
            this.connect();
        }, this.options.reconnectInterval || 3000);
    }

    getConnectionStatus(): boolean {
        return this.isConnected;
    }

    getReconnectAttempts(): number {
        return this.reconnectAttempts;
    }
}