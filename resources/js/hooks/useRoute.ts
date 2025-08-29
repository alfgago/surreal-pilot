import { usePage } from '@inertiajs/react';
import { route as ziggyRoute } from 'ziggy-js';
import { PageProps } from '@/types';

export function useRoute() {
    const { ziggy } = usePage<PageProps>().props;
    
    return (name: string, params?: any, absolute?: boolean) => {
        return ziggyRoute(name, params, absolute, {
            ...(ziggy || {}),
            location: new URL(ziggy?.location || window.location.href),
        });
    };
}