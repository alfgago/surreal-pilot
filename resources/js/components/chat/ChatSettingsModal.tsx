import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { 
    Settings, 
    Zap, 
    Key, 
    Brain,
    Save,
    RefreshCw,
    AlertCircle,
    CheckCircle,
    Eye,
    EyeOff
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';
import axios from 'axios';

interface ChatSettings {
    provider: string;
    model: string;
    temperature: number;
    max_tokens: number;
    system_prompt?: string;
    api_keys: {
        openai?: string;
        anthropic?: string;
        gemini?: string;
    };
    preferences: {
        auto_save_conversations: boolean;
        show_token_usage: boolean;
        enable_context_memory: boolean;
        stream_responses: boolean;
    };
}

interface ChatSettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
    engineType: 'playcanvas' | 'unreal';
    currentSettings: ChatSettings;
    onSettingsUpdate: (settings: ChatSettings) => void;
}

const PROVIDERS = {
    openai: { name: 'OpenAI', models: ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo'] },
    anthropic: { name: 'Anthropic', models: ['claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307', 'claude-sonnet-4-20250514'] },
    gemini: { name: 'Google Gemini', models: ['gemini-pro', 'gemini-pro-vision'] },
    ollama: { name: 'Ollama (Local)', models: ['llama2', 'codellama', 'mistral'] }
};

export default function ChatSettingsModal({
    isOpen,
    onClose,
    engineType,
    currentSettings,
    onSettingsUpdate
}: ChatSettingsModalProps) {
    // Provide default settings if currentSettings is undefined or incomplete
    const defaultSettings: ChatSettings = {
        provider: 'anthropic',
        model: 'claude-3-5-sonnet-20241022',
        temperature: 0.7,
        max_tokens: 1024,
        system_prompt: '',
        api_keys: {
            openai: '',
            anthropic: '',
            gemini: ''
        },
        preferences: {
            auto_save_conversations: true,
            show_token_usage: false,
            enable_context_memory: true,
            stream_responses: true,
        }
    };

    const [settings, setSettings] = useState<ChatSettings>({
        ...defaultSettings,
        ...currentSettings
    });
    const [isLoading, setIsLoading] = useState(false);
    const [showApiKeys, setShowApiKeys] = useState<Record<string, boolean>>({});
    const { toast } = useToast();

    useEffect(() => {
        if (currentSettings) {
            setSettings({
                ...defaultSettings,
                ...currentSettings
            });
        }
    }, [currentSettings]);

    const handleSaveSettings = async () => {
        setIsLoading(true);
        try {
            const response = await axios.post('/api/chat/settings', {
                ...settings,
                engine_type: engineType
            });

            if (response.data.success) {
                onSettingsUpdate(settings);
                toast({
                    title: "Settings Saved",
                    description: "Your chat settings have been updated successfully.",
                });
                onClose();
            }
        } catch (error) {
            console.error('Failed to save settings:', error);
            toast({
                title: "Save Failed",
                description: "Failed to save settings. Please try again.",
                variant: "destructive",
            });
        } finally {
            setIsLoading(false);
        }
    };

    const handleTestConnection = async (provider: string) => {
        try {
            const response = await axios.post('/api/chat/test-connection', {
                provider,
                api_key: settings.api_keys[provider as keyof typeof settings.api_keys]
            });

            if (response.data.success) {
                toast({
                    title: "Connection Successful",
                    description: `Successfully connected to ${PROVIDERS[provider as keyof typeof PROVIDERS].name}`,
                });
            }
        } catch (error) {
            toast({
                title: "Connection Failed",
                description: `Failed to connect to ${PROVIDERS[provider as keyof typeof PROVIDERS].name}`,
                variant: "destructive",
            });
        }
    };

    const toggleApiKeyVisibility = (provider: string) => {
        setShowApiKeys(prev => ({
            ...prev,
            [provider]: !prev[provider]
        }));
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center space-x-2">
                        <Settings className="w-5 h-5" />
                        <span>Chat Settings</span>
                        <Badge variant="outline">
                            {engineType === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine'}
                        </Badge>
                    </DialogTitle>
                    <DialogDescription>
                        Configure your AI assistant settings and preferences
                    </DialogDescription>
                </DialogHeader>

                <Tabs defaultValue="model" className="w-full">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="model">Model</TabsTrigger>
                        <TabsTrigger value="api-keys">API Keys</TabsTrigger>
                        <TabsTrigger value="advanced">Advanced</TabsTrigger>
                        <TabsTrigger value="preferences">Preferences</TabsTrigger>
                    </TabsList>

                    <TabsContent value="model" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Brain className="w-5 h-5" />
                                    <span>AI Model Configuration</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="provider">AI Provider</Label>
                                        <Select
                                            value={settings.provider}
                                            onValueChange={(value) => setSettings(prev => ({ 
                                                ...prev, 
                                                provider: value,
                                                model: PROVIDERS[value as keyof typeof PROVIDERS].models[0]
                                            }))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(PROVIDERS).map(([key, provider]) => (
                                                    <SelectItem key={key} value={key}>
                                                        {provider.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="model">Model</Label>
                                        <Select
                                            value={settings.model}
                                            onValueChange={(value) => setSettings(prev => ({ ...prev, model: value }))}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select model" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(settings.provider && PROVIDERS[settings.provider as keyof typeof PROVIDERS]?.models || []).map((model) => (
                                                    <SelectItem key={model} value={model}>
                                                        {model}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>Temperature: {settings.temperature}</Label>
                                    <Slider
                                        value={[settings.temperature]}
                                        onValueChange={([value]) => setSettings(prev => ({ ...prev, temperature: value }))}
                                        max={2}
                                        min={0}
                                        step={0.1}
                                        className="w-full"
                                    />
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>More Focused</span>
                                        <span>More Creative</span>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="max_tokens">Max Tokens</Label>
                                    <Input
                                        id="max_tokens"
                                        type="number"
                                        value={settings.max_tokens}
                                        onChange={(e) => setSettings(prev => ({ 
                                            ...prev, 
                                            max_tokens: parseInt(e.target.value) || 1000 
                                        }))}
                                        min={100}
                                        max={4000}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="api-keys" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center space-x-2">
                                    <Key className="w-5 h-5" />
                                    <span>API Key Management</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {Object.entries(PROVIDERS).map(([key, provider]) => (
                                    <div key={key} className="space-y-2">
                                        <Label htmlFor={`${key}_api_key`}>{provider.name} API Key</Label>
                                        <div className="flex space-x-2">
                                            <div className="relative flex-1">
                                                <Input
                                                    id={`${key}_api_key`}
                                                    type={showApiKeys[key] ? "text" : "password"}
                                                    value={settings.api_keys[key as keyof typeof settings.api_keys] || ''}
                                                    onChange={(e) => setSettings(prev => ({
                                                        ...prev,
                                                        api_keys: {
                                                            ...prev.api_keys,
                                                            [key]: e.target.value
                                                        }
                                                    }))}
                                                    placeholder={`Enter ${provider.name} API key`}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="absolute right-2 top-1/2 -translate-y-1/2 h-6 w-6 p-0"
                                                    onClick={() => toggleApiKeyVisibility(key)}
                                                >
                                                    {showApiKeys[key] ? (
                                                        <EyeOff className="w-3 h-3" />
                                                    ) : (
                                                        <Eye className="w-3 h-3" />
                                                    )}
                                                </Button>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleTestConnection(key)}
                                                disabled={!settings.api_keys[key as keyof typeof settings.api_keys]}
                                            >
                                                Test
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="advanced" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Advanced Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="system_prompt">System Prompt</Label>
                                    <textarea
                                        id="system_prompt"
                                        className="w-full min-h-[100px] p-3 border rounded-md resize-none"
                                        value={settings.system_prompt || ''}
                                        onChange={(e) => setSettings(prev => ({ ...prev, system_prompt: e.target.value }))}
                                        placeholder={`Enter custom system prompt for ${engineType} development...`}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="preferences" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>User Preferences</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label>Auto-save Conversations</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Automatically save chat conversations
                                        </p>
                                    </div>
                                    <Switch
                                        checked={settings.preferences.auto_save_conversations}
                                        onCheckedChange={(checked) => setSettings(prev => ({
                                            ...prev,
                                            preferences: { ...prev.preferences, auto_save_conversations: checked }
                                        }))}
                                    />
                                </div>

                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label>Show Token Usage</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Display token consumption for each message
                                        </p>
                                    </div>
                                    <Switch
                                        checked={settings.preferences.show_token_usage}
                                        onCheckedChange={(checked) => setSettings(prev => ({
                                            ...prev,
                                            preferences: { ...prev.preferences, show_token_usage: checked }
                                        }))}
                                    />
                                </div>

                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label>Enable Context Memory</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Remember conversation context across sessions
                                        </p>
                                    </div>
                                    <Switch
                                        checked={settings.preferences.enable_context_memory}
                                        onCheckedChange={(checked) => setSettings(prev => ({
                                            ...prev,
                                            preferences: { ...prev.preferences, enable_context_memory: checked }
                                        }))}
                                    />
                                </div>

                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label>Stream Responses</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Show AI responses as they are generated
                                        </p>
                                    </div>
                                    <Switch
                                        checked={settings.preferences.stream_responses}
                                        onCheckedChange={(checked) => setSettings(prev => ({
                                            ...prev,
                                            preferences: { ...prev.preferences, stream_responses: checked }
                                        }))}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                <div className="flex justify-end space-x-2 pt-4 border-t">
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={handleSaveSettings} disabled={isLoading}>
                        {isLoading ? (
                            <RefreshCw className="w-4 h-4 mr-2 animate-spin" />
                        ) : (
                            <Save className="w-4 h-4 mr-2" />
                        )}
                        Save Settings
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}