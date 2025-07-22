# Future Commands for wp-cli-builtnorth

This document tracks potential commands to add to the wp-cli-builtnorth package.

## 1. Media Commands

### `wp builtnorth media clean`
Clean up unused media and optimize storage:
- Remove unattached/orphaned media files
- Find and remove duplicate images
- Clean up unused image sizes
- Identify broken media references
- Regenerate thumbnails for specific post types

Example usage:
```bash
wp builtnorth media clean --dry-run
wp builtnorth media clean --remove-orphans
wp builtnorth media clean --remove-duplicates
```

## 2. Enhanced Search & Replace

### `wp builtnorth search-replace`
Smarter search and replace with better serialized data handling:
- Preview mode with highlighted changes
- Smart URL replacement (protocol-agnostic)
- Handle Gutenberg block content
- Exclude specific tables/columns
- Better serialized data support

Example usage:
```bash
wp builtnorth search-replace "old.com" "new.com" --smart
wp builtnorth search-replace "old.com" "new.com" --preview
wp builtnorth search-replace "old.com" "new.com" --exclude-tables="wp_logs,wp_sessions"
```

## 3. Permission & Security Commands

### `wp builtnorth fix-permissions`
Fix file and folder permissions:
- Set correct permissions (files: 644, folders: 755)
- Fix ownership issues
- Handle special directories (uploads, cache)
- Report security issues
- Check for world-writable files

Example usage:
```bash
wp builtnorth fix-permissions
wp builtnorth fix-permissions --check-only
wp builtnorth fix-permissions --fix-ownership
```

## 4. Content Audit Commands

### `wp builtnorth audit`
Comprehensive content and site auditing:
- Find broken links (internal and external)
- Identify unused images
- List posts without featured images
- Find duplicate content
- Check for empty categories/tags
- Inactive users report
- Plugin compatibility check
- Theme template usage

Example usage:
```bash
wp builtnorth audit content
wp builtnorth audit users --inactive-days=90
wp builtnorth audit plugins --check-compatibility
wp builtnorth audit media --find-unused
```

## 5. Scaffolding Commands

### `wp builtnorth create`
Quick scaffolding for common patterns:
- Modern Gutenberg blocks with build setup
- Custom post types with full configuration
- ACF field groups from JSON schema
- Page templates with common layouts
- Custom taxonomies with terms

Example usage:
```bash
wp builtnorth create block --name="testimonial" --namespace="custom"
wp builtnorth create cpt --name="portfolio" --with-taxonomy="project-type"
wp builtnorth create acf --from-json="fields.json"
```

## 6. Development Helpers

### Email Management
```bash
# Catch all emails locally
wp builtnorth dev mail --catch-all="dev@test.com"

# Log emails to file instead of sending
wp builtnorth dev mail --log-only

# Preview email templates
wp builtnorth dev mail --preview="password-reset"
```

### URL/Domain Helpers
```bash
# Force HTTPS in development
wp builtnorth dev urls --force-https

# Quick domain switching
wp builtnorth dev urls --temp-domain="mysite.test"

# Add domain aliases for headless
wp builtnorth dev urls --add-alias="localhost:3000"
```

### User Management
```bash
# Create test users with all roles
wp builtnorth dev users --create-set

# Generate magic login link
wp builtnorth dev login --user="admin"

# Bulk password reset
wp builtnorth dev users --reset-all-passwords="password123"
```

### Content Generation
```bash
# Generate themed content
wp builtnorth dev content --type="ecommerce" --count=50
wp builtnorth dev content --type="blog" --with-comments
wp builtnorth dev content --type="portfolio"

# Generate block patterns
wp builtnorth dev blocks --generate-patterns
```

### Database Snapshots
```bash
# Quick snapshots
wp builtnorth dev snapshot --create="before-update"
wp builtnorth dev snapshot --restore="before-update"
wp builtnorth dev snapshot --list

# Anonymize data
wp builtnorth dev anonymize
```

