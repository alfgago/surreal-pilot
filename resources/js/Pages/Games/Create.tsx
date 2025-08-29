import { useState, useEffect } from "react";
import { Head, useForm, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    ArrowLeft,
    Search,
    Play,
    Download,
    Star,
    Clock,
    Code,
    Globe,
    Plus,
} from "lucide-react";
import MainLayout from "@/Layouts/MainLayout";
import { PageProps } from "@/types";

interface DemoTemplate {
    id: string;
    name: string;
    description: string;
    engine_type: "unreal" | "playcanvas";
    repository_url: string;
    preview_image?: string;
    tags: string[];
    difficulty_level: "beginner" | "intermediate" | "advanced";
    estimated_setup_time: number;
    is_active: boolean;
}

interface Workspace {
    id: number;
    name: string;
    engine_type: string;
}

interface CreateGameProps extends PageProps {
    templates: DemoTemplate[];
    workspaces: Workspace[];
    selectedTemplate?: string;
    selectedWorkspace?: number;
}

export default function CreateGame({
    templates,
    workspaces,
    selectedTemplate,
    selectedWorkspace,
}: CreateGameProps) {
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedEngine, setSelectedEngine] = useState<string>("all");
    const [selectedDifficulty, setSelectedDifficulty] = useState<string>("all");
    const [step, setStep] = useState<"template" | "details">("template");
    const [chosenTemplate, setChosenTemplate] = useState<DemoTemplate | null>(
        selectedTemplate
            ? templates.find((t) => t.id === selectedTemplate) || null
            : null
    );

    const { data, setData, post, processing, errors, reset } = useForm({
        title: "",
        description: "",
        workspace_id: selectedWorkspace || "",
        template_id: selectedTemplate || "",
    });

    useEffect(() => {
        if (chosenTemplate) {
            setData("template_id", chosenTemplate.id);
            setStep("details");
        }
    }, [chosenTemplate]);

    const categories = [
        "All",
        "Action",
        "Adventure",
        "Platformer",
        "Racing",
        "Puzzle",
        "Strategy",
    ];
    const difficulties = ["All", "Beginner", "Intermediate", "Advanced"];

    const filteredTemplates = templates.filter((template) => {
        const matchesSearch =
            template.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            template.description
                .toLowerCase()
                .includes(searchQuery.toLowerCase()) ||
            template.tags.some((tag) =>
                tag.toLowerCase().includes(searchQuery.toLowerCase())
            );
        const matchesEngine =
            selectedEngine === "all" || template.engine_type === selectedEngine;
        const matchesDifficulty =
            selectedDifficulty === "all" ||
            template.difficulty_level === selectedDifficulty.toLowerCase();

        return (
            matchesSearch &&
            matchesEngine &&
            matchesDifficulty &&
            template.is_active
        );
    });

    const handleTemplateSelect = (template: DemoTemplate) => {
        setChosenTemplate(template);
    };

    const handleCreateGame = (e: React.FormEvent) => {
        e.preventDefault();
        post("/games", {
            onSuccess: () => {
                router.visit("/games");
            },
        });
    };

    const handleBack = () => {
        if (step === "details") {
            setStep("template");
            setChosenTemplate(null);
            setData("template_id", "");
        } else {
            router.visit("/games");
        }
    };

    const handleSkipTemplate = () => {
        setChosenTemplate(null);
        setData("template_id", "");
        setStep("details");
    };

    return (
        <MainLayout>
            <Head title="Create New Game" />

            <div className="min-h-screen bg-background">
                {/* Header */}
                <header className="border-b border-border bg-card/50 backdrop-blur-sm">
                    <div className="container mx-auto px-4 py-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <button
                                    onClick={handleBack}
                                    className="flex items-center space-x-2 text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <ArrowLeft className="w-4 h-4" />
                                    <span>Back</span>
                                </button>
                                <div>
                                    <h1 className="text-xl font-serif font-black text-foreground">
                                        {step === "template"
                                            ? "Choose a Template"
                                            : "Game Details"}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        {step === "template"
                                            ? "Start with a template or create from scratch"
                                            : "Configure your new game"}
                                    </p>
                                </div>
                            </div>
                            {step === "template" && (
                                <Button
                                    variant="outline"
                                    onClick={handleSkipTemplate}
                                >
                                    Skip Template
                                </Button>
                            )}
                        </div>
                    </div>
                </header>

                <div className="container mx-auto px-4 py-8">
                    {step === "template" ? (
                        <>
                            {/* Template Selection */}
                            <div className="mb-8 space-y-4">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search templates..."
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="pl-10"
                                    />
                                </div>

                                <div className="flex flex-wrap gap-4">
                                    <div className="flex flex-wrap gap-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Engine:
                                        </span>
                                        <Button
                                            variant={
                                                selectedEngine === "all"
                                                    ? "default"
                                                    : "outline"
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setSelectedEngine("all")
                                            }
                                        >
                                            All
                                        </Button>
                                        <Button
                                            variant={
                                                selectedEngine === "unreal"
                                                    ? "default"
                                                    : "outline"
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setSelectedEngine("unreal")
                                            }
                                        >
                                            Unreal Engine
                                        </Button>
                                        <Button
                                            variant={
                                                selectedEngine === "playcanvas"
                                                    ? "default"
                                                    : "outline"
                                            }
                                            size="sm"
                                            onClick={() =>
                                                setSelectedEngine("playcanvas")
                                            }
                                        >
                                            PlayCanvas
                                        </Button>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Difficulty:
                                        </span>
                                        {difficulties.map((difficulty) => (
                                            <Button
                                                key={difficulty}
                                                variant={
                                                    selectedDifficulty ===
                                                    difficulty.toLowerCase()
                                                        ? "default"
                                                        : "outline"
                                                }
                                                size="sm"
                                                onClick={() =>
                                                    setSelectedDifficulty(
                                                        difficulty.toLowerCase()
                                                    )
                                                }
                                            >
                                                {difficulty}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Templates Grid */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {filteredTemplates.map((template) => (
                                    <Card
                                        key={template.id}
                                        className="overflow-hidden hover:shadow-lg transition-shadow cursor-pointer"
                                        onClick={() =>
                                            handleTemplateSelect(template)
                                        }
                                    >
                                        <div className="aspect-video relative overflow-hidden">
                                            {template.preview_image ? (
                                                <img
                                                    src={template.preview_image}
                                                    alt={template.name}
                                                    className="w-full h-full object-cover"
                                                />
                                            ) : (
                                                <div className="w-full h-full bg-muted flex items-center justify-center">
                                                    {template.engine_type ===
                                                    "unreal" ? (
                                                        <Code className="w-12 h-12 text-muted-foreground" />
                                                    ) : (
                                                        <Globe className="w-12 h-12 text-muted-foreground" />
                                                    )}
                                                </div>
                                            )}
                                            <div className="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <Button
                                                    size="sm"
                                                    className="bg-white/20 backdrop-blur-sm hover:bg-white/30"
                                                >
                                                    <Plus className="w-4 h-4 mr-2" />
                                                    Use Template
                                                </Button>
                                            </div>
                                        </div>

                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <CardTitle className="text-lg font-serif font-bold">
                                                        {template.name}
                                                    </CardTitle>
                                                    <div className="flex items-center space-x-2 mt-1">
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            {template.engine_type ===
                                                            "unreal"
                                                                ? "Unreal Engine"
                                                                : "PlayCanvas"}
                                                        </Badge>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs capitalize"
                                                        >
                                                            {
                                                                template.difficulty_level
                                                            }
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-1 text-sm text-muted-foreground">
                                                    <Clock className="w-3 h-3" />
                                                    <span>
                                                        {Math.ceil(
                                                            template.estimated_setup_time /
                                                                60
                                                        )}
                                                        m
                                                    </span>
                                                </div>
                                            </div>
                                            <CardDescription className="text-sm">
                                                {template.description}
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent>
                                            <div className="flex flex-wrap gap-1">
                                                {template.tags.map((tag) => (
                                                    <Badge
                                                        key={tag}
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {tag}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            {filteredTemplates.length === 0 && (
                                <div className="text-center py-12">
                                    <p className="text-muted-foreground">
                                        No templates found matching your
                                        criteria.
                                    </p>
                                    <Button
                                        variant="outline"
                                        className="mt-4"
                                        onClick={() => {
                                            setSearchQuery("");
                                            setSelectedEngine("all");
                                            setSelectedDifficulty("all");
                                        }}
                                    >
                                        Clear Filters
                                    </Button>
                                </div>
                            )}
                        </>
                    ) : (
                        /* Game Details Form */
                        <div className="max-w-2xl mx-auto">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="font-serif font-bold">
                                        Create New Game
                                    </CardTitle>
                                    <CardDescription>
                                        {chosenTemplate
                                            ? `Using template: ${chosenTemplate.name}`
                                            : "Creating a new game from scratch"}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={handleCreateGame}
                                        className="space-y-6"
                                    >
                                        <div>
                                            <label
                                                htmlFor="title"
                                                className="block text-sm font-medium text-foreground mb-2"
                                            >
                                                Game Title *
                                            </label>
                                            <Input
                                                id="title"
                                                type="text"
                                                value={data.title}
                                                onChange={(e) =>
                                                    setData(
                                                        "title",
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Enter your game title"
                                                className={
                                                    errors.title
                                                        ? "border-red-500"
                                                        : ""
                                                }
                                            />
                                            {errors.title && (
                                                <p className="text-red-500 text-sm mt-1">
                                                    {errors.title}
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <label
                                                htmlFor="description"
                                                className="block text-sm font-medium text-foreground mb-2"
                                            >
                                                Description
                                            </label>
                                            <Textarea
                                                id="description"
                                                value={data.description}
                                                onChange={(e) =>
                                                    setData(
                                                        "description",
                                                        e.target.value
                                                    )
                                                }
                                                placeholder="Describe your game..."
                                                rows={4}
                                                className={
                                                    errors.description
                                                        ? "border-red-500"
                                                        : ""
                                                }
                                            />
                                            {errors.description && (
                                                <p className="text-red-500 text-sm mt-1">
                                                    {errors.description}
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <label
                                                htmlFor="workspace_id"
                                                className="block text-sm font-medium text-foreground mb-2"
                                            >
                                                Workspace *
                                            </label>
                                            <Select
                                                value={data.workspace_id.toString()}
                                                onValueChange={(value) =>
                                                    setData(
                                                        "workspace_id",
                                                        parseInt(value)
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    className={
                                                        errors.workspace_id
                                                            ? "border-red-500"
                                                            : ""
                                                    }
                                                >
                                                    <SelectValue placeholder="Select a workspace" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {workspaces.map(
                                                        (workspace) => (
                                                            <SelectItem
                                                                key={
                                                                    workspace.id
                                                                }
                                                                value={workspace.id.toString()}
                                                            >
                                                                <div className="flex items-center space-x-2">
                                                                    {workspace.engine_type ===
                                                                    "unreal" ? (
                                                                        <Code className="w-4 h-4" />
                                                                    ) : (
                                                                        <Globe className="w-4 h-4" />
                                                                    )}
                                                                    <span>
                                                                        {
                                                                            workspace.name
                                                                        }
                                                                    </span>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs"
                                                                    >
                                                                        {
                                                                            workspace.engine_type
                                                                        }
                                                                    </Badge>
                                                                </div>
                                                            </SelectItem>
                                                        )
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            {errors.workspace_id && (
                                                <p className="text-red-500 text-sm mt-1">
                                                    {errors.workspace_id}
                                                </p>
                                            )}
                                        </div>

                                        {chosenTemplate && (
                                            <div className="p-4 bg-muted rounded-lg">
                                                <h4 className="font-medium text-foreground mb-2">
                                                    Selected Template
                                                </h4>
                                                <div className="flex items-center space-x-3">
                                                    <div className="w-12 h-8 bg-background rounded overflow-hidden">
                                                        {chosenTemplate.preview_image ? (
                                                            <img
                                                                src={
                                                                    chosenTemplate.preview_image
                                                                }
                                                                alt={
                                                                    chosenTemplate.name
                                                                }
                                                                className="w-full h-full object-cover"
                                                            />
                                                        ) : (
                                                            <div className="w-full h-full flex items-center justify-center">
                                                                {chosenTemplate.engine_type ===
                                                                "unreal" ? (
                                                                    <Code className="w-4 h-4 text-muted-foreground" />
                                                                ) : (
                                                                    <Globe className="w-4 h-4 text-muted-foreground" />
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-sm">
                                                            {
                                                                chosenTemplate.name
                                                            }
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                chosenTemplate.description
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        <div className="flex items-center space-x-4 pt-4">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? "Creating..."
                                                    : "Create Game"}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={handleBack}
                                            >
                                                Back
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
