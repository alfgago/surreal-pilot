# PlayCanvas Cloud Publishing

This document describes the PlayCanvas cloud publishing feature implementation.

## API Endpoint

### POST /api/workspace/publish-playcanvas-cloud

Publishes a PlayCanvas workspace to PlayCanvas cloud hosting.

#### Request Parameters

- `workspace_id` (required, integer): The ID of the workspace to publish
- `playcanvas_api_key` (required, string): PlayCanvas API key for authentication
- `playcanvas_project_id` (required, string): PlayCanvas project ID for the target project
- `save_credentials` (optional, boolean): Whether to save the credentials to the company for future use

#### Response

```json
{
  "success": true,
  "data": {
    "workspace_id": 123,
    "launch_url": "https://playcanv.as/project-id/",
    "status": "published",
    "publish_time": 2.45,
    "platform": "playcanvas_cloud",
    "credentials_saved": true
  }
}
```

#### Error Responses

- `400`: Invalid workspace type or workspace not ready
- `422`: Validation errors or PlayCanvas API errors
- `500`: Build failures or other server errors

## Credit System Integration

PlayCanvas cloud publishing deducts credits for build tokens only (no MCP surcharge):

- **Build Cost**: 1.0 credit per publish operation
- **MCP Surcharge**: 0.0 credits (as per requirement 7.4)

The credit deduction includes metadata:
```php
[
    'workspace_id' => $workspace->id,
    'engine_type' => 'playcanvas',
    'publish_type' => 'cloud',
    'project_id' => $credentials['project_id'],
    'build_cost' => 1.0,
    'mcp_surcharge' => 0,
]
```

## Company Credential Management

Companies can store PlayCanvas credentials for convenience:

```php
// Check if company has credentials
$company->hasPlayCanvasCredentials(); // returns boolean

// Access credentials (API key is hidden in serialization)
$company->playcanvas_api_key;    // Available in code
$company->playcanvas_project_id; // Available in code and serialization
```

## UI Integration Example

Here's how the UI should integrate with this feature:

### 1. Publish Button with Credential Modal

```javascript
// Example Vue.js component structure
{
  data() {
    return {
      showCredentialModal: false,
      credentials: {
        api_key: '',
        project_id: '',
        save_credentials: false
      },
      publishing: false
    }
  },
  
  methods: {
    async publishToPlayCanvasCloud() {
      this.publishing = true;
      
      try {
        const response = await fetch('/api/workspace/publish-playcanvas-cloud', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.authToken}`
          },
          body: JSON.stringify({
            workspace_id: this.workspace.id,
            playcanvas_api_key: this.credentials.api_key,
            playcanvas_project_id: this.credentials.project_id,
            save_credentials: this.credentials.save_credentials
          })
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Show success message with launch URL
          this.showSuccessMessage(result.data.launch_url);
          this.showCredentialModal = false;
        } else {
          // Show error message
          this.showErrorMessage(result.message);
        }
      } catch (error) {
        this.showErrorMessage('Failed to publish to PlayCanvas cloud');
      } finally {
        this.publishing = false;
      }
    }
  }
}
```

### 2. Credential Input Form

```html
<!-- Example credential input modal -->
<div v-if="showCredentialModal" class="modal">
  <div class="modal-content">
    <h3>Publish to PlayCanvas Cloud</h3>
    
    <form @submit.prevent="publishToPlayCanvasCloud">
      <div class="form-group">
        <label for="api_key">PlayCanvas API Key:</label>
        <input 
          id="api_key"
          v-model="credentials.api_key"
          type="password"
          required
          placeholder="Enter your PlayCanvas API key"
        />
        <small>You can find this in your PlayCanvas account settings</small>
      </div>
      
      <div class="form-group">
        <label for="project_id">Project ID:</label>
        <input 
          id="project_id"
          v-model="credentials.project_id"
          type="text"
          required
          placeholder="Enter your PlayCanvas project ID"
        />
        <small>The ID of the PlayCanvas project to publish to</small>
      </div>
      
      <div class="form-group">
        <label>
          <input 
            v-model="credentials.save_credentials"
            type="checkbox"
          />
          Save credentials for future use
        </label>
      </div>
      
      <div class="form-actions">
        <button type="button" @click="showCredentialModal = false">Cancel</button>
        <button type="submit" :disabled="publishing">
          {{ publishing ? 'Publishing...' : 'Publish to PlayCanvas Cloud' }}
        </button>
      </div>
    </form>
  </div>
