# BuiltNorth WP-CLI Commands

A modular WP-CLI package for BuiltNorth WordPress development workflows.

## Installation

```bash
wp package install builtnorth/wp-cli-builtnorth
```

## Commands

### Setup (Standard)
Standard WordPress project setup:

```bash
wp builtnorth setup --name="My Project" --email="admin@example.com" --password="secure123"
```

This command provides:
- **WordPress installation** with database management
- **Theme handling** - Installs from composer, setup/data/themes, or default WordPress themes
- **Plugin activation** - Activates all available plugins
- **Content import** - Imports from setup/data/content/*.xml
- **Media import** - Imports with automatic logo/icon detection
- **Settings configuration** - Timezone, permalinks, upload settings
- **Page setup** - Configures home and blog pages if found

Options:
- `--name=<name>` - Project name (required)
- `--username=<username>` - WordPress admin username (default: admin)
- `--password=<password>` - WordPress admin password (required)
- `--email=<email>` - WordPress admin email (required)

### Setup BN Project
BuiltNorth-specific project setup with Compass theme:

```bash
wp builtnorth setup-bn-project --name="My Project" --email="admin@example.com" --password="secure123"
```

This enhanced setup includes everything from standard setup plus:
- **Compass theme conversion** - Automatically converts the Compass theme to a project-specific theme
- **Theme customization** - Renames theme to match project name, removes git references
- **Dependency management** - Removes compass from composer.json after conversion
- **NPM workspace integration** - Adds the new theme to npm workspaces and updates build scripts
- **Advanced configuration** - Additional BuiltNorth-specific optimizations

Options:
- `--name=<name>` - Project name (required)
- `--username=<username>` - WordPress admin username (default: admin)
- `--password=<password>` - WordPress admin password (required)
- `--email=<email>` - WordPress admin email (required)
- `--skip-wordpress` - Skip WordPress installation
- `--yes` - Skip confirmation prompts

### Configure
Configure WordPress after installation:

```bash
wp builtnorth configure
```

Options:
- `--skip-content` - Skip content import
- `--skip-media` - Skip media import
- `--url=<url>` - Override site URL for search-replace
- `--yes` - Skip confirmation prompts

### Post Type Switch
Convert posts from one post type to another:

```bash
# Basic conversion
wp builtnorth post-type-switch --from=post --to=article

# Preview changes without making them
wp builtnorth post-type-switch --from=post --to=news --dry-run

# Convert only published posts
wp builtnorth post-type-switch --from=post --to=resource --status=publish

# Convert with taxonomy handling
wp builtnorth post-type-switch --from=post --to=portfolio --include-taxonomies
```

Features:
- **Safe conversion** with validation and confirmation prompts
- **Dry run mode** to preview changes
- **Taxonomy handling** - preserves shared taxonomies, removes unsupported ones
- **Status filtering** - convert only posts with specific status
- **Batch limiting** - test with a limited number of posts first
- **Automatic cleanup** - flushes rewrite rules and clears caches

Options:
- `--from=<post-type>` - Source post type (required)
- `--to=<post-type>` - Target post type (required)
- `--status=<status>` - Only convert posts with this status (default: any)
- `--limit=<number>` - Limit number of posts to convert
- `--dry-run` - Preview changes without making them
- `--include-taxonomies` - Handle taxonomy mappings between post types
- `--yes` - Skip confirmation prompt

### Generate
Generate test content for development:

```bash
wp builtnorth generate --count=20 --post-type=post
```

Options:
- `--count=<count>` - Number of posts to generate (default: 10)
- `--post-type=<post-type>` - Post type to generate (default: post)
- `--taxonomy=<taxonomy>` - Taxonomy to assign terms from
- `--terms=<terms>` - Comma-separated list of term slugs to create/assign
- `--random-terms` - Randomly assign terms to posts
- `--with-content` - Generate content for posts

## Adding New Commands

1. Create a new command class in `src/Commands/` extending `BaseCommand`
2. Register it in `command.php`
3. Use traits from `src/Traits/` for common functionality

Example:
```php
namespace BuiltNorth\CLI\Commands;

use BuiltNorth\CLI\BaseCommand;

class ExportCommand extends BaseCommand {
    protected $command_name = 'export';
    
    protected function get_shortdesc() {
        return 'Export site data';
    }
    
    public function __invoke($args, $assoc_args) {
        // Implementation
    }
}
```

## Architecture

- **BaseCommand** - Common functionality for all commands
- **Traits** - Reusable functionality (FileOperations, etc.)
- **Utils** - Helper classes
- **Commands** - Individual command implementations

This modular structure allows easy addition of new commands like import/export, migration tools, and more.