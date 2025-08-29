import { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Gamepad2 } from 'lucide-react';

interface AuthLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AuthLayout({ children, title }: AuthLayoutProps) {
    return (
        <>
            {title && <Head title={title} />}
            <div className="min-h-screen bg-gradient-to-br from-background to-muted flex flex-col justify-center py-12 sm:px-6 lg:px-8">
                <div className="sm:mx-auto sm:w-full sm:max-w-md">
                    {/* Logo */}
                    <div className="flex justify-center">
                        <Link href="/" className="flex items-center space-x-2">
                            <div className="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                                <Gamepad2 className="w-6 h-6 text-primary-foreground" />
                            </div>
                            <span className="font-serif font-black text-2xl text-foreground">SurrealPilot</span>
                        </Link>
                    </div>
                    
                    {/* Tagline */}
                    <p className="mt-2 text-center text-sm text-muted-foreground">
                        AI-powered game development copilot
                    </p>
                </div>

                <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                    <div className="bg-card py-8 px-4 shadow-lg sm:rounded-lg sm:px-10 border border-border">
                        {children}
                    </div>
                </div>

                {/* Footer */}
                <div className="mt-8 text-center">
                    <p className="text-xs text-muted-foreground">
                        © 2025 SurrealPilot. All rights reserved.
                    </p>
                </div>
            </div>
        </>
    );
}