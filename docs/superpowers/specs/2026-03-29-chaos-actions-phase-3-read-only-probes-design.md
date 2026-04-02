# ChaosDonkey Phase 3 Design: Read-Only Probe Actions

Date: 2026-03-29
Scope: Add Magento-aligned read-only probe actions to the existing kick flow

## Status
- Design approved in brainstorming session (implementation not started)

## Goal
Expand ChaosDonkey with read-only operational probes that improve operator visibility while preserving the existing `chaosdonkey:kick` orchestration model and Magento best practices.

## Confirmed Product Decisions
- Phase focus: action expansion (Phase 3)
- Action family: read-only stress probes
- Initial scope: 3 probes
- Primary success criterion: operator visibility
- Trigger model: via existing kick rolls (not a separate probe command)
- Probe types:
  - indexer status snapshot
  - cache backend health snapshot
  - queue/cron health snapshot
- Cron behavior: probes are eligible in both cron and CLI kick execution
- Roll mapping strategy: replace existing non-action (`napping`) slots with probe outcomes
- Output depth: summary + top details
- Config controls: per-probe admin enable toggles
- Architectural direction: probe abstraction layer (Magento best-practice oriented)

## Deterministic Roll Mapping (Phase 3)
To keep planning and tests deterministic, Phase 3 assigns fixed D20 values:

| Roll | Outcome code |
|------|--------------|
| `5`  | `indexer_status_snapshot` |
| `6`  | `cache_backend_health_snapshot` |
| `7`  | `cron_queue_health_snapshot` |

All existing mappings for `1`, `2`, `3`, `4`, and `20` remain unchanged.

These three values are selected from current non-action slots to satisfy the “replace napping slots” decision.

## Architecture
Phase 3 extends the current action framework by introducing a small probe abstraction while keeping probes as first-class kick outcomes.

Core principles:
- Keep command entrypoints thin and orchestration centralized in `Model/KickExecutor`.
- Keep probe behaviors isolated in single-purpose action classes.
- Preserve the existing DI action pool pattern and config-driven gating.
- Reuse existing reroll/fallback semantics for disabled actions.

Planned shape:
- Existing `ChaosActionInterface` remains the execution contract used by the action pool.
- No additional probe-specific interface in Phase 3 (YAGNI).
- Probe actions implement only `ChaosActionInterface` and reuse shared probe helper services.
- Add shared probe output formatting service for consistent “summary + top details” output.

This balances immediate delivery with maintainability if probe count grows in later phases.

## Components and Responsibilities

### New interfaces/services
- `Model/Probe/ProbeOutputFormatter.php`
  - Shared formatter for concise operator output.
  - Public API:
    - `formatSummary(ProbeSnapshot $snapshot): string`
    - `formatTopDetails(ProbeSnapshot $snapshot, int $limit = 5): array<string>`
    - `formatLines(ProbeSnapshot $snapshot, int $limit = 5): array<string>`
  - Ownership rule:
    - `ProbeSnapshot.summary` is the canonical human-readable summary content.
    - `formatSummary()` only applies canonical envelope/prefix; it does not invent alternate summaries.
  - Deterministic top-details ordering rule:
    - sort by severity (`warn` > `unavailable` > `unknown` > `ok`)
    - then by `subsystem` ASC
    - then by `item` ASC
    - then take first `5`
    - notable-row guarantee:
      - include non-`ok` rows before any `ok` rows
      - include `ok` rows only when cap remains
  - Fixed-order override rule:
    - probes may declare fixed-order detail output; when set, formatter preserves provided order and skips severity sorting.
  - Produces a stable shape:
    - summary headline
    - bounded top details (warnings, notable counters/items)
  - Canonical line formats:
    - summary: `Probe[<probe_code>] status=<status> msg="<summary>"`
    - detail: `ProbeDetail[<probe_code>] subsystem=<subsystem> item=<item> status=<status> value="<value>"`

- `Model/Probe/ClockInterface.php`
  - Method:
    - `nowUtc(): \DateTimeImmutable`
  - Scope:
    - single time source for cron/queue lookback calculations and tests.