</div>
```

### 3. Mobile-Optimized UI Considerations

For mobile optimization (as per requirement 8.1-8.7):

- Use large touch targets (minimum 44px)
- Provide clear visual feedback during publishing
- Show progress indicators for the build process
- Optimize for both portrait and landscape orientations
- Include smart suggestions for common project IDs

## Testing

The feature includes comprehensive test coverage:

- **Unit Tests**: `tests/Unit/Services/PublishServicePlayCanvasCloudTest.php`
- **Feature Tests**: `tests/Feature/Api/PlayCanvasCloudPublishTest.php`
- **Model Tests**: `tests/Unit/Models/CompanyPlayCanvasCredentialsTest.php`

Run tests with:
```bash
php artisan test tests/Unit/Services/PublishServicePlayCanvasCloudTest.php
php artisan test tests/Feature/Api/PlayCanvasCloudPublishTest.php
php artisan test tests/Unit/Models/CompanyPlayCanvasCredentialsTest.php
```

## Storage Configuration

The PlayCanvas integration uses Laravel's storage system to manage workspace files and build artifacts. You can configure different storage backends for different purposes:

### Configuration Files

- **Workspace Configuration**: `config/workspace.php`
- **Storage Configuration**: `config/filesystems.php`
- **Environment Variables**: `.env`

### Storage Options

1. **Local Storage**: Files stored on the server's filesystem
2. **S3 Storage**: Files stored on Amazon S3 or compatible services
3. **Custom Storage**: Any Laravel-supported storage driver

### Environment Variables

```bash
# Workspace source files storage
WORKSPACE_STORAGE_DISK=local

# Build artifacts storage  
BUILDS_STORAGE_DISK=s3

# Build retention policy (days)
BUILD_RETENTION_DAYS=30

# Temporary directory for builds
WORKSPACE_TEMP_DIR=/tmp
```

### Storage Workflow

1. **Workspace Files**: Stored using `WORKSPACE_STORAGE_DISK`
2. **Build Process**: Downloads workspace files to temp directory if needed
3. **Build Artifacts**: Stored using `BUILDS_STORAGE_DISK` with metadata tracking
4. **Publishing**: Uses stored build artifacts or rebuilds if needed
5. **Cleanup**: Automatic cleanup of old builds based on retention policy

### Build Cleanup

Clean up old build artifacts using the Artisan command:

```bash
# Clean up builds older than configured retention period
php artisan workspace:cleanup-builds

# Dry run to see what would be deleted
php artisan workspace:cleanup-builds --dry-run

# Override retention period
php artisan workspace:cleanup-builds --days=7

# Skip confirmation prompts
php artisan workspace:cleanup-builds --force
```

### Workspace Metadata

Build information is stored in the workspace metadata:

```php
$workspace->metadata = [
    'latest_build_path' => 'builds/company_id/workspace_id/2025-08-05_14-30-15',
    'build_timestamp' => '2025-08-05T14:30:15.000000Z',
    'build_storage_disk' => 's3'
];
```

## Security Considerations

- PlayCanvas API keys are stored encrypted and hidden from serialization
- Credentials are only saved when explicitly requested by the user
- API keys are transmitted securely over HTTPS
- Input validation prevents malicious data injection
- Build artifacts are stored securely using configured storage backends
- Temporary files are automatically cleaned up after operations