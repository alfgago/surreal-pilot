import { Link, usePage } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { PageProps } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MessageSquare, Gamepad2, Settings } from 'lucide-react';

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    return (
        <MainLayout title="Dashboard">
            <div className="p-6">
                <div className="max-w-7xl mx-auto">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-foreground mb-2">
                            Welcome back, {user?.name}!
                        </h1>
                        <p className="text-muted-foreground">
                            Your AI-powered game development workspace is ready.
                        </p>
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <MessageSquare className="w-5 h-5 text-primary" />
                                    <span>AI Chat</span>
                                </CardTitle>
                                <CardDescription>
                                    Start a conversation with your AI assistant
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Link href="/chat">
                                    <Button className="w-full">
                                        <MessageSquare className="w-4 h-4 mr-2" />
                                        Open Chat
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Gamepad2 className="w-5 h-5 text-primary" />
                                    <span>Games</span>
                                </CardTitle>
                                <CardDescription>
                                    Manage your game projects
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Link href="/games">
                                    <Button variant="outline" className="w-full">
                                        <Gamepad2 className="w-4 h-4 mr-2" />
                                        View Games
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                        
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Settings className="w-5 h-5 text-primary" />
                                    <span>Settings</span>
                                </CardTitle>
                                <CardDescription>
                                    Configure your preferences
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Link href="/settings">
                                    <Button variant="outline" className="w-full">
                                        <Settings className="w-4 h-4 mr-2" />
                                        Open Settings
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Account Information</CardTitle>
                            <CardDescription>
                                Your current account details
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Name</dt>
                                    <dd className="mt-1 text-sm text-foreground">{user?.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Email</dt>
                                    <dd className="mt-1 text-sm text-foreground">{user?.email}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">User ID</dt>
                                    <dd className="mt-1 text-sm text-foreground">{user?.id}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-muted-foreground">Company ID</dt>
                                    <dd className="mt-1 text-sm text-foreground">{user?.current_company_id || 'None'}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}