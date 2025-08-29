import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';

declare global {
    interface Window {
        axios: AxiosInstance;
        Ziggy: any;
    }

    var route: typeof ziggyRoute;
}

declare module '@inertiajs/react' {
    interface PageProps extends import('./index').InertiaSharedProps {}
}

declare module 'ziggy-js' {
    export function route(): any;
    export function route(name: string, params?: any, absolute?: boolean, config?: any): string;
}

export {};