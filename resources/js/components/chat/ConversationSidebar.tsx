import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { 
    Plus, 
    Search, 
    MessageSquare, 
    Clock, 
    Trash2,
    X,
    Loader2
} from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';
import axios from 'axios';

interface Conversation {
    id: number;
    title: string;
    description?: string;
    updated_at: string;
    message_count: number;
    last_message_preview: string;
}

interface ConversationSidebarProps {
    workspaceId: number;
    conversations: Conversation[];
    currentConversationId?: number;
    onConversationSelect: (conversationId: number) => void;
    onClose?: () => void;
    className?: string;
}

export default function ConversationSidebar({
    workspaceId,
    conversations: initialConversations,
    currentConversationId,
    onConversationSelect,
    onClose,
    className = ''
}: ConversationSidebarProps) {
    const [conversations, setConversations] = useState<Conversation[]>(initialConversations);
    const [searchQuery, setSearchQuery] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isCreating, setIsCreating] = useState(false);
    const { toast } = useToast();

    // Filter conversations based on search query
    const filteredConversations = conversations.filter(conversation =>
        conversation.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        conversation.last_message_preview.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const handleCreateConversation = async () => {
        setIsCreating(true);
        try {
            const response = await axios.post(`/api/workspaces/${workspaceId}/conversations`, {
                title: `New Chat ${new Date().toLocaleDateString()}`
            });

            if (response.data.success) {
                const newConversation = response.data.conversation;
                setConversations(prev => [newConversation, ...prev]);
                onConversationSelect(newConversation.id);
                
                toast({
                    title: "New Conversation",
                    description: "Created a new chat conversation.",
                });
            }
        } catch (error) {
            console.error('Failed to create conversation:', error);
            toast({
                title: "Creation Failed",
                description: "Failed to create new conversation. Please try again.",
                variant: "destructive",
            });
        } finally {
            setIsCreating(false);
        }
    };

    const handleDeleteConversation = async (conversationId: number, event: React.MouseEvent) => {
        event.stopPropagation();
        
        try {
            const response = await axios.delete(`/api/conversations/${conversationId}`);
            
            if (response.data.success) {
                setConversations(prev => prev.filter(conv => conv.id !== conversationId));
                
                // If we deleted the current conversation, select the first available one
                if (currentConversationId === conversationId && conversations.length > 1) {
                    const remainingConversations = conversations.filter(conv => conv.id !== conversationId);
                    if (remainingConversations.length > 0) {
                        onConversationSelect(remainingConversations[0].id);
                    }
                }
                
                toast({
                    title: "Conversation Deleted",
                    description: "The conversation has been removed.",
                });
            }
        } catch (error) {
            console.error('Failed to delete conversation:', error);
            toast({
                title: "Delete Failed",
                description: "Failed to delete conversation. Please try again.",
                variant: "destructive",
            });
        }
    };

    const handleConversationClick = (conversationId: number) => {
        onConversationSelect(conversationId);
        if (onClose) {
            onClose(); // Close sidebar on mobile after selection
        }
    };

    const formatTimestamp = (timestamp: string) => {
        const date = new Date(timestamp);
        const now = new Date();
        const diffInHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);

        if (diffInHours < 1) {
            return 'Just now';
        } else if (diffInHours < 24) {
            return `${Math.floor(diffInHours)}h ago`;
        } else if (diffInHours < 168) { // 7 days
            return `${Math.floor(diffInHours / 24)}d ago`;
        } else {
            return date.toLocaleDateString();
        }
    };

    return (
        <div className={`w-80 border-r bg-muted/30 flex flex-col h-full ${className}`}>
            {/* Header */}
            <div className="p-4 border-b">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center space-x-2">
                        <MessageSquare className="w-5 h-5" />
                        <span className="font-semibold">Conversations</span>
                    </div>
                    {onClose && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="lg:hidden"
                            onClick={onClose}
                        >
                            <X className="w-4 h-4" />
                        </Button>
                    )}
                </div>

                <Button 
                    className="w-full" 
                    onClick={handleCreateConversation}
                    disabled={isCreating}
                >
                    {isCreating ? (
                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    ) : (
                        <Plus className="w-4 h-4 mr-2" />
                    )}
                    New Chat
                </Button>
            </div>

            {/* Search */}
            <div className="p-4 border-b">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    <Input
                        placeholder="Search conversations..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-10"
                    />
                </div>
            </div>

            {/* Conversations List */}
            <ScrollArea className="flex-1">
                <div className="p-2">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="w-6 h-6 animate-spin" />
                        </div>
                    ) : filteredConversations.length > 0 ? (
                        <div className="space-y-1">
                            {filteredConversations.map((conversation) => (
                                <Card
                                    key={conversation.id}
                                    className={`cursor-pointer transition-colors hover:bg-accent group ${
                                        currentConversationId === conversation.id 
                                            ? 'bg-accent border-primary' 
                                            : 'border-transparent'
                                    }`}
                                    onClick={() => handleConversationClick(conversation.id)}
                                >
                                    <CardContent className="p-3">
                                        <div className="flex items-start justify-between mb-2">
                                            <h3 className="font-medium text-sm truncate flex-1 pr-2">
                                                {conversation.title}
                                            </h3>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                                                onClick={(e) => handleDeleteConversation(conversation.id, e)}
                                            >
                                                <Trash2 className="w-3 h-3" />
                                            </Button>
                                        </div>
                                        
                                        <p className="text-xs text-muted-foreground truncate mb-2">
                                            {conversation.last_message_preview || 'No messages yet'}
                                        </p>
                                        
                                        <div className="flex items-center justify-between">
                                            <Badge variant="secondary" className="text-xs">
                                                {conversation.message_count} messages
                                            </Badge>
                                            <div className="flex items-center text-xs text-muted-foreground">
                                                <Clock className="w-3 h-3 mr-1" />
                                                <span>{formatTimestamp(conversation.updated_at)}</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <MessageSquare className="w-12 h-12 mx-auto text-muted-foreground mb-4" />
                            <div className="space-y-2">
                                <h3 className="font-medium">No conversations found</h3>
                                <p className="text-sm text-muted-foreground">
                                    {searchQuery 
                                        ? 'Try adjusting your search terms' 
                                        : 'Start a new chat to begin'
                                    }
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </ScrollArea>
        </div>
    );
}