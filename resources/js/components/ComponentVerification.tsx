import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface ComponentVerificationProps {
    className?: string;
}

export function ComponentVerification({ className }: ComponentVerificationProps) {
    const [testResults, setTestResults] = React.useState<Record<string, boolean>>({});

    React.useEffect(() => {
        // Test component rendering
        const tests = {
            'Button renders': !!document.querySelector('[data-slot="button"]'),
            'Card renders': !!document.querySelector('[data-slot="card"]'),
            'Input renders': !!document.querySelector('[data-slot="input"]'),
            'CSS variables loaded': !!getComputedStyle(document.documentElement).getPropertyValue('--background'),
            'Tailwind classes work': !!document.querySelector('.bg-background'),
        };
        
        setTestResults(tests);
    }, []);

    const allTestsPassed = Object.values(testResults).every(Boolean);

    return (
        <Card className={cn("w-full max-w-md", className)}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Component Verification
                    <Badge variant={allTestsPassed ? "default" : "destructive"}>
                        {allTestsPassed ? "✓ Passed" : "✗ Failed"}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    Testing migrated shadcn/ui components
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {Object.entries(testResults).map(([test, passed]) => (
                    <div key={test} className="flex items-center justify-between">
                        <span className="text-sm">{test}</span>
                        <Badge variant={passed ? "default" : "destructive"} className="text-xs">
                            {passed ? "✓" : "✗"}
                        </Badge>
                    </div>
                ))}
                
                <div className="pt-4 space-y-2">
                    <Button size="sm" className="w-full">Test Button</Button>
                    <Input placeholder="Test input field" className="text-sm" />
                </div>
            </CardContent>
        </Card>
    );
}