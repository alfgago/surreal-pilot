import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from '@/components/ui/use-toast';
import { Toaster } from '@/components/ui/toaster';
import { ComponentVerification } from '@/components/ComponentVerification';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ComponentTest() {
    const handleToast = () => {
        toast({
            title: "Test Toast",
            description: "This is a test toast message to verify the toast system is working.",
        });
    };

    return (
        <GuestLayout>
            <Head title="Component Test" />
            
            <div className="min-h-screen bg-background p-8">
                <div className="max-w-4xl mx-auto space-y-8">
                    <div className="text-center">
                        <h1 className="text-4xl font-bold text-foreground mb-2">Component Library Test</h1>
                        <p className="text-muted-foreground">Testing all migrated shadcn/ui components</p>
                    </div>

                    <div className="flex justify-center">
                        <ComponentVerification />
                    </div>

                    <Separator />

                    {/* Buttons */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Buttons</CardTitle>
                            <CardDescription>Various button variants and sizes</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2 flex-wrap">
                                <Button>Default</Button>
                                <Button variant="secondary">Secondary</Button>
                                <Button variant="outline">Outline</Button>
                                <Button variant="ghost">Ghost</Button>
                                <Button variant="link">Link</Button>
                                <Button variant="destructive">Destructive</Button>
                            </div>
                            <div className="flex gap-2 flex-wrap">
                                <Button size="sm">Small</Button>
                                <Button size="default">Default</Button>
                                <Button size="lg">Large</Button>
                                <Button size="icon">🎮</Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Form Elements */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Form Elements</CardTitle>
                            <CardDescription>Input fields, labels, and form controls</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input id="email" type="email" placeholder="Enter your email" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="select">Select Engine</Label>
                                    <Select>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose engine" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="unreal">Unreal Engine</SelectItem>
                                            <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="message">Message</Label>
                                <Textarea id="message" placeholder="Enter your message" />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Badges and Avatar */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Badges & Avatar</CardTitle>
                            <CardDescription>Status indicators and user avatars</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2 flex-wrap">
                                <Badge>Default</Badge>
                                <Badge variant="secondary">Secondary</Badge>
                                <Badge variant="outline">Outline</Badge>
                                <Badge variant="destructive">Error</Badge>
                            </div>
                            <div className="flex items-center gap-4">
                                <Avatar>
                                    <AvatarImage src="/placeholder-user.jpg" alt="User" />
                                    <AvatarFallback>JD</AvatarFallback>
                                </Avatar>
                                <div>
                                    <p className="font-medium">John Doe</p>
                                    <p className="text-sm text-muted-foreground">Game Developer</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Tabs */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Tabs</CardTitle>
                            <CardDescription>Tabbed content navigation</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Tabs defaultValue="games" className="w-full">
                                <TabsList className="grid w-full grid-cols-3">
                                    <TabsTrigger value="games">Games</TabsTrigger>
                                    <TabsTrigger value="chat">Chat</TabsTrigger>
                                    <TabsTrigger value="settings">Settings</TabsTrigger>
                                </TabsList>
                                <TabsContent value="games" className="mt-4">
                                    <p className="text-muted-foreground">Your game projects will appear here.</p>
                                </TabsContent>
                                <TabsContent value="chat" className="mt-4">
                                    <p className="text-muted-foreground">AI chat conversations will appear here.</p>
                                </TabsContent>
                                <TabsContent value="settings" className="mt-4">
                                    <p className="text-muted-foreground">Application settings will appear here.</p>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>

                    {/* Interactive Components */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Interactive Components</CardTitle>
                            <CardDescription>Dialogs, dropdowns, and toasts</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-4 flex-wrap">
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button variant="outline">Open Dialog</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Test Dialog</DialogTitle>
                                            <DialogDescription>
                                                This is a test dialog to verify the dialog component is working correctly.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="py-4">
                                            <p>Dialog content goes here.</p>
                                        </div>
                                    </DialogContent>
                                </Dialog>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">Open Menu</Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuItem>Profile</DropdownMenuItem>
                                        <DropdownMenuItem>Settings</DropdownMenuItem>
                                        <DropdownMenuItem>Logout</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <Button onClick={handleToast} variant="outline">
                                    Show Toast
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
            
            <Toaster />
        </GuestLayout>
    );
}