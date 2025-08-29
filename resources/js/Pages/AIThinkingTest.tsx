import React from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import AIThinkingDisplayTest from '@/components/chat/AIThinkingDisplayTest';

export default function AIThinkingTest() {
    return (
        <MainLayout title="AI Thinking Display Test">
            <Head title="AI Thinking Display Test" />
            
            <div className="min-h-screen bg-background">
                <div className="container mx-auto py-8">
                    <AIThinkingDisplayTest />
                </div>
            </div>
        </MainLayout>
    );
}