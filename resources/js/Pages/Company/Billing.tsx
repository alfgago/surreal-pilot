import { Head, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import MainLayout from '@/Layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    CreditCard, 
    TrendingUp, 
    Calendar, 
    DollarSign, 
    Activity,
    AlertTriangle,
    CheckCircle,
    Clock
} from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import PaymentMethods from '@/components/billing/PaymentMethods';

interface Company {
    id: number;
    name: string;
    credits: number;
    plan: string;
    monthly_credit_limit: number;
}

interface CreditTransaction {
    id: number;
    amount: number;
    type: 'credit' | 'debit';
    description: string;
    created_at: string;
    metadata?: {
        engine_type?: string;
        mcp_surcharge?: number;
    };
}

interface SubscriptionPlan {
    id: number;
    name: string;
    slug: string;
    monthly_credits: number;
    price_cents: number;
    features: string[];
    allow_unreal: boolean;
    allow_multiplayer: boolean;
    allow_advanced_publish: boolean;
}

interface PaymentMethod {
    id: string;
    type: string;
    card?: {
        brand: string;
        last4: string;
        exp_month: number;
        exp_year: number;
    };
    is_default: boolean;
    created_at: string;
}

interface BillingProps extends PageProps {
    company: Company;
    analytics: {
        daily_usage: Record<string, { debits: number; credits: number }>;
        total_debits: number;
        total_credits: number;
        net_usage: number;
        transaction_count: number;
    };
    engineAnalytics: {
        total_usage: number;
        total_mcp_surcharges: number;
        engine_breakdown: {
            unreal: { usage: number; transactions: number; mcp_surcharge: number };
            playcanvas: { usage: number; transactions: number; mcp_surcharge: number };
            other: { usage: number; transactions: number; mcp_surcharge: number };
        };
    };
    recentTransactions: CreditTransaction[];
    balanceSummary: {
        current_credits: number;
        monthly_limit: number;
        current_month_usage: number;
        remaining_monthly_allowance: number;
        is_approaching_limit: boolean;
        plan: string;
    };
    subscriptionPlans: SubscriptionPlan[];
    currentSubscription?: {
        id: string;
        stripe_status: string;
        ends_at?: string;
    };
    paymentMethods: PaymentMethod[];
    defaultPaymentMethod?: string;
}

