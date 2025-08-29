import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

interface CreditBalance {
    current_credits: number;
    last_updated: string;
    balance_summary: {
        current_credits: number;
        monthly_limit: number;
        current_month_usage: number;
        remaining_monthly_allowance: number;
        is_approaching_limit: boolean;
        plan: string;
    };
}

interface BillingSummary {
    balance_summary: {
        current_credits: number;
        monthly_limit: number;
        current_month_usage: number;
        remaining_monthly_allowance: number;
        is_approaching_limit: boolean;
        plan: string;
    };
    current_month_usage: number;
    usage_trend: {
        absolute: number;
        percentage: number;
        direction: 'up' | 'down' | 'stable';
    };
    last_updated: string;
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

interface TransactionsResponse {
    transactions: CreditTransaction[];
    total: number;
    has_more: boolean;
}

export function useCreditBalance(refreshInterval: number = 30000) {
    const [balance, setBalance] = useState<CreditBalance | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchBalance = useCallback(async () => {
        try {
            const response = await axios.get('/api/billing/balance');
            setBalance(response.data);
            setError(null);
        } catch (err) {
            setError('Failed to fetch credit balance');
            console.error('Error fetching credit balance:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchBalance();
        
        const interval = setInterval(fetchBalance, refreshInterval);
        
        return () => clearInterval(interval);
    }, [fetchBalance, refreshInterval]);

    return { balance, loading, error, refetch: fetchBalance };
}

export function useBillingSummary() {
    const [summary, setSummary] = useState<BillingSummary | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchSummary = useCallback(async () => {
        try {
            const response = await axios.get('/api/billing/summary');
            setSummary(response.data);
            setError(null);
        } catch (err) {
            setError('Failed to fetch billing summary');
            console.error('Error fetching billing summary:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchSummary();
    }, [fetchSummary]);

    return { summary, loading, error, refetch: fetchSummary };
}

export function useTransactions(limit: number = 20) {
    const [transactions, setTransactions] = useState<CreditTransaction[]>([]);
    const [total, setTotal] = useState(0);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchTransactions = useCallback(async (offset: number = 0, append: boolean = false) => {
        try {
            if (append) {
                setLoadingMore(true);
            } else {
                setLoading(true);
            }

            const response = await axios.get('/api/billing/transactions', {
                params: { limit, offset }
            });

            const data: TransactionsResponse = response.data;

            if (append) {
                setTransactions(prev => [...prev, ...data.transactions]);
            } else {
                setTransactions(data.transactions);
            }

            setTotal(data.total);
            setHasMore(data.has_more);
            setError(null);
        } catch (err) {
            setError('Failed to fetch transactions');
            console.error('Error fetching transactions:', err);
        } finally {
            setLoading(false);
            setLoadingMore(false);
        }
    }, [limit]);

    const loadMore = useCallback(() => {
        if (!loadingMore && hasMore) {
            fetchTransactions(transactions.length, true);
        }
    }, [fetchTransactions, transactions.length, loadingMore, hasMore]);

    useEffect(() => {
        fetchTransactions();
    }, [fetchTransactions]);

    return { 
        transactions, 
        total, 
        hasMore, 
        loading, 
        loadingMore, 
        error, 
        loadMore, 
        refetch: () => fetchTransactions() 
    };
}

export function useUsageAnalytics(from: string, to: string) {
    const [analytics, setAnalytics] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchAnalytics = useCallback(async () => {
        try {
            setLoading(true);
            const response = await axios.get('/api/billing/analytics', {
                params: { from, to }
            });
            setAnalytics(response.data);
            setError(null);
        } catch (err) {
            setError('Failed to fetch usage analytics');
            console.error('Error fetching usage analytics:', err);
        } finally {
            setLoading(false);
        }
    }, [from, to]);

    useEffect(() => {
        if (from && to) {
            fetchAnalytics();
        }
    }, [fetchAnalytics, from, to]);

    return { analytics, loading, error, refetch: fetchAnalytics };
}

// Hook for real-time credit updates during chat sessions
export function useRealTimeCreditUpdates() {
    const [credits, setCredits] = useState<number | null>(null);
    const [isUpdating, setIsUpdating] = useState(false);

    const updateCredits = useCallback(async () => {
        try {
            setIsUpdating(true);
            const response = await axios.get('/api/billing/balance');
            setCredits(response.data.current_credits);
        } catch (err) {
            console.error('Error updating credits:', err);
        } finally {
            setIsUpdating(false);
        }
    }, []);

    // Listen for credit updates from chat events
    useEffect(() => {
        const handleCreditUpdate = () => {
            updateCredits();
        };

        // Listen for custom events that might be dispatched after API calls
        window.addEventListener('credit-updated', handleCreditUpdate);
        
        return () => {
            window.removeEventListener('credit-updated', handleCreditUpdate);
        };
    }, [updateCredits]);

    return { credits, isUpdating, updateCredits };
}