import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import AuthLayout from '@/Layouts/AuthLayout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export default function Login({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout>
            <Head title="Log in" />

            <div className="space-y-6">
                <div className="space-y-2 text-center">
                    <h1 className="text-2xl font-bold">Welcome back</h1>
                    <p className="text-muted-foreground">
                        Sign in to your SurrealPilot account
                    </p>
                </div>

                <div>
                    {status && (
                        <div className="mb-4 text-sm font-medium text-green-600">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className={errors.email ? 'border-red-500' : ''}
                                autoComplete="username"
                                autoFocus
                                onChange={(e) => setData('email', e.target.value)}
                                required
                            />
                            {errors.email && (
                                <div className="text-sm text-red-600">{errors.email}</div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className={errors.password ? 'border-red-500' : ''}
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            {errors.password && (
                                <div className="text-sm text-red-600">{errors.password}</div>
                            )}
                        </div>

                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="remember"
                                checked={data.remember}
                                onCheckedChange={(checked) => setData('remember', checked as boolean)}
                            />
                            <Label htmlFor="remember" className="text-sm">
                                Remember me
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing ? 'Signing in...' : 'Sign in'}
                        </Button>
                    </form>

                    <div className="mt-6 text-center text-sm">
                        <span className="text-muted-foreground">Don't have an account? </span>
                        <Link
                            href={route('register')}
                            className="font-medium text-primary hover:text-primary/80"
                        >
                            Sign up
                        </Link>
                    </div>

                    <div className="mt-4 text-center">
                        <Link
                            href={route('password.request')}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Forgot your password?
                        </Link>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}