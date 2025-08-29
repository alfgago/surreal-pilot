import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

export function useFlash() {
    const { flash } = usePage<PageProps>().props;
    
    return {
        success: flash.success,
        error: flash.error,
        hasSuccess: !!flash.success,
        hasError: !!flash.error,
        hasMessages: !!(flash.success || flash.error),
    };
}