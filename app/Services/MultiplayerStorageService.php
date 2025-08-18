<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MultiplayerStorageService
{
    private string $disk;
    private string $basePath;
    private int $maxFileSize;
    private array $allowedExtensions;

    public function __construct()
    {
        $this->disk = config('multiplayer.storage_disk', 'public');
        $this->basePath = config('multiplayer.storage_path', 'multiplayer');
        $this->maxFileSize = config('multiplayer.max_file_size', 10485760); // 10MB
        $this->allowedExtensions = config('multiplayer.allowed_extensions', ['json', 'txt', 'dat', 'save']);
    }

    /**
     * Store a multiplayer progress file for a workspace.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @param UploadedFile $file
     * @param string $filename
     * @return array Returns [path, url]
     * @throws \Exception
     */
    public function storeProgressFile(Workspace $workspace, string $sessionId, UploadedFile $file, string $filename): array
    {
        $this->validateFile($file);

        $sanitizedFilename = $this->sanitizeFilename($filename);
        $relativePath = $this->getProgressFilePath($workspace, $sessionId, $sanitizedFilename);

        try {
            // Store the file
            $storedPath = Storage::disk($this->disk)->putFileAs(
                dirname($relativePath),
                $file,
                basename($relativePath)
            );

            // Generate public URL
            $url = Storage::disk($this->disk)->url($storedPath);

            Log::info('Multiplayer progress file stored', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'filename' => $sanitizedFilename,
                'path' => $storedPath,
                'size' => $file->getSize(),
            ]);

            return [
                'path' => $storedPath,
                'url' => $url,
                'filename' => $sanitizedFilename,
                'size' => $file->getSize(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to store multiplayer progress file', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'filename' => $sanitizedFilename,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to store progress file: ' . $e->getMessage());
        }
    }

    /**
     * Store multiplayer server data as JSON.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @param array $data
     * @param string $filename
     * @return array Returns [path, url]
     * @throws \Exception
     */
    public function storeServerData(Workspace $workspace, string $sessionId, array $data, string $filename = 'server_data.json'): array
    {
        $sanitizedFilename = $this->sanitizeFilename($filename);
        $relativePath = $this->getProgressFilePath($workspace, $sessionId, $sanitizedFilename);

        try {
            // Convert data to JSON
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if ($jsonData === false) {
                throw new \Exception('Failed to encode data as JSON');
            }

            // Store the JSON file
            $stored = Storage::disk($this->disk)->put($relativePath, $jsonData);

            if (!$stored) {
                throw new \Exception('Failed to write file to storage');
            }

            // Generate public URL
            $url = Storage::disk($this->disk)->url($relativePath);

            Log::info('Multiplayer server data stored', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'filename' => $sanitizedFilename,
                'path' => $relativePath,
                'data_size' => strlen($jsonData),
            ]);

            return [
                'path' => $relativePath,
                'url' => $url,
                'filename' => $sanitizedFilename,
                'size' => strlen($jsonData),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to store multiplayer server data', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'filename' => $sanitizedFilename,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to store server data: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve a progress file for a workspace session.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @param string $filename
     * @return array|null Returns [content, path, url] or null if not found
     */
    public function getProgressFile(Workspace $workspace, string $sessionId, string $filename): ?array
    {
        $sanitizedFilename = $this->sanitizeFilename($filename);
        $relativePath = $this->getProgressFilePath($workspace, $sessionId, $sanitizedFilename);

        if (!Storage::disk($this->disk)->exists($relativePath)) {
            return null;
        }

        try {
            $content = Storage::disk($this->disk)->get($relativePath);
            $url = Storage::disk($this->disk)->url($relativePath);

            return [
                'content' => $content,
                'path' => $relativePath,
                'url' => $url,
                'filename' => $sanitizedFilename,
                'size' => Storage::disk($this->disk)->size($relativePath),
                'last_modified' => Storage::disk($this->disk)->lastModified($relativePath),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to retrieve multiplayer progress file', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'filename' => $sanitizedFilename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * List all progress files for a workspace session.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @return array
     */
    public function listProgressFiles(Workspace $workspace, string $sessionId): array
    {
        $sessionPath = $this->getSessionPath($workspace, $sessionId);

        try {
            $files = Storage::disk($this->disk)->files($sessionPath);
            $fileList = [];

            foreach ($files as $filePath) {
                $filename = basename($filePath);
                $url = Storage::disk($this->disk)->url($filePath);
                
                $fileList[] = [
                    'filename' => $filename,
                    'path' => $filePath,
                    'url' => $url,
                    'size' => Storage::disk($this->disk)->size($filePath),
                    'last_modified' => Storage::disk($this->disk)->lastModified($filePath),
                ];
            }

            return $fileList;

        } catch (\Exception $e) {
            Log::error('Failed to list multiplayer progress files', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Delete a progress file.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @param string $filename
     * @return bool
     */
    public function deleteProgressFile(Workspace $workspace, string $sessionId, string $filename): bool
    {
        $sanitizedFilename = $this->sanitizeFilename($filename);
        $relativePath = $this->getProgressFilePath($workspace, $sessionId, $sanitizedFilename);

        try {
            $deleted = Storage::disk($this->disk)->delete($relativePath);

            if ($deleted) {
                Log::info('Multiplayer progress file deleted', [
                    'workspace_id' => $workspace->id,
                    'session_id' => $sessionId,
                    'filename' => $sanitizedFilename,
                    'path' => $relativePath,
                ]);
            }

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Failed to delete multiplayer progress file', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'filename' => $sanitizedFilename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clean up all files for a session.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @return bool
     */
    public function cleanupSession(Workspace $workspace, string $sessionId): bool
    {
        $sessionPath = $this->getSessionPath($workspace, $sessionId);

        try {
            $deleted = Storage::disk($this->disk)->deleteDirectory($sessionPath);

            if ($deleted) {
                Log::info('Multiplayer session files cleaned up', [
                    'workspace_id' => $workspace->id,
                    'session_id' => $sessionId,
                    'path' => $sessionPath,
                ]);
            }

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Failed to cleanup multiplayer session files', [
                'workspace_id' => $workspace->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the public URL for a progress file.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @param string $filename
     * @return string|null
     */
    public function getProgressFileUrl(Workspace $workspace, string $sessionId, string $filename): ?string
    {
        $sanitizedFilename = $this->sanitizeFilename($filename);
        $relativePath = $this->getProgressFilePath($workspace, $sessionId, $sanitizedFilename);

        if (!Storage::disk($this->disk)->exists($relativePath)) {
            return null;
        }

        return Storage::disk($this->disk)->url($relativePath);
    }

    /**
     * Get the storage path for a session.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @return string
     */
    private function getSessionPath(Workspace $workspace, string $sessionId): string
    {
        return "{$this->basePath}/company_{$workspace->company_id}/workspace_{$workspace->id}/session_{$sessionId}";
    }

    /**
     * Get the full path for a progress file.
     *
     * @param Workspace $workspace
     * @param string $sessionId
     * @param string $filename
     * @return string
     */
    private function getProgressFilePath(Workspace $workspace, string $sessionId, string $filename): string
    {
        $sessionPath = $this->getSessionPath($workspace, $sessionId);
        return "{$sessionPath}/{$filename}";
    }

    /**
     * Sanitize a filename to prevent directory traversal and other security issues.
     *
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove directory separators and other dangerous characters
        $filename = preg_replace('/[\/\\\\:*?"<>|]/', '', $filename);
        
        // Remove leading dots to prevent hidden files
        $filename = ltrim($filename, '.');
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'unnamed_' . Str::random(8);
        }

        // Limit filename length
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 95 - strlen($extension)) . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Validate an uploaded file.
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \Exception('File extension not allowed. Allowed extensions: ' . implode(', ', $this->allowedExtensions));
        }

        // Check if file is valid
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }
    }

    /**
     * Get storage statistics for a workspace.
     *
     * @param Workspace $workspace
     * @return array
     */
    public function getStorageStats(Workspace $workspace): array
    {
        $workspacePath = "{$this->basePath}/company_{$workspace->company_id}/workspace_{$workspace->id}";
        
        try {
            $files = Storage::disk($this->disk)->allFiles($workspacePath);
            $totalSize = 0;
            $fileCount = count($files);

            foreach ($files as $file) {
                $totalSize += Storage::disk($this->disk)->size($file);
            }

            return [
                'file_count' => $fileCount,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            ];

        } catch (\Exception $e) {
            return [
                'file_count' => 0,
                'total_size' => 0,
                'total_size_mb' => 0,
            ];
        }
    }
}