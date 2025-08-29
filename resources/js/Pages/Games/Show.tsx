import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/use-toast';
import MainLayout from '@/Layouts/MainLayout';
import PlayCanvasPreview from '@/components/engine/PlayCanvasPreview';
import { FileManager } from '@/components/games/FileManager';
import GamePublishing from '@/components/games/GamePublishing';
import { 
  Play, 
  Edit, 
  Save, 
  Trash2, 
  Download, 
  Share
} from 'lucide-react';

interface GameFile {
  id: string;
  name: string;
  path: string;
  size: number;
  type: 'script' | 'asset' | 'scene' | 'config';
  lastModified: string;
  content?: string;
}

interface Game {
  id: string;
  title: string;
  description: string;
  engine: 'playcanvas' | 'unreal';
  status: 'draft' | 'published' | 'archived';
  thumbnail?: string;
  createdAt: string;
  updatedAt: string;
  files: GameFile[];
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
  metadata: {
    version: string;
    tags: string[];
    playCount: number;
    lastPlayed?: string;
  };
}

interface Props {
  game: Game;
}

export default function GameShow({ game }: Props) {
  const [isEditing, setIsEditing] = useState(false);
  const [gameData, setGameData] = useState(game);
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);

  const handleSaveGame = () => {
    router.put(`/games/${game.id}`, {
      title: gameData.title,
      description: gameData.description,
      status: gameData.status,
    }, {
      onSuccess: () => {
        toast({
          title: "Game saved",
          description: "Your game has been saved successfully.",
        });
        setIsEditing(false);
      },
      onError: () => {
        toast({
          title: "Error",
          description: "Failed to save game. Please try again.",
          variant: "destructive",
        });
      }
    });
  };

  const handleDeleteGame = () => {
    router.delete(`/games/${game.id}`, {
      onSuccess: () => {
        toast({
          title: "Game deleted",
          description: "Your game has been deleted successfully.",
        });
        router.visit('/games');
      },
      onError: () => {
        toast({
          title: "Error",
          description: "Failed to delete game. Please try again.",
          variant: "destructive",
        });
      }
    });
  };



  return (
    <MainLayout>
      <Head title={`${game.title} - Game Editor`} />
      
      <div className="container mx-auto px-4 py-8">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div className="flex items-center space-x-4">
            <Button
              variant="ghost"
              onClick={() => router.visit('/games')}
            >
              ← Back to Games
            </Button>
            <div>
              <h1 className="text-3xl font-bold">{gameData.title}</h1>
              <p className="text-muted-foreground">{gameData.description}</p>
            </div>
            <Badge variant={gameData.status === 'published' ? 'default' : 'secondary'}>
              {gameData.status}
            </Badge>
          </div>
          
          <div className="flex items-center space-x-2">
            <Button
              variant="outline"
              onClick={() => window.open(`/games/${game.id}/play`, '_blank')}
            >
              <Play className="w-4 h-4 mr-2" />
              Play
            </Button>
            <Button
              variant="outline"
              onClick={() => setIsEditing(!isEditing)}
            >
              <Edit className="w-4 h-4 mr-2" />
              {isEditing ? 'Cancel' : 'Edit'}
            </Button>
            {isEditing && (
              <Button onClick={handleSaveGame}>
                <Save className="w-4 h-4 mr-2" />
                Save
              </Button>
            )}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
              <DialogTrigger asChild>
                <Button variant="destructive">
                  <Trash2 className="w-4 h-4 mr-2" />
                  Delete
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Delete Game</DialogTitle>
                  <DialogDescription>
                    Are you sure you want to delete "{game.title}"? This action cannot be undone.
                  </DialogDescription>
                </DialogHeader>
                <div className="flex justify-end space-x-2">
                  <Button variant="outline" onClick={() => setShowDeleteDialog(false)}>
                    Cancel
                  </Button>
                  <Button variant="destructive" onClick={handleDeleteGame}>
                    Delete Game
                  </Button>
                </div>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main Content */}
          <div className="lg:col-span-2">
            <Tabs defaultValue="preview" className="w-full">
              <TabsList className="grid w-full grid-cols-5">
                <TabsTrigger value="preview">Preview</TabsTrigger>
                <TabsTrigger value="files">Files</TabsTrigger>
                <TabsTrigger value="publish">Publish</TabsTrigger>
                <TabsTrigger value="settings">Settings</TabsTrigger>
                <TabsTrigger value="analytics">Analytics</TabsTrigger>
              </TabsList>
              
              <TabsContent value="preview" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle>Game Preview</CardTitle>
                    <CardDescription>
                      Live preview of your {game.engine} game
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {game.engine === 'playcanvas' ? (
                      <PlayCanvasPreview 
                        gameId={game.id} 
                        previewUrl={game.preview_url}
                      />
                    ) : (
                      <div className="aspect-video bg-muted rounded-lg flex items-center justify-center">
                        <p className="text-muted-foreground">
                          Unreal Engine preview not available in web editor
                        </p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="files" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle>Project Files</CardTitle>
                    <CardDescription>
                      Manage your game files and assets
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <FileManager 
                      gameId={game.id} 
                      files={game.files}
                      onFileUpdate={() => router.reload({ only: ['game'] })}
                    />
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="publish" className="space-y-4">
                <GamePublishing 
                  game={gameData}
                  onGameUpdate={(updates) => setGameData(prev => ({ ...prev, ...updates }))}
                />
              </TabsContent>
              
              <TabsContent value="settings" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle>Game Settings</CardTitle>
                    <CardDescription>
                      Configure your game properties and metadata
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {isEditing ? (
                      <>
                        <div className="space-y-2">
                          <Label htmlFor="title">Title</Label>
                          <Input
                            id="title"
                            value={gameData.title}
                            onChange={(e) => setGameData({...gameData, title: e.target.value})}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="description">Description</Label>
                          <Textarea
                            id="description"
                            value={gameData.description}
                            onChange={(e) => setGameData({...gameData, description: e.target.value})}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="status">Status</Label>
                          <select
                            id="status"
                            value={gameData.status}
                            onChange={(e) => setGameData({...gameData, status: e.target.value as any})}
                            className="w-full p-2 border rounded-md"
                          >
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                          </select>
                        </div>
                      </>
                    ) : (
                      <>
                        <div>
                          <Label>Title</Label>
                          <p className="text-sm">{gameData.title}</p>
                        </div>
                        <div>
                          <Label>Description</Label>
                          <p className="text-sm">{gameData.description}</p>
                        </div>
                        <div>
                          <Label>Status</Label>
                          <Badge variant={gameData.status === 'published' ? 'default' : 'secondary'}>
                            {gameData.status}
                          </Badge>
                        </div>
                      </>
                    )}
                    <div>
                      <Label>Engine</Label>
                      <p className="text-sm capitalize">{game.engine}</p>
                    </div>
                    <div>
                      <Label>Version</Label>
                      <p className="text-sm">{game.metadata.version}</p>
                    </div>
                    <div>
                      <Label>Created</Label>
                      <p className="text-sm">{new Date(game.createdAt).toLocaleDateString()}</p>
                    </div>
                    <div>
                      <Label>Last Modified</Label>
                      <p className="text-sm">{new Date(game.updatedAt).toLocaleDateString()}</p>
                    </div>
                  </CardContent>
                </Card>
              </TabsContent>
              
              <TabsContent value="analytics" className="space-y-4">
                <Card>
                  <CardHeader>
                    <CardTitle>Game Analytics</CardTitle>
                    <CardDescription>
                      View your game's performance and usage statistics
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="text-center p-4 border rounded-lg">
                        <p className="text-2xl font-bold">{game.metadata.playCount}</p>
                        <p className="text-sm text-muted-foreground">Total Plays</p>
                      </div>
                      <div className="text-center p-4 border rounded-lg">
                        <p className="text-2xl font-bold">{game.files.length}</p>
                        <p className="text-sm text-muted-foreground">Files</p>
                      </div>
                    </div>
                    {game.metadata.lastPlayed && (
                      <div className="mt-4">
                        <Label>Last Played</Label>
                        <p className="text-sm">{new Date(game.metadata.lastPlayed).toLocaleDateString()}</p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Quick Actions</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <Button className="w-full" onClick={() => window.open(`/games/${game.id}/play`, '_blank')}>
                  <Play className="w-4 h-4 mr-2" />
                  Play Game
                </Button>
                <Button variant="outline" className="w-full">
                  <Share className="w-4 h-4 mr-2" />
                  Share Game
                </Button>
                <Button variant="outline" className="w-full">
                  <Download className="w-4 h-4 mr-2" />
                  Export Project
                </Button>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Game Info</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Engine:</span>
                  <span className="text-sm capitalize">{game.engine}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Status:</span>
                  <Badge variant={game.status === 'published' ? 'default' : 'secondary'} className="text-xs">
                    {game.status}
                  </Badge>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Files:</span>
                  <span className="text-sm">{game.files.length}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Plays:</span>
                  <span className="text-sm">{game.metadata.playCount}</span>
                </div>
              </CardContent>
            </Card>

            {game.metadata.tags.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle>Tags</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex flex-wrap gap-2">
                    {game.metadata.tags.map((tag, index) => (
                      <Badge key={index} variant="outline" className="text-xs">
                        {tag}
                      </Badge>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>
    </MainLayout>
  );
}