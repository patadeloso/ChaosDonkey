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

## Architecture
Phase 3 extends the current action framework by introducing a small probe abstraction while keeping probes as first-class kick outcomes.

Core principles:
- Keep command entrypoints thin and orchestration centralized in `Model/KickExecutor`.
- Keep probe behaviors isolated in single-purpose action classes.
- Preserve the existing DI action pool pattern and config-driven gating.
- Reuse existing reroll/fallback semantics for disabled actions.

Planned shape:
- Existing `ChaosActionInterface` remains the execution contract used by the action pool.
- Add `Api/ProbeActionInterface` as a probe-specific marker/contract for read-only action semantics and future extensibility.
- Probe actions implement both interfaces.
- Add shared probe output formatting service for consistent “summary + top details” output.

This balances immediate delivery with maintainability if probe count grows in later phases.

## Components and Responsibilities

### New interfaces/services
- `Api/ProbeActionInterface.php`
  - Defines probe-specific contract/semantics for read-only probes.
  - Keeps probe concerns explicit without changing existing action pool behavior.

- `Model/Probe/ProbeOutputFormatter.php`
  - Shared formatter for concise operator output.
  - Produces a stable shape:
    - summary headline
    - bounded top details (warnings, notable counters/items)

### New actions
- `Action/IndexerStatusSnapshot.php`
  - Read-only snapshot of indexer mode/state indicators.

- `Action/CacheBackendHealthSnapshot.php`
  - Read-only snapshot of cache type/backend health signals.

- `Action/CronQueueHealthSnapshot.php`
  - Read-only snapshot for cron/queue health indicators available within module constraints.

### Existing components to extend
- `etc/di.xml`
  - Register new probe action codes in the action pool map.

- `Model/RollOutcomeResolver.php`
  - Add probe outcomes by replacing selected `napping` roll slots.

- `Model/KickExecutor.php`
  - Include probe codes in reroll-eligible action code set.
  - Preserve existing retry limit and fallback behavior.

- `etc/adminhtml/system.xml`
  - Add per-probe yes/no toggles under `admin/chaos_donkey`.

- `etc/config.xml`
  - Add defaults for per-probe toggles.

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
- maintain current scope policy used by module’s operational settings

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

## Error Handling and Safety
- Probe actions are read-only by contract.
- Probe failures should degrade gracefully where possible:
  - provide clear warning lines to operator output
  - avoid crashing entire kick flow for recoverable probe-source issues
- Keep detail output bounded to avoid log/console noise.
- Use PSR-3 logging only for diagnostics that complement console output.
- Do not leak sensitive backend details in probe output.

## Testing Strategy

### Unit tests to add
- `Test/Unit/Action/IndexerStatusSnapshotTest.php`
- `Test/Unit/Action/CacheBackendHealthSnapshotTest.php`
- `Test/Unit/Action/CronQueueHealthSnapshotTest.php`

Coverage focus:
- summary output correctness
- top-detail inclusion/capping
- graceful handling for unavailable/partial data

### Unit tests to update
- `Test/Unit/Model/ConfigTest.php`
  - new toggle accessors/constants and action-code enable checks

- `Test/Unit/Model/RollOutcomeResolverTest.php`
  - new probe roll mappings

- `Test/Unit/Model/KickExecutorTest.php`
  - reroll behavior for disabled probes
  - fallback behavior when probe/action outcomes are unavailable

- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php` (as needed)
  - probe output surfaced through existing command pipeline

### Regression gate
- run full suite: `vendor/bin/phpunit`

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
6. Existing action behavior is preserved.
7. Relevant unit tests pass, and full suite remains green.

## Recommendation
Implement Phase 3 using the probe abstraction layer with first-class action integration. This preserves current architecture, follows Magento DI/config patterns, improves operator visibility, and keeps future probe expansion maintainable.
