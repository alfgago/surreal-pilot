# SurrealPilot Task Tracker

Keep this file updated as features are implemented.

## Done

-   [x] Plan capabilities: `allow_unreal`, `allow_multiplayer`, `allow_advanced_publish`, `allow_byo_keys`
-   [x] Seeded plans: Starter ($5/10k, Web & Mobile only), Pro ($25/60k, multiplayer), Studio ($50/120k, BYO keys + advanced publish)
-   [x] HMAC middleware for engine payloads
-   [x] SSE streaming chat + credit deduction and rate limits
-   [x] BYO provider keys (Studio): encrypted storage + Provider Settings page
-   [x] Billing dashboard + checkout stubs (subscription and top-up)
-   [x] Marketing pages, shared header, brand colors
-   [x] Desktop chat: PlayCanvas preview, MCP command helper, dry-run validator, workspaces list
-   [x] Windows HOME resolution in `LocalConfigManager`

## In Progress

-   [ ] README overhaul (architecture, plans, flows)
-   [x] Header account links: tenant-aware routes wired

## Next

-   [x] Shared workspace sidebar include (engine filter wired)
-   [x] Template picker modal with metadata and previews (desktop)
-   [ ] Deterministic Undo by `patch_id` (persist diffs/etag and call MCP undo)
    -   Backend groundwork added: MCP undo endpoint integration for PlayCanvas and patch persistence
-   [ ] Stripe webhooks: subscription and top-up crediting (test mode)
    -   [ ] Run test-mode end-to-end: checkout → webhook → credits/billing history
-   [ ] Upgrade CTAs across UI for gated features
    -   [x] Desktop chat CTAs for Multiplayer (Pro) and Advanced Publish (Studio)
-   [ ] Sentry wiring (Laravel + UE plugin)
-   [ ] Docs: UE plugin + Web & Mobile workflows, Admin guides, API docs

## Later

-   [ ] Multiplayer helpers (Pro/Studio)
-   [ ] Advanced publish helpers (Studio): Steam/iOS/Android
-   [ ] PWA polish
