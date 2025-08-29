import { FormEventHandler, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, ArrowRight, Gamepad2, Monitor, Smartphone } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';

interface Engine {
    type: string;
    name: string;
    description: string;
    icon?: string;
    features?: string[];
    available: boolean;
    requirements?: string[];
}

interface EngineSelectionProps {
    engines: Record<string, Engine>;
}

interface EngineSelectionForm {
    engine_type: string;
}

export default function EngineSelection({ engines }: EngineSelectionProps) {
    const [selectedEngine, setSelectedEngine] = useState<string>('');
    
    const { data, setData, post, processing, errors } = useForm<EngineSelectionForm>({
        engine_type: '',
    });

    const handleEngineSelect = (engineType: string) => {
        setSelectedEngine(engineType);
        setData('engine_type', engineType);
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        
        if (!data.engine_type) {
            return;
        }

        post('/engine-selection');
    };

    const getEngineIcon = (engineType: string) => {
        switch (engineType) {
            case 'playcanvas':
                return <Monitor className="w-8 h-8 text-primary" />;
            case 'unreal':
                return <Gamepad2 className="w-8 h-8 text-primary" />;
            default:
                return <Gamepad2 className="w-8 h-8 text-primary" />;
        }
    };

    return (
        <MainLayout>
            <Head title="Select Game Engine" />

            <div className="max-w-4xl mx-auto px-4 py-8">
                {/* Header */}
                <div className="text-center mb-12">
                    <h1 className="text-4xl font-serif font-black text-foreground mb-4">
                        Choose Your Game Engine
                    </h1>
                    <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
                        Select the game engine you want to work with. You can change this later in your settings.
                    </p>
                </div>

                <form onSubmit={handleSubmit}>
                    {/* Engine Options */}
                    <div className="grid md:grid-cols-2 gap-6 mb-8">
                        {Object.entries(engines).map(([engineType, engine]) => (
                            <Card 
                                key={engineType}
                                className={`border-2 cursor-pointer transition-all duration-200 ${
                                    selectedEngine === engineType 
                                        ? 'border-primary bg-primary/5 shadow-lg' 
                                        : 'border-border hover:border-primary/50 hover:bg-card/80'
                                } ${!engine.available ? 'opacity-50 cursor-not-allowed' : ''}`}
                                onClick={() => engine.available && handleEngineSelect(engineType)}
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center space-x-4">
                                            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                                                {getEngineIcon(engineType)}
                                            </div>
                                            <div>
                                                <CardTitle className="font-serif font-bold text-xl flex items-center">
                                                    {engine.name}
                                                    {!engine.available && (
                                                        <Badge variant="secondary" className="ml-2 text-xs">
                                                            Coming Soon
                                                        </Badge>
                                                    )}
                                                </CardTitle>
                                                <CardDescription className="mt-1">
                                                    {engine.description}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        {selectedEngine === engineType && (
                                            <CheckCircle className="w-6 h-6 text-primary" />
                                        )}
                                    </div>
                                </CardHeader>
                                
                                {engine.features && (
                                    <CardContent>
                                        <div className="space-y-3">
                                            <h4 className="font-semibold text-sm text-foreground">Key Features:</h4>
                                            <ul className="space-y-1">
                                                {engine.features.map((feature, index) => (
                                                    <li key={index} className="flex items-center text-sm text-muted-foreground">
                                                        <CheckCircle className="w-3 h-3 text-green-500 mr-2 flex-shrink-0" />
                                                        {feature}
                                                    </li>
                                                ))}
                                            </ul>
                                            
                                            {engine.requirements && (
                                                <div className="mt-4 pt-3 border-t border-border">
                                                    <h4 className="font-semibold text-sm text-foreground mb-2">Requirements:</h4>
                                                    <ul className="space-y-1">
                                                        {engine.requirements.map((requirement, index) => (
                                                            <li key={index} className="text-xs text-muted-foreground">
                                                                • {requirement}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                )}
                            </Card>
                        ))}
                    </div>

                    {/* Error Display */}
                    {errors.engine_type && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div className="text-sm text-red-600">{errors.engine_type}</div>
                        </div>
                    )}

                    {/* Continue Button */}
                    <div className="text-center">
                        <Button 
                            type="submit" 
                            size="lg" 
                            disabled={!selectedEngine || processing}
                            className="px-8"
                        >
                            {processing ? 'Setting up...' : 'Continue to Workspaces'}
                            <ArrowRight className="w-5 h-5 ml-2" />
                        </Button>
                    </div>
                </form>

                {/* Help Text */}
                <div className="mt-8 text-center">
                    <p className="text-sm text-muted-foreground">
                        Need help choosing? Check out our{' '}
                        <a href="#" className="text-primary hover:underline">
                            engine comparison guide
                        </a>{' '}
                        or{' '}
                        <a href="/support" className="text-primary hover:underline">
                            contact support
                        </a>.
                    </p>
                </div>
            </div>
        </MainLayout>
    );
}