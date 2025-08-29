import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    CreditCard, 
    Plus, 
    Trash2, 
    CheckCircle,
    AlertCircle,
    Calendar
} from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

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

interface PaymentMethodsProps {
    paymentMethods: PaymentMethod[];
    defaultPaymentMethod?: string;
    onUpdate?: () => void;
}

export default function PaymentMethods({ 
    paymentMethods, 
    defaultPaymentMethod,
    onUpdate 
}: PaymentMethodsProps) {
    const [isAddingCard, setIsAddingCard] = useState(false);
    const { post, processing } = useForm();

    const handleAddPaymentMethod = () => {
        // This would typically redirect to Stripe's hosted payment method setup
        post('/billing/setup-payment-method', {
            onSuccess: () => {
                setIsAddingCard(false);
                onUpdate?.();
            },
        });
    };

    const handleDeletePaymentMethod = (paymentMethodId: string) => {
        if (confirm('Are you sure you want to delete this payment method?')) {
            post(`/billing/payment-methods/${paymentMethodId}/delete`, {
                onSuccess: () => {
                    onUpdate?.();
                },
            });
        }
    };

    const handleSetDefault = (paymentMethodId: string) => {
        post(`/billing/payment-methods/${paymentMethodId}/default`, {
            onSuccess: () => {
                onUpdate?.();
            },
        });
    };

    const getCardBrandIcon = (brand: string) => {
        // You could add specific brand icons here
        return <CreditCard className="h-5 w-5" />;
    };

    const formatCardBrand = (brand: string) => {
        return brand.charAt(0).toUpperCase() + brand.slice(1);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between">
                    Payment Methods
                    <Button 
                        onClick={handleAddPaymentMethod}
                        disabled={processing || isAddingCard}
                        size="sm"
                    >
                        <Plus className="h-4 w-4 mr-2" />
                        Add Card
                    </Button>
                </CardTitle>
                <CardDescription>
                    Manage your payment methods for subscriptions and credit purchases
                </CardDescription>
            </CardHeader>
            <CardContent>
                {paymentMethods.length === 0 ? (
                    <div className="text-center py-8">
                        <CreditCard className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">No payment methods</h3>
                        <p className="text-muted-foreground mb-4">
                            Add a payment method to purchase credits or subscribe to a plan
                        </p>
                        <Button 
                            onClick={handleAddPaymentMethod}
                            disabled={processing || isAddingCard}
                        >
                            <Plus className="h-4 w-4 mr-2" />
                            Add Your First Card
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {paymentMethods.map((method) => (
                            <div 
                                key={method.id} 
                                className="flex items-center justify-between p-4 border rounded-lg"
                            >
                                <div className="flex items-center space-x-4">
                                    {method.card && getCardBrandIcon(method.card.brand)}
                                    <div>
                                        <div className="flex items-center space-x-2">
                                            <span className="font-medium">
                                                {method.card && formatCardBrand(method.card.brand)} •••• {method.card?.last4}
                                            </span>
                                            {method.is_default && (
                                                <Badge variant="default" className="text-xs">
                                                    <CheckCircle className="h-3 w-3 mr-1" />
                                                    Default
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="flex items-center text-sm text-muted-foreground space-x-4">
                                            {method.card && (
                                                <span className="flex items-center">
                                                    <Calendar className="h-3 w-3 mr-1" />
                                                    Expires {method.card.exp_month}/{method.card.exp_year}
                                                </span>
                                            )}
                                            <span>
                                                Added {formatDistanceToNow(new Date(method.created_at), { addSuffix: true })}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center space-x-2">
                                    {!method.is_default && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleSetDefault(method.id)}
                                            disabled={processing}
                                        >
                                            Set Default
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleDeletePaymentMethod(method.id)}
                                        disabled={processing}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Payment Security Notice */}
                <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div className="flex items-start space-x-3">
                        <CheckCircle className="h-5 w-5 text-blue-600 mt-0.5" />
                        <div className="text-sm">
                            <p className="font-medium text-blue-900">Secure Payment Processing</p>
                            <p className="text-blue-700 mt-1">
                                All payment information is securely processed by Stripe. 
                                We never store your complete card details on our servers.
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}