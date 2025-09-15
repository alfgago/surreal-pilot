import { ReactNode, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    MessageSquare,
    FolderOpen,
    BookTemplate as Template,
    Eye,
    Upload,
    Users,
    History,
    CreditCard,
    Settings,
    Gamepad2,
    Code,
    Globe,
    CheckCircle,
    AlertCircle,
    Loader2,
    Zap,
    Menu,
    X,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { PageProps, Workspace } from '@/types';
import { WorkspaceSwitcher } from '@/components/layout/WorkspaceSwitcher';
import { UserMenu } from '@/components/layout/UserMenu';
import CreditBalance from '@/components/billing/CreditBalance';

interface MainLayoutProps {
    children: ReactNode;
    title?: string;
    currentWorkspace?: Workspace;
    workspaces?: Workspace[];
    credits?: {
        current: number;
        total: number;
    };
    engineStatus?: 'connected' | 'connecting' | 'disconnected' | 'error';
}

interface NavigationItem {
    name: string;
    href: string;
    icon: any;
    mobileOrder: number;
    playCanvasOnly?: boolean;
    adminOnly?: boolean;
    proOnly?: boolean;
}

const navigationItems: NavigationItem[] = [
    { name: 'Chat', href: '/chat', icon: MessageSquare, mobileOrder: 1 },
    { name: 'Games', href: '/games', icon: FolderOpen, mobileOrder: 2 },
    { name: 'Templates', href: '/templates', icon: Template, mobileOrder: 3 },
    { name: 'History', href: '/history', icon: History, mobileOrder: 6 },
    { name: 'Multiplayer', href: '/multiplayer', icon: Users, mobileOrder: 7, playCanvasOnly: true },
    { name: 'Preview', href: '/preview', icon: Eye, mobileOrder: 4 },
    { name: 'Publish', href: '/publish', icon: Upload, mobileOrder: 5 },
    { name: 'Billing', href: '/company/billing', icon: CreditCard, mobileOrder: 0 },
    { name: 'Settings', href: '/settings', icon: Settings, mobileOrder: 0 },
];

export default function MainLayout({
    children,
    title,
    currentWorkspace,
    workspaces = [],
    credits = { current: 0, total: 0 },
    engineStatus = 'disconnected'
}: MainLayoutProps) {
    const { auth, ziggy } = usePage<PageProps>().props;
    const [showMobileSidebar, setShowMobileSidebar] = useState(false);
    
    const currentPath = ziggy?.location || '';
    const currentEngine = currentWorkspace?.engine_type || 'playcanvas';

    // Filter navigation based on engine and user permissions
    const filteredNavItems = navigationItems.filter((item) => {
        if (item.playCanvasOnly && currentEngine !== 'playcanvas') return false;
        return true;
    });

    // Dynamic status badge based on engine and connection state
    const getStatusBadge = () => {
        if (currentEngine === 'playcanvas') {
            return (
                <Badge variant="outline" className="text-xs">
                    <CheckCircle className="w-3 h-3 mr-1 text-green-500" />
                    Ready
                </Badge>
            );
        }

        switch (engineStatus) {
            case 'connected':
                return (
                    <Badge variant="outline" className="text-xs">
                        <CheckCircle className="w-3 h-3 mr-1 text-green-500" />
                        Connected
                    </Badge>
                );
            case 'connecting':
                return (
                    <Badge variant="outline" className="text-xs">
                        <Loader2 className="w-3 h-3 mr-1 animate-spin" />
                        Connecting...
                    </Badge>
                );
            case 'error':
                return (
                    <Badge variant="destructive" className="text-xs">
                        <AlertCircle className="w-3 h-3 mr-1" />
                        Error
                    </Badge>
                );
            default:
                return (
                    <Badge variant="secondary" className="text-xs">
                        <AlertCircle className="w-3 h-3 mr-1" />
                        Disconnected
                    </Badge>
                );
        }
    };

    // Engine identification badge
    const getEngineBadge = () => (
        <Badge variant="secondary" className="text-xs">
            {currentEngine === 'unreal' ? (
                <>
                    <Code className="w-3 h-3 mr-1" />
                    Unreal
                </>
            ) : (
                <>
                    <Globe className="w-3 h-3 mr-1" />
                    PlayCanvas
                </>
            )}
        </Badge>
    );

    return (
        <>
            {title && <Head title={title} />}
            <div className="min-h-screen bg-background">
                {/* Desktop Sidebar */}
                <div className="hidden lg:block fixed inset-y-0 left-0 w-64 bg-sidebar border-r border-sidebar-border">
                    <div className="flex flex-col h-full">
                        {/* Logo/Brand */}
                        <div className="p-4 border-b border-sidebar-border">
                            <div className="flex items-center space-x-2">
                                <div className="w-8 h-8 bg-sidebar-primary rounded-lg flex items-center justify-center">
                                    <Gamepad2 className="w-5 h-5 text-sidebar-primary-foreground" />
                                </div>
                                <span className="font-serif font-black text-sidebar-foreground">SurrealPilot</span>
                            </div>
                        </div>

                        {/* Primary Navigation */}
                        <nav className="flex-1 p-4">
                            <div className="space-y-1">
                                {filteredNavItems.map((item) => {
                                    const isActive = currentPath.startsWith(item.href);
                                    return (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            className={cn(
                                                'flex items-center space-x-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                isActive
                                                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                                    : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                                            )}
                                        >
                                            <item.icon className="w-4 h-4" />
                                            <span>{item.name}</span>
                                        </Link>
                                    );
                                })}
                            </div>
                        </nav>

                        {/* Credits Display */}
                        <div className="p-4 border-t border-sidebar-border">
                            <CreditBalance showDetails={true} />
                        </div>
                    </div>
                </div>

                {/* Mobile Sidebar Overlay */}
                {showMobileSidebar && (
                    <div className="fixed inset-0 z-50 lg:hidden">
                        <div className="absolute inset-0 bg-black/50" onClick={() => setShowMobileSidebar(false)} />
                        <div className="absolute left-0 top-0 h-full w-64 bg-sidebar border-r border-sidebar-border">
                            <div className="flex flex-col h-full">
                                {/* Logo with close button */}
                                <div className="p-4 border-b border-sidebar-border">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-2">
                                            <div className="w-8 h-8 bg-sidebar-primary rounded-lg flex items-center justify-center">
                                                <Gamepad2 className="w-5 h-5 text-sidebar-primary-foreground" />
                                            </div>
                                            <span className="font-serif font-black text-sidebar-foreground">SurrealPilot</span>
                                        </div>
                                        <Button variant="ghost" size="sm" onClick={() => setShowMobileSidebar(false)}>
                                            <X className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>

                                {/* Mobile Navigation */}
                                <nav className="flex-1 p-4">
                                    <div className="space-y-1">
                                        {filteredNavItems.map((item) => {
                                            const isActive = currentPath.startsWith(item.href);
                                            return (
                                                <Link
                                                    key={item.name}
                                                    href={item.href}
                                                    onClick={() => setShowMobileSidebar(false)}
                                                    className={cn(
                                                        'flex items-center space-x-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                        isActive
                                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                                            : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                                                    )}
                                                >
                                                    <item.icon className="w-4 h-4" />
                                                    <span>{item.name}</span>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </nav>
                            </div>
                        </div>
                    </div>
                )}

                {/* Main Content Area */}
                <div className="lg:pl-64">
                    {/* Global Header */}
                    <header className="sticky top-0 z-40 border-b border-border bg-card/50 backdrop-blur-sm">
                        <div className="flex items-center justify-between p-4">
                            <div className="flex items-center space-x-4">
                                {/* Mobile menu trigger */}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="lg:hidden"
                                    onClick={() => setShowMobileSidebar(true)}
                                >
                                    <Menu className="w-4 h-4" />
                                </Button>

                                {/* Workspace Switcher */}
                                <WorkspaceSwitcher
                                    currentWorkspace={currentWorkspace}
                                    workspaces={workspaces}
                                />
                            </div>

                            {/* Global Status Elements */}
                            <div className="flex items-center space-x-2">
                                {getEngineBadge()}
                                {getStatusBadge()}
                                <Badge variant="outline" className="text-xs hidden sm:flex">
                                    <Zap className="w-3 h-3 mr-1" />
                                    GPT-4
                                </Badge>
                                <CreditBalance showDetails={true} className="hidden md:flex" />
                                <UserMenu user={auth.user} />
                            </div>
                        </div>
                    </header>

                    {/* Page Content */}
                    <main className="min-h-[calc(100vh-73px)]">{children}</main>
                </div>

                {/* Mobile Bottom Navigation */}
                <div className="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-card border-t border-border">
                    <div className="flex items-center justify-around py-2">
                        {filteredNavItems
                            .filter((item) => item.mobileOrder > 0)
                            .sort((a, b) => a.mobileOrder - b.mobileOrder)
                            .slice(0, 5)
                            .map((item) => {
                                const isActive = currentPath.startsWith(item.href);
                                return (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={cn(
                                            'flex flex-col items-center space-y-1 px-3 py-2 rounded-lg transition-colors min-w-0',
                                            isActive ? 'text-primary' : 'text-muted-foreground hover:text-foreground'
                                        )}
                                    >
                                        <item.icon className="w-5 h-5" />
                                        <span className="text-xs truncate">{item.name}</span>
                                    </Link>
                                );
                            })}
                    </div>
                </div>

                {/* Mobile bottom padding */}
                <div className="h-20 lg:hidden" />
            </div>
        </>
    );
}