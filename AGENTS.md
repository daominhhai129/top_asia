# Repository Guidelines

## Local-to-Staging & GitHub Push Policy

- **Immediate dual-sync after every completed change on local**: Whenever a task/change worked on the local computer is finished and verified, **immediately deploy/push the changes to the staging site (`https://topasia.vn/staging/`) AND commit/push to GitHub (`origin`, branch `main`)**. Never leave completed changes only on local or only on one target.
- **Order & verification**:
  1. Run pre-push checks (`git status`, `git diff --name-status origin/main..HEAD`, syntax/UI verification) and commit/push to `origin` (`https://github.com/daominhhai129/top_asia.git`, branch `main`).
  2. Immediately trigger/execute the staging sync (`https://topasia.vn/staging/`) via the configured deployment channel (e.g., staging Git pull / cPanel deploy / SSH / FTP / Novamira MCP) and verify staging reflects the update.
  3. If no staging deployment channel or credentials are configured/accessible from the local environment, never pretend staging was updated — report clearly that GitHub (`origin/main`) was pushed and state that staging deployment is blocked/pending manual pull or staging connection setup.

## Git & Remote Push Policy

- **Always commit and push all code changes** to `origin` (`https://github.com/daominhhai129/top_asia.git`, branch `main`) when finishing work. Never leave completed changes only as local commits.
- **Strictly ignore and never stage, commit, or push**:
  - **Media uploads** (`wp-content/uploads/` and uploaded site media)
  - **Database files and backups** (`*.sql`, `*.sql.gz`, `*.sqlite`, `*.db`, `*.daf`, `dup-installer/`, `installer.php`, `wp-content/aiowps_backups/`, `wp-config.php`)
  - **3rd-party plugins, parent theme, and WordPress core** (`wp-content/plugins/*` except explicitly tracked custom plugins, `wp-content/themes/flatsome/`, `wp-admin/`, `wp-includes/`, and root WordPress core files)
- **Tracked scope**: `.gitignore` ignores everything (`*`) by default and whitelists only custom project files (`.gitignore`, `AGENTS.md`, `wp-content/themes/giao-dien/`, `wp-content/plugins/ocean-contact-buttons/`, `wp-content/novamira-sandbox/`). Do not force-add (`git add -f`) ignored directories or add 3rd-party plugins without explicit user approval.
- **Pre-push check**: Before running `git push`, inspect `git status` and `git diff --name-status origin/main..HEAD` to confirm only intended custom code changes are included.
