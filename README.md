# BuiltNorth WP-CLI Commands

A modular WP-CLI package for BuiltNorth WordPress development workflows.

## Installation

```bash
wp package install builtnorth/wp-cli-builtnorth
```

## Commands

### Setup
Initialize a new BuiltNorth project:

```bash
wp builtnorth setup --name="My Project"
```

Options:
- `--name=<name>` - Project name
- `--username=<username>` - WordPress admin username
- `--password=<password>` - WordPress admin password
- `--email=<email>` - WordPress admin email
- `--skip-wordpress` - Skip WordPress installation

### Configure
Configure WordPress after installation:

```bash
wp builtnorth configure
```

Options:
- `--skip-content` - Skip content import
- `--skip-media` - Skip media import
- `--url=<url>` - Override site URL for search-replace

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