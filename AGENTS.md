# Repository Guidelines

## Git & Remote Push Policy

- **Always commit and push all code changes** to `origin` (`https://github.com/daominhhai129/top_asia.git`, branch `main`) when finishing work. Never leave completed changes only as local commits.
- **Strictly ignore and never stage, commit, or push**:
  - **Media uploads** (`wp-content/uploads/` and uploaded site media)
  - **Database files and backups** (`*.sql`, `*.sql.gz`, `*.sqlite`, `*.db`, `*.daf`, `dup-installer/`, `installer.php`, `wp-content/aiowps_backups/`, `wp-config.php`)
  - **3rd-party plugins, parent theme, and WordPress core** (`wp-content/plugins/*` except explicitly tracked custom plugins, `wp-content/themes/flatsome/`, `wp-admin/`, `wp-includes/`, and root WordPress core files)
- **Tracked scope**: `.gitignore` ignores everything (`*`) by default and whitelists only custom project files (`.gitignore`, `AGENTS.md`, `wp-content/themes/giao-dien/`, `wp-content/plugins/ocean-contact-buttons/`, `wp-content/novamira-sandbox/`). Do not force-add (`git add -f`) ignored directories or add 3rd-party plugins without explicit user approval.
- **Pre-push check**: Before running `git push`, inspect `git status` and `git diff --name-status origin/main..HEAD` to confirm only intended custom code changes are included.