export default function Billing({
    company,
    analytics,
    engineAnalytics,
    recentTransactions,
    balanceSummary,
    subscriptionPlans,
    currentSubscription,
    paymentMethods,
    defaultPaymentMethod
}: BillingProps) {
    const { post, processing } = useForm();

    const handlePlanUpgrade = (planSlug: string) => {
        post(`/billing/checkout/subscription`, {
            plan_slug: planSlug,
        });
    };

    const handleTopUp = () => {
        post(`/billing/checkout/topup`, {
            credits: 10000,
        });
    };

    const formatCurrency = (cents: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(cents / 100);
    };

    const formatCredits = (credits: number) => {
        return new Intl.NumberFormat('en-US').format(credits);
    };

    const getUsagePercentage = () => {
        if (balanceSummary.monthly_limit <= 0) return 0;
        return (balanceSummary.current_month_usage / balanceSummary.monthly_limit) * 100;
    };

    const currentPlan = subscriptionPlans.find(plan => plan.slug === company.plan);

    return (
        <MainLayout>
            <Head title="Billing & Credits" />
            
            <div className="container mx-auto px-4 py-8">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">Billing & Credits</h1>
                    <p className="text-gray-600 mt-2">Manage your subscription and monitor credit usage</p>
                </div>

                {/* Credit Balance Overview */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Current Credits</CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCredits(balanceSummary.current_credits)}</div>
                            {balanceSummary.is_approaching_limit && (
                                <div className="flex items-center text-amber-600 text-sm mt-1">
                                    <AlertTriangle className="h-3 w-3 mr-1" />
                                    Approaching limit
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Monthly Usage</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCredits(balanceSummary.current_month_usage)}</div>
                            <div className="text-xs text-muted-foreground">
                                {getUsagePercentage().toFixed(1)}% of monthly limit
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Monthly Limit</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCredits(balanceSummary.monthly_limit)}</div>
                            <div className="text-xs text-muted-foreground">
                                {formatCredits(balanceSummary.remaining_monthly_allowance)} remaining
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Current Plan</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold capitalize">{company.plan}</div>
                            {currentPlan && (
                                <div className="text-xs text-muted-foreground">
                                    {formatCurrency(currentPlan.price_cents)}/month
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Tabs defaultValue="overview" className="space-y-6">
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="usage">Usage Analytics</TabsTrigger>
                        <TabsTrigger value="transactions">Transaction History</TabsTrigger>
                        <TabsTrigger value="subscription">Subscription</TabsTrigger>
                        <TabsTrigger value="payment">Payment Methods</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="space-y-6">
                        {/* Usage Progress */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Monthly Usage Progress</CardTitle>
                                <CardDescription>
                                    Your credit usage for the current billing period
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex justify-between text-sm">
                                        <span>Used: {formatCredits(balanceSummary.current_month_usage)}</span>
                                        <span>Limit: {formatCredits(balanceSummary.monthly_limit)}</span>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            className={`h-2 rounded-full ${
                                                getUsagePercentage() > 90 ? 'bg-red-500' :
                                                getUsagePercentage() > 75 ? 'bg-amber-500' : 'bg-green-500'
                                            }`}
                                            style={{ width: `${Math.min(getUsagePercentage(), 100)}%` }}
                                        />
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatCredits(balanceSummary.remaining_monthly_allowance)} credits remaining this month
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                                <CardDescription>
                                    Manage your credits and subscription
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex gap-4">
                                <Button 
                                    onClick={handleTopUp}
                                    disabled={processing}
                                    variant="outline"
                                >
                                    <CreditCard className="h-4 w-4 mr-2" />
                                    Buy 10,000 Credits
                                </Button>
                                {currentPlan && currentPlan.slug !== 'enterprise' && (
                                    <Button 
                                        onClick={() => handlePlanUpgrade('pro')}
                                        disabled={processing}
                                    >
                                        <TrendingUp className="h-4 w-4 mr-2" />
                                        Upgrade Plan
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="usage" className="space-y-6">
                        {/* Engine Usage Breakdown */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Usage by Engine</CardTitle>
                                <CardDescription>
                                    Credit usage breakdown by game engine this month
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {Object.entries(engineAnalytics.engine_breakdown).map(([engine, data]) => (
                                        <div key={engine} className="flex items-center justify-between p-3 border rounded-lg">
                                            <div className="flex items-center space-x-3">
                                                <Activity className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <div className="font-medium capitalize">{engine}</div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {data.transactions} transactions
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="font-medium">{formatCredits(data.usage)} credits</div>
                                                {data.mcp_surcharge > 0 && (
                                                    <div className="text-xs text-muted-foreground">
                                                        +{formatCredits(data.mcp_surcharge)} MCP surcharge
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Monthly Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Monthly Summary</CardTitle>
                                <CardDescription>
                                    Overall usage statistics for the current month
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div className="text-center p-4 border rounded-lg">
                                        <div className="text-2xl font-bold text-red-600">{formatCredits(analytics.total_debits)}</div>
                                        <div className="text-sm text-muted-foreground">Total Spent</div>
                                    </div>
                                    <div className="text-center p-4 border rounded-lg">
                                        <div className="text-2xl font-bold text-green-600">{formatCredits(analytics.total_credits)}</div>
                                        <div className="text-sm text-muted-foreground">Credits Added</div>
                                    </div>
                                    <div className="text-center p-4 border rounded-lg">
                                        <div className="text-2xl font-bold">{analytics.transaction_count}</div>
                                        <div className="text-sm text-muted-foreground">Transactions</div>
                                    </div>
                                    <div className="text-center p-4 border rounded-lg">
                                        <div className="text-2xl font-bold">{formatCredits(engineAnalytics.total_mcp_surcharges)}</div>
                                        <div className="text-sm text-muted-foreground">MCP Surcharges</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="transactions" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent Transactions</CardTitle>
                                <CardDescription>
                                    Your latest credit transactions and usage history
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {recentTransactions.map((transaction) => (
                                        <div key={transaction.id} className="flex items-center justify-between p-3 border rounded-lg">
                                            <div className="flex items-center space-x-3">
                                                {transaction.type === 'credit' ? (
                                                    <CheckCircle className="h-5 w-5 text-green-500" />
                                                ) : (
                                                    <Activity className="h-5 w-5 text-red-500" />
                                                )}
                                                <div>
                                                    <div className="font-medium">{transaction.description}</div>
                                                    <div className="text-sm text-muted-foreground flex items-center">
                                                        <Clock className="h-3 w-3 mr-1" />
                                                        {formatDistanceToNow(new Date(transaction.created_at), { addSuffix: true })}
                                                        {transaction.metadata?.engine_type && (
                                                            <Badge variant="outline" className="ml-2 text-xs">
                                                                {transaction.metadata.engine_type}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className={`font-medium ${
                                                transaction.type === 'credit' ? 'text-green-600' : 'text-red-600'
                                            }`}>
                                                {transaction.type === 'credit' ? '+' : '-'}{formatCredits(transaction.amount)}
                                            </div>
                                        </div>
                                    ))}
                                    {recentTransactions.length === 0 && (
                                        <div className="text-center py-8 text-muted-foreground">
                                            No transactions found
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="subscription" className="space-y-6">
                        {/* Current Subscription */}
                        {currentSubscription && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Current Subscription</CardTitle>
                                    <CardDescription>
                                        Your active subscription details
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium capitalize">{company.plan} Plan</div>
                                            <div className="text-sm text-muted-foreground">
                                                Status: <Badge variant={currentSubscription.stripe_status === 'active' ? 'default' : 'secondary'}>
                                                    {currentSubscription.stripe_status}
                                                </Badge>
                                            </div>
                                        </div>
                                        {currentPlan && (
                                            <div className="text-right">
                                                <div className="font-medium">{formatCurrency(currentPlan.price_cents)}/month</div>
                                                <div className="text-sm text-muted-foreground">
                                                    {formatCredits(currentPlan.monthly_credits)} credits/month
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Available Plans */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Available Plans</CardTitle>
                                <CardDescription>
                                    Choose the plan that best fits your needs
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    {subscriptionPlans.map((plan) => (
                                        <div key={plan.id} className={`border rounded-lg p-4 ${
                                            plan.slug === company.plan ? 'border-blue-500 bg-blue-50' : ''
                                        }`}>
                                            <div className="text-center">
                                                <h3 className="font-semibold text-lg">{plan.name}</h3>
                                                <div className="text-2xl font-bold mt-2">
                                                    {formatCurrency(plan.price_cents)}
                                                </div>
                                                <div className="text-sm text-muted-foreground">per month</div>
                                                <div className="text-sm font-medium mt-2">
                                                    {formatCredits(plan.monthly_credits)} credits
                                                </div>
                                            </div>
                                            
                                            <div className="mt-4 space-y-2">
                                                {plan.features.map((feature, index) => (
                                                    <div key={index} className="flex items-center text-sm">
                                                        <CheckCircle className="h-3 w-3 text-green-500 mr-2" />
                                                        {feature}
                                                    </div>
                                                ))}
                                            </div>

                                            <div className="mt-4">
                                                {plan.slug === company.plan ? (
                                                    <Badge className="w-full justify-center">Current Plan</Badge>
                                                ) : (
                                                    <Button 
                                                        className="w-full" 
                                                        variant={plan.slug === 'pro' ? 'default' : 'outline'}
                                                        onClick={() => handlePlanUpgrade(plan.slug)}
                                                        disabled={processing}
                                                    >
                                                        {plan.price_cents > (currentPlan?.price_cents || 0) ? 'Upgrade' : 'Downgrade'}
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="payment" className="space-y-6">
                        <PaymentMethods 
                            paymentMethods={paymentMethods}
                            defaultPaymentMethod={defaultPaymentMethod}
                            onUpdate={() => window.location.reload()}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}