- `Model/Probe/SystemClock.php`
  - Default implementation of `ClockInterface` for production runtime.
  - Returns UTC `DateTimeImmutable` values.

- `Model/Probe/ProbeSnapshot.php`
  - Lightweight DTO/value object for probe results.
  - Fields:
    - `probeCode` (string)
    - `status` (`ok|warn|unknown|unavailable`)
    - `summary` (string)
    - `details` (array of `ProbeDetailRow`)

- `Model/Probe/ProbeDetailRow.php`
  - Common normalized detail row shape shared by all probes.
  - Fields:
    - `subsystem` (string)
    - `item` (string)
    - `value` (string)
    - `status` (`ok|warn|unknown|unavailable`)

### New actions
- `Action/IndexerStatusSnapshot.php`
  - Read-only snapshot of indexer mode/state indicators.
  - Required summary fields:
    - total indexer count
    - invalid/reindex-required count
    - scheduled vs realtime count (if available)
  - Detail rows use common `ProbeDetailRow` fields:
    - `subsystem=indexer`, `item=<indexer_id>`, `value=state=<state>;mode=<mode|unavailable>`, `status=<mapped status>`
  - Data sources:
    - `Magento\Indexer\Model\IndexerRegistry` (+ indexer state APIs)
    - `Magento\Indexer\Model\Indexer\CollectionFactory` (deterministic full indexer enumeration)
  - Status rules:
    - row status `warn` when indexer state indicates invalid/reindex required (takes precedence)
    - row status `unknown` when mode is unavailable but state is otherwise readable
    - row status `ok` otherwise
    - probe status `unknown` if indexer enumeration/state read fails
    - probe status `warn` when invalid/reindex-required count > 0 (takes precedence over unknown mode rows)
    - probe status `unknown` when invalid count is 0 and one or more mode values are unavailable
    - probe status `ok` when invalid count is 0 and all mode values are readable
  - Mode fallback rule:
    - if mode cannot be resolved for any indexer, emit `modes=unavailable` in summary and mark affected rows `status=unknown`.
  - Canonical summary template:
    - normal: `<total> indexers, <invalid> need reindex, modes: schedule=<scheduled>, realtime=<realtime>`
    - mode-unavailable: `<total> indexers, <invalid> need reindex, modes=unavailable`
  - Canonical detail value template:
    - `state=<state>;mode=<mode|unavailable>`

- `Action/CacheBackendHealthSnapshot.php`
  - Read-only snapshot of cache type/backend health signals.
  - Required summary fields:
    - total cache type count
    - enabled cache type count
    - backend adapter resolution status
  - Detail rows use common `ProbeDetailRow` fields:
    - one row per cache type: `subsystem=cache`, `item=<cache_type>`, `value=enabled=<true|false>`, `status=ok`
    - one backend row: `subsystem=cache_backend`, `item=default_frontend`, `value=<backend class basename>`, `status=ok|warn|unknown`
  - Data sources:
    - `Magento\Framework\App\Cache\TypeListInterface`
    - `Magento\Framework\App\Cache\Frontend\Pool` (exact required DI boundary for backend resolution)
  - Default frontend identification rule:
    - resolve frontend id `default` only
    - if `default` frontend is not resolvable, backend status becomes `unknown`
  - Read-only constraint for backend checks:
    - metadata/instance-resolution only (no writes, no flushes, no mutation methods)
  - Backend resolution-failure definition:
    - `default` frontend exists but resolving backend class basename throws exception/throwable
  - Status rules:
    - `ok`: cache types are discoverable and default frontend backend resolves without exception
    - `warn`: cache types are discoverable, `default` frontend exists, but backend resolution fails
    - `unknown`: cache metadata cannot be read safely OR `default` frontend cannot be resolved
  - Canonical summary template:
    - ok: `<total> cache types, <enabled> enabled, backend adapter=<adapter_label>`
    - warn: `<total> cache types, <enabled> enabled, backend adapter resolution degraded`
    - unknown: `cache snapshot unavailable`
  - Canonical detail value templates:
    - cache row: `enabled=<true|false>`
    - backend row: `<adapter_label|resolution_failed|unavailable>`
  - Adapter label safety rule:
    - use sanitized backend class basename label only (`[a-z0-9_]+`), never hostnames/connection strings.

