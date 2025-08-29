import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowRight, Gamepad2, Code, Zap, Users, Shield, Sparkles } from 'lucide-react';
import { useAuth, useFlash } from '@/hooks';

export default function Welcome() {
    const { user, isAuthenticated } = useAuth();
    const { success, error } = useFlash();

    return (
        <>
            <Head title="SurrealPilot - AI Copilot for Game Development">
                <meta name="description" content="Accelerate your game development with intelligent assistance for Unreal Engine and PlayCanvas. Get real-time code suggestions, debugging help, and creative guidance." />
                <meta name="keywords" content="AI, game development, Unreal Engine, PlayCanvas, copilot, code assistance" />
                <meta property="og:title" content="SurrealPilot - AI Copilot for Game Development" />
                <meta property="og:description" content="Accelerate your game development with intelligent assistance for Unreal Engine and PlayCanvas." />
                <meta property="og:type" content="website" />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content="SurrealPilot - AI Copilot for Game Development" />
                <meta name="twitter:description" content="Accelerate your game development with intelligent assistance for Unreal Engine and PlayCanvas." />
            </Head>
            
            <div className="min-h-screen bg-background">
                {/* Header */}
                <header className="border-b border-border bg-card/50 backdrop-blur-sm">
                    <div className="container mx-auto px-4 py-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-2">
                                <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                                    <Gamepad2 className="w-5 h-5 text-primary-foreground" />
                                </div>
                                <span className="text-xl font-serif font-black text-foreground">SurrealPilot</span>
                            </div>
                            <nav className="hidden md:flex items-center space-x-6">
                                <a href="#features" className="text-muted-foreground hover:text-foreground transition-colors">
                                    Features
                                </a>
                                <a href="#pricing" className="text-muted-foreground hover:text-foreground transition-colors">
                                    Pricing
                                </a>
                                <a href="#about" className="text-muted-foreground hover:text-foreground transition-colors">
                                    About
                                </a>
                            </nav>
                            <div className="flex items-center space-x-3">
                                {isAuthenticated ? (
                                    <Button asChild>
                                        <Link href="/dashboard">Dashboard</Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button variant="ghost" asChild>
                                            <Link href="/login">Sign In</Link>
                                        </Button>
                                        <Button asChild>
                                            <Link href="/register">Get Started</Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                {/* Flash Messages */}
                {(success || error) && (
                    <div className="container mx-auto px-4 py-4">
                        {success && (
                            <div className="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                                {success}
                            </div>
                        )}
                        {error && (
                            <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                                {error}
                            </div>
                        )}
                    </div>
                )}

                {/* Hero Section */}
                <section className="py-20 px-4">
                    <div className="container mx-auto text-center max-w-4xl">
                        <Badge variant="secondary" className="mb-4">
                            <Sparkles className="w-3 h-3 mr-1" />
                            AI-Powered Development
                        </Badge>
                        <h1 className="text-4xl md:text-6xl font-serif font-black text-foreground mb-6 leading-tight">
                            Your AI Copilot for
                            <span className="text-primary block">Game Development</span>
                        </h1>
                        <p className="text-xl text-muted-foreground mb-8 leading-relaxed">
                            Accelerate your game development with intelligent assistance for Unreal Engine and PlayCanvas. Get real-time
                            code suggestions, debugging help, and creative guidance.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Button size="lg" className="text-lg px-8" asChild>
                                <Link href="/register">
                                    Start Building <ArrowRight className="ml-2 w-5 h-5" />
                                </Link>
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                className="text-lg px-8 bg-transparent"
                                onClick={() => alert("Demo video coming soon!")}
                            >
                                Watch Demo
                            </Button>
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section id="features" className="py-20 px-4 bg-card/30">
                    <div className="container mx-auto max-w-6xl">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl md:text-4xl font-serif font-black text-foreground mb-4">
                                Powerful Features for Modern Game Development
                            </h2>
                            <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
                                Everything you need to build amazing games faster with AI assistance
                            </p>
                        </div>

                        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                        <Code className="w-6 h-6 text-primary" />
                                    </div>
                                    <CardTitle className="font-serif font-bold">Multi-Engine Support</CardTitle>
                                    <CardDescription>
                                        Works seamlessly with Unreal Engine C++/Blueprints and PlayCanvas for web/mobile games
                                    </CardDescription>
                                </CardHeader>
                            </Card>

                            <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                        <Zap className="w-6 h-6 text-primary" />
                                    </div>
                                    <CardTitle className="font-serif font-bold">Real-time AI Chat</CardTitle>
                                    <CardDescription>
                                        Streaming responses with context-aware suggestions and intelligent debugging assistance
                                    </CardDescription>
                                </CardHeader>
                            </Card>

                            <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                        <Users className="w-6 h-6 text-primary" />
                                    </div>
                                    <CardTitle className="font-serif font-bold">Team Collaboration</CardTitle>
                                    <CardDescription>
                                        Company-wide workspaces with role-based access and shared project management
                                    </CardDescription>
                                </CardHeader>
                            </Card>

                            <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                        <Shield className="w-6 h-6 text-primary" />
                                    </div>
                                    <CardTitle className="font-serif font-bold">Enterprise Ready</CardTitle>
                                    <CardDescription>
                                        Secure multi-tenancy with subscription plans and credit-based usage tracking
                                    </CardDescription>
                                </CardHeader>
                            </Card>

                            <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                        <Gamepad2 className="w-6 h-6 text-primary" />
                                    </div>
                                    <CardTitle className="font-serif font-bold">Live Game Preview</CardTitle>
                                    <CardDescription>
                                        Instant preview of PlayCanvas games with real-time updates and testing capabilities
                                    </CardDescription>
                                </CardHeader>
                            </Card>

                            <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                        <Sparkles className="w-6 h-6 text-primary" />
                                    </div>
                                    <CardTitle className="font-serif font-bold">Smart Templates</CardTitle>
                                    <CardDescription>
                                        Pre-built game templates and samples to jumpstart your development process
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="py-20 px-4">
                    <div className="container mx-auto text-center max-w-3xl">
                        <h2 className="text-3xl md:text-4xl font-serif font-black text-foreground mb-6">
                            Ready to Transform Your Game Development?
                        </h2>
                        <p className="text-lg text-muted-foreground mb-8">
                            Join thousands of developers who are building better games faster with AI assistance.
                        </p>
                        <Button size="lg" className="text-lg px-8" asChild>
                            <Link href="/register">
                                Get Started Free <ArrowRight className="ml-2 w-5 h-5" />
                            </Link>
                        </Button>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-border bg-card/50 py-12 px-4">
                    <div className="container mx-auto">
                        <div className="flex flex-col md:flex-row justify-between items-center">
                            <div className="flex items-center space-x-2 mb-4 md:mb-0">
                                <div className="w-6 h-6 bg-primary rounded flex items-center justify-center">
                                    <Gamepad2 className="w-4 h-4 text-primary-foreground" />
                                </div>
                                <span className="font-serif font-black text-foreground">SurrealPilot</span>
                            </div>
                            <div className="flex space-x-6 text-sm text-muted-foreground">
                                <Link href="/privacy" className="hover:text-foreground transition-colors">
                                    Privacy
                                </Link>
                                <Link href="/terms" className="hover:text-foreground transition-colors">
                                    Terms
                                </Link>
                                <Link href="/support" className="hover:text-foreground transition-colors">
                                    Support
                                </Link>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}