import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Mail, MessageCircle, Book, ExternalLink } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Support() {
    return (
        <GuestLayout title="Support">
            <Head title="Support - SurrealPilot">
                <meta name="description" content="Get help with SurrealPilot - Find documentation, contact support, and get answers to common questions." />
            </Head>
            
            <div className="max-w-6xl mx-auto px-4 py-16">
                <div className="text-center mb-12">
                    <h1 className="text-4xl font-serif font-black text-foreground mb-4">
                        How can we help you?
                    </h1>
                    <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
                        Get the support you need to build amazing games with SurrealPilot
                    </p>
                </div>

                <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                    <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                        <CardHeader>
                            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                <Book className="w-6 h-6 text-primary" />
                            </div>
                            <CardTitle className="font-serif font-bold">Documentation</CardTitle>
                            <CardDescription>
                                Comprehensive guides and API documentation to get you started
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" className="w-full" asChild>
                                <a href="#" target="_blank" rel="noopener noreferrer">
                                    View Docs <ExternalLink className="w-4 h-4 ml-2" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                        <CardHeader>
                            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                <MessageCircle className="w-6 h-6 text-primary" />
                            </div>
                            <CardTitle className="font-serif font-bold">Community</CardTitle>
                            <CardDescription>
                                Join our Discord community to connect with other developers
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" className="w-full" asChild>
                                <a href="#" target="_blank" rel="noopener noreferrer">
                                    Join Discord <ExternalLink className="w-4 h-4 ml-2" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="border-border bg-card hover:bg-card/80 transition-colors">
                        <CardHeader>
                            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                <Mail className="w-6 h-6 text-primary" />
                            </div>
                            <CardTitle className="font-serif font-bold">Email Support</CardTitle>
                            <CardDescription>
                                Get direct help from our support team via email
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" className="w-full" asChild>
                                <a href="mailto:support@surrealpilot.com">
                                    Contact Support <Mail className="w-4 h-4 ml-2" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <div className="bg-card/50 rounded-lg p-8 text-center">
                    <h2 className="text-2xl font-serif font-bold text-foreground mb-4">
                        Frequently Asked Questions
                    </h2>
                    <p className="text-muted-foreground mb-6">
                        Find answers to common questions about SurrealPilot
                    </p>
                    
                    <div className="space-y-6 text-left max-w-3xl mx-auto">
                        <div>
                            <h3 className="font-semibold text-foreground mb-2">
                                How do I get started with SurrealPilot?
                            </h3>
                            <p className="text-muted-foreground">
                                Simply <Link href="/register" className="text-primary hover:underline">create an account</Link>, 
                                select your game engine (Unreal Engine or PlayCanvas), and start chatting with our AI assistant.
                            </p>
                        </div>
                        
                        <div>
                            <h3 className="font-semibold text-foreground mb-2">
                                What game engines are supported?
                            </h3>
                            <p className="text-muted-foreground">
                                We currently support Unreal Engine (C++ and Blueprints) and PlayCanvas for web/mobile game development.
                            </p>
                        </div>
                        
                        <div>
                            <h3 className="font-semibold text-foreground mb-2">
                                How does the credit system work?
                            </h3>
                            <p className="text-muted-foreground">
                                Credits are consumed based on AI usage. Each message and response uses credits based on the complexity 
                                and length of the interaction. You can monitor your usage in the dashboard.
                            </p>
                        </div>
                        
                        <div>
                            <h3 className="font-semibold text-foreground mb-2">
                                Can I use SurrealPilot for commercial projects?
                            </h3>
                            <p className="text-muted-foreground">
                                Yes! SurrealPilot is designed for both personal and commercial game development projects. 
                                Check our pricing plans for the best option for your needs.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}