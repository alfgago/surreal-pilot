import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle 
} from '@/components/ui/dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Share, 
  Copy, 
  Check, 
  ExternalLink, 
  Globe, 
  Code, 
  Settings,
  Calendar,
  Eye,
  Users,
  Loader2,
  AlertCircle,
  Twitter,
  Facebook,
  Linkedin,
  MessageCircle,
  Mail,
  Link as LinkIcon,
  Download,
  QrCode,
  Smartphone,
  Monitor
} from 'lucide-react';
import { cn } from '@/lib/utils';

// Enhanced Game interface based on the model
interface GameData {
  id: number;
  title: string;
  description?: string;
  preview_url?: string;
  published_url?: string;
  thumbnail_url?: string;
  metadata?: any;
  engine_type: 'unreal' | 'playcanvas';
  status: string;
  version?: string;
  interaction_count?: number;
  thinking_history?: any[];
  game_mechanics?: any;
  sharing_settings?: SharingSettings;
  build_status?: 'building' | 'success' | 'failed';
  last_build_at?: string;
  workspace?: {
    id: number;
    name: string;
    engine_type: string;
  };
}

interface SharingSettings {
  allowEmbedding?: boolean;
  showControls?: boolean;
  showInfo?: boolean;
  expirationDays?: number;
}

interface ShareResult {
  success: boolean;
  share_token?: string;
  share_url?: string;
  embed_url?: string;
  expires_at?: string;
  options?: SharingSettings;
  snapshot_path?: string;
  created_at?: string;
  message?: string;
  error?: string;
}

interface SharingStats {
  total_plays: number;
  last_played?: string;
  is_public: boolean;
  has_share_token: boolean;
  sharing_settings: SharingSettings;
  created_at: string;
  updated_at: string;
}

interface GameSharingModalProps {
  game: GameData | null;
  isOpen: boolean;
  onClose: () => void;
  onShare?: (options: SharingSettings) => Promise<ShareResult>;
  onUpdateSettings?: (settings: SharingSettings) => Promise<boolean>;
  onRevokeLink?: () => Promise<boolean>;
  className?: string;
}

