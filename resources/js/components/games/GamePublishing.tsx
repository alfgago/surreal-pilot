import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from '@/components/ui/use-toast';
import { 
  Rocket, 
  Share, 
  Download, 
  Settings, 
  Clock, 
  CheckCircle, 
  XCircle, 
  Loader2,
  Copy,
  ExternalLink,
  Code,
  Globe,
  Lock,
  History,
  Play
} from 'lucide-react';
import axios from 'axios';

interface GameBuild {
  id: number;
  version: string;
  status: 'building' | 'success' | 'failed';
  build_duration?: string;
  total_size?: string;
  file_count: number;
  created_at: string;
  completed_at?: string;
}

interface Game {
  id: string;
  title: string;
  description: string;
  status: 'draft' | 'published' | 'archived';
  version: string;
  is_public: boolean;
  share_token?: string;
  build_status: 'none' | 'building' | 'success' | 'failed';
  last_build_at?: string;
  published_at?: string;
  sharing_settings?: {
    allow_embedding: boolean;
    show_controls: boolean;
    show_info: boolean;
  };
}

interface GamePublishingProps {
  game: Game;
  onGameUpdate: (updatedGame: Partial<Game>) => void;
}

export default function GamePublishing({ game, onGameUpdate }: GamePublishingProps) {
  const [isBuilding, setIsBuilding] = useState(false);
  const [buildHistory, setBuildHistory] = useState<GameBuild[]>([]);
  const [showPublishDialog, setShowPublishDialog] = useState(false);
  const [showShareDialog, setShowShareDialog] = useState(false);
  const [publishSettings, setPublishSettings] = useState({
    is_public: game.is_public || false,
    sharing_settings: {
      allow_embedding: true,
      show_controls: true,
      show_info: true,
      ...game.sharing_settings,
    },
  });

  // Poll build status when building
  useEffect(() => {
    if (game.build_status === 'building') {
      const interval = setInterval(checkBuildStatus, 2000);
      return () => clearInterval(interval);
    }
  }, [game.build_status]);

  // Load build history on mount
  useEffect(() => {
    loadBuildHistory();
  }, []);

  const checkBuildStatus = async () => {
    try {
      const response = await axios.get(`/api/games/${game.id}/build/status`);
      const { build_status, latest_build } = response.data;
      
      if (build_status !== 'building') {
        onGameUpdate({ build_status, last_build_at: latest_build?.completed_at });
        setIsBuilding(false);
        
        if (build_status === 'success') {
          toast({
            title: "Build Complete",
            description: `Version ${latest_build?.version} built successfully`,
          });
        } else if (build_status === 'failed') {
          toast({
            title: "Build Failed",
            description: "There was an error building your game",
            variant: "destructive",
          });
        }
        
        loadBuildHistory();
      }
    } catch (error) {
      console.error('Failed to check build status:', error);
    }
  };

  const loadBuildHistory = async () => {
    try {
      const response = await axios.get(`/api/games/${game.id}/build/history`);
      setBuildHistory(response.data.builds);
    } catch (error) {
      console.error('Failed to load build history:', error);
    }
  };

  const startBuild = async () => {
    try {
      setIsBuilding(true);
      await axios.post(`/api/games/${game.id}/build`, {
        minify: true,
        optimize_assets: true,
        include_debug: false,
      });
      
      onGameUpdate({ build_status: 'building', last_build_at: new Date().toISOString() });
      
      toast({
        title: "Build Started",
        description: "Your game is being built. This may take a few minutes.",
      });
    } catch (error: any) {
      setIsBuilding(false);
      toast({
        title: "Build Failed",
        description: error.response?.data?.error || "Failed to start build",
        variant: "destructive",
      });
    }
  };

  const publishGame = async () => {
    try {
      const response = await axios.post(`/api/games/${game.id}/publish`, publishSettings);
      const updatedGame = response.data.game;
      
      onGameUpdate({
        status: 'published',
        published_at: updatedGame.published_at,
        is_public: publishSettings.is_public,
        sharing_settings: publishSettings.sharing_settings,
        share_token: updatedGame.share_token,
      });
      
      setShowPublishDialog(false);
      
      toast({
        title: "Game Published",
        description: "Your game is now live and accessible!",
      });
    } catch (error: any) {
      toast({
        title: "Publish Failed",
        description: error.response?.data?.error || "Failed to publish game",
        variant: "destructive",
      });
    }
  };

  const unpublishGame = async () => {
    try {
      await axios.post(`/api/games/${game.id}/unpublish`);
      
      onGameUpdate({
        status: 'draft',
        is_public: false,
        published_at: null,
      });
      
      toast({
        title: "Game Unpublished",
        description: "Your game is no longer publicly accessible",
      });
    } catch (error: any) {
      toast({
        title: "Unpublish Failed",
        description: error.response?.data?.error || "Failed to unpublish game",
        variant: "destructive",
      });
    }
  };

  const generateShareToken = async () => {
    try {
      const response = await axios.post(`/api/games/${game.id}/share-token`);
      const { share_token } = response.data;
      
      onGameUpdate({ share_token });
      
      toast({
        title: "Share Token Generated",
        description: "New sharing links have been created",
      });
    } catch (error: any) {
      toast({
        title: "Failed to Generate Token",
        description: error.response?.data?.error || "Failed to generate share token",
        variant: "destructive",
      });
    }
  };

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    toast({
      title: "Copied to Clipboard",
      description: `${label} copied successfully`,
    });
  };

  const getShareUrl = () => game.share_token ? `${window.location.origin}/games/shared/${game.share_token}` : '';
  const getEmbedUrl = () => game.share_token ? `${window.location.origin}/games/embed/${game.share_token}` : '';

  const getBuildStatusIcon = (status: string) => {
    switch (status) {
      case 'building':
        return <Loader2 className="w-4 h-4 animate-spin text-blue-500" />;
      case 'success':
        return <CheckCircle className="w-4 h-4 text-green-500" />;
      case 'failed':
        return <XCircle className="w-4 h-4 text-red-500" />;
      default:
        return <Clock className="w-4 h-4 text-gray-500" />;
    }
  };

  const getBuildStatusColor = (status: string) => {
    switch (status) {
      case 'building':
        return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'success':
        return 'bg-green-100 text-green-800 border-green-200';
      case 'failed':
        return 'bg-red-100 text-red-800 border-red-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  return (
    <div className="space-y-6">
      {/* Build Status Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <Settings className="w-5 h-5" />
              <span>Build & Deploy</span>
            </div>
            <Badge variant="outline" className={getBuildStatusColor(game.build_status)}>
              <div className="flex items-center space-x-1">
                {getBuildStatusIcon(game.build_status)}
                <span className="capitalize">{game.build_status}</span>
              </div>
            </Badge>
          </CardTitle>
          <CardDescription>
            Build and deploy your game for sharing and publishing
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Current Version: {game.version}</p>
              {game.last_build_at && (
                <p className="text-sm text-muted-foreground">
                  Last built: {new Date(game.last_build_at).toLocaleString()}
                </p>
              )}
            </div>
            <div className="flex space-x-2">
              <Button
                onClick={startBuild}
                disabled={isBuilding || game.build_status === 'building'}
                variant="outline"
              >
                {isBuilding || game.build_status === 'building' ? (
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                ) : (
                  <Download className="w-4 h-4 mr-2" />
                )}
                {isBuilding || game.build_status === 'building' ? 'Building...' : 'Build Game'}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Publishing Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center space-x-2">
            <Rocket className="w-5 h-5" />
            <span>Publishing</span>
          </CardTitle>
          <CardDescription>
            Make your game publicly accessible and shareable
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <div className="flex items-center space-x-2">
                <Badge variant={game.status === 'published' ? 'default' : 'secondary'}>
                  {game.status === 'published' ? (
                    <Globe className="w-3 h-3 mr-1" />
                  ) : (
                    <Lock className="w-3 h-3 mr-1" />
                  )}
                  {game.status}
                </Badge>
                {game.is_public && (
                  <Badge variant="outline" className="text-green-600">
                    Public
                  </Badge>
                )}
              </div>
              {game.published_at && (
                <p className="text-sm text-muted-foreground mt-1">
                  Published: {new Date(game.published_at).toLocaleString()}
                </p>
              )}
            </div>
            <div className="flex space-x-2">
              {game.status === 'published' ? (
                <>
                  <Dialog open={showShareDialog} onOpenChange={setShowShareDialog}>
                    <DialogTrigger asChild>
                      <Button variant="outline">
                        <Share className="w-4 h-4 mr-2" />
                        Share
                      </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-md">
                      <DialogHeader>
                        <DialogTitle>Share Game</DialogTitle>
                        <DialogDescription>
                          Share your game with others using these links
                        </DialogDescription>
                      </DialogHeader>
                      <div className="space-y-4">
                        <div>
                          <Label>Public Game Link</Label>
                          <div className="flex space-x-2 mt-1">
                            <Input value={getShareUrl()} readOnly className="text-sm" />
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => copyToClipboard(getShareUrl(), 'Share URL')}
                            >
                              <Copy className="w-4 h-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => window.open(getShareUrl(), '_blank')}
                            >
                              <ExternalLink className="w-4 h-4" />
                            </Button>
                          </div>
                        </div>
                        
                        {publishSettings.sharing_settings.allow_embedding && (
                          <div>
                            <Label>Embed Link</Label>
                            <div className="flex space-x-2 mt-1">
                              <Input value={getEmbedUrl()} readOnly className="text-sm" />
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => copyToClipboard(getEmbedUrl(), 'Embed URL')}
                              >
                                <Copy className="w-4 h-4" />
                              </Button>
                            </div>
                          </div>
                        )}
                        
                        <div>
                          <Label>Embed Code</Label>
                          <Textarea
                            value={`<iframe src="${getEmbedUrl()}" width="800" height="600" frameborder="0"></iframe>`}
                            readOnly
                            className="text-sm font-mono"
                            rows={3}
                          />
                          <Button
                            size="sm"
                            variant="outline"
                            className="mt-2"
                            onClick={() => copyToClipboard(
                              `<iframe src="${getEmbedUrl()}" width="800" height="600" frameborder="0"></iframe>`,
                              'Embed code'
                            )}
                          >
                            <Code className="w-4 h-4 mr-2" />
                            Copy Embed Code
                          </Button>
                        </div>
                        
                        <div className="flex justify-between">
                          <Button variant="outline" onClick={generateShareToken}>
                            Generate New Links
                          </Button>
                          <Button variant="destructive" onClick={unpublishGame}>
                            Unpublish
                          </Button>
                        </div>
                      </div>
                    </DialogContent>
                  </Dialog>
                </>
              ) : (
                <Dialog open={showPublishDialog} onOpenChange={setShowPublishDialog}>
                  <DialogTrigger asChild>
                    <Button 
                      disabled={game.build_status !== 'success'}
                      className="bg-green-600 hover:bg-green-700"
                    >
                      <Rocket className="w-4 h-4 mr-2" />
                      Publish Game
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>Publish Game</DialogTitle>
                      <DialogDescription>
                        Configure how your game will be shared and accessed
                      </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <Label>Make Public</Label>
                          <p className="text-sm text-muted-foreground">
                            Allow anyone with the link to play your game
                          </p>
                        </div>
                        <Switch
                          checked={publishSettings.is_public}
                          onCheckedChange={(checked) => 
                            setPublishSettings(prev => ({ ...prev, is_public: checked }))
                          }
                        />
                      </div>
                      
                      <div className="space-y-3">
                        <Label>Sharing Options</Label>
                        
                        <div className="flex items-center justify-between">
                          <div>
                            <Label className="text-sm">Allow Embedding</Label>
                            <p className="text-xs text-muted-foreground">
                              Let others embed your game on their websites
                            </p>
                          </div>
                          <Switch
                            checked={publishSettings.sharing_settings.allow_embedding}
                            onCheckedChange={(checked) => 
                              setPublishSettings(prev => ({
                                ...prev,
                                sharing_settings: { ...prev.sharing_settings, allow_embedding: checked }
                              }))
                            }
                          />
                        </div>
                        
                        <div className="flex items-center justify-between">
                          <div>
                            <Label className="text-sm">Show Controls</Label>
                            <p className="text-xs text-muted-foreground">
                              Display game controls and UI elements
                            </p>
                          </div>
                          <Switch
                            checked={publishSettings.sharing_settings.show_controls}
                            onCheckedChange={(checked) => 
                              setPublishSettings(prev => ({
                                ...prev,
                                sharing_settings: { ...prev.sharing_settings, show_controls: checked }
                              }))
                            }
                          />
                        </div>
                        
                        <div className="flex items-center justify-between">
                          <div>
                            <Label className="text-sm">Show Game Info</Label>
                            <p className="text-xs text-muted-foreground">
                              Display game title, description, and metadata
                            </p>
                          </div>
                          <Switch
                            checked={publishSettings.sharing_settings.show_info}
                            onCheckedChange={(checked) => 
                              setPublishSettings(prev => ({
                                ...prev,
                                sharing_settings: { ...prev.sharing_settings, show_info: checked }
                              }))
                            }
                          />
                        </div>
                      </div>
                      
                      <div className="flex justify-end space-x-2">
                        <Button variant="outline" onClick={() => setShowPublishDialog(false)}>
                          Cancel
                        </Button>
                        <Button onClick={publishGame} className="bg-green-600 hover:bg-green-700">
                          <Rocket className="w-4 h-4 mr-2" />
                          Publish Game
                        </Button>
                      </div>
                    </div>
                  </DialogContent>
                </Dialog>
              )}
            </div>
          </div>
          
          {game.build_status !== 'success' && game.status !== 'published' && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
              <p className="text-sm text-yellow-800">
                <strong>Note:</strong> You need a successful build before you can publish your game.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Build History */}
      {buildHistory.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <History className="w-5 h-5" />
              <span>Build History</span>
            </CardTitle>
            <CardDescription>
              Previous builds and deployment history
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {buildHistory.map((build) => (
                <div key={build.id} className="flex items-center justify-between p-3 border rounded-lg">
                  <div className="flex items-center space-x-3">
                    {getBuildStatusIcon(build.status)}
                    <div>
                      <p className="font-medium">Version {build.version}</p>
                      <p className="text-sm text-muted-foreground">
                        {new Date(build.created_at).toLocaleString()}
                      </p>
                    </div>
                  </div>
                  <div className="text-right text-sm text-muted-foreground">
                    {build.build_duration && <p>Duration: {build.build_duration}</p>}
                    {build.total_size && <p>Size: {build.total_size}</p>}
                    <p>Files: {build.file_count}</p>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}