### Debug Helpers
```bash
# Pretty debug output
wp builtnorth dev debug --pretty

# Monitor in real-time
wp builtnorth dev monitor --queries
wp builtnorth dev monitor --hooks

# Performance profiling
wp builtnorth dev profile --slow-queries
```

### Development Mode
```bash
# Toggle individual features
wp builtnorth dev toggle maintenance
wp builtnorth dev toggle caching
wp builtnorth dev toggle debug

# All-in-one dev mode
wp builtnorth dev mode --on
wp builtnorth dev mode --off
```

## 7. Sync Commands

### `wp builtnorth sync`
Selective syncing between environments:
- Sync only media files
- Sync database with exclusions
- Handle large files efficiently
- Exclude sensitive data
- Progress indicators

Example usage:
```bash
wp builtnorth sync media --from=production --to=local
wp builtnorth sync database --from=staging --exclude-users
wp builtnorth sync content --from=production --exclude-orders
```

## 8. Enhanced Maintenance Mode

### `wp builtnorth maintenance`
Advanced maintenance mode features:
- IP whitelist for testing
- Scheduled maintenance windows
- Custom maintenance pages
- API endpoint exclusions
- Notification system

Example usage:
```bash
wp builtnorth maintenance on --exclude-ips="192.168.1.1" --message="Back at 3pm"
wp builtnorth maintenance schedule --start="2pm" --duration="30m"
wp builtnorth maintenance --custom-page="maintenance.html"
```

## 9. Plugin/Theme Inspector

### `wp builtnorth inspect`
Deep inspection of plugins and themes:
- List all hooks/filters used
- Show database tables created
- List all options stored
- Performance impact analysis
- Code quality metrics
- Dependency analysis

Example usage:
```bash
wp builtnorth inspect plugin polaris-forms
wp builtnorth inspect theme compass --show-hooks
wp builtnorth inspect plugin woocommerce --performance
```

## 10. Optimization Commands

### `wp builtnorth optimize`
Quick optimization tasks:
- Image compression in place
- Database optimization
- Autoload option cleanup
- Transient cleanup
- Post revision cleanup
- Spam comment removal

Example usage:
```bash
wp builtnorth optimize images --quality=85
wp builtnorth optimize database
wp builtnorth optimize autoload --threshold=1000
wp builtnorth optimize all
```

## 11. Backup & Restore (Enhanced)

### `wp builtnorth backup`
Enhanced backup functionality:
- Automatic naming and organization
- Include uploads, plugins, themes
- Smart exclusions (logs, cache)
- Backup rotation
- Cloud storage integration
- Incremental backups

Example usage:
```bash
wp builtnorth backup --with-uploads --with-plugins
wp builtnorth backup --exclude-logs --exclude-cache
wp builtnorth backup --rotate=5 --to=s3
```

### `wp builtnorth restore`
Safe restore with validation:
- Version compatibility checks
- Automatic pre-restore backup
- Selective restore options
- URL update handling
- Rollback capability

Example usage:
```bash
wp builtnorth restore --file=backup.tar.gz
wp builtnorth restore --file=prod.sql --update-urls --anonymize
wp builtnorth rollback
```

## 12. Migration Commands

### `wp builtnorth migrate`
Complete migration toolkit:
- Export with all dependencies
- Import with validation
- URL migration
- Server compatibility check
- Progress tracking

Example usage:
```bash
wp builtnorth migrate export --all
wp builtnorth migrate import --file=site-export.zip
wp builtnorth migrate check-compatibility --target=php8.1
```

## Implementation Priority

Based on common use cases, recommended implementation order:

1. **Development Helpers** - Most used during daily development
2. **Media Clean** - Every project needs this
3. **Enhanced Backup/Restore** - Critical for safety
4. **Content Audit** - Great for maintenance
5. **Fix Permissions** - Common pain point
6. **Optimization Commands** - Performance wins

## Notes

- All commands should support `--dry-run` where applicable
- Include progress bars for long-running operations
- Add `--format=json` for scripting integration
- Consider adding hooks for extensibility
- Ensure all commands work within Lando environment