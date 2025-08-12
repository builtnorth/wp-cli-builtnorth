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

## 13. Block Management Commands

### `wp builtnorth blocks inventory`
Comprehensive block usage analysis:

```bash
# Basic inventory
wp builtnorth blocks inventory

# Output:
# Block Registry Analysis
# =====================
# Total registered blocks: 147
# Core blocks: 89
# Custom blocks: 23
# Plugin blocks: 35 (ACF: 12, WooCommerce: 15, Others: 8)
# 
# Usage Statistics:
# core/paragraph ............. 3,456 instances (45.2%)
# core/heading ............... 1,234 instances (16.1%)
# core/image ................. 890 instances (11.6%)
# custom/hero ................ 45 instances (0.6%)
# acf/testimonial ............ 12 instances (0.2%)
# 
# Unused Registered Blocks (34):
# - custom/old-hero (registered but never used)
# - core/verse (available but never used)

# Find specific block usage
wp builtnorth blocks inventory --filter="custom/*"

# Export detailed report
wp builtnorth blocks inventory --format=csv --include-meta

# Performance impact analysis
wp builtnorth blocks inventory --analyze-performance
```

### `wp builtnorth blocks convert`
Migrate between block types or from classic content:

```bash
# Classic to blocks conversion
wp builtnorth blocks convert classic-to-blocks --post-type=post --dry-run

# Block type conversion
wp builtnorth blocks convert --from="core/gallery" --to="custom/gallery"
# Maps attributes intelligently

# ACF to native blocks
wp builtnorth blocks convert acf-to-native --block="testimonial"
# Generates block registration code and converts instances
```

## 14. Multi-site Management

### `wp builtnorth multisite clone`
Duplicate sites within a multisite network:

```bash
# Full site clone
wp builtnorth multisite clone --from=2 --to-slug="new-region" --to-title="New Region Site"

# What it does:
# 1. Creates new site in network
# 2. Copies all database tables
# 3. Updates URLs in content
# 4. Copies uploads/2 to uploads/5
# 5. Maintains user permissions
# 6. Updates site-specific options

# Selective cloning
wp builtnorth multisite clone --from=2 --to-slug="test-site" \
  --skip-users \
  --skip-media \
  --skip-comments \
  --content-after="2024-01-01"

# Template-based cloning
wp builtnorth multisite clone --template="store" --to-slug="store-west"
```

### `wp builtnorth multisite sync`
Synchronize settings/content across network sites:

```bash
# Sync all settings from main site
wp builtnorth multisite sync settings --source=1 --targets=all

# Selective sync
wp builtnorth multisite sync settings --source=1 --targets=2,3,4 \
  --include="timezone_string,date_format,time_format"

# Plugin activation sync
wp builtnorth multisite sync plugins --source=1

# Content sync
wp builtnorth multisite sync content --source=1 --post-type=page \
  --strategy=update

# Menu sync
wp builtnorth multisite sync menus --source=1 --menu="main-navigation"
```

## 15. Performance Analysis (Enhanced)

### `wp builtnorth performance analyze`
Deep performance profiling and bottleneck identification:

```bash
# Comprehensive analysis
wp builtnorth performance analyze

# Output:
# WordPress Performance Analysis
# =============================
# 
# Database Performance:
# - Total queries: 245
# - Slow queries (>0.05s): 12
# - Duplicate queries: 34
# 
# Memory Usage:
# - Peak memory: 45.3 MB
# - Largest consumers:
#   1. Plugin: woocommerce (12.3 MB)
#   2. Theme: compass (5.2 MB)
# 
# Hook Performance:
# - Total actions: 1,234
# - Slowest hooks:
#   1. init: 0.34s (45 callbacks)
#   2. wp: 0.23s (23 callbacks)
# 
# Autoloaded Data:
# - Total size: 2.3 MB (WARNING: should be <1MB)

# Focus on specific area
wp builtnorth performance analyze --focus=plugins

# Monitor specific page
wp builtnorth performance analyze --url="/shop" --requests=10
```

### `wp builtnorth performance optimize`
Automated performance improvements:

```bash
# Interactive optimization wizard
wp builtnorth performance optimize --interactive

# Auto mode (safe optimizations only)
wp builtnorth performance optimize --auto --safe

# Aggressive mode
wp builtnorth performance optimize --auto --aggressive
# Includes:
# - Disable unused plugins
# - Remove all revisions
# - Clear all caches
# - Optimize all images
```

## 16. Code Quality & Standards

### `wp builtnorth code scan`
Comprehensive code analysis for quality and security:

```bash
# Full scan
wp builtnorth code scan --path=wp-content/themes/compass

# Output:
# Code Quality Report
# ==================
# 
# WordPress Coding Standards:
# - Errors: 45
# - Warnings: 234
# 
# Security Issues:
# - High: 2 (SQL injection, XSS)
# - Medium: 5 (Missing nonces)
# - Low: 12 (Output escaping)
# 
# Deprecated Functions:
# - get_currentuserinfo() → wp_get_current_user() (3 instances)

# Security focused scan
wp builtnorth code scan --security --path=wp-content/plugins/custom

# Generate report
wp builtnorth code scan --path=. --format=html --output=report.html
```

