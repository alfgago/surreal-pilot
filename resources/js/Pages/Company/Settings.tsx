import { Head, useForm, usePage, router } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import MainLayout from '@/Layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { Trash2, UserPlus, Settings, Users } from 'lucide-react';
import { PageProps } from '@/types';
import RoleManagement from '@/components/company/RoleManagement';
import CompanyPreferences from '@/components/company/CompanyPreferences';

interface CompanyUser {
    id: number;
    name: string;
    email: string;
    pivot: {
        role: string;
        created_at: string;
    };
}

interface CompanyInvitation {
    id: number;
    email: string;
    role: string;
    created_at: string;
}

interface Company {
    id: number;
    name: string;
    plan: string;
    credits: number;
    monthly_credit_limit: number;
    users: CompanyUser[];
    invitations: CompanyInvitation[];
    is_owner: boolean;
    preferences?: {
        timezone?: string;
        default_engine?: string;
        auto_save?: boolean;
        notifications_enabled?: boolean;
        collaboration_enabled?: boolean;
        public_templates?: boolean;
        description?: string;
        website?: string;
    };
}

interface CompanySettingsProps extends PageProps {
    company: Company;
}

export default function CompanySettings({ company }: CompanySettingsProps) {
    const { flash } = usePage().props;

    // Company settings form
    const { data: settingsData, setData: setSettingsData, patch: patchSettings, processing: settingsProcessing, errors: settingsErrors } = useForm({
        name: company.name,
        plan: company.plan || '',
    });

    // Team invitation form
    const { data: inviteData, setData: setInviteData, post: postInvite, processing: inviteProcessing, errors: inviteErrors, reset: resetInvite } = useForm({
        email: '',
        role: 'member',
    });

    const handleSettingsSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        patchSettings('/company/settings');
    };

    const handleInviteSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        postInvite('/company/invite', {
            onSuccess: () => resetInvite(),
        });
    };

    const handleRemoveUser = (userId: number) => {
        if (confirm('Are you sure you want to remove this user from the company?')) {
            router.delete(`/company/users/${userId}`);
        }
    };

    const handleCancelInvitation = (invitationId: number) => {
        if (confirm('Are you sure you want to cancel this invitation?')) {
            router.delete(`/company/invitations/${invitationId}`);
        }
    };

    const getRoleBadgeVariant = (role: string) => {
        switch (role) {
            case 'owner':
                return 'default';
            case 'admin':
                return 'secondary';
            case 'member':
                return 'outline';
            default:
                return 'outline';
        }
    };

    return (
        <MainLayout>
            <Head title="Company Settings" />

            <div className="container mx-auto py-8 px-4 max-w-4xl">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold tracking-tight">Company Settings</h1>
                    <p className="text-muted-foreground">
                        Manage your company information and team members
                    </p>
                </div>

                {flash.success && (
                    <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                        {flash.success}
                    </div>
                )}

                <div className="space-y-8">
                    {/* Company Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                Company Information
                            </CardTitle>
                            <CardDescription>
                                Update your company details and subscription plan
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSettingsSubmit} className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Company Name</Label>
                                        <Input
                                            id="name"
                                            value={settingsData.name}
                                            onChange={(e) => setSettingsData('name', e.target.value)}
                                            disabled={!company.is_owner}
                                            className={settingsErrors.name ? 'border-red-500' : ''}
                                        />
                                        {settingsErrors.name && (
                                            <p className="text-sm text-red-600">{settingsErrors.name}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="plan">Subscription Plan</Label>
                                        <Select
                                            value={settingsData.plan}
                                            onValueChange={(value) => setSettingsData('plan', value)}
                                            disabled={!company.is_owner}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="starter">Starter</SelectItem>
                                                <SelectItem value="pro">Pro</SelectItem>
                                                <SelectItem value="enterprise">Enterprise</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {settingsErrors.plan && (
                                            <p className="text-sm text-red-600">{settingsErrors.plan}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Current Credits</Label>
                                        <div className="text-2xl font-bold text-green-600">
                                            {company.credits.toLocaleString()}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Monthly Credit Limit</Label>
                                        <div className="text-2xl font-bold">
                                            {company.monthly_credit_limit.toLocaleString()}
                                        </div>
                                    </div>
                                </div>

                                {company.is_owner && (
                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={settingsProcessing}>
                                            {settingsProcessing ? 'Saving...' : 'Save Changes'}
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </CardContent>
                    </Card>

                    {/* Team Management */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Team Management
                            </CardTitle>
                            <CardDescription>
                                Manage team members and their roles
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Invite New Member */}
                            {company.is_owner && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold flex items-center gap-2">
                                        <UserPlus className="h-4 w-4" />
                                        Invite Team Member
                                    </h3>
                                    <form onSubmit={handleInviteSubmit} className="flex gap-4">
                                        <div className="flex-1">
                                            <Input
                                                type="email"
                                                placeholder="Enter email address"
                                                value={inviteData.email}
                                                onChange={(e) => setInviteData('email', e.target.value)}
                                                className={inviteErrors.email ? 'border-red-500' : ''}
                                            />
                                            {inviteErrors.email && (
                                                <p className="text-sm text-red-600 mt-1">{inviteErrors.email}</p>
                                            )}
                                        </div>
                                        <Select
                                            value={inviteData.role}
                                            onValueChange={(value) => setInviteData('role', value)}
                                        >
                                            <SelectTrigger className="w-32">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="member">Member</SelectItem>
                                                <SelectItem value="admin">Admin</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Button type="submit" disabled={inviteProcessing}>
                                            {inviteProcessing ? 'Inviting...' : 'Invite'}
                                        </Button>
                                    </form>
                                </div>
                            )}

                            <Separator />

                            {/* Current Team Members */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Team Members ({company.users.length})</h3>
                                <div className="space-y-3">
                                    {company.users.map((user) => (
                                        <div key={user.id} className="flex items-center justify-between p-3 border rounded-lg">
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                                                    {user.name.charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    <div className="font-medium">{user.name}</div>
                                                    <div className="text-sm text-muted-foreground">{user.email}</div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <RoleManagement
                                                    user={user}
                                                    isOwner={user.pivot.role === 'owner'}
                                                    canEdit={company.is_owner}
                                                />
                                                {company.is_owner && user.pivot.role !== 'owner' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleRemoveUser(user.id)}
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Pending Invitations */}
                            {company.invitations.length > 0 && (
                                <>
                                    <Separator />
                                    <div className="space-y-4">
                                        <h3 className="text-lg font-semibold">Pending Invitations ({company.invitations.length})</h3>
                                        <div className="space-y-3">
                                            {company.invitations.map((invitation) => (
                                                <div key={invitation.id} className="flex items-center justify-between p-3 border rounded-lg bg-yellow-50">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                                                            ?
                                                        </div>
                                                        <div>
                                                            <div className="font-medium">{invitation.email}</div>
                                                            <div className="text-sm text-muted-foreground">
                                                                Invited {new Date(invitation.created_at).toLocaleDateString()}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline">
                                                            {invitation.role}
                                                        </Badge>
                                                        {company.is_owner && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleCancelInvitation(invitation.id)}
                                                                className="text-red-600 hover:text-red-700"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Company Preferences */}
                    <CompanyPreferences company={company} />
                </div>
            </div>
        </MainLayout>
    );
}