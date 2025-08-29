import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { GameGrid } from '@/components/games/GameGrid';
import { Search, Plus, Grid3X3, List, Gamepad2, ArrowLeft, Filter } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import { PageProps } from '@/types';

interface Game {
  id: number;
  title: string;
  description: string;
  preview_url?: string;
  published_url?: string;
  thumbnail_url?: string;
  metadata?: any;
  created_at: string;
  updated_at: string;
  engine_type: 'unreal' | 'playcanvas';
  is_published: boolean;
  has_preview: boolean;
  has_thumbnail: boolean;
  display_url?: string;
  conversation_id?: number;
  workspace: {
    id: number;
    name: string;
    engine_type: string;
  };
  conversation?: {
    id: number;
    title: string;
    created_at: string;
  };
}

interface GamesIndexProps extends PageProps {
  games: Game[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    has_more_pages: boolean;
  };
  filters: {
    search?: string;
    engine?: string;
    status?: string;
  };
}

export default function GamesIndex({ games, pagination, filters }: GamesIndexProps) {
  const [searchQuery, setSearchQuery] = useState(filters.search || '');
  const [filterEngine, setFilterEngine] = useState(filters.engine || 'all');
  const [filterStatus, setFilterStatus] = useState(filters.status || 'all');
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [showMobileFilters, setShowMobileFilters] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchQuery !== filters.search) {
        applyFilters();
      }
    }, 300);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  const applyFilters = () => {
    setIsLoading(true);
    router.get('/games', {
      search: searchQuery || undefined,
      engine: filterEngine !== 'all' ? filterEngine : undefined,
      status: filterStatus !== 'all' ? filterStatus : undefined,
    }, {
      preserveState: true,
      onFinish: () => setIsLoading(false),
    });
  };

  const handleEngineChange = (value: string) => {
    setFilterEngine(value);
    setIsLoading(true);
    router.get('/games', {
      search: searchQuery || undefined,
      engine: value !== 'all' ? value : undefined,
      status: filterStatus !== 'all' ? filterStatus : undefined,
    }, {
      preserveState: true,
      onFinish: () => setIsLoading(false),
    });
  };

  const handleStatusChange = (value: string) => {
    setFilterStatus(value);
    setIsLoading(true);
    router.get('/games', {
      search: searchQuery || undefined,
      engine: filterEngine !== 'all' ? filterEngine : undefined,
      status: value !== 'all' ? value : undefined,
    }, {
      preserveState: true,
      onFinish: () => setIsLoading(false),
    });
  };

  const handleCreateGame = () => {
    router.visit('/games/create');
  };

  return (
    <MainLayout>
      <Head title="My Games" />
      
      <div className="min-h-screen bg-background">
        {/* Header */}
        <header className="border-b border-border bg-card/50 backdrop-blur-sm">
          <div className="container mx-auto px-3 md:px-4 py-3 md:py-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-2 md:space-x-4 min-w-0">
                <button
                  onClick={() => router.visit('/chat')}
                  className="flex items-center space-x-1 md:space-x-2 text-muted-foreground hover:text-foreground transition-colors"
                >
                  <ArrowLeft className="w-4 h-4" />
                  <span className="hidden sm:inline">Back to Chat</span>
                </button>
                <div className="flex items-center space-x-2 min-w-0">
                  <div className="w-6 h-6 md:w-8 md:h-8 bg-primary rounded-lg flex items-center justify-center">
                    <Gamepad2 className="w-4 h-4 md:w-5 md:h-5 text-primary-foreground" />
                  </div>
                  <div className="min-w-0">
                    <h1 className="text-lg md:text-xl font-serif font-black text-foreground truncate">My Games</h1>
                    <p className="text-xs md:text-sm text-muted-foreground">{pagination.total} projects</p>
                  </div>
                </div>
              </div>
              <Button onClick={handleCreateGame} size="sm" className="shrink-0">
                <Plus className="w-4 h-4 md:mr-2" />
                <span className="hidden md:inline">New Game</span>
              </Button>
            </div>
          </div>
        </header>

        <div className="container mx-auto px-3 md:px-4 py-4 md:py-8">
          {/* Filters and Search */}
          <Card className="border-border bg-card mb-4 md:mb-8">
            <CardContent className="p-3 md:p-6">
              {/* Mobile Search and Filter Toggle */}
              <div className="flex items-center space-x-2 md:hidden mb-4">
                <div className="flex-1 relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  <Input
                    placeholder="Search games..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10 bg-input border-border"
                  />
                </div>
                <Button variant="outline" size="sm" onClick={() => setShowMobileFilters(!showMobileFilters)}>
                  <Filter className="w-4 h-4" />
                </Button>
              </div>

              {/* Desktop Layout */}
              <div className="hidden md:flex flex-col md:flex-row gap-4">
                <div className="flex-1 relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  <Input
                    placeholder="Search games..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="pl-10 bg-input border-border"
                  />
                </div>
                <div className="flex items-center space-x-4">
                  <Select value={filterEngine} onValueChange={handleEngineChange}>
                    <SelectTrigger className="w-40 bg-input border-border">
                      <SelectValue placeholder="Engine" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Engines</SelectItem>
                      <SelectItem value="unreal">Unreal Engine</SelectItem>
                      <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                    </SelectContent>
                  </Select>
                  <Select value={filterStatus} onValueChange={handleStatusChange}>
                    <SelectTrigger className="w-32 bg-input border-border">
                      <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Status</SelectItem>
                      <SelectItem value="active">Active</SelectItem>
                      <SelectItem value="published">Published</SelectItem>
                      <SelectItem value="archived">Archived</SelectItem>
                    </SelectContent>
                  </Select>
                  <div className="flex items-center border border-border rounded-lg">
                    <Button
                      variant={viewMode === 'grid' ? 'default' : 'ghost'}
                      size="sm"
                      onClick={() => setViewMode('grid')}
                      className="rounded-r-none"
                    >
                      <Grid3X3 className="w-4 h-4" />
                    </Button>
                    <Button
                      variant={viewMode === 'list' ? 'default' : 'ghost'}
                      size="sm"
                      onClick={() => setViewMode('list')}
                      className="rounded-l-none"
                    >
                      <List className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </div>

              {/* Mobile Filters */}
              {showMobileFilters && (
                <div className="md:hidden space-y-3 pt-4 border-t border-border">
                  <div className="grid grid-cols-2 gap-3">
                    <Select value={filterEngine} onValueChange={handleEngineChange}>
                      <SelectTrigger className="bg-input border-border">
                        <SelectValue placeholder="Engine" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Engines</SelectItem>
                        <SelectItem value="unreal">Unreal Engine</SelectItem>
                        <SelectItem value="playcanvas">PlayCanvas</SelectItem>
                      </SelectContent>
                    </Select>
                    <Select value={filterStatus} onValueChange={handleStatusChange}>
                      <SelectTrigger className="bg-input border-border">
                        <SelectValue placeholder="Status" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="published">Published</SelectItem>
                        <SelectItem value="archived">Archived</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="flex items-center justify-center">
                    <div className="flex items-center border border-border rounded-lg">
                      <Button
                        variant={viewMode === 'grid' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => setViewMode('grid')}
                        className="rounded-r-none"
                      >
                        <Grid3X3 className="w-4 h-4" />
                      </Button>
                      <Button
                        variant={viewMode === 'list' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => setViewMode('list')}
                        className="rounded-l-none"
                      >
                        <List className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Games Grid/List */}
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
          ) : games.length === 0 ? (
            <Card className="border-border bg-card">
              <CardContent className="p-6 md:p-12 text-center">
                <div className="w-12 h-12 md:w-16 md:h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
                  <Gamepad2 className="w-6 h-6 md:w-8 md:h-8 text-muted-foreground" />
                </div>
                <h3 className="text-base md:text-lg font-serif font-bold text-foreground mb-2">No games found</h3>
                <p className="text-sm md:text-base text-muted-foreground mb-4 md:mb-6">
                  {searchQuery || filterEngine !== 'all' || filterStatus !== 'all'
                    ? 'Try adjusting your search or filters'
                    : 'Create your first game to get started'}
                </p>
                <Button onClick={handleCreateGame} size="sm">
                  <Plus className="w-4 h-4 mr-2" />
                  Create New Game
                </Button>
              </CardContent>
            </Card>
          ) : (
            <GameGrid games={games} viewMode={viewMode} />
          )}

          {/* Pagination */}
          {pagination.last_page > 1 && (
            <div className="flex items-center justify-center space-x-2 mt-8">
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.current_page === 1}
                onClick={() => router.get('/games', { 
                  ...filters, 
                  page: pagination.current_page - 1 
                })}
              >
                Previous
              </Button>
              <span className="text-sm text-muted-foreground">
                Page {pagination.current_page} of {pagination.last_page}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.current_page === pagination.last_page}
                onClick={() => router.get('/games', { 
                  ...filters, 
                  page: pagination.current_page + 1 
                })}
              >
                Next
              </Button>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
}