import { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Gamepad2, LogIn, UserPlus } from 'lucide-react';

interface GuestLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function GuestLayout({ children, title }: GuestLayoutProps) {
    return (
        <>
            {title && <Head title={title} />}
            <div className="min-h-screen bg-background">
                <header className="border-b border-border bg-card/50 backdrop-blur-sm">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center h-16">
                            {/* Logo */}
                            <div className="flex items-center space-x-2">
                                <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                                    <Gamepad2 className="w-5 h-5 text-primary-foreground" />
                                </div>
                                <span className="font-serif font-black text-xl text-foreground">SurrealPilot</span>
                            </div>

                            {/* Navigation */}
                            <div className="flex items-center space-x-4">
                                <Link href="/login">
                                    <Button variant="ghost" size="sm">
                                        <LogIn className="w-4 h-4 mr-2" />
                                        Sign In
                                    </Button>
                                </Link>
                                <Link href="/register">
                                    <Button size="sm">
                                        <UserPlus className="w-4 h-4 mr-2" />
                                        Get Started
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </header>
                
                <main>
                    {children}
                </main>

                {/* Footer */}
                <footer className="border-t border-border bg-card/30">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div className="flex flex-col md:flex-row justify-between items-center">
                            <div className="flex items-center space-x-2 mb-4 md:mb-0">
                                <div className="w-6 h-6 bg-primary rounded flex items-center justify-center">
                                    <Gamepad2 className="w-4 h-4 text-primary-foreground" />
                                </div>
                                <span className="font-serif font-black text-foreground">SurrealPilot</span>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                © 2025 SurrealPilot. All rights reserved.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}