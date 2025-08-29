import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { 
    CreditCard, 
    AlertTriangle, 
    RefreshCw,
    TrendingDown,
    TrendingUp
} from 'lucide-react';
import { useCreditBalance } from '@/hooks/useBilling';
import { router } from '@inertiajs/react';

interface CreditBalanceProps {
    showDetails?: boolean;
    refreshInterval?: number;
    className?: string;
}

export default function CreditBalance({ 
    showDetails = false, 
    refreshInterval = 30000,
    className = '' 
}: CreditBalanceProps) {
    const { balance, loading, error, refetch } = useCreditBalance(refreshInterval);
    const [isRefreshing, setIsRefreshing] = useState(false);

    const handleRefresh = async () => {
        setIsRefreshing(true);
        await refetch();
        setIsRefreshing(false);
    };

    const handleViewBilling = () => {
        router.visit('/company/billing');
    };

    const formatCredits = (credits: number) => {
        return new Intl.NumberFormat('en-US').format(credits);
    };

    if (loading && !balance) {
        return (
            <div className={`flex items-center space-x-2 ${className}`}>
                <CreditCard className="h-4 w-4 animate-pulse" />
                <span className="text-sm text-muted-foreground">Loading...</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className={`flex items-center space-x-2 ${className}`}>
                <AlertTriangle className="h-4 w-4 text-red-500" />
                <span className="text-sm text-red-600">Error loading credits</span>
                <Button 
                    variant="ghost" 
                    size="sm" 
                    onClick={handleRefresh}
                    disabled={isRefreshing}
                >
                    <RefreshCw className={`h-3 w-3 ${isRefreshing ? 'animate-spin' : ''}`} />
                </Button>
            </div>
        );
    }

    if (!balance) {
        return null;
    }

    const { current_credits, balance_summary } = balance;
    const isApproachingLimit = balance_summary.is_approaching_limit;
    const usagePercentage = balance_summary.monthly_limit > 0 
        ? (balance_summary.current_month_usage / balance_summary.monthly_limit) * 100 
        : 0;

    return (
        <div className={`flex items-center space-x-2 ${className}`}>
            <div className="flex items-center space-x-2">
                <CreditCard className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium">
                    {formatCredits(current_credits)} credits
                </span>
                
                {isApproachingLimit && (
                    <Badge variant="destructive" className="text-xs">
                        <AlertTriangle className="h-3 w-3 mr-1" />
                        Low
                    </Badge>
                )}
            </div>

            {showDetails && (
                <div className="flex items-center space-x-2 text-xs text-muted-foreground">
                    <span>|</span>
                    <span>
                        {formatCredits(balance_summary.current_month_usage)} / {formatCredits(balance_summary.monthly_limit)} used
                    </span>
                    {usagePercentage > 0 && (
                        <div className="flex items-center">
                            {usagePercentage > 75 ? (
                                <TrendingUp className="h-3 w-3 text-red-500" />
                            ) : (
                                <TrendingDown className="h-3 w-3 text-green-500" />
                            )}
                            <span className={usagePercentage > 75 ? 'text-red-600' : 'text-green-600'}>
                                {usagePercentage.toFixed(0)}%
                            </span>
                        </div>
                    )}
                </div>
            )}

            <div className="flex items-center space-x-1">
                <Button 
                    variant="ghost" 
                    size="sm" 
                    onClick={handleRefresh}
                    disabled={isRefreshing}
                    className="h-6 w-6 p-0"
                >
                    <RefreshCw className={`h-3 w-3 ${isRefreshing ? 'animate-spin' : ''}`} />
                </Button>
                
                {showDetails && (
                    <Button 
                        variant="outline" 
                        size="sm" 
                        onClick={handleViewBilling}
                        className="text-xs"
                    >
                        Billing
                    </Button>
                )}
            </div>
        </div>
    );
}