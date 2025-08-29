import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Search, Clock, Code, Gamepad2, Zap } from 'lucide-react';

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
}

interface Props {
    templates: Template[];
    filters: {
        engine: string;
        difficulty: string;
        search: string;
    };
    stats: {
        total: number;
        playcanvas: number;
        unreal: number;
    };
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

export default function TemplatesIndex({ templates, filters, stats }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search);

    const handleSearch = (value: string) => {
        setSearchTerm(value);
        router.get('/templates', {
            ...filters,
            search: value,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleFilterChange = (key: string, value: string) => {
        router.get('/templates', {
            ...filters,
            [key]: value,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const formatSetupTime = (seconds: number) => {
        if (seconds < 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m`;
    };

    return (
        <MainLayout>
            <Head title="Templates Library" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold">Templates Library</h1>
                    <p className="text-muted-foreground mt-2">
                        Choose from our collection of game templates to jumpstart your project
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Code className="h-5 w-5 text-blue-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.total}</p>
                                    <p className="text-sm text-muted-foreground">Total Templates</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Gamepad2 className="h-5 w-5 text-green-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.playcanvas}</p>
                                    <p className="text-sm text-muted-foreground">PlayCanvas</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center space-x-2">
                                <Zap className="h-5 w-5 text-purple-600" />
                                <div>
                                    <p className="text-2xl font-bold">{stats.unreal}</p>
                                    <p className="text-sm text-muted-foreground">Unreal Engine</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Search templates..."
                            value={searchTerm}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-10"
                        />
                    </div>
                    <Select
                        value={filters.engine}
                        onValueChange={(value) => handleFilterChange('engine', value)}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Engine Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Engines</SelectItem>
                            <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                            <SelectItem value="unreal">Unreal Engine</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.difficulty}
                        onValueChange={(value) => handleFilterChange('difficulty', value)}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Difficulty" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Levels</SelectItem>
                            <SelectItem value="beginner">Beginner</SelectItem>
                            <SelectItem value="intermediate">Intermediate</SelectItem>
                            <SelectItem value="advanced">Advanced</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Templates Grid */}
                {templates.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Code className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No templates found</h3>
                            <p className="text-muted-foreground">
                                Try adjusting your search criteria or filters
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {templates.map((template) => {
                            const EngineIcon = engineIcons[template.engine_type];
                            
                            return (
                                <Card key={template.id} className="group hover:shadow-lg transition-shadow">
                                    <div className="aspect-video bg-gradient-to-br from-blue-50 to-indigo-100 relative overflow-hidden">
                                        {template.preview_image ? (
                                            <img
                                                src={template.preview_image}
                                                alt={template.name}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                        ) : (
                                            <div className="flex items-center justify-center h-full">
                                                <EngineIcon className="h-12 w-12 text-muted-foreground" />
                                            </div>
                                        )}
                                        <div className="absolute top-2 right-2">
                                            <Badge className={difficultyColors[template.difficulty_level]}>
                                                {template.difficulty_level}
                                            </Badge>
                                        </div>
                                    </div>
                                    <CardHeader>
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <CardTitle className="text-lg">{template.name}</CardTitle>
                                                <CardDescription className="mt-1">
                                                    {template.description}
                                                </CardDescription>
                                            </div>
                                            <EngineIcon className="h-5 w-5 text-muted-foreground ml-2 flex-shrink-0" />
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {/* Tags */}
                                            {template.tags.length > 0 && (
                                                <div className="flex flex-wrap gap-1">
                                                    {template.tags.slice(0, 3).map((tag) => (
                                                        <Badge key={tag} variant="secondary" className="text-xs">
                                                            {tag}
                                                        </Badge>
                                                    ))}
                                                    {template.tags.length > 3 && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            +{template.tags.length - 3}
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}

                                            {/* Setup Time */}
                                            <div className="flex items-center text-sm text-muted-foreground">
                                                <Clock className="h-4 w-4 mr-1" />
                                                Setup time: {formatSetupTime(template.estimated_setup_time)}
                                            </div>

                                            {/* Actions */}
                                            <div className="flex gap-2">
                                                <Button asChild className="flex-1">
                                                    <Link href={`/templates/${template.id}`}>
                                                        View Details
                                                    </Link>
                                                </Button>
                                                <Button variant="outline" asChild>
                                                    <a
                                                        href={template.repository_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        GitHub
                                                    </a>
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </MainLayout>
    );
}