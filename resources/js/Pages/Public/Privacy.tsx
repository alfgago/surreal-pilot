import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Privacy() {
    return (
        <GuestLayout title="Privacy Policy">
            <Head title="Privacy Policy - SurrealPilot">
                <meta name="description" content="SurrealPilot Privacy Policy - Learn how we collect, use, and protect your personal information." />
            </Head>
            
            <div className="max-w-4xl mx-auto px-4 py-16">
                <div className="prose prose-lg max-w-none">
                    <h1 className="text-4xl font-serif font-black text-foreground mb-8">Privacy Policy</h1>
                    
                    <p className="text-muted-foreground mb-8">
                        Last updated: {new Date().toLocaleDateString()}
                    </p>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Information We Collect</h2>
                        <p className="text-muted-foreground mb-4">
                            We collect information you provide directly to us, such as when you create an account, 
                            use our services, or contact us for support.
                        </p>
                        <ul className="list-disc pl-6 text-muted-foreground space-y-2">
                            <li>Account information (name, email, company details)</li>
                            <li>Usage data and analytics</li>
                            <li>Game development project data</li>
                            <li>Communication preferences</li>
                        </ul>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">How We Use Your Information</h2>
                        <p className="text-muted-foreground mb-4">
                            We use the information we collect to provide, maintain, and improve our services:
                        </p>
                        <ul className="list-disc pl-6 text-muted-foreground space-y-2">
                            <li>Provide AI-powered game development assistance</li>
                            <li>Process payments and manage subscriptions</li>
                            <li>Send important service updates and notifications</li>
                            <li>Improve our AI models and services</li>
                        </ul>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Data Security</h2>
                        <p className="text-muted-foreground mb-4">
                            We implement appropriate security measures to protect your personal information against 
                            unauthorized access, alteration, disclosure, or destruction.
                        </p>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Contact Us</h2>
                        <p className="text-muted-foreground">
                            If you have any questions about this Privacy Policy, please contact us at{' '}
                            <Link href="/support" className="text-primary hover:underline">
                                our support page
                            </Link>.
                        </p>
                    </section>
                </div>
            </div>
        </GuestLayout>
    );
}