import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { User, Calendar, MessageSquare, Gamepad2, Zap } from 'lucide-react';

interface ProfileProps {
    user: {
        id: number;
        name: string;
        email: string;
        bio?: string;
        timezone: string;
        avatar_url?: string;
        email_notifications: boolean;
        browser_notifications: boolean;
        created_at: string;
    };
    company?: {
        id: number;
        name: string;
        plan: string;
        credits: number;
    };
    stats: {
        games_created: number;
        messages_sent: number;
        credits_used: number;
        member_since: string;
    };
    timezones: Record<string, string>;
}

export default function Profile() {
    const { user, company, stats, timezones } = usePage<ProfileProps>().props;
    const { flash } = usePage().props;

    const { data, setData, patch, processing, errors, reset } = useForm({
        name: user.name,
        email: user.email,
        bio: user.bio || '',
        timezone: user.timezone,
        avatar_url: user.avatar_url || '',
        email_notifications: user.email_notifications,
        browser_notifications: user.browser_notifications,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'), {
            onSuccess: () => {
                // Form will be updated with new data automatically
            },
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <MainLayout>
            <Head title="Profile Settings" />
            
            <div className="container mx-auto px-4 py-8 max-w-4xl">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>
                    <p className="text-gray-600 dark:text-gray-400 mt-2">
                        Manage your personal information and preferences
                    </p>
                </div>

                {flash.success && (
                    <div className="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        {flash.success}
                    </div>
                )}

                {flash.error && (
                    <div className="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                        {flash.error}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Profile Stats */}
                    <div className="lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    Profile Stats
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Gamepad2 className="h-4 w-4 text-blue-500" />
                                        <span className="text-sm">Games Created</span>
                                    </div>
                                    <Badge variant="secondary">{stats.games_created}</Badge>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <MessageSquare className="h-4 w-4 text-green-500" />
                                        <span className="text-sm">Messages Sent</span>
                                    </div>
                                    <Badge variant="secondary">{stats.messages_sent}</Badge>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Zap className="h-4 w-4 text-yellow-500" />
                                        <span className="text-sm">Credits Used</span>
                                    </div>
                                    <Badge variant="secondary">{stats.credits_used}</Badge>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-purple-500" />
                                        <span className="text-sm">Member Since</span>
                                    </div>
                                    <span className="text-sm text-gray-600 dark:text-gray-400">
                                        {formatDate(stats.member_since)}
                                    </span>
                                </div>

                                {company && (
                                    <div className="pt-4 border-t">
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-medium">Company</span>
                                                <span className="text-sm">{company.name}</span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-medium">Plan</span>
                                                <Badge variant="outline">{company.plan}</Badge>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-medium">Credits</span>
                                                <Badge variant="secondary">{company.credits}</Badge>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Profile Form */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Personal Information</CardTitle>
                                <CardDescription>
                                    Update your personal details and preferences
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit} className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">Full Name</Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                className={errors.name ? 'border-red-500' : ''}
                                                required
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-red-600">{errors.name}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email Address</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                className={errors.email ? 'border-red-500' : ''}
                                                required
                                            />
                                            {errors.email && (
                                                <p className="text-sm text-red-600">{errors.email}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="bio">Bio</Label>
                                        <Textarea
                                            id="bio"
                                            value={data.bio}
                                            onChange={(e) => setData('bio', e.target.value)}
                                            placeholder="Tell us a bit about yourself..."
                                            className={errors.bio ? 'border-red-500' : ''}
                                            rows={3}
                                        />
                                        {errors.bio && (
                                            <p className="text-sm text-red-600">{errors.bio}</p>
                                        )}
                                        <p className="text-sm text-gray-500">
                                            {data.bio.length}/500 characters
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="timezone">Timezone</Label>
                                            <Select
                                                value={data.timezone}
                                                onValueChange={(value) => setData('timezone', value)}
                                            >
                                                <SelectTrigger className={errors.timezone ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Select timezone" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(timezones).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.timezone && (
                                                <p className="text-sm text-red-600">{errors.timezone}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="avatar_url">Avatar URL</Label>
                                            <Input
                                                id="avatar_url"
                                                type="url"
                                                value={data.avatar_url}
                                                onChange={(e) => setData('avatar_url', e.target.value)}
                                                placeholder="https://example.com/avatar.jpg"
                                                className={errors.avatar_url ? 'border-red-500' : ''}
                                            />
                                            {errors.avatar_url && (
                                                <p className="text-sm text-red-600">{errors.avatar_url}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <h3 className="text-lg font-medium">Notification Preferences</h3>
                                        
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="email_notifications">Email Notifications</Label>
                                                <p className="text-sm text-gray-500">
                                                    Receive notifications via email
                                                </p>
                                            </div>
                                            <Switch
                                                id="email_notifications"
                                                checked={data.email_notifications}
                                                onCheckedChange={(checked) => setData('email_notifications', checked)}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="browser_notifications">Browser Notifications</Label>
                                                <p className="text-sm text-gray-500">
                                                    Receive push notifications in your browser
                                                </p>
                                            </div>
                                            <Switch
                                                id="browser_notifications"
                                                checked={data.browser_notifications}
                                                onCheckedChange={(checked) => setData('browser_notifications', checked)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Saving...' : 'Save Changes'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}