import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Terms() {
    return (
        <GuestLayout title="Terms of Service">
            <Head title="Terms of Service - SurrealPilot">
                <meta name="description" content="SurrealPilot Terms of Service - Read our terms and conditions for using our AI-powered game development platform." />
            </Head>
            
            <div className="max-w-4xl mx-auto px-4 py-16">
                <div className="prose prose-lg max-w-none">
                    <h1 className="text-4xl font-serif font-black text-foreground mb-8">Terms of Service</h1>
                    
                    <p className="text-muted-foreground mb-8">
                        Last updated: {new Date().toLocaleDateString()}
                    </p>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Acceptance of Terms</h2>
                        <p className="text-muted-foreground mb-4">
                            By accessing and using SurrealPilot, you accept and agree to be bound by the terms 
                            and provision of this agreement.
                        </p>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Use License</h2>
                        <p className="text-muted-foreground mb-4">
                            Permission is granted to temporarily use SurrealPilot for personal and commercial 
                            game development purposes. This is the grant of a license, not a transfer of title.
                        </p>
                        <p className="text-muted-foreground mb-4">Under this license you may not:</p>
                        <ul className="list-disc pl-6 text-muted-foreground space-y-2">
                            <li>Modify or copy the materials</li>
                            <li>Use the materials for any commercial purpose without proper subscription</li>
                            <li>Attempt to reverse engineer any software</li>
                            <li>Remove any copyright or other proprietary notations</li>
                        </ul>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Service Availability</h2>
                        <p className="text-muted-foreground mb-4">
                            We strive to maintain high availability of our services, but we do not guarantee 
                            uninterrupted access. We may temporarily suspend service for maintenance or updates.
                        </p>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Payment Terms</h2>
                        <p className="text-muted-foreground mb-4">
                            Subscription fees are billed in advance on a monthly or annual basis. Credits are 
                            consumed based on AI usage and are non-refundable.
                        </p>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Limitation of Liability</h2>
                        <p className="text-muted-foreground mb-4">
                            In no event shall SurrealPilot be liable for any damages arising out of the use or 
                            inability to use our services.
                        </p>
                    </section>

                    <section className="mb-8">
                        <h2 className="text-2xl font-serif font-bold text-foreground mb-4">Contact Information</h2>
                        <p className="text-muted-foreground">
                            If you have any questions about these Terms of Service, please contact us at{' '}
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