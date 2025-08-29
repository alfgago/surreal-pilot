import { Head, useForm, usePage, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Slider } from '@/components/ui/slider';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { 
    Settings, 
    Brain, 
    Key, 
    Palette, 
    Bell, 
    Trash2, 
    Eye, 
    EyeOff,
    AlertTriangle,
    CheckCircle,
    XCircle
} from 'lucide-react';

interface Provider {
    name: string;
    description: string;
    requires_key: boolean;
    status: string;
}

interface SettingsProps {
    providers: Record<string, Provider>;
    preferences: Record<string, any>;
    apiKeyStatus: Record<string, boolean>;
    isCompanyOwner: boolean;
    user: {
        id: number;
        name: string;
        email: string;
    };
    company?: {
        id: number;
        name: string;
        plan: string;
    };
}

export default function Settings() {
    const { providers, preferences, apiKeyStatus, isCompanyOwner, user, company } = usePage<SettingsProps>().props;
    const { flash } = usePage().props;
    
    const [showApiKeys, setShowApiKeys] = useState<Record<string, boolean>>({});
    const [deleteConfirm, setDeleteConfirm] = useState<string | null>(null);

    // Settings form
    const { data: settingsData, setData: setSettingsData, patch: patchSettings, processing: settingsProcessing, errors: settingsErrors } = useForm({
        'ai.default_provider': preferences?.ai?.default_provider || 'anthropic',
        'ai.temperature': preferences?.ai?.temperature || 0.7,
        'ai.stream_responses': preferences?.ai?.stream_responses ?? true,
        'ai.save_history': preferences?.ai?.save_history ?? true,
        'ui.theme': preferences?.ui?.theme || 'system',
        'ui.compact_mode': preferences?.ui?.compact_mode ?? false,
        'ui.show_line_numbers': preferences?.ui?.show_line_numbers ?? true,
        'notifications.email': preferences?.notifications?.email ?? true,
        'notifications.browser': preferences?.notifications?.browser ?? true,
        'notifications.chat_mentions': preferences?.notifications?.chat_mentions ?? true,
        'notifications.game_updates': preferences?.notifications?.game_updates ?? true,
    });

    // API Keys form
    const { data: apiData, setData: setApiData, patch: patchApiKeys, processing: apiProcessing, errors: apiErrors, reset: resetApiForm } = useForm({
        openai_api_key: '',
        anthropic_api_key: '',
        gemini_api_key: '',
        playcanvas_api_key: '',
        playcanvas_project_id: '',
    });

    const submitSettings: FormEventHandler = (e) => {
        e.preventDefault();
        patchSettings(route('settings.update'));
    };

    const submitApiKeys: FormEventHandler = (e) => {
        e.preventDefault();
        patchApiKeys(route('settings.api-keys.update'), {
            onSuccess: () => {
                resetApiForm();
            },
        });
    };

    const removeApiKey = (provider: string) => {
        router.delete(route('settings.api-keys.remove', provider), {
            onSuccess: () => {
                setDeleteConfirm(null);
            },
        });
    };

    const toggleApiKeyVisibility = (provider: string) => {
        setShowApiKeys(prev => ({
            ...prev,
            [provider]: !prev[provider]
        }));
    };

    const getProviderIcon = (provider: string) => {
        switch (provider) {
            case 'anthropic':
                return '🤖';
            case 'openai':
                return '🧠';
            case 'gemini':
                return '💎';
            case 'ollama':
                return '🦙';
            default:
                return '🔧';
        }
    };

    return (
        <MainLayout>
            <Head title="Settings" />
            
            <div className="container mx-auto px-4 py-8 max-w-6xl">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Settings</h1>
                    <p className="text-gray-600 dark:text-gray-400 mt-2">
                        Configure your AI preferences, API keys, and application settings
                    </p>
                </div>

                {flash.success && (
                    <div className="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center gap-2">
                        <CheckCircle className="h-5 w-5" />
                        {flash.success}
                    </div>
                )}

                {flash.error && (
                    <div className="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center gap-2">
                        <XCircle className="h-5 w-5" />
                        {flash.error}
                    </div>
                )}

                <Tabs defaultValue="ai" className="space-y-6">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="ai" className="flex items-center gap-2">
                            <Brain className="h-4 w-4" />
                            AI Settings
                        </TabsTrigger>
                        <TabsTrigger value="api-keys" className="flex items-center gap-2">
                            <Key className="h-4 w-4" />
                            API Keys
                        </TabsTrigger>
                        <TabsTrigger value="interface" className="flex items-center gap-2">
                            <Palette className="h-4 w-4" />
                            Interface
                        </TabsTrigger>
                        <TabsTrigger value="notifications" className="flex items-center gap-2">
                            <Bell className="h-4 w-4" />
                            Notifications
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="ai">
                        <Card>
                            <CardHeader>
                                <CardTitle>AI Configuration</CardTitle>
                                <CardDescription>
                                    Configure your AI assistant preferences and behavior
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitSettings} className="space-y-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="default_provider">Default AI Provider</Label>
                                        <Select
                                            value={settingsData['ai.default_provider']}
                                            onValueChange={(value) => setSettingsData('ai.default_provider', value)}
                                        >
                                            <SelectTrigger className={settingsErrors['ai.default_provider'] ? 'border-red-500' : ''}>
                                                <SelectValue placeholder="Select AI provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(providers).map(([key, provider]) => (
                                                    <SelectItem key={key} value={key}>
                                                        <div className="flex items-center gap-2">
                                                            <span>{getProviderIcon(key)}</span>
                                                            <div>
                                                                <div className="font-medium">{provider.name}</div>
                                                                <div className="text-sm text-gray-500">{provider.description}</div>
                                                            </div>
                                                        </div>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {settingsErrors['ai.default_provider'] && (
                                            <p className="text-sm text-red-600">{settingsErrors['ai.default_provider']}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="temperature">
                                            Temperature: {settingsData['ai.temperature']}
                                        </Label>
                                        <Slider
                                            value={[settingsData['ai.temperature']]}
                                            onValueChange={(value) => setSettingsData('ai.temperature', value[0])}
                                            max={1}
                                            min={0}
                                            step={0.1}
                                            className="w-full"
                                        />
                                        <p className="text-sm text-gray-500">
                                            Lower values make responses more focused, higher values more creative
                                        </p>
                                        {settingsErrors['ai.temperature'] && (
                                            <p className="text-sm text-red-600">{settingsErrors['ai.temperature']}</p>
                                        )}
                                    </div>

                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="stream_responses">Stream Responses</Label>
                                                <p className="text-sm text-gray-500">
                                                    Show AI responses as they're generated
                                                </p>
                                            </div>
                                            <Switch
                                                id="stream_responses"
                                                checked={settingsData['ai.stream_responses']}
                                                onCheckedChange={(checked) => setSettingsData('ai.stream_responses', checked)}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="save_history">Save Chat History</Label>
                                                <p className="text-sm text-gray-500">
                                                    Keep a record of your conversations
                                                </p>
                                            </div>
                                            <Switch
                                                id="save_history"
                                                checked={settingsData['ai.save_history']}
                                                onCheckedChange={(checked) => setSettingsData('ai.save_history', checked)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={settingsProcessing}>
                                            {settingsProcessing ? 'Saving...' : 'Save AI Settings'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="api-keys">
                        <Card>
                            <CardHeader>
                                <CardTitle>API Key Management</CardTitle>
                                <CardDescription>
                                    {isCompanyOwner 
                                        ? "Manage API keys for your company's AI providers"
                                        : "Only company owners can manage API keys"
                                    }
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!isCompanyOwner ? (
                                    <div className="text-center py-8">
                                        <AlertTriangle className="h-12 w-12 text-yellow-500 mx-auto mb-4" />
                                        <p className="text-gray-600 dark:text-gray-400">
                                            You need to be a company owner to manage API keys.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-6">
                                        {/* Current API Key Status */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {Object.entries(providers).map(([key, provider]) => (
                                                <div key={key} className="p-4 border rounded-lg">
                                                    <div className="flex items-center justify-between mb-2">
                                                        <div className="flex items-center gap-2">
                                                            <span>{getProviderIcon(key)}</span>
                                                            <span className="font-medium">{provider.name}</span>
                                                        </div>
                                                        {apiKeyStatus[key] ? (
                                                            <Badge variant="default" className="bg-green-100 text-green-800">
                                                                <CheckCircle className="h-3 w-3 mr-1" />
                                                                Configured
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary">
                                                                <XCircle className="h-3 w-3 mr-1" />
                                                                Not Set
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-500 mb-3">{provider.description}</p>
                                                    {apiKeyStatus[key] && (
                                                        <Dialog>
                                                            <DialogTrigger asChild>
                                                                <Button 
                                                                    variant="outline" 
                                                                    size="sm"
                                                                    onClick={() => setDeleteConfirm(key)}
                                                                >
                                                                    <Trash2 className="h-3 w-3 mr-1" />
                                                                    Remove
                                                                </Button>
                                                            </DialogTrigger>
                                                            <DialogContent>
                                                                <DialogHeader>
                                                                    <DialogTitle>Remove API Key</DialogTitle>
                                                                    <DialogDescription>
                                                                        Are you sure you want to remove the {provider.name} API key? 
                                                                        This will disable {provider.name} functionality.
                                                                    </DialogDescription>
                                                                </DialogHeader>
                                                                <DialogFooter>
                                                                    <Button 
                                                                        variant="outline" 
                                                                        onClick={() => setDeleteConfirm(null)}
                                                                    >
                                                                        Cancel
                                                                    </Button>
                                                                    <Button 
                                                                        variant="destructive"
                                                                        onClick={() => removeApiKey(key)}
                                                                    >
                                                                        Remove Key
                                                                    </Button>
                                                                </DialogFooter>
                                                            </DialogContent>
                                                        </Dialog>
                                                    )}
                                                </div>
                                            ))}
                                        </div>

                                        {/* API Key Form */}
                                        <form onSubmit={submitApiKeys} className="space-y-4">
                                            <h3 className="text-lg font-medium">Add or Update API Keys</h3>
                                            
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="openai_api_key">OpenAI API Key</Label>
                                                    <div className="relative">
                                                        <Input
                                                            id="openai_api_key"
                                                            type={showApiKeys.openai ? "text" : "password"}
                                                            value={apiData.openai_api_key}
                                                            onChange={(e) => setApiData('openai_api_key', e.target.value)}
                                                            placeholder="sk-..."
                                                            className={apiErrors.openai_api_key ? 'border-red-500' : ''}
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="absolute right-0 top-0 h-full px-3"
                                                            onClick={() => toggleApiKeyVisibility('openai')}
                                                        >
                                                            {showApiKeys.openai ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </Button>
                                                    </div>
                                                    {apiErrors.openai_api_key && (
                                                        <p className="text-sm text-red-600">{apiErrors.openai_api_key}</p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="anthropic_api_key">Anthropic API Key</Label>
                                                    <div className="relative">
                                                        <Input
                                                            id="anthropic_api_key"
                                                            type={showApiKeys.anthropic ? "text" : "password"}
                                                            value={apiData.anthropic_api_key}
                                                            onChange={(e) => setApiData('anthropic_api_key', e.target.value)}
                                                            placeholder="sk-ant-..."
                                                            className={apiErrors.anthropic_api_key ? 'border-red-500' : ''}
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="absolute right-0 top-0 h-full px-3"
                                                            onClick={() => toggleApiKeyVisibility('anthropic')}
                                                        >
                                                            {showApiKeys.anthropic ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </Button>
                                                    </div>
                                                    {apiErrors.anthropic_api_key && (
                                                        <p className="text-sm text-red-600">{apiErrors.anthropic_api_key}</p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="gemini_api_key">Gemini API Key</Label>
                                                    <div className="relative">
                                                        <Input
                                                            id="gemini_api_key"
                                                            type={showApiKeys.gemini ? "text" : "password"}
                                                            value={apiData.gemini_api_key}
                                                            onChange={(e) => setApiData('gemini_api_key', e.target.value)}
                                                            placeholder="AI..."
                                                            className={apiErrors.gemini_api_key ? 'border-red-500' : ''}
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="absolute right-0 top-0 h-full px-3"
                                                            onClick={() => toggleApiKeyVisibility('gemini')}
                                                        >
                                                            {showApiKeys.gemini ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </Button>
                                                    </div>
                                                    {apiErrors.gemini_api_key && (
                                                        <p className="text-sm text-red-600">{apiErrors.gemini_api_key}</p>
                                                    )}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="playcanvas_api_key">PlayCanvas API Key</Label>
                                                    <div className="relative">
                                                        <Input
                                                            id="playcanvas_api_key"
                                                            type={showApiKeys.playcanvas ? "text" : "password"}
                                                            value={apiData.playcanvas_api_key}
                                                            onChange={(e) => setApiData('playcanvas_api_key', e.target.value)}
                                                            placeholder="PlayCanvas API Key"
                                                            className={apiErrors.playcanvas_api_key ? 'border-red-500' : ''}
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="absolute right-0 top-0 h-full px-3"
                                                            onClick={() => toggleApiKeyVisibility('playcanvas')}
                                                        >
                                                            {showApiKeys.playcanvas ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </Button>
                                                    </div>
                                                    {apiErrors.playcanvas_api_key && (
                                                        <p className="text-sm text-red-600">{apiErrors.playcanvas_api_key}</p>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="playcanvas_project_id">PlayCanvas Project ID</Label>
                                                <Input
                                                    id="playcanvas_project_id"
                                                    type="text"
                                                    value={apiData.playcanvas_project_id}
                                                    onChange={(e) => setApiData('playcanvas_project_id', e.target.value)}
                                                    placeholder="123456"
                                                    className={apiErrors.playcanvas_project_id ? 'border-red-500' : ''}
                                                />
                                                {apiErrors.playcanvas_project_id && (
                                                    <p className="text-sm text-red-600">{apiErrors.playcanvas_project_id}</p>
                                                )}
                                            </div>

                                            <div className="flex justify-end">
                                                <Button type="submit" disabled={apiProcessing}>
                                                    {apiProcessing ? 'Saving...' : 'Save API Keys'}
                                                </Button>
                                            </div>
                                        </form>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="interface">
                        <Card>
                            <CardHeader>
                                <CardTitle>Interface Preferences</CardTitle>
                                <CardDescription>
                                    Customize the appearance and behavior of the interface
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitSettings} className="space-y-6">
                                    <div className="space-y-2">
                                        <Label htmlFor="theme">Theme</Label>
                                        <Select
                                            value={settingsData['ui.theme']}
                                            onValueChange={(value) => setSettingsData('ui.theme', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select theme" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="light">Light</SelectItem>
                                                <SelectItem value="dark">Dark</SelectItem>
                                                <SelectItem value="system">System</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="compact_mode">Compact Mode</Label>
                                                <p className="text-sm text-gray-500">
                                                    Use a more compact interface layout
                                                </p>
                                            </div>
                                            <Switch
                                                id="compact_mode"
                                                checked={settingsData['ui.compact_mode']}
                                                onCheckedChange={(checked) => setSettingsData('ui.compact_mode', checked)}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="show_line_numbers">Show Line Numbers</Label>
                                                <p className="text-sm text-gray-500">
                                                    Display line numbers in code editors
                                                </p>
                                            </div>
                                            <Switch
                                                id="show_line_numbers"
                                                checked={settingsData['ui.show_line_numbers']}
                                                onCheckedChange={(checked) => setSettingsData('ui.show_line_numbers', checked)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={settingsProcessing}>
                                            {settingsProcessing ? 'Saving...' : 'Save Interface Settings'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="notifications">
                        <Card>
                            <CardHeader>
                                <CardTitle>Notification Settings</CardTitle>
                                <CardDescription>
                                    Control when and how you receive notifications
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitSettings} className="space-y-6">
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="email_notifications">Email Notifications</Label>
                                                <p className="text-sm text-gray-500">
                                                    Receive notifications via email
                                                </p>
                                            </div>
                                            <Switch
                                                id="email_notifications"
                                                checked={settingsData['notifications.email']}
                                                onCheckedChange={(checked) => setSettingsData('notifications.email', checked)}
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
                                                checked={settingsData['notifications.browser']}
                                                onCheckedChange={(checked) => setSettingsData('notifications.browser', checked)}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="chat_mentions">Chat Mentions</Label>
                                                <p className="text-sm text-gray-500">
                                                    Get notified when mentioned in chat
                                                </p>
                                            </div>
                                            <Switch
                                                id="chat_mentions"
                                                checked={settingsData['notifications.chat_mentions']}
                                                onCheckedChange={(checked) => setSettingsData('notifications.chat_mentions', checked)}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="game_updates">Game Updates</Label>
                                                <p className="text-sm text-gray-500">
                                                    Get notified about game build updates
                                                </p>
                                            </div>
                                            <Switch
                                                id="game_updates"
                                                checked={settingsData['notifications.game_updates']}
                                                onCheckedChange={(checked) => setSettingsData('notifications.game_updates', checked)}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={settingsProcessing}>
                                            {settingsProcessing ? 'Saving...' : 'Save Notification Settings'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}