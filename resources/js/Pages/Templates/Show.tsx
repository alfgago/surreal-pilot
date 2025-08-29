import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Clock, Code, ExternalLink, Gamepad2, Zap, Download, Play } from 'lucide-react';

interface Template {
    id: string;
    name: string;
    description: string;
    engine_type: 'playcanvas' | 'unreal';
    preview_image?: string;
    tags: string[];
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    estimated_setup_time: number;
    repository_url: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    template: Template;
    relatedTemplates: Array<{
        id: string;
        name: string;
        description: string;
        preview_image?: string;
        difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    }>;
}

const difficultyColors = {
    beginner: 'bg-green-100 text-green-800',
    intermediate: 'bg-yellow-100 text-yellow-800',
    advanced: 'bg-red-100 text-red-800',
};

const engineIcons = {
    playcanvas: Gamepad2,
    unreal: Zap,
};

export default function TemplateShow({ template, relatedTemplates }: Props) {
    const EngineIcon = engineIcons[template.engine_type];

    const formatSetupTime = (seconds: number) => {
        if (seconds < 60) return `${seconds} seconds`;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return remainingSeconds > 0 
            ? `${minutes} minutes ${remainingSeconds} seconds`
            : `${minutes} minutes`;
    };

    const handleUseTemplate = () => {
        // Navigate to workspace creation with this template pre-selected
        router.get('/workspaces/create', {
            template: template.id,
            engine: template.engine_type,
        });
    };

    return (
        <MainLayout>
            <Head title={`${template.name} - Template`} />

            <div className="space-y-6">
                {/* Back Button */}
                <Button variant="ghost" asChild>
                    <Link href="/templates">
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Back to Templates
                    </Link>
                </Button>

                {/* Header */}
                <div className="flex flex-col lg:flex-row gap-6">
                    {/* Preview Image */}
                    <div className="lg:w-1/2">
                        <div className="aspect-video bg-gradient-to-br from-blue-50 to-indigo-100 rounded-lg overflow-hidden relative group">
                            {template.preview_image ? (
                                <img
                                    src={template.preview_image}
                                    alt={template.name}
                                    className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                            ) : (
                                <div className="flex items-center justify-center h-full">
                                    <EngineIcon className="h-16 w-16 text-muted-foreground" />
                                </div>
                            )}
                            <div className="absolute top-4 right-4">
                                <Badge className={difficultyColors[template.difficulty_level]}>
                                    {template.difficulty_level}
                                </Badge>
                            </div>
                            {/* Interactive Preview Overlay */}
                            <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                                <div className="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    {template.engine_type === 'playcanvas' && (
                                        <Button variant="secondary" size="sm">
                                            <Play className="h-4 w-4 mr-2" />
                                            Preview Demo
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Template Info */}
                    <div className="lg:w-1/2 space-y-4">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <EngineIcon className="h-6 w-6 text-muted-foreground" />
                                <span className="text-sm text-muted-foreground capitalize">
                                    {template.engine_type === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine'}
                                </span>
                            </div>
                            <h1 className="text-3xl font-bold">{template.name}</h1>
                            <p className="text-muted-foreground mt-2 text-lg">
                                {template.description}
                            </p>
                        </div>

                        {/* Tags */}
                        {template.tags.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {template.tags.map((tag) => (
                                    <Badge key={tag} variant="secondary">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>
                        )}

                        {/* Setup Time */}
                        <div className="flex items-center text-muted-foreground">
                            <Clock className="h-5 w-5 mr-2" />
                            <span>Estimated setup time: {formatSetupTime(template.estimated_setup_time)}</span>
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 pt-4">
                            <Button onClick={handleUseTemplate} size="lg" className="flex-1">
                                <Download className="h-4 w-4 mr-2" />
                                Use This Template
                            </Button>
                            <Button variant="outline" size="lg" asChild>
                                <a
                                    href={template.repository_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    View Source
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Details Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Code className="h-5 w-5" />
                                Technical Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Engine:</span>
                                <span className="capitalize">
                                    {template.engine_type === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine'}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Difficulty:</span>
                                <Badge className={difficultyColors[template.difficulty_level]}>
                                    {template.difficulty_level}
                                </Badge>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Setup Time:</span>
                                <span>{formatSetupTime(template.estimated_setup_time)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Template ID:</span>
                                <code className="text-sm bg-muted px-2 py-1 rounded">
                                    {template.id}
                                </code>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Getting Started</CardTitle>
                            <CardDescription>
                                How to use this template in your project
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <h4 className="font-medium">1. Create New Workspace</h4>
                                <p className="text-sm text-muted-foreground">
                                    Click "Use This Template" to create a new workspace with this template
                                </p>
                            </div>
                            <div className="space-y-2">
                                <h4 className="font-medium">2. Customize Your Game</h4>
                                <p className="text-sm text-muted-foreground">
                                    Modify the template code to match your game vision
                                </p>
                            </div>
                            <div className="space-y-2">
                                <h4 className="font-medium">3. Test & Deploy</h4>
                                <p className="text-sm text-muted-foreground">
                                    Use our preview and publishing tools to share your game
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Repository Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Repository Information</CardTitle>
                        <CardDescription>
                            Source code and additional resources
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium">GitHub Repository</p>
                                <p className="text-sm text-muted-foreground">
                                    View the complete source code and documentation
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <a
                                    href={template.repository_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink className="h-4 w-4 mr-2" />
                                    Open Repository
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Related Templates */}
                {relatedTemplates.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Related Templates</CardTitle>
                            <CardDescription>
                                Other {template.engine_type === 'playcanvas' ? 'PlayCanvas' : 'Unreal Engine'} templates you might like
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {relatedTemplates.map((relatedTemplate) => (
                                    <Link
                                        key={relatedTemplate.id}
                                        href={`/templates/${relatedTemplate.id}`}
                                        className="group block"
                                    >
                                        <div className="border rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                            <div className="aspect-video bg-gradient-to-br from-blue-50 to-indigo-100 relative">
                                                {relatedTemplate.preview_image ? (
                                                    <img
                                                        src={relatedTemplate.preview_image}
                                                        alt={relatedTemplate.name}
                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                                    />
                                                ) : (
                                                    <div className="flex items-center justify-center h-full">
                                                        <EngineIcon className="h-8 w-8 text-muted-foreground" />
                                                    </div>
                                                )}
                                                <div className="absolute top-2 right-2">
                                                    <Badge className={difficultyColors[relatedTemplate.difficulty_level]} size="sm">
                                                        {relatedTemplate.difficulty_level}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="p-3">
                                                <h4 className="font-medium text-sm group-hover:text-blue-600 transition-colors">
                                                    {relatedTemplate.name}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                                                    {relatedTemplate.description}
                                                </p>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </MainLayout>
    );
}