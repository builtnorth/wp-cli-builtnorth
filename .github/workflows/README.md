# Workflows

Each workflow calls a reusable workflow from `builtnorth/.github`, so the
pipeline itself lives there and this package only passes its own settings.

| Workflow | Runs on | Calls |
|---|---|---|
| `tests.yml` | pull requests to `main`, manual dispatch | `composer-package-ci.yml` |
| `scheduled-ci.yml` | every Monday at 14:00 UTC, manual dispatch | `composer-package-ci.yml` |
| `release.yml` | a `v*.*.*` tag push, manual dispatch with a version | `composer-package-release.yml` |

## Releasing

Run **Release** from the Actions tab with the version to release (for
example `1.2.0`, without the `v`), or push a `v1.2.0` tag. The reusable
workflow tags the release, generates the changelog from Conventional
Commit subjects and publishes the GitHub release for `builtnorth/wp-cli-builtnorth`.

Commit types decide the changelog section and the suggested version bump:
`feat` is a minor release, a `!` after the type or a `BREAKING CHANGE:`
footer is a major release, and everything else is a patch.

## Settings passed to the release workflow

| Input | Value |
|---|---|
| `package-name` | `wp-cli-builtnorth` |
| `package-display-name` | `WP CLI Built North` |
| `composer-namespace` | `builtnorth/wp-cli-builtnorth` |
| `composer-repository-profile` | `coding-standards-only` |

Secrets are inherited from the organisation.