export function GameSharingModal({
  game,
  isOpen,
  onClose,
  onShare,
  onUpdateSettings,
  onRevokeLink,
  className
}: GameSharingModalProps) {
  // State management
  const [activeTab, setActiveTab] = useState<'share' | 'embed' | 'social' | 'settings'>('share');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [copiedField, setCopiedField] = useState<string | null>(null);
  
  // Sharing options state
  const [sharingOptions, setSharingOptions] = useState<SharingSettings>({
    allowEmbedding: true,
    showControls: true,
    showInfo: true,
    expirationDays: 30,
  });
  
  // Share result state
  const [shareResult, setShareResult] = useState<ShareResult | null>(null);
  const [sharingStats, setSharingStats] = useState<SharingStats | null>(null);
  
  // Preview state
  const [previewMode, setPreviewMode] = useState<'desktop' | 'mobile'>('desktop');

  // Initialize sharing options from game data
  useEffect(() => {
    if (game?.sharing_settings) {
      setSharingOptions(prev => ({
        ...prev,
        ...game.sharing_settings
      }));
    }
  }, [game]);

  // Load sharing stats when modal opens
  useEffect(() => {
    if (isOpen && game) {
      loadSharingStats();
    }
  }, [isOpen, game]);

  // Clear messages after delay
  useEffect(() => {
    if (success || error) {
      const timer = setTimeout(() => {
        setSuccess(null);
        setError(null);
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [success, error]);

  // Load sharing statistics
  const loadSharingStats = useCallback(async () => {
    if (!game) return;
    
    try {
      const response = await fetch(`/api/games/${game.id}/sharing/stats`);
      if (response.ok) {
        const data = await response.json();
        setSharingStats(data.stats);
      }
    } catch (err) {
      console.error('Failed to load sharing stats:', err);
    }
  }, [game]);

  // Handle sharing
  const handleShare = useCallback(async () => {
    if (!game || !onShare) return;
    
    setLoading(true);
    setError(null);
    setSuccess(null);
    
    try {
      const result = await onShare(sharingOptions);
      
      if (result.success) {
        setShareResult(result);
        setSuccess('Shareable link created successfully!');
        await loadSharingStats(); // Refresh stats
      } else {
        setError(result.message || 'Failed to create shareable link');
      }
    } catch (err) {
      setError('An unexpected error occurred while creating the share link');
      console.error('Share error:', err);
    } finally {
      setLoading(false);
    }
  }, [game, onShare, sharingOptions, loadSharingStats]);

  // Handle settings update
  const handleUpdateSettings = useCallback(async () => {
    if (!game || !onUpdateSettings) return;
    
    setLoading(true);
    setError(null);
    setSuccess(null);
    
    try {
      const success = await onUpdateSettings(sharingOptions);
      
      if (success) {
        setSuccess('Sharing settings updated successfully!');
        await loadSharingStats(); // Refresh stats
      } else {
        setError('Failed to update sharing settings');
      }
    } catch (err) {
      setError('An unexpected error occurred while updating settings');
      console.error('Update settings error:', err);
    } finally {
      setLoading(false);
    }
  }, [game, onUpdateSettings, sharingOptions, loadSharingStats]);

  // Handle link revocation
  const handleRevokeLink = useCallback(async () => {
    if (!game || !onRevokeLink) return;
    
    setLoading(true);
    setError(null);
    setSuccess(null);
    
    try {
      const success = await onRevokeLink();
      
      if (success) {
        setShareResult(null);
        setSuccess('Share link revoked successfully!');
        await loadSharingStats(); // Refresh stats
      } else {
        setError('Failed to revoke share link');
      }
    } catch (err) {
      setError('An unexpected error occurred while revoking the link');
      console.error('Revoke error:', err);
    } finally {
      setLoading(false);
    }
  }, [game, onRevokeLink, loadSharingStats]);

  // Copy to clipboard functionality
  const copyToClipboard = useCallback(async (text: string, field: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedField(field);
      setTimeout(() => setCopiedField(null), 2000);
    } catch (err) {
      console.error('Failed to copy to clipboard:', err);
      setError('Failed to copy to clipboard');
    }
  }, []);

  // Generate social sharing URLs
  const getSocialShareUrls = useCallback((shareUrl: string, title: string) => {
    const encodedUrl = encodeURIComponent(shareUrl);
    const encodedTitle = encodeURIComponent(`Check out my game: ${title}`);
    
    return {
      twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
      facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
      linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
      whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
      email: `mailto:?subject=${encodedTitle}&body=Check out this game I created: ${encodedUrl}`,
    };
  }, []);

  // Generate embed code
  const generateEmbedCode = useCallback((embedUrl: string, title: string) => {
    const width = previewMode === 'mobile' ? '375' : '800';
    const height = previewMode === 'mobile' ? '667' : '600';
    
    return `<iframe 
  src="${embedUrl}" 
  width="${width}" 
  height="${height}" 
  frameborder="0" 
  allowfullscreen
  title="${title}"
  allow="fullscreen; gamepad; microphone; camera">
</iframe>`;
  }, [previewMode]);

  if (!game) return null;

  const shareUrl = shareResult?.share_url || (sharingStats?.has_share_token ? `${window.location.origin}/games/shared/${game.id}` : '');
  const embedUrl = shareResult?.embed_url || (sharingStats?.has_share_token ? `${window.location.origin}/games/embed/${game.id}` : '');
  const socialUrls = shareUrl ? getSocialShareUrls(shareUrl, game.title) : null;
  const embedCode = embedUrl ? generateEmbedCode(embedUrl, game.title) : '';

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className={cn("max-w-4xl max-h-[90vh] overflow-hidden", className)}>
        <DialogHeader>
          <DialogTitle className="flex items-center space-x-2">
            <Share className="w-5 h-5" />
            <span>Share Game</span>
            <Badge variant="secondary" className="ml-2">
              {game.engine_type === 'playcanvas' ? 'PlayCanvas' : 'Unreal'}
            </Badge>
          </DialogTitle>
          <DialogDescription>
            Share "{game.title}" with others or embed it on your website
          </DialogDescription>
        </DialogHeader>

        {/* Status Messages */}
        {error && (
          <div className="flex items-center space-x-2 p-3 bg-destructive/10 border border-destructive/20 rounded-md">
            <AlertCircle className="w-4 h-4 text-destructive" />
            <span className="text-sm text-destructive">{error}</span>
          </div>
        )}
        
        {success && (
          <div className="flex items-center space-x-2 p-3 bg-green-500/10 border border-green-500/20 rounded-md">
            <Check className="w-4 h-4 text-green-500" />
            <span className="text-sm text-green-500">{success}</span>
          </div>
        )}

        <div className="flex-1 overflow-hidden">
          <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as any)} className="h-full">
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="share" className="flex items-center space-x-1">
                <LinkIcon className="w-4 h-4" />
                <span>Share Link</span>
              </TabsTrigger>
              <TabsTrigger value="embed" className="flex items-center space-x-1">
                <Code className="w-4 h-4" />
                <span>Embed</span>
              </TabsTrigger>
              <TabsTrigger value="social" className="flex items-center space-x-1">
                <Users className="w-4 h-4" />
                <span>Social</span>
              </TabsTrigger>
              <TabsTrigger value="settings" className="flex items-center space-x-1">
                <Settings className="w-4 h-4" />
                <span>Settings</span>
              </TabsTrigger>
            </TabsList>

            <div className="mt-4 h-[500px] overflow-y-auto">
              {/* Share Link Tab */}
              <TabsContent value="share" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg">Public Share Link</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Create a public link that anyone can use to play your game
                    </p>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {shareUrl ? (
                      <div className="space-y-3">
                        <div className="flex items-center space-x-2">
                          <Input 
                            value={shareUrl} 
                            readOnly 
                            className="flex-1"
                          />
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => copyToClipboard(shareUrl, 'share_url')}
                            className="shrink-0"
                          >
                            {copiedField === 'share_url' ? (
                              <Check className="w-4 h-4" />
                            ) : (
                              <Copy className="w-4 h-4" />
                            )}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="shrink-0"
                          >
                            <a href={shareUrl} target="_blank" rel="noopener noreferrer">
                              <ExternalLink className="w-4 h-4" />
                            </a>
                          </Button>
                        </div>
                        
                        {sharingStats && (
                          <div className="grid grid-cols-2 gap-4 p-3 bg-muted rounded-md">
                            <div className="text-center">
                              <div className="text-2xl font-bold">{sharingStats.total_plays}</div>
                              <div className="text-xs text-muted-foreground">Total Plays</div>
                            </div>
                            <div className="text-center">
                              <div className="text-2xl font-bold">
                                {sharingStats.last_played ? 'Recent' : 'Never'}
                              </div>
                              <div className="text-xs text-muted-foreground">Last Played</div>
                            </div>
                          </div>
                        )}
                        
                        <div className="flex justify-between items-center">
                          <Button
                            variant="outline"
                            onClick={handleUpdateSettings}
                            disabled={loading}
                          >
                            {loading ? (
                              <Loader2 className="w-4 h-4 animate-spin mr-2" />
                            ) : (
                              <Settings className="w-4 h-4 mr-2" />
                            )}
                            Update Settings
                          </Button>
                          
                          <Button
                            variant="destructive"
                            onClick={handleRevokeLink}
                            disabled={loading}
                          >
                            Revoke Link
                          </Button>
                        </div>
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <Globe className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
                        <p className="text-muted-foreground mb-4">
                          No public share link exists yet
                        </p>
                        <Button
                          onClick={handleShare}
                          disabled={loading}
                          className="w-full"
                        >
                          {loading ? (
                            <Loader2 className="w-4 h-4 animate-spin mr-2" />
                          ) : (
                            <Share className="w-4 h-4 mr-2" />
                          )}
                          Create Share Link
                        </Button>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>

              {/* Embed Tab */}
              <TabsContent value="embed" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg">Embed Code</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Embed your game directly on websites and blogs
                    </p>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {embedUrl && sharingOptions.allowEmbedding ? (
                      <div className="space-y-4">
                        <div className="flex items-center justify-between">
                          <Label>Preview Mode</Label>
                          <div className="flex items-center space-x-2">
                            <Button
                              variant={previewMode === 'desktop' ? 'default' : 'outline'}
                              size="sm"
                              onClick={() => setPreviewMode('desktop')}
                            >
                              <Monitor className="w-4 h-4 mr-1" />
                              Desktop
                            </Button>
                            <Button
                              variant={previewMode === 'mobile' ? 'default' : 'outline'}
                              size="sm"
                              onClick={() => setPreviewMode('mobile')}
                            >
                              <Smartphone className="w-4 h-4 mr-1" />
                              Mobile
                            </Button>
                          </div>
                        </div>
                        
                        <div className="space-y-2">
                          <Label>Embed Code</Label>
                          <div className="relative">
                            <pre className="bg-muted p-3 rounded-md text-sm overflow-x-auto">
                              <code>{embedCode}</code>
                            </pre>
                            <Button
                              variant="outline"
                              size="sm"
                              className="absolute top-2 right-2"
                              onClick={() => copyToClipboard(embedCode, 'embed_code')}
                            >
                              {copiedField === 'embed_code' ? (
                                <Check className="w-4 h-4" />
                              ) : (
                                <Copy className="w-4 h-4" />
                              )}
                            </Button>
                          </div>
                        </div>
                        
                        <div className="space-y-2">
                          <Label>Direct Embed URL</Label>
                          <div className="flex items-center space-x-2">
                            <Input 
                              value={embedUrl} 
                              readOnly 
                              className="flex-1"
                            />
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => copyToClipboard(embedUrl, 'embed_url')}
                            >
                              {copiedField === 'embed_url' ? (
                                <Check className="w-4 h-4" />
                              ) : (
                                <Copy className="w-4 h-4" />
                              )}
                            </Button>
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <Code className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
                        <p className="text-muted-foreground mb-4">
                          {!embedUrl 
                            ? "Create a share link first to enable embedding"
                            : "Embedding is disabled in sharing settings"
                          }
                        </p>
                        {!embedUrl && (
                          <Button
                            onClick={handleShare}
                            disabled={loading}
                          >
                            Create Share Link
                          </Button>
                        )}
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>

              {/* Social Tab */}
              <TabsContent value="social" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg">Social Sharing</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Share your game on social media platforms
                    </p>
                  </CardHeader>
                  <CardContent>
                    {shareUrl && socialUrls ? (
                      <div className="grid grid-cols-2 gap-3">
                        <Button
                          variant="outline"
                          className="justify-start"
                          asChild
                        >
                          <a href={socialUrls.twitter} target="_blank" rel="noopener noreferrer">
                            <Twitter className="w-4 h-4 mr-2 text-blue-400" />
                            Twitter
                          </a>
                        </Button>
                        
                        <Button
                          variant="outline"
                          className="justify-start"
                          asChild
                        >
                          <a href={socialUrls.facebook} target="_blank" rel="noopener noreferrer">
                            <Facebook className="w-4 h-4 mr-2 text-blue-600" />
                            Facebook
                          </a>
                        </Button>
                        
                        <Button
                          variant="outline"
                          className="justify-start"
                          asChild
                        >
                          <a href={socialUrls.linkedin} target="_blank" rel="noopener noreferrer">
                            <Linkedin className="w-4 h-4 mr-2 text-blue-700" />
                            LinkedIn
                          </a>
                        </Button>
                        
                        <Button
                          variant="outline"
                          className="justify-start"
                          asChild
                        >
                          <a href={socialUrls.whatsapp} target="_blank" rel="noopener noreferrer">
                            <MessageCircle className="w-4 h-4 mr-2 text-green-500" />
                            WhatsApp
                          </a>
                        </Button>
                        
                        <Button
                          variant="outline"
                          className="justify-start col-span-2"
                          asChild
                        >
                          <a href={socialUrls.email}>
                            <Mail className="w-4 h-4 mr-2" />
                            Email
                          </a>
                        </Button>
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <Users className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
                        <p className="text-muted-foreground mb-4">
                          Create a share link first to enable social sharing
                        </p>
                        <Button
                          onClick={handleShare}
                          disabled={loading}
                        >
                          Create Share Link
                        </Button>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>

              {/* Settings Tab */}
              <TabsContent value="settings" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle className="text-lg">Sharing Settings</CardTitle>
                    <p className="text-sm text-muted-foreground">
                      Configure how your game appears when shared
                    </p>
                  </CardHeader>
                  <CardContent className="space-y-6">
                    <div className="space-y-4">
                      <div className="flex items-center justify-between">
                        <div className="space-y-0.5">
                          <Label>Allow Embedding</Label>
                          <p className="text-sm text-muted-foreground">
                            Let others embed your game on their websites
                          </p>
                        </div>
                        <Switch
                          checked={sharingOptions.allowEmbedding}
                          onCheckedChange={(checked) => 
                            setSharingOptions(prev => ({ ...prev, allowEmbedding: checked }))
                          }
                        />
                      </div>
                      
                      <Separator />
                      
                      <div className="flex items-center justify-between">
                        <div className="space-y-0.5">
                          <Label>Show Game Controls</Label>
                          <p className="text-sm text-muted-foreground">
                            Display play/pause and other game controls
                          </p>
                        </div>
                        <Switch
                          checked={sharingOptions.showControls}
                          onCheckedChange={(checked) => 
                            setSharingOptions(prev => ({ ...prev, showControls: checked }))
                          }
                        />
                      </div>
                      
                      <Separator />
                      
                      <div className="flex items-center justify-between">
                        <div className="space-y-0.5">
                          <Label>Show Game Information</Label>
                          <p className="text-sm text-muted-foreground">
                            Display game title, description, and creator info
                          </p>
                        </div>
                        <Switch
                          checked={sharingOptions.showInfo}
                          onCheckedChange={(checked) => 
                            setSharingOptions(prev => ({ ...prev, showInfo: checked }))
                          }
                        />
                      </div>
                      
                      <Separator />
                      
                      <div className="space-y-2">
                        <Label>Link Expiration</Label>
                        <div className="flex items-center space-x-2">
                          <Input
                            type="number"
                            min="1"
                            max="365"
                            value={sharingOptions.expirationDays || 30}
                            onChange={(e) => 
                              setSharingOptions(prev => ({ 
                                ...prev, 
                                expirationDays: parseInt(e.target.value) || 30 
                              }))
                            }
                            className="w-20"
                          />
                          <span className="text-sm text-muted-foreground">days</span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                          Set to 0 for no expiration (not recommended)
                        </p>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
            </div>
          </Tabs>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
          {activeTab === 'settings' && (
            <Button
              onClick={handleUpdateSettings}
              disabled={loading}
            >
              {loading ? (
                <Loader2 className="w-4 h-4 animate-spin mr-2" />
              ) : (
                <Settings className="w-4 h-4 mr-2" />
              )}
              Save Settings
            </Button>
          )}
          {activeTab === 'share' && !shareUrl && (
            <Button
              onClick={handleShare}
              disabled={loading}
            >
              {loading ? (
                <Loader2 className="w-4 h-4 animate-spin mr-2" />
              ) : (
                <Share className="w-4 h-4 mr-2" />
              )}
              Create Share Link
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default GameSharingModal;