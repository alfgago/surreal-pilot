import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Shield, UserCheck, User } from 'lucide-react';

interface CompanyUser {
    id: number;
    name: string;
    email: string;
    pivot: {
        role: string;
        created_at: string;
    };
}

interface RoleManagementProps {
    user: CompanyUser;
    isOwner: boolean;
    canEdit: boolean;
}

export default function RoleManagement({ user, isOwner, canEdit }: RoleManagementProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [selectedRole, setSelectedRole] = useState(user.pivot.role);
    const [isUpdating, setIsUpdating] = useState(false);

    const handleRoleUpdate = () => {
        if (selectedRole === user.pivot.role) {
            setIsEditing(false);
            return;
        }

        setIsUpdating(true);
        router.patch(`/company/users/${user.id}/role`, {
            role: selectedRole,
        }, {
            onSuccess: () => {
                setIsEditing(false);
                setIsUpdating(false);
            },
            onError: () => {
                setIsUpdating(false);
                setSelectedRole(user.pivot.role); // Reset to original role
            },
        });
    };

    const handleCancel = () => {
        setSelectedRole(user.pivot.role);
        setIsEditing(false);
    };

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'owner':
                return <Shield className="h-4 w-4" />;
            case 'admin':
                return <UserCheck className="h-4 w-4" />;
            case 'member':
                return <User className="h-4 w-4" />;
            default:
                return <User className="h-4 w-4" />;
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

    const getRoleDescription = (role: string) => {
        switch (role) {
            case 'owner':
                return 'Full access to all company settings and billing';
            case 'admin':
                return 'Can manage team members and company settings';
            case 'member':
                return 'Can access workspaces and collaborate on projects';
            default:
                return '';
        }
    };

    if (!canEdit || user.pivot.role === 'owner') {
        return (
            <div className="flex items-center gap-2">
                <Badge variant={getRoleBadgeVariant(user.pivot.role)} className="flex items-center gap-1">
                    {getRoleIcon(user.pivot.role)}
                    {user.pivot.role}
                </Badge>
            </div>
        );
    }

    if (isEditing) {
        return (
            <div className="flex items-center gap-2">
                <Select value={selectedRole} onValueChange={setSelectedRole}>
                    <SelectTrigger className="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="member">
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4" />
                                Member
                            </div>
                        </SelectItem>
                        <SelectItem value="admin">
                            <div className="flex items-center gap-2">
                                <UserCheck className="h-4 w-4" />
                                Admin
                            </div>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button
                    size="sm"
                    onClick={handleRoleUpdate}
                    disabled={isUpdating}
                >
                    {isUpdating ? 'Saving...' : 'Save'}
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={handleCancel}
                    disabled={isUpdating}
                >
                    Cancel
                </Button>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <Badge 
                variant={getRoleBadgeVariant(user.pivot.role)} 
                className="flex items-center gap-1 cursor-pointer hover:bg-opacity-80"
                onClick={() => setIsEditing(true)}
                title={getRoleDescription(user.pivot.role)}
            >
                {getRoleIcon(user.pivot.role)}
                {user.pivot.role}
            </Badge>
            <Button
                size="sm"
                variant="ghost"
                onClick={() => setIsEditing(true)}
                className="text-xs text-muted-foreground hover:text-foreground"
            >
                Edit
            </Button>
        </div>
    );
}