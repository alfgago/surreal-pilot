import { router } from '@inertiajs/react';

export interface WebSocketMessage {
    event: string;
    data: any;
    channel?: string;
}

export interface WebSocketOptions {
    onOpen?: () => void;
    onClose?: () => void;
    onError?: (error: Event) => void;
    onMessage?: (message: WebSocketMessage) => void;
    reconnectInterval?: number;
    maxReconnectAttempts?: number;
    heartbeatInterval?: number;
}

export class WebSocketService {
    private ws: WebSocket | null = null;
    private url: string;
    private options: WebSocketOptions;
    private reconnectAttempts = 0;
    private isConnected = false;
    private heartbeatTimer: NodeJS.Timeout | null = null;
    private eventListeners: Map<string, ((data: any) => void)[]> = new Map();
    private channels: Set<string> = new Set();

    constructor(url: string, options: WebSocketOptions = {}) {
        this.url = url;
        this.options = {
            reconnectInterval: 3000,
            maxReconnectAttempts: 5,
            heartbeatInterval: 30000,
            ...options,
        };
    }

    connect(): void {
        if (this.ws) {
            this.disconnect();
        }

        try {
            this.ws = new WebSocket(this.url);

            this.ws.onopen = () => {
                console.log('WebSocket connection opened');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.startHeartbeat();
                this.options.onOpen?.();
                
                // Resubscribe to channels
                this.channels.forEach(channel => {
                    this.subscribe(channel);
                });
            };

            this.ws.onclose = () => {
                console.log('WebSocket connection closed');
                this.isConnected = false;
                this.stopHeartbeat();
                this.options.onClose?.();
                this.handleReconnect();
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.options.onError?.(error);
            };

            this.ws.onmessage = (event) => {
                try {
                    const message: WebSocketMessage = JSON.parse(event.data);
                    this.handleMessage(message);
                } catch (error) {
                    console.error('Failed to parse WebSocket message:', error);
                }
            };

        } catch (error) {
            console.error('Failed to create WebSocket connection:', error);
            this.handleReconnect();
        }
    }

    disconnect(): void {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.isConnected = false;
        this.stopHeartbeat();
    }

    subscribe(channel: string): void {
        this.channels.add(channel);
        
        if (this.isConnected && this.ws) {
            this.send({
                event: 'subscribe',
                data: { channel },
            });
        }
    }

    unsubscribe(channel: string): void {
        this.channels.delete(channel);
        
        if (this.isConnected && this.ws) {
            this.send({
                event: 'unsubscribe',
                data: { channel },
            });
        }
    }

    send(message: WebSocketMessage): void {
        if (this.isConnected && this.ws) {
            this.ws.send(JSON.stringify(message));
        } else {
            console.warn('WebSocket not connected, message not sent:', message);
        }
    }

    on(eventType: string, callback: (data: any) => void): void {
        if (!this.eventListeners.has(eventType)) {
            this.eventListeners.set(eventType, []);
        }
        this.eventListeners.get(eventType)!.push(callback);
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

    private handleMessage(message: WebSocketMessage): void {
        // Handle system messages
        if (message.event === 'pong') {
            return; // Heartbeat response
        }

        // Notify global message handler
        this.options.onMessage?.(message);

        // Notify specific event listeners
        const listeners = this.eventListeners.get(message.event) || [];
        listeners.forEach(callback => {
            try {
                callback(message.data);
            } catch (error) {
                console.error(`Error in WebSocket event handler for ${message.event}:`, error);
            }
        });
    }

    private startHeartbeat(): void {
        if (this.options.heartbeatInterval && this.options.heartbeatInterval > 0) {
            this.heartbeatTimer = setInterval(() => {
                if (this.isConnected) {
                    this.send({
                        event: 'ping',
                        data: { timestamp: Date.now() },
                    });
                }
            }, this.options.heartbeatInterval);
        }
    }

    private stopHeartbeat(): void {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    private handleReconnect(): void {
        if (this.reconnectAttempts >= (this.options.maxReconnectAttempts || 5)) {
            console.error('Max WebSocket reconnection attempts reached');
            return;
        }

        this.reconnectAttempts++;
        console.log(`Attempting to reconnect WebSocket (${this.reconnectAttempts}/${this.options.maxReconnectAttempts})...`);

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

    getSubscribedChannels(): string[] {
        return Array.from(this.channels);
    }
}

// Create a singleton instance for the app
let websocketService: WebSocketService | null = null;

export function getWebSocketService(): WebSocketService {
    if (!websocketService) {
        // Use the current domain with ws/wss protocol
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws`;
        
        websocketService = new WebSocketService(wsUrl, {
            onOpen: () => {
                console.log('Global WebSocket connected');
            },
            onClose: () => {
                console.log('Global WebSocket disconnected');
            },
            onError: (error) => {
                // Silently handle WebSocket errors since WebSocket server is not configured
                console.warn('WebSocket connection failed - WebSocket server not configured');
            },
        });
    }
    
    return websocketService;
}