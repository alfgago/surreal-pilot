import axios from 'axios';

export interface EngineStatus {
    status: 'connected' | 'disconnected' | 'error' | 'connecting';
    message?: string;
    details?: any;
}

export interface UnrealConnectionStatus {
    connected: boolean;
    version?: string;
    plugin_version?: string;
    project_name?: string;
    last_ping?: string;
    error?: string;
}

export interface PlayCanvasStatus {
    mcp_running: boolean;
    port?: number;
    preview_available: boolean;
    preview_url?: string;
    last_update?: string;
    error?: string;
}

export interface Workspace {
    id: number;
    name: string;
    engine_type: 'playcanvas' | 'unreal';
    status: string;
    preview_url?: string;
    published_url?: string;
    mcp_port?: number;
    mcp_pid?: number;
}

class EngineService {
    private baseUrl = '/api';

    /**
     * Get engine status for a workspace
     */
    async getEngineStatus(workspaceId: number): Promise<EngineStatus> {
        try {
            const response = await axios.get(`${this.baseUrl}/workspaces/${workspaceId}/engine/status`);
            return response.data;
        } catch (error) {
            console.error('Failed to get engine status:', error);
            return {
                status: 'error',
                message: 'Failed to get engine status',
                details: error
            };
        }
    }

    /**
     * Get Unreal Engine connection status
     */
    async getUnrealConnectionStatus(workspaceId: number): Promise<UnrealConnectionStatus> {
        try {
            const response = await axios.get(`${this.baseUrl}/workspaces/${workspaceId}/unreal/status`);
            return response.data;
        } catch (error) {
            console.error('Failed to get Unreal connection status:', error);
            return {
                connected: false,
                error: 'Failed to connect to Unreal Engine'
            };
        }
    }

    /**
     * Test Unreal Engine connection
     */
    async testUnrealConnection(workspaceId: number, settings?: any): Promise<UnrealConnectionStatus> {
        try {
            const response = await axios.post(`${this.baseUrl}/workspaces/${workspaceId}/unreal/test`, settings);
            return response.data;
        } catch (error) {
            console.error('Failed to test Unreal connection:', error);
            return {
                connected: false,
                error: 'Connection test failed'
            };
        }
    }

    /**
     * Get PlayCanvas status
     */
    async getPlayCanvasStatus(workspaceId: number): Promise<PlayCanvasStatus> {
        try {
            const response = await axios.get(`${this.baseUrl}/workspaces/${workspaceId}/playcanvas/status`);
            return response.data;
        } catch (error) {
            console.error('Failed to get PlayCanvas status:', error);
            return {
                mcp_running: false,
                preview_available: false,
                error: 'Failed to get PlayCanvas status'
            };
        }
    }

    /**
     * Refresh PlayCanvas preview
     */
    async refreshPlayCanvasPreview(workspaceId: number): Promise<{ success: boolean; preview_url?: string; error?: string }> {
        try {
            const response = await axios.post(`${this.baseUrl}/workspaces/${workspaceId}/playcanvas/refresh`);
            return response.data;
        } catch (error) {
            console.error('Failed to refresh PlayCanvas preview:', error);
            return {
                success: false,
                error: 'Failed to refresh preview'
            };
        }
    }

    /**
     * Start PlayCanvas MCP server
     */
    async startPlayCanvasMcp(workspaceId: number): Promise<{ success: boolean; port?: number; preview_url?: string; error?: string }> {
        try {
            const response = await axios.post(`${this.baseUrl}/workspaces/${workspaceId}/playcanvas/start`);
            return response.data;
        } catch (error) {
            console.error('Failed to start PlayCanvas MCP:', error);
            return {
                success: false,
                error: 'Failed to start MCP server'
            };
        }
    }

    /**
     * Stop PlayCanvas MCP server
     */
    async stopPlayCanvasMcp(workspaceId: number): Promise<{ success: boolean; error?: string }> {
        try {
            const response = await axios.post(`${this.baseUrl}/workspaces/${workspaceId}/playcanvas/stop`);
            return response.data;
        } catch (error) {
            console.error('Failed to stop PlayCanvas MCP:', error);
            return {
                success: false,
                error: 'Failed to stop MCP server'
            };
        }
    }

    /**
     * Get workspace context for AI
     */
    async getWorkspaceContext(workspaceId: number): Promise<any> {
        try {
            const response = await axios.get(`${this.baseUrl}/workspaces/${workspaceId}/context`);
            return response.data;
        } catch (error) {
            console.error('Failed to get workspace context:', error);
            return null;
        }
    }

    /**
     * Send AI message with engine context
     */
    async sendMessageWithContext(workspaceId: number, message: string, includeContext = true): Promise<any> {
        try {
            const payload: any = {
                message,
                workspace_id: workspaceId
            };

            if (includeContext) {
                const context = await this.getWorkspaceContext(workspaceId);
                if (context) {
                    payload.context = context;
                }
            }

            const response = await axios.post(`${this.baseUrl}/chat/message`, payload);
            return response.data;
        } catch (error) {
            console.error('Failed to send message with context:', error);
            throw error;
        }
    }

    /**
     * Get engine-specific AI configuration
     */
    async getEngineAiConfig(engineType: 'playcanvas' | 'unreal'): Promise<any> {
        try {
            const response = await axios.get(`${this.baseUrl}/engine/${engineType}/ai-config`);
            return response.data;
        } catch (error) {
            console.error('Failed to get engine AI config:', error);
            return null;
        }
    }

    /**
     * Update engine-specific AI configuration
     */
    async updateEngineAiConfig(engineType: 'playcanvas' | 'unreal', config: any): Promise<{ success: boolean; error?: string }> {
        try {
            const response = await axios.put(`${this.baseUrl}/engine/${engineType}/ai-config`, config);
            return response.data;
        } catch (error) {
            console.error('Failed to update engine AI config:', error);
            return {
                success: false,
                error: 'Failed to update AI configuration'
            };
        }
    }
}

export const engineService = new EngineService();