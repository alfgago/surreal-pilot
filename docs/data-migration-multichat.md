# Multi-Chat Data Migration Documentation

## Overview

This document describes the data migration process for implementing multi-chat functionality in SurrealPilot. The migration ensures that existing workspaces are properly updated to support multiple chat conversations while preserving all existing data.

## Migration Components

### 1. Migration Command

**Command:** `php artisan migrate:workspaces-to-multichat`

**Purpose:** Migrates existing workspaces to support multi-chat functionality by creating default conversations and migrating existing chat history.

**Options:**
- `--dry-run`: Run migration in dry-run mode without making changes
- `--force`: Force migration even if conversations already exist

### 2. Validation Command

**Command:** `php artisan validate:multichat-data-integrity`

**Purpose:** Validates data integrity after migration to ensure all relationships are correct.

## Migration Process

### Step 1: Pre-Migration Validation

Before running the migration, ensure:

1. Database backup is created
2. All existing workspaces are in a stable state
3. No active chat sessions are running

### Step 2: Dry-Run Migration

```bash
php artisan migrate:workspaces-to-multichat --dry-run
```

This will show what changes would be made without actually modifying the database.

### Step 3: Execute Migration

```bash
php artisan migrate:workspaces-to-multichat
```

### Step 4: Post-Migration Validation

```bash
php artisan validate:multichat-data-integrity
```

## What the Migration Does

### 1. Creates Default Conversations

For each existing workspace that doesn't have conversations:
- Creates a "Default Chat" conversation
- Sets the conversation creation date to match the workspace creation date
- Adds a descriptive message indicating it was created during migration

### 2. Migrates Chat History

If existing patches contain chat history:
- Extracts messages from patch envelope JSON
- Creates ChatMessage records for each message
- Preserves original timestamps and metadata
- Links messages to the default conversation

### 3. Updates Game References

For existing games without conversation references:
- Associates games with the default conversation
- Preserves all existing game metadata

### 4. Data Integrity Validation

Ensures:
- All workspaces have at least one conversation
- All conversations belong to valid workspaces
- All chat messages belong to valid conversations
- All games with conversation_id reference valid conversations

## Database Schema Changes

The migration works with the following new tables (already created by previous migrations):

### chat_conversations
```sql
CREATE TABLE chat_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    INDEX idx_workspace_conversations (workspace_id, updated_at DESC)
);
```

### chat_messages
```sql
CREATE TABLE chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content LONGTEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_messages (conversation_id, created_at ASC)
);
```

### games (updated)
```sql
ALTER TABLE games ADD COLUMN conversation_id BIGINT UNSIGNED NULL;
ALTER TABLE games ADD FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE SET NULL;
```

## Rollback Strategy

If rollback is needed:

1. **Backup Current State:** Create a database backup before rollback
2. **Remove New Data:** Delete migrated conversations and messages
3. **Reset Game References:** Set conversation_id to NULL for all games
4. **Validate:** Ensure original workspace data is intact

### Rollback Commands

```sql
-- Remove migrated conversations and messages
DELETE FROM chat_messages WHERE conversation_id IN (
    SELECT id FROM chat_conversations WHERE description LIKE '%migration%'
);

DELETE FROM chat_conversations WHERE description LIKE '%migration%';

-- Reset game conversation references
UPDATE games SET conversation_id = NULL;
```

## Testing

### Automated Tests

The migration includes comprehensive tests in `tests/Feature/WorkspaceMultiChatMigrationTest.php`:

- ✅ Creates default conversations for existing workspaces
- ✅ Preserves existing workspace data
- ✅ Migrates chat history from patches
- ✅ Updates existing games with conversation references
- ✅ Validates data integrity
- ✅ Supports dry-run mode
- ✅ Skips workspaces with existing conversations
- ✅ Supports force flag for re-migration
- ✅ Handles empty database gracefully

### Manual Testing

1. **Before Migration:**
   ```bash
   # Check current state
   php artisan validate:multichat-data-integrity
   ```

2. **Dry-Run Test:**
   ```bash
   php artisan migrate:workspaces-to-multichat --dry-run
   ```

3. **Execute Migration:**
   ```bash
   php artisan migrate:workspaces-to-multichat
   ```

4. **Validate Results:**
   ```bash
   php artisan validate:multichat-data-integrity
   ```

## Troubleshooting

### Common Issues

1. **Migration Fails with Constraint Errors**
   - Ensure all foreign key relationships are properly defined
   - Check that workspace IDs exist and are valid

2. **Orphaned Data After Migration**
   - Run the validation command to identify issues
   - Use the rollback strategy if necessary

3. **Performance Issues with Large Datasets**
   - Consider running migration during low-traffic periods
   - Monitor database performance during migration

### Error Recovery

If the migration fails partway through:

1. Check the error logs for specific issues
2. Run the validation command to assess current state
3. Fix any data inconsistencies
4. Re-run the migration with `--force` flag if needed

## Requirements Compliance

This migration satisfies the following requirements:

- **11.1:** Existing users can access their previous workspaces
- **11.2:** Existing workspace data is preserved and properly migrated
- **11.3:** Existing chat history is maintained and accessible

## Post-Migration Verification

After successful migration, verify:

1. All workspaces have default conversations
2. Existing workspace metadata is preserved
3. Any existing chat history is properly migrated
4. Games are associated with conversations
5. All database relationships are intact
6. Application functionality works as expected

## Support

For issues with the migration process:

1. Check the application logs for detailed error messages
2. Run the validation command to identify specific problems
3. Review the test suite for expected behavior
4. Consult the rollback strategy if recovery is needed