- `Action/CronQueueHealthSnapshot.php`
  - Read-only snapshot for cron/queue health indicators available within module constraints.
  - Scope boundary: one probe class with two clearly separated collectors internally:
    - cron collector (required)
    - queue collector (optional when queue signals are available)
  - Required summary fields:
    - cron status headline (healthy/degraded/unknown)
    - queue status headline (healthy/degraded/unknown/unavailable)
  - Detail row granularity (deterministic):
    - `subsystem=cron`, `item=failures_last_60m`, `value=<count>`, `status=ok|warn`
    - `subsystem=cron`, `item=pending_older_15m`, `value=<count>`, `status=ok|warn`
    - `subsystem=queue`, `item=tables_present`, `value=true|false`, `status=ok|unavailable`
    - `subsystem=queue`, `item=activity_last_60m`, `value=<count|n/a>`, `status=ok|warn|unavailable|unknown`
  - Emission rule:
    - always emit all four detail rows in the fixed order above (never omit rows)
  - Data sources:
    - cron: `cron_schedule` via `Magento\Framework\App\ResourceConnection`
    - queue (optional): tables `queue`, `queue_message`, `queue_message_status` via `ResourceConnection` when present
  - Partial-table classification rule:
    - if any required queue table is missing, classify queue as `unavailable` (`tables_present=false`)
  - Status rules:
    - cron `ok`: no warning indicators in lookback window
    - cron `warn`: backlog/failure indicators exceed threshold
    - cron `unknown`: cron data source unavailable
    - queue `unavailable`: queue tables absent in current installation
    - queue `warn`: queue tables present, zero queue activity in lookback, and cron is already in warn state
    - queue `ok`: queue tables present and queryable
    - queue `unknown`: queue tables present but query failure/exception occurs
  - Overall probe status precedence (deterministic):
    - if cron is `warn` OR queue is `warn` => overall `warn`
    - else if cron is `unknown` OR queue is `unknown` => overall `unknown`
    - else if queue is `unavailable` and cron is `ok` => overall `ok` (with queue-unavailable detail row)
    - else => overall `ok`
  - Headline mapping (deterministic):
    - `ok -> healthy`
    - `warn -> degraded`
    - `unknown -> unknown`
    - `unavailable -> unavailable`
  - Threshold defaults for v1 (config-free):
    - lookback window: 60 minutes
    - cron warning indicator A: `count(*)` where `status in ('error','missed')` and `scheduled_at >= now-60m` is `> 0`
    - cron warning indicator B: `count(*)` where `status='pending'` and `scheduled_at < now-15m` is `> 10`
    - queue activity signal: `count(*)` from `queue_message_status` where `updated_at >= now-60m`
  - Canonical summary template:
    - `cron=<cron_headline>, queue=<queue_headline>, failures_last_60m=<n|n/a>, pending_older_15m=<n|n/a>, activity_last_60m=<n|n/a>`
  - Canonical detail value templates:
    - `failures_last_60m`: `<n>`
    - `pending_older_15m`: `<n>`
    - `tables_present`: `<true|false>`
    - `activity_last_60m`: `<n|n/a>`

### Existing components to extend
- `etc/di.xml`
  - Register new probe action codes in the action pool map.
  - Bind clock dependency:
    - `Model\Probe\ClockInterface` preference => `Model\Probe\SystemClock`

- `Model/RollOutcomeResolver.php`
  - Add probe outcomes with fixed mapping:
    - `5 => indexer_status_snapshot`
    - `6 => cache_backend_health_snapshot`
    - `7 => cron_queue_health_snapshot`

- `Model/KickExecutor.php`
  - Include probe codes in reroll-eligible action code set.
  - Preserve existing retry limit and fallback behavior.
  - Result boundary decision:
    - keep shared executor result message list as the single carrier for both CLI and cron paths
    - probe actions use existing transport behavior: buffered output lines only (probe summary and details are both emitted to output)
    - probe actions must return empty `ChaosActionResult` summaries to avoid duplicate `Probe[...]` lines
    - executor appends, in order: buffered action output lines, then non-empty summary
    - summary append rule remains generic for all actions: append only when non-empty
    - executor remains probe-agnostic (no probe-type branching or interface checks)

