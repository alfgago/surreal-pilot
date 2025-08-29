import { usePage } from '@inertiajs/react';

interface PageProps {
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
        };
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

export function useAuth() {
    const { props } = usePage<PageProps>();
    
    return {
        user: props.auth?.user || null,
        isAuthenticated: !!props.auth?.user,
    };
}

export function useFlash() {
    const { props } = usePage<PageProps>();
    
    return {
        success: props.flash?.success || null,
        error: props.flash?.error || null,
    };
}