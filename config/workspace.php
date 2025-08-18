<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace Storage Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines how workspace files and build artifacts
    | are stored in your Laravel application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Workspace Storage Disk
    |--------------------------------------------------------------------------
    |
    | This defines which storage disk should be used for storing workspace
    | source files. This can be 'local', 's3', or any other configured disk.
    |
    */
    'workspace_disk' => env('WORKSPACE_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Build Artifacts Storage Disk
    |--------------------------------------------------------------------------
    |
    | This defines which storage disk should be used for storing build
    | artifacts (compiled games). This can be 'local', 's3', or any other
    | configured disk.
    |
    */
    'builds_disk' => env('BUILDS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Build Retention Policy
    |--------------------------------------------------------------------------
    |
    | This defines how long build artifacts should be retained before
    | automatic cleanup. Set to null to disable automatic cleanup.
    |
    */
    'build_retention_days' => env('BUILD_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    |
    | This defines the base directory for temporary files during build
    | processes. Defaults to system temp directory.
    |
    */
    'temp_directory' => env('WORKSPACE_TEMP_DIR', sys_get_temp_dir()),

];