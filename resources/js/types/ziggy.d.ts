declare module './ziggy' {
    interface ZiggyConfig {
        url: string;
        port: number | null;
        defaults: Record<string, any>;
        routes: Record<string, any>;
    }
    
    export const Ziggy: ZiggyConfig;
}

declare module './ziggy.js' {
    interface ZiggyConfig {
        url: string;
        port: number | null;
        defaults: Record<string, any>;
        routes: Record<string, any>;
    }
    
    export const Ziggy: ZiggyConfig;
}