- `Cron/ChaosDonkeyKickCron.php`
  - Extend cron execution logging to include probe-only messages from executor-returned message lines (summary + top details), not just start/skip/completion markers.
  - Probe-only filter rule:
    - log only lines prefixed with `Probe[` or `ProbeDetail[`
  - Ordering rule:
    - log messages in received order, unchanged

- `etc/adminhtml/system.xml`
  - Add per-probe yes/no toggles under `admin/chaos_donkey`.

- `etc/config.xml`
  - Add defaults for per-probe toggles:
    - `enable_indexer_status_snapshot=1`
    - `enable_cache_backend_health_snapshot=1`
    - `enable_cron_queue_health_snapshot=1`

- `Model/Config.php`
  - Add config path constants + typed accessors.
  - Extend `isActionEnabled()` action-code gating to probe codes.

## Config Model and Naming
Use stable, feature-oriented config paths under existing namespace:

- `admin/chaos_donkey/enable_indexer_status_snapshot`
- `admin/chaos_donkey/enable_cache_backend_health_snapshot`
- `admin/chaos_donkey/enable_cron_queue_health_snapshot`

Config UX policy:
- `system.xml` yes/no select fields (`Magento\Config\Model\Config\Source\Yesno`)
- explicit operational comments for each toggle
- defaults defined in `etc/config.xml`
- scope: default only (`showInDefault=1`, `showInWebsite=0`, `showInStore=0`)
- default-on rationale: Phase 3 is read-only and optimized for visibility; operators can disable any probe individually

## Runtime Data Flow
1. Kick starts via CLI command or cron-triggered job.
2. `KickExecutor` rolls D20.
3. `RollOutcomeResolver` maps roll to outcome code.
4. If outcome is action/probe and toggle is disabled:
   - reroll up to current cap (20 attempts).
5. If attempts exhausted:
   - fallback to `napping` (unchanged).
6. If enabled action/probe selected:
    - `ActionPool` resolves action service by outcome code.
    - action executes and emits summary + top details.
7. Existing state persistence (`last_run`, `last_kick`, `last_outcome`) remains unchanged.

Cron output policy:
- CLI path: full summary + top details rendered to console output.
- Cron path: consume the same executor result message list, filter to probe-prefixed lines, and log those lines to module logger context.
- Logging format policy: plain canonical message strings only (no additional structured PSR-3 context required in Phase 3).
- Logger-prefix rule:
  - if cron logger helper/channel prepends text, that prefix is transport metadata and not part of canonical probe payload assertions.
  - tests should assert canonical payload substring presence (not exact full-line equality including transport prefix).
- Time-source rule:
  - lookback calculations use injected clock/time provider (not direct `new \DateTimeImmutable()` in probe classes) for deterministic tests.
  - canonical time basis for DB comparisons: UTC timestamps derived from `ClockInterface::nowUtc()`.

Failure output normalization (deterministic):
- Indexer enumeration/state failure:
  - summary: `status=unknown msg="<total|n/a> indexers, <invalid|n/a> need reindex, modes=unavailable"`
  - detail: `subsystem=indexer item=enumeration status=unknown value="unavailable"`
- Cache metadata read failure:
  - summary: `status=unknown msg="cache snapshot unavailable"`
  - detail: `subsystem=cache item=metadata status=unknown value="unavailable"`
- Cache backend resolution failure:
  - summary: `status=warn msg="<total> cache types, <enabled> enabled, backend adapter resolution degraded"`
  - detail: `subsystem=cache_backend item=default_frontend status=warn value="resolution_failed"`