### `wp builtnorth code fix`
Automated code fixes and updates:

```bash
# Fix coding standards
wp builtnorth code fix --path=wp-content/themes/compass --standard=WordPress

# Update deprecated functions
wp builtnorth code fix --update-deprecated --backup

# Security fixes (interactive)
wp builtnorth code fix --security --interactive
```

## 17. Advanced Migration Tools (Enhanced)

### `wp builtnorth migrate prepare`
Prepare site for migration with environment-specific adjustments:

```bash
# Prepare for production
wp builtnorth migrate prepare --target=production

# What it does:
# 1. Deactivate dev plugins
# 2. Optimize database
# 3. Update configurations
# 4. Security hardening
# 5. Create migration package

# Custom profiles
wp builtnorth migrate prepare --profile=staging
# Uses .migrate-profiles/staging.yml
```

### `wp builtnorth migrate diff`
Compare environments to ensure consistency:

```bash
# Compare local to staging
wp builtnorth migrate diff --source=local --target=staging

# Output:
# - Database differences
# - Plugin version mismatches
# - Theme differences
# - Configuration variations
# - Content statistics

# Generate sync script
wp builtnorth migrate diff --source=local --target=staging --generate-sync
```

## 18. Team Collaboration

### `wp builtnorth team share`
Share development environment state:

```bash
# Create shareable snapshot
wp builtnorth team share --create

# What it includes:
# 1. Database export (anonymized option)
# 2. Uploads sample
# 3. Theme/plugin list with versions
# 4. Configuration snapshot
# 5. Content statistics

# Create with options
wp builtnorth team share --create \
  --anonymize \
  --include-uploads=100 \
  --expire="7d" \
  --password="secret"

# Apply shared state
wp builtnorth team share --apply=https://share.url/package.zip
```

### `wp builtnorth team handoff`
Generate comprehensive project documentation:

```bash
# Generate handoff docs
wp builtnorth team handoff --format=markdown

# Creates documentation with:
# - Architecture overview
# - Custom post types/taxonomies
# - Database structure
# - API endpoints
# - Cron jobs
# - Development workflow
# - Known issues
# - Key customizations

# Interactive mode
wp builtnorth team handoff --interactive
# Adds context through Q&A
```

## 19. REST API Helpers

### `wp builtnorth api test`
Comprehensive API testing and documentation:

```bash
# Test all endpoints
wp builtnorth api test --all

# Output:
# - Endpoints tested: 67
# - Passed/Failed
# - Performance metrics
# - Security issues

# Test with authentication
wp builtnorth api test --endpoint="/custom/v1/orders" \
  --auth=application-password \
  --user=admin

# Load testing
wp builtnorth api test --endpoint="/wp/v2/posts" \
  --concurrent=10 \
  --requests=100
```

### `wp builtnorth api mock`
Create mock API server for development:

```bash
# Generate mock data
wp builtnorth api mock --endpoint="/custom/v1/products" --count=50

# Start mock server
wp builtnorth api mock --serve --port=3000
# Mirrors WP REST API with mock data

# Export for Postman/Insomnia
wp builtnorth api generate --format=postman
```

## 20. Workflow Automation

### `wp builtnorth workflow create`
Create reusable task sequences:

```bash
# Create workflow interactively
wp builtnorth workflow create "daily-maintenance"

# Wizard:
# 1. Select tasks
# 2. Configure each task
# 3. Set schedule

# Run manually
wp builtnorth workflow run daily-maintenance

# List workflows
wp builtnorth workflow list

# Complex workflow example
wp builtnorth workflow create "deployment"
# tasks:
#   - migrate prepare --target=production
#   - performance optimize --auto
#   - code scan --security
#   - backup create --full
```

## Implementation Priority

Based on common use cases, recommended implementation order:

1. **Development Helpers** - Most used during daily development
2. **Performance Analysis/Optimize** - Universal need for optimization
3. **Block Management** - Critical for modern WordPress
4. **Team Collaboration** - Improves workflow efficiency
5. **Code Quality** - Maintains standards automatically
6. **Migration Tools** - Essential for deployments
7. **Media Clean** - Every project needs this
8. **Enhanced Backup/Restore** - Critical for safety
9. **Content Audit** - Great for maintenance
10. **Fix Permissions** - Common pain point

## Notes

- All commands should support `--dry-run` where applicable
- Include progress bars for long-running operations
- Add `--format=json` for scripting integration
- Consider adding hooks for extensibility
- Ensure all commands work within Lando environment
- Commands should be composable (output of one can be input to another)
- Include `--quiet` and `--verbose` modes
- Support for `.builtnorthrc` configuration files