import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Cog, Globe, Bell, Shield } from 'lucide-react';

interface CompanyPreferencesProps {
    company: {
        id: number;
        name: string;
        preferences?: {
            timezone?: string;
            default_engine?: string;
            auto_save?: boolean;
            notifications_enabled?: boolean;
            collaboration_enabled?: boolean;
            public_templates?: boolean;
            description?: string;
            website?: string;
        };
        is_owner: boolean;
    };
}

export default function CompanyPreferences({ company }: CompanyPreferencesProps) {
    const preferences = company.preferences || {};
    
    const { data, setData, patch, processing, errors } = useForm({
        timezone: preferences.timezone || 'UTC',
        default_engine: preferences.default_engine || 'playcanvas',
        auto_save: preferences.auto_save ?? true,
        notifications_enabled: preferences.notifications_enabled ?? true,
        collaboration_enabled: preferences.collaboration_enabled ?? true,
        public_templates: preferences.public_templates ?? false,
        description: preferences.description || '',
        website: preferences.website || '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/company/preferences');
    };

    const timezones = [
        { value: 'UTC', label: 'UTC' },
        { value: 'America/New_York', label: 'Eastern Time' },
        { value: 'America/Chicago', label: 'Central Time' },
        { value: 'America/Denver', label: 'Mountain Time' },
        { value: 'America/Los_Angeles', label: 'Pacific Time' },
        { value: 'Europe/London', label: 'London' },
        { value: 'Europe/Paris', label: 'Paris' },
        { value: 'Europe/Berlin', label: 'Berlin' },
        { value: 'Asia/Tokyo', label: 'Tokyo' },
        { value: 'Asia/Shanghai', label: 'Shanghai' },
        { value: 'Australia/Sydney', label: 'Sydney' },
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Cog className="h-5 w-5" />
                    Company Preferences
                </CardTitle>
                <CardDescription>
                    Configure default settings and preferences for your company
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Company Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold flex items-center gap-2">
                            <Globe className="h-4 w-4" />
                            Company Information
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    placeholder="Brief description of your company"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    disabled={!company.is_owner}
                                    className={errors.description ? 'border-red-500' : ''}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-600">{errors.description}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="website">Website</Label>
                                <Input
                                    id="website"
                                    type="url"
                                    placeholder="https://example.com"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    disabled={!company.is_owner}
                                    className={errors.website ? 'border-red-500' : ''}
                                />
                                {errors.website && (
                                    <p className="text-sm text-red-600">{errors.website}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Default Settings */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold flex items-center gap-2">
                            <Shield className="h-4 w-4" />
                            Default Settings
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="timezone">Default Timezone</Label>
                                <Select
                                    value={data.timezone}
                                    onValueChange={(value) => setData('timezone', value)}
                                    disabled={!company.is_owner}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {timezones.map((tz) => (
                                            <SelectItem key={tz.value} value={tz.value}>
                                                {tz.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="default_engine">Default Engine</Label>
                                <Select
                                    value={data.default_engine}
                                    onValueChange={(value) => setData('default_engine', value)}
                                    disabled={!company.is_owner}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                                        <SelectItem value="unreal">Unreal Engine</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    {/* Feature Settings */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold flex items-center gap-2">
                            <Bell className="h-4 w-4" />
                            Feature Settings
                        </h3>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Auto-save Projects</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Automatically save project changes
                                    </p>
                                </div>
                                <Switch
                                    checked={data.auto_save}
                                    onCheckedChange={(checked) => setData('auto_save', checked)}
                                    disabled={!company.is_owner}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Notifications</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Enable email notifications for team activities
                                    </p>
                                </div>
                                <Switch
                                    checked={data.notifications_enabled}
                                    onCheckedChange={(checked) => setData('notifications_enabled', checked)}
                                    disabled={!company.is_owner}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Real-time Collaboration</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Enable real-time collaboration features
                                    </p>
                                </div>
                                <Switch
                                    checked={data.collaboration_enabled}
                                    onCheckedChange={(checked) => setData('collaboration_enabled', checked)}
                                    disabled={!company.is_owner}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Public Templates</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Allow sharing templates publicly
                                    </p>
                                </div>
                                <Switch
                                    checked={data.public_templates}
                                    onCheckedChange={(checked) => setData('public_templates', checked)}
                                    disabled={!company.is_owner}
                                />
                            </div>
                        </div>
                    </div>

                    {company.is_owner && (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : 'Save Preferences'}
                            </Button>
                        </div>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}