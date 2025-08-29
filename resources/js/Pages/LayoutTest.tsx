import { Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    MessageSquare, 
    Gamepad2, 
    Settings, 
    Globe, 
    Code,
    CheckCircle,
    Users,
    CreditCard
} from 'lucide-react';

export default function LayoutTest() {
    // Mock data for testing
    const mockWorkspace = {
        id: 1,
        name: 'Test Racing Game',
        engine: 'playcanvas' as const,
        company_id: 1,
        user_id: 1,
        created_at: '2025-01-01',
        updated_at: '2025-01-01'
    };

    const mockWorkspaces = [
        mockWorkspace,
        {
            id: 2,
            name: 'VR Adventure',
            engine: 'unreal' as const,
            company_id: 1,
            user_id: 1,
            created_at: '2025-01-01',
            updated_at: '2025-01-01'
        }
    ];

    const mockCredits = {
        current: 1247,
        total: 2000
    };

    return (
        <MainLayout 
            title="Layout Test"
            currentWorkspace={mockWorkspace}
            workspaces={mockWorkspaces}
            credits={mockCredits}
            engineStatus="connected"
        >
            <div className="p-6">
                <div className="max-w-7xl mx-auto">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-foreground mb-2">
                            Layout System Test
                        </h1>
                        <p className="text-muted-foreground">
                            Testing the MainLayout with navigation, workspace switcher, and mobile responsiveness.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <MessageSquare className="w-5 h-5 text-primary" />
                                    <span>Navigation Test</span>
                                </CardTitle>
                                <CardDescription>
                                    Test the sidebar navigation and mobile menu
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Badge variant="outline">Desktop Sidebar: ✓</Badge>
                                    <Badge variant="outline">Mobile Menu: ✓</Badge>
                                    <Badge variant="outline">Bottom Navigation: ✓</Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Globe className="w-5 h-5 text-blue-500" />
                                    <span>Workspace Switcher</span>
                                </CardTitle>
                                <CardDescription>
                                    Test workspace switching functionality
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Badge variant="secondary">Current: {mockWorkspace.name}</Badge>
                                    <Badge variant="outline">Engine: PlayCanvas</Badge>
                                    <Badge variant="outline">Status: <CheckCircle className="w-3 h-3 ml-1 text-green-500" /></Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <CreditCard className="w-5 h-5 text-primary" />
                                    <span>Credits Display</span>
                                </CardTitle>
                                <CardDescription>
                                    Test credit balance display
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Badge variant="outline">
                                        {mockCredits.current.toLocaleString()} / {mockCredits.total.toLocaleString()}
                                    </Badge>
                                    <div className="w-full bg-secondary rounded-full h-2">
                                        <div 
                                            className="bg-primary h-2 rounded-full" 
                                            style={{ width: `${(mockCredits.current / mockCredits.total) * 100}%` }}
                                        ></div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Mobile Responsiveness Test</CardTitle>
                            <CardDescription>
                                Resize your browser window to test mobile layouts
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div className="text-center p-4 bg-muted rounded-lg">
                                    <div className="text-sm font-medium">Mobile (&lt; 768px)</div>
                                    <div className="text-xs text-muted-foreground mt-1">
                                        Sidebar hidden, bottom nav visible
                                    </div>
                                </div>
                                <div className="text-center p-4 bg-muted rounded-lg">
                                    <div className="text-sm font-medium">Tablet (768px - 1024px)</div>
                                    <div className="text-xs text-muted-foreground mt-1">
                                        Collapsible sidebar
                                    </div>
                                </div>
                                <div className="text-center p-4 bg-muted rounded-lg">
                                    <div className="text-sm font-medium">Desktop (&gt; 1024px)</div>
                                    <div className="text-xs text-muted-foreground mt-1">
                                        Fixed sidebar visible
                                    </div>
                                </div>
                                <div className="text-center p-4 bg-muted rounded-lg">
                                    <div className="text-sm font-medium">Wide (&gt; 1280px)</div>
                                    <div className="text-xs text-muted-foreground mt-1">
                                        Full layout with margins
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="mt-8 flex flex-wrap gap-4">
                        <Link href="/dashboard">
                            <Button>
                                <Gamepad2 className="w-4 h-4 mr-2" />
                                Go to Dashboard
                            </Button>
                        </Link>
                        <Link href="/chat">
                            <Button variant="outline">
                                <MessageSquare className="w-4 h-4 mr-2" />
                                Open Chat
                            </Button>
                        </Link>
                        <Link href="/settings">
                            <Button variant="outline">
                                <Settings className="w-4 h-4 mr-2" />
                                Settings
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}