import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { toast } from '@/components/ui/use-toast';
import { router } from '@inertiajs/react';
import { 
  Upload, 
  Download, 
  Save, 
  File, 
  FolderOpen, 
  Eye,
  Plus,
  Trash2
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

interface FileManagerProps {
  gameId: string;
  files: GameFile[];
  onFileUpdate?: () => void;
}

export function FileManager({ gameId, files, onFileUpdate }: FileManagerProps) {
  const [selectedFile, setSelectedFile] = useState<GameFile | null>(null);
  const [fileContent, setFileContent] = useState('');
  const [showFileUpload, setShowFileUpload] = useState(false);
  const [showNewFile, setShowNewFile] = useState(false);
  const [newFileName, setNewFileName] = useState('');
  const [newFileType, setNewFileType] = useState<GameFile['type']>('script');

  const handleFileSelect = (file: GameFile) => {
    setSelectedFile(file);
    // Load file content if it's a text file
    if (file.type === 'script' || file.type === 'config') {
      setFileContent(file.content || '');
    }
  };

  const handleSaveFile = () => {
    if (!selectedFile) return;

    router.put(`/games/${gameId}/files/${selectedFile.id}`, {
      content: fileContent,
    }, {
      onSuccess: () => {
        toast({
          title: "File saved",
          description: `${selectedFile.name} has been saved successfully.`,
        });
        onFileUpdate?.();
      },
      onError: () => {
        toast({
          title: "Error",
          description: "Failed to save file. Please try again.",
          variant: "destructive",
        });
      }
    });
  };

  const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
    const uploadFiles = event.target.files;
    if (!uploadFiles) return;

    const formData = new FormData();
    Array.from(uploadFiles).forEach(file => {
      formData.append('files[]', file);
    });

    router.post(`/games/${gameId}/files`, formData, {
      onSuccess: () => {
        toast({
          title: "Files uploaded",
          description: "Your files have been uploaded successfully.",
        });
        setShowFileUpload(false);
        onFileUpdate?.();
      },
      onError: () => {
        toast({
          title: "Error",
          description: "Failed to upload files. Please try again.",
          variant: "destructive",
        });
      }
    });
  };

  const handleCreateNewFile = () => {
    if (!newFileName.trim()) return;

    router.post(`/games/${gameId}/files`, {
      name: newFileName,
      type: newFileType,
      content: newFileType === 'script' ? '// New script file\n' : newFileType === 'config' ? '{\n  \n}' : '',
    }, {
      onSuccess: () => {
        toast({
          title: "File created",
          description: `${newFileName} has been created successfully.`,
        });
        setShowNewFile(false);
        setNewFileName('');
        onFileUpdate?.();
      },
      onError: () => {
        toast({
          title: "Error",
          description: "Failed to create file. Please try again.",
          variant: "destructive",
        });
      }
    });
  };

  const handleDeleteFile = (file: GameFile) => {
    router.delete(`/games/${gameId}/files/${file.id}`, {
      onSuccess: () => {
        toast({
          title: "File deleted",
          description: `${file.name} has been deleted successfully.`,
        });
        if (selectedFile?.id === file.id) {
          setSelectedFile(null);
          setFileContent('');
        }
        onFileUpdate?.();
      },
      onError: () => {
        toast({
          title: "Error",
          description: "Failed to delete file. Please try again.",
          variant: "destructive",
        });
      }
    });
  };

  const getFileIcon = (type: string) => {
    switch (type) {
      case 'script': return '📜';
      case 'asset': return '🎨';
      case 'scene': return '🎬';
      case 'config': return '⚙️';
      default: return '📄';
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {/* File List */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <h4 className="font-medium">Files</h4>
          <div className="flex space-x-2">
            <Dialog open={showNewFile} onOpenChange={setShowNewFile}>
              <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                  <Plus className="w-4 h-4 mr-2" />
                  New
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Create New File</DialogTitle>
                  <DialogDescription>
                    Create a new file in your game project
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                  <div>
                    <label className="text-sm font-medium">File Name</label>
                    <Input
                      value={newFileName}
                      onChange={(e) => setNewFileName(e.target.value)}
                      placeholder="Enter file name..."
                    />
                  </div>
                  <div>
                    <label className="text-sm font-medium">File Type</label>
                    <select
                      value={newFileType}
                      onChange={(e) => setNewFileType(e.target.value as GameFile['type'])}
                      className="w-full p-2 border rounded-md"
                    >
                      <option value="script">Script</option>
                      <option value="config">Config</option>
                      <option value="scene">Scene</option>
                      <option value="asset">Asset</option>
                    </select>
                  </div>
                  <div className="flex justify-end space-x-2">
                    <Button variant="outline" onClick={() => setShowNewFile(false)}>
                      Cancel
                    </Button>
                    <Button onClick={handleCreateNewFile}>
                      Create File
                    </Button>
                  </div>
                </div>
              </DialogContent>
            </Dialog>

            <Dialog open={showFileUpload} onOpenChange={setShowFileUpload}>
              <DialogTrigger asChild>
                <Button size="sm">
                  <Upload className="w-4 h-4 mr-2" />
                  Upload
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Upload Files</DialogTitle>
                  <DialogDescription>
                    Select files to upload to your game project
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                  <Input
                    type="file"
                    multiple
                    onChange={handleFileUpload}
                    accept=".js,.json,.png,.jpg,.jpeg,.gif,.mp3,.wav,.obj,.fbx"
                  />
                  <p className="text-sm text-muted-foreground">
                    Supported formats: JS, JSON, PNG, JPG, GIF, MP3, WAV, OBJ, FBX
                  </p>
                </div>
              </DialogContent>
            </Dialog>
          </div>
        </div>
        
        <div className="border rounded-lg max-h-96 overflow-y-auto">
          {files.map((file) => (
            <div
              key={file.id}
              className={`p-3 border-b last:border-b-0 cursor-pointer hover:bg-muted/50 ${
                selectedFile?.id === file.id ? 'bg-muted' : ''
              }`}
              onClick={() => handleFileSelect(file)}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <span>{getFileIcon(file.type)}</span>
                  <div>
                    <p className="font-medium text-sm">{file.name}</p>
                    <p className="text-xs text-muted-foreground">
                      {formatFileSize(file.size)} • {file.type}
                    </p>
                  </div>
                </div>
                <div className="flex space-x-1">
                  <Button variant="ghost" size="sm">
                    <Download className="w-4 h-4" />
                  </Button>
                  <Button 
                    variant="ghost" 
                    size="sm"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleDeleteFile(file);
                    }}
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            </div>
          ))}
          {files.length === 0 && (
            <div className="p-8 text-center text-muted-foreground">
              <FolderOpen className="w-12 h-12 mx-auto mb-2" />
              <p>No files in this project</p>
              <p className="text-sm">Upload or create files to get started</p>
            </div>
          )}
        </div>
      </div>
      
      {/* File Editor */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <h4 className="font-medium">
            {selectedFile ? selectedFile.name : 'Select a file'}
          </h4>
          {selectedFile && (selectedFile.type === 'script' || selectedFile.type === 'config') && (
            <Button size="sm" onClick={handleSaveFile}>
              <Save className="w-4 h-4 mr-2" />
              Save
            </Button>
          )}
        </div>
        <div className="border rounded-lg h-96">
          {selectedFile ? (
            selectedFile.type === 'script' || selectedFile.type === 'config' ? (
              <Textarea
                value={fileContent}
                onChange={(e) => setFileContent(e.target.value)}
                className="h-full resize-none font-mono text-sm"
                placeholder="File content will appear here..."
              />
            ) : selectedFile.type === 'asset' ? (
              <div className="h-full flex items-center justify-center bg-muted/20">
                <div className="text-center">
                  <File className="w-12 h-12 mx-auto mb-2 text-muted-foreground" />
                  <p className="text-sm text-muted-foreground">
                    Asset preview not available
                  </p>
                  <Button variant="outline" size="sm" className="mt-2">
                    <Download className="w-4 h-4 mr-2" />
                    Download
                  </Button>
                </div>
              </div>
            ) : (
              <div className="h-full flex items-center justify-center bg-muted/20">
                <div className="text-center">
                  <Eye className="w-12 h-12 mx-auto mb-2 text-muted-foreground" />
                  <p className="text-sm text-muted-foreground">
                    Preview not available for this file type
                  </p>
                </div>
              </div>
            )
          ) : (
            <div className="h-full flex items-center justify-center bg-muted/20">
              <div className="text-center">
                <FolderOpen className="w-12 h-12 mx-auto mb-2 text-muted-foreground" />
                <p className="text-sm text-muted-foreground">
                  Select a file to view or edit
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}