- Cron query failure:
  - summary: `status=<overall_status_from_precedence> msg="cron=unknown, queue=<queue_headline>, failures_last_60m=n/a, pending_older_15m=n/a, activity_last_60m=<n|n/a>"`
  - detail: `subsystem=cron item=failures_last_60m status=unknown value="n/a"`
  - detail: `subsystem=cron item=pending_older_15m status=unknown value="n/a"`
  - detail: `subsystem=queue item=tables_present status=<ok|unavailable> value="<true|false>"`
  - detail: `subsystem=queue item=activity_last_60m status=<ok|warn|unavailable|unknown> value="<n|n/a>"`
- Queue tables unavailable:
  - summary: `status=<overall_status_from_precedence> msg="cron=<cron_headline>, queue=unavailable, failures_last_60m=<n|n/a>, pending_older_15m=<n|n/a>, activity_last_60m=n/a"`
  - detail: `subsystem=cron item=failures_last_60m status=<ok|warn|unknown> value="<n|n/a>"`
  - detail: `subsystem=cron item=pending_older_15m status=<ok|warn|unknown> value="<n|n/a>"`
  - detail: `subsystem=queue item=tables_present status=unavailable value="false"`
  - detail: `subsystem=queue item=activity_last_60m status=unavailable value="n/a"`
- Queue query failure:
  - summary: `status=<overall_status_from_precedence> msg="cron=<cron_headline>, queue=unknown, failures_last_60m=<n|n/a>, pending_older_15m=<n|n/a>, activity_last_60m=n/a"`
  - detail: `subsystem=cron item=failures_last_60m status=<ok|warn|unknown> value="<n|n/a>"`
  - detail: `subsystem=cron item=pending_older_15m status=<ok|warn|unknown> value="<n|n/a>"`
  - detail: `subsystem=queue item=tables_present status=ok value="true"`
  - detail: `subsystem=queue item=activity_last_60m status=unknown value="n/a"`

## Error Handling and Safety
- Probe actions are read-only by contract.
- Probe failures should degrade gracefully where possible:
  - provide clear warning lines to operator output
  - avoid crashing entire kick flow for recoverable probe-source issues
- Exception boundary rule:
  - probe actions must catch probe-source exceptions and normalize to `warn|unknown|unavailable` probe lines.
  - no executor-level throwable handling changes are required for Phase 3.
- Keep detail output bounded to avoid log/console noise.
- Top-detail cap is fixed at `5` rows per probe for both CLI and cron logging.
- Use PSR-3 logging only for diagnostics that complement console output.
- Do not leak sensitive backend details in probe output.
- `ChaosActionResult::isSuccess()` mapping for probe actions:
  - probe status `ok` => `true`
  - probe status `warn` => `true`
  - probe status `unknown` => `false`
  - probe status `unavailable` => `true`

## Testing Strategy

### Unit tests to add
- `Test/Unit/Action/IndexerStatusSnapshotTest.php`
- `Test/Unit/Action/CacheBackendHealthSnapshotTest.php`
- `Test/Unit/Action/CronQueueHealthSnapshotTest.php`
- `Test/Unit/Model/Probe/ProbeOutputFormatterTest.php`

Coverage focus:
- summary output correctness
- top-detail inclusion/capping
- graceful handling for unavailable/partial data
- deterministic overall-status precedence for mixed cron/queue states
- canonical line formatting and deterministic ordering

Test seam/stub requirements:
- Add/extend local test stubs (or thin adapters) for:
  - `Magento\Framework\App\ResourceConnection`
  - `Magento\Framework\App\Cache\Frontend\Pool`
  - `Magento\Framework\App\Cache\TypeListInterface`
  - `Magento\Indexer\Model\IndexerRegistry`
  - `Magento\Indexer\Model\Indexer\CollectionFactory`
- Implementation plan must include either:
  - direct stub additions for these dependencies, or
  - wrapper adapters with narrower contracts to reduce stub surface

### Unit tests to update
- `Test/Unit/Model/ConfigTest.php`
  - new toggle accessors/constants and action-code enable checks

- `Test/Unit/Model/RollOutcomeResolverTest.php`
  - explicit assertions for roll values `5/6/7`

- `Test/Unit/Model/KickExecutorTest.php`
  - reroll behavior for disabled probes
  - fallback behavior when probe/action outcomes are unavailable

- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php` (as needed)
  - probe output surfaced through existing command pipeline

- `Test/Unit/Cron/ChaosDonkeyKickCronTest.php`
  - cron logs include probe summaries/details forwarded from executor result messages (bounded to top 5)

### Example output shape (normative)
CLI sample:
- `Probe[indexer_status_snapshot] status=warn msg="42 indexers, 3 need reindex, modes: schedule=35, realtime=7"`
- `ProbeDetail[indexer_status_snapshot] subsystem=indexer item=catalogrule_rule status=warn value="state=invalid;mode=schedule"`

Cron logger sample:
- `Probe[indexer_status_snapshot] status=warn msg="42 indexers, 3 need reindex, modes: schedule=35, realtime=7"`
- `ProbeDetail[indexer_status_snapshot] subsystem=indexer item=catalogrule_rule status=warn value="state=invalid;mode=schedule"`

Cache sample:
- `Probe[cache_backend_health_snapshot] status=ok msg="14 cache types, 14 enabled, backend adapter=redis"`
- `ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=ok value="redis"`

Cron/queue sample:
- `Probe[cron_queue_health_snapshot] status=warn msg="cron=degraded, queue=unavailable, failures_last_60m=3, pending_older_15m=12, activity_last_60m=n/a"`
- `ProbeDetail[cron_queue_health_snapshot] subsystem=cron item=failures_last_60m status=warn value="3"`
- `ProbeDetail[cron_queue_health_snapshot] subsystem=queue item=tables_present status=unavailable value="false"`

## Probe Status Matrix (v1)

| Probe | Condition | Status |
|------|-----------|--------|
| Indexer | any indexer invalid/reindex-required | `warn` |
| Indexer | no invalid/reindex-required indexers and all modes readable | `ok` |
| Indexer | no invalid indexers but one or more mode values unavailable | `unknown` |
| Cache | cache types readable + backend adapter resolves | `ok` |
| Cache | cache types readable + backend adapter resolution fails | `warn` |
| Cache | cache metadata read fails | `unknown` |
| Cache | default frontend cannot be resolved | `unknown` |
| Cron | failures in lookback OR pending backlog threshold exceeded | `warn` |
| Cron | warning thresholds not hit | `ok` |
| Cron | cron data source unavailable | `unknown` |
| Queue | required queue tables absent | `unavailable` |
| Queue | tables present + queryable + no warning condition | `ok` |
| Queue | tables present + no activity in lookback while cron is warn | `warn` |
| Queue | tables present but query fails | `unknown` |

### Regression gate
- run full suite: `vendor/bin/phpunit`

### Manual validation checklist (required)
- CLI kick path:
  - run `bin/magento chaosdonkey:kick` with probe roll outcomes forced/mocked
  - verify canonical `Probe[...]` and `ProbeDetail[...]` lines
- Status command regression:
  - run `bin/magento chaosdonkey:status`
  - verify output remains unchanged
- Admin config-path verification:
  - verify new toggles persist/read under `admin/chaos_donkey/*`
- Cron path:
  - run cron-triggered kick and verify probe-prefixed log lines
  - verify no probe logs when probe toggles are disabled

## Out of Scope
- separate dedicated probe CLI command
- probe scheduling independent from current kick/cron flow
- probe history persistence tables or dashboards
- configurable roll mapping UI
- non-read-only state-changing probe behaviors

## Acceptance Criteria
1. Three read-only probes are available as kick outcomes.
2. Each probe has an admin toggle and default config value.
3. Probe outcomes can execute via both CLI and cron kick paths.
4. Disabled probes follow existing reroll/fallback semantics.
5. Probe output provides concise summary + top details.
6. Fixed roll mapping (`5/6/7`) is covered by resolver tests.
7. Cron-triggered probe runs produce visibility logs with bounded details.
8. Existing action behavior is preserved.
9. Relevant unit tests pass, and full suite remains green.

## Recommendation
Implement Phase 3 using the probe abstraction layer with first-class action integration. This preserves current architecture, follows Magento DI/config patterns, improves operator visibility, and keeps future probe expansion maintainable.
