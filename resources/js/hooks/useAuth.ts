import { usePage } from '@inertiajs/react';
import { User, PageProps } from '@/types';

export function useAuth() {
    const { auth } = usePage<PageProps>().props;
    
    return {
        user: auth.user as User | null,
        isAuthenticated: !!auth.user,
    };
}