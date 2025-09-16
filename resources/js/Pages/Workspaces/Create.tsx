import { FormEventHandler, useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Gamepad2, Monitor, CheckCircle } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';

interface Engine {
    type: string;
    name: string;
    description: string;
    available: boolean;
    features?: string[];
}

interface Template {
    id: number;
    name: string;
    description?: string;
    thumbnail_url?: string;
}

interface CreateWorkspaceProps {
    engines: Record<string, Engine>;
}

interface CreateWorkspaceForm {
    name: string;
    engine_type: string;
    template_id: string;
}

export default function CreateWorkspace({ engines }: CreateWorkspaceProps) {
    const [templates, setTemplates] = useState<Template[]>([]);
    const [loadingTemplates, setLoadingTemplates] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<CreateWorkspaceForm>({
        name: '',
        engine_type: '',
        template_id: 'none',
    });

    const loadTemplates = async (engineType: string) => {
        if (!engineType) return;
        
        setLoadingTemplates(true);
        try {
            const response = await fetch(`/workspaces/templates?engine_type=${engineType}`);
            const result = await response.json();
            if (result.success) {
                setTemplates(result.templates);
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
            setTemplates([]);
        } finally {
            setLoadingTemplates(false);
        }
    };

    useEffect(() => {
        if (data.engine_type) {
            loadTemplates(data.engine_type);
            // Reset template selection when engine changes
            setData('template_id', 'none');
        } else {
            setTemplates([]);
        }
    }, [data.engine_type]);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        // Convert "none" to null for the backend
        const formData = {
            ...data,
            template_id: data.template_id === 'none' ? null : data.template_id
        };

        post('/workspaces', {
            data: formData,
        });
    };

    const getEngineIcon = (engineType: string) => {
        switch (engineType) {
            case 'gdevelop':
                return <Gamepad2 className="w-6 h-6 text-primary" />;
            case 'godot':
                return <Gamepad2 className="w-6 h-6 text-primary" />;
            case 'playcanvas':
                return <Monitor className="w-6 h-6 text-primary" />;
            case 'unreal':
                return <Gamepad2 className="w-6 h-6 text-primary" />;
            default:
                return <Gamepad2 className="w-6 h-6 text-primary" />;
        }
    };

    return (
        <MainLayout>
            <Head title="Create Workspace" />

            <div className="max-w-4xl mx-auto px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <Link 
                        href="/workspaces" 
                        className="inline-flex items-center text-muted-foreground hover:text-foreground mb-4"
                    >
                        <ArrowLeft className="w-4 h-4 mr-2" />
                        Back to Workspaces
                    </Link>
                    <h1 className="text-3xl font-serif font-black text-foreground mb-2">
                        Create New Workspace
                    </h1>
                    <p className="text-muted-foreground">
                        Set up a new workspace for your game development project
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-8">
                    {/* Workspace Name */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Workspace Details</CardTitle>
                            <CardDescription>
                                Give your workspace a name that describes your project
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <Label htmlFor="name">Workspace Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className={errors.name ? 'border-red-500' : ''}
                                    placeholder="My Awesome Game"
                                    required
                                />
                                {errors.name && (
                                    <div className="text-sm text-red-600">{errors.name}</div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Engine Selection */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Choose Game Engine</CardTitle>
                            <CardDescription>
                                Select the game engine you want to use for this workspace
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid md:grid-cols-2 gap-4">
                                {Object.entries(engines).map(([engineType, engine]) => (
                                    <Card 
                                        key={engineType}
                                        className={`border-2 cursor-pointer transition-all duration-200 ${
                                            data.engine_type === engineType 
                                                ? 'border-primary bg-primary/5 shadow-lg' 
                                                : 'border-border hover:border-primary/50 hover:bg-card/80'
                                        } ${!engine.available ? 'opacity-50 cursor-not-allowed' : ''}`}
                                        onClick={() => engine.available && setData('engine_type', engineType)}
                                    >
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-center space-x-3">
                                                    <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                                                        {getEngineIcon(engineType)}
                                                    </div>
                                                    <div>
                                                        <CardTitle className="text-lg flex items-center">
                                                            {engine.name}
                                                            {!engine.available && (
                                                                <Badge variant="secondary" className="ml-2 text-xs">
                                                                    Coming Soon
                                                                </Badge>
                                                            )}
                                                        </CardTitle>
                                                        <CardDescription className="text-sm">
                                                            {engine.description}
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                                {data.engine_type === engineType && (
                                                    <CheckCircle className="w-5 h-5 text-primary" />
                                                )}
                                            </div>
                                        </CardHeader>
                                        
                                        {engine.features && (
                                            <CardContent className="pt-0">
                                                <ul className="space-y-1">
                                                    {engine.features.slice(0, 3).map((feature, index) => (
                                                        <li key={index} className="flex items-center text-xs text-muted-foreground">
                                                            <CheckCircle className="w-3 h-3 text-green-500 mr-2 flex-shrink-0" />
                                                            {feature}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </CardContent>
                                        )}
                                    </Card>
                                ))}
                            </div>
                            {errors.engine_type && (
                                <div className="text-sm text-red-600 mt-2">{errors.engine_type}</div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Template Selection */}
                    {data.engine_type && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Choose Template (Optional)</CardTitle>
                                <CardDescription>
                                    Start with a template or create a blank project
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Label htmlFor="template">Template</Label>
                                    <Select 
                                        value={data.template_id} 
                                        onValueChange={(value) => setData('template_id', value)}
                                    >
                                        <SelectTrigger className={errors.template_id ? 'border-red-500' : ''}>
                                            <SelectValue placeholder={loadingTemplates ? "Loading templates..." : "Choose a template"} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Blank Project</SelectItem>
                                            {templates.map((template) => (
                                                <SelectItem key={template.id} value={template.id.toString()}>
                                                    <div>
                                                        <div className="font-medium">{template.name}</div>
                                                        {template.description && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {template.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.template_id && (
                                        <div className="text-sm text-red-600">{errors.template_id}</div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Submit Button */}
                    <div className="flex justify-end space-x-4">
                        <Link href="/workspaces">
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button 
                            type="submit" 
                            disabled={processing || !data.name || !data.engine_type}
                            size="lg"
                        >
                            {processing ? 'Creating...' : 'Create Workspace'}
                        </Button>
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}