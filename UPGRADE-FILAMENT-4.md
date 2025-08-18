---
title: Filament v4 Upgrade Task List
---

# Filament v4 Upgrade Checklist

-   [ ] Preconditions

    -   [ ] PHP >= 8.2 (project requires ^8.2)
    -   [ ] Laravel >= 11.28 (project on ^12.0)
    -   [ ] Tailwind CSS v4 (already present in `package.json`)

-   [ ] Install upgrade helper and run it

    -   [ ] `composer require filament/upgrade:"~4.0" -W --dev`
    -   [ ] `vendor/bin/filament-v4`
    -   [ ] Run any composer commands suggested by the script

-   [ ] Upgrade core packages

    -   [ ] `composer require filament/filament:"^4.0" -W --no-update`
    -   [ ] `composer update`

-   [ ] Publish and adjust config

    -   [ ] `php artisan vendor:publish --tag=filament-config`
    -   [ ] In `config/filament.php`, set `'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public')` to preserve v3 default if desired.

-   [ ] Tailwind theme check

    -   [ ] If using a custom Filament theme CSS: replace `@config 'tailwind.config.js'` with `@source` entries as per v4 docs.
    -   [ ] Otherwise skip (no custom Filament theme detected).

-   [ ] Breaking changes audit and fixes

    -   [ ] Tables: new default primary key sort. Keep or disable with `Table::configureUsing(fn(Table $t)=>$t->defaultKeySort(false));` (opt-in if needed).
    -   [ ] Tables: filters are deferred by default. Keep or disable with `deferFilters(false)` or global configure (opt-in if needed).
    -   [ ] Tables: `all` pagination option not available by default. Re-enable per-table if needed.
    -   [ ] Authorization: replace any `can*()` overrides on resources with `get*AuthorizationResponse()` equivalents where necessary.
    -   [ ] URL params renamed (e.g., `activeRelationManager` → `relation`). Update if we generate such URLs.
    -   [ ] File visibility defaults to private. If public URLs are required, set visibility for `FileUpload`, `ImageColumn`, `ImageEntry` globally.
    -   [ ] Tenancy: v4 auto-scopes queries. Review and optionally remove redundant manual scopes.

-   [ ] Code updates in repo

    -   [ ] Update `app/Filament/**` resources for any API changes (columns, entries, fields signatues, etc.).
    -   [ ] Replace any deprecated APIs (e.g., `BadgeColumn` aliases) with v4 equivalents if flagged by the upgrade tool.

-   [ ] Run and fix tests

    -   [ ] `php artisan test`
    -   [ ] Address any failures.

-   [ ] Cleanup
    -   [ ] `composer remove filament/upgrade`
    -   [ ] Commit changes

References:

-   Filament v4 Upgrade Guide: https://filamentphp.com/docs/4.x/upgrade-guide
-   What’s new in Filament v4: https://filamentphp.com/content/leandrocfe-whats-new-in-filament-v4
