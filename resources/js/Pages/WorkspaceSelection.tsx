import { FormEventHandler, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Plus, Folder, Calendar, ArrowRight, Gamepad2 } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';

interface Workspace {
    id: number;
    name: string;
    engine_type: string;
    created_at: string;
    updated_at: string;
}

interface Template {
    id: number;
    name: string;
    description?: string;
    thumbnail_url?: string;
}

interface EngineInfo {
    name: string;
    description: string;
    icon: string;
}

interface WorkspaceSelectionProps {
    engineInfo: EngineInfo;
    workspaces: Workspace[];
    engineType: string;
}

interface CreateWorkspaceForm {
    name: string;
    template_id: string;
}

export default function WorkspaceSelection({ engineInfo, workspaces, engineType }: WorkspaceSelectionProps) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [templates, setTemplates] = useState<Template[]>([]);
    const [loadingTemplates, setLoadingTemplates] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<CreateWorkspaceForm>({
        name: '',
        template_id: 'none',
    });

    const selectWorkspace = (workspaceId: number) => {
        router.post('/workspace-selection', { workspace_id: workspaceId });
    };

    const loadTemplates = async () => {
        if (templates.length > 0) return; // Already loaded
        
        setLoadingTemplates(true);
        try {
            const response = await fetch('/workspace-selection/templates');
            const result = await response.json();
            if (result.success) {
                setTemplates(result.templates);
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
        } finally {
            setLoadingTemplates(false);
        }
    };

    const handleCreateWorkspace: FormEventHandler = (e) => {
        e.preventDefault();

        // Convert "none" to null for the backend
        const formData = {
            ...data,
            template_id: data.template_id === 'none' ? null : data.template_id
        };

        post('/workspace-selection/create', {
            data: formData,
            onSuccess: () => {
                reset();
                setIsCreateModalOpen(false);
            },
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <MainLayout>
            <Head title="Select Workspace" />

            <div className="max-w-6xl mx-auto px-4 py-8">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center space-x-3 mb-4">
                        <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                            <Gamepad2 className="w-6 h-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-serif font-black text-foreground">
                                Select Workspace
                            </h1>
                            <p className="text-muted-foreground">
                                Choose a workspace for {engineInfo.name} development
                            </p>
                        </div>
                    </div>
                    
                    <Badge variant="secondary" className="mb-4">
                        {engineInfo.name}
                    </Badge>
                </div>

                {/* Create New Workspace Button */}
                <div className="mb-6">
                    <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                        <DialogTrigger asChild>
                            <Button 
                                size="lg" 
                                className="mb-4"
                                onClick={loadTemplates}
                            >
                                <Plus className="w-5 h-5 mr-2" />
                                Create New Workspace
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-md">
                            <DialogHeader>
                                <DialogTitle>Create New Workspace</DialogTitle>
                                <DialogDescription>
                                    Set up a new workspace for your {engineInfo.name} project
                                </DialogDescription>
                            </DialogHeader>
                            
                            <form onSubmit={handleCreateWorkspace} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Workspace Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className={errors.name ? 'border-red-500' : ''}
                                        placeholder="My Game Project"
                                        required
                                    />
                                    {errors.name && (
                                        <div className="text-sm text-red-600">{errors.name}</div>
                                    )}
                                </div>



                                <div className="space-y-2">
                                    <Label htmlFor="template">Template (Optional)</Label>
                                    <Select 
                                        value={data.template_id} 
                                        onValueChange={(value) => setData('template_id', value)}
                                    >
                                        <SelectTrigger className={errors.template_id ? 'border-red-500' : ''}>
                                            <SelectValue placeholder={loadingTemplates ? "Loading templates..." : "Choose a template"} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No template</SelectItem>
                                            {templates.map((template) => (
                                                <SelectItem key={template.id} value={template.id.toString()}>
                                                    {template.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.template_id && (
                                        <div className="text-sm text-red-600">{errors.template_id}</div>
                                    )}
                                </div>

                                <div className="flex justify-end space-x-3 pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsCreateModalOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Creating...' : 'Create Workspace'}
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Existing Workspaces */}
                {workspaces.length > 0 ? (
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {workspaces.map((workspace) => (
                            <Card 
                                key={workspace.id} 
                                className="border-border bg-card hover:bg-card/80 transition-colors cursor-pointer"
                                onClick={() => selectWorkspace(workspace.id)}
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center space-x-3">
                                            <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                                                <Folder className="w-5 h-5 text-primary" />
                                            </div>
                                            <div>
                                                <CardTitle className="font-serif font-bold text-lg">
                                                    {workspace.name}
                                                </CardTitle>
                                                <div className="flex items-center text-sm text-muted-foreground mt-1">
                                                    <Calendar className="w-3 h-3 mr-1" />
                                                    {formatDate(workspace.updated_at)}
                                                </div>
                                            </div>
                                        </div>
                                        <ArrowRight className="w-5 h-5 text-muted-foreground" />
                                    </div>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card className="border-dashed border-2 border-border bg-card/50">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <div className="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                                <Folder className="w-8 h-8 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold text-foreground mb-2">
                                No workspaces yet
                            </h3>
                            <p className="text-muted-foreground text-center mb-6 max-w-md">
                                Create your first workspace to start building games with {engineInfo.name}
                            </p>
                            <Button onClick={() => setIsCreateModalOpen(true)}>
                                <Plus className="w-4 h-4 mr-2" />
                                Create Your First Workspace
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </MainLayout>
    );
}