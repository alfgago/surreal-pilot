export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    current_company_id?: number;
    created_at: string;
    updated_at: string;
}

export interface Company {
    id: number;
    name: string;
    slug: string;
    credits: number;
    subscription_plan_id?: number;
    created_at: string;
    updated_at: string;
}

export interface Workspace {
    id: number;
    name: string;
    engine_type: 'unreal' | 'playcanvas';
    company_id: number;
    user_id: number;
    created_at: string;
    updated_at: string;
    games?: Game[];
    conversations?: ChatConversation[];
}

export interface Game {
    id: number;
    name: string;
    engine: 'unreal' | 'playcanvas';
    workspace_id: number;
    demo_template_id?: number;
    created_at: string;
    updated_at: string;
    workspace?: Workspace;
    template?: DemoTemplate;
}

export interface ChatConversation {
    id: number;
    title: string;
    workspace_id: number;
    user_id: number;
    created_at: string;
    updated_at: string;
    messages?: ChatMessage[];
    workspace?: Workspace;
}

export interface ChatMessage {
    id: number;
    conversation_id: number;
    user_id?: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
    metadata?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface DemoTemplate {
    id: number;
    name: string;
    description?: string;
    engine: 'unreal' | 'playcanvas';
    template_data?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface CreditTransaction {
    id: number;
    company_id: number;
    user_id?: number;
    amount: number;
    type: 'purchase' | 'usage' | 'refund' | 'bonus';
    description?: string;
    metadata?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface SubscriptionPlan {
    id: number;
    name: string;
    price: number;
    credits_included: number;
    features: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface MultiplayerSession {
    id: number;
    game_id: number;
    host_user_id: number;
    session_code: string;
    status: 'active' | 'ended';
    max_players: number;
    current_players: number;
    created_at: string;
    updated_at: string;
}

export interface Patch {
    id: number;
    workspace_id: number;
    user_id: number;
    file_path: string;
    original_content?: string;
    patch_content: string;
    applied: boolean;
    created_at: string;
    updated_at: string;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    flash: {
        success?: string;
        error?: string;
    };
    ziggy?: {
        location: string;
        query: Record<string, string>;
    };
    [key: string]: any;
}

export type InertiaSharedProps = PageProps;

// Form data types
export interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface WorkspaceForm {
    name: string;
    engine: 'unreal' | 'playcanvas';
}

export interface GameForm {
    name: string;
    demo_template_id?: number;
}

export interface ChatMessageForm {
    content: string;
    conversation_id?: number;
}

// API Response types
export interface ApiResponse<T = any> {
    data: T;
    message?: string;
    status: 'success' | 'error';
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

// Engine specific types
export interface EngineStatus {
    connected: boolean;
    version?: string;
    last_ping?: string;
}

export interface PlayCanvasProject {
    id: string;
    name: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

export interface UnrealProject {
    name: string;
    path: string;
    engine_version: string;
    last_opened?: string;
}

// Chat streaming types
export interface StreamingMessage {
    id: string;
    content: string;
    role: 'assistant';
    finished: boolean;
}

// AI Thinking Process types
export interface ThinkingProcess {
    step: string;
    reasoning: string;
    decisions: string[];
    implementation: string;
    timestamp: string;
}

export interface ThinkingStep {
    title: string;
    content: string;
    type: 'analysis' | 'decision' | 'implementation' | 'validation';
    duration: number;
    timestamp: string;
}

// Error types
export interface ValidationErrors {
    [key: string]: string[];
}

export interface ApiError {
    message: string;
    errors?: ValidationErrors;
    code?: string;
}