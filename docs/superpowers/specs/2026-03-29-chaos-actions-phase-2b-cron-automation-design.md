# ChaosDonkey Phase 2B Design: Cron Automation

Date: 2026-03-29
Scope: Magento-native scheduled execution for ChaosDonkey kicks

## Goal
Allow ChaosDonkey kicks to run automatically on a configurable Magento cron schedule, with explicit admin gating and predictable operator logs.

## Architecture
Implement cron execution as a Magento-native cron job (`etc/crontab.xml` + cron class), not shelling out to `bin/magento`.

To avoid duplicating logic between CLI and cron, extract shared kick execution into a reusable service (for example `Model/KickExecutor`).

- `chaosdonkey:kick` command delegates to the shared executor.
- Cron job delegates to the same executor.
- Existing action toggles and reroll behavior remain the single source of truth.

## Admin Config Model
Add Phase 2B cron config under `admin/chaos_donkey`:

- `cron_enabled` (yes/no, default `0`)
- `cron_expression` (text, default `*/30 * * * *`)
- `cron_allowed_hours` (text, optional, default empty)

Scope policy for Phase 2B:
- default scope only (`showInDefault=1`, website/store hidden)

`cron_allowed_hours` format (v1):
- comma-separated `0-23` integers, example: `1,2,3,22,23`
- empty means "no hour restriction"

## Components
### `etc/adminhtml/system.xml`
Add cron controls to the existing Chaos Donkey config group.

### `etc/config.xml`
Add default cron values.

### `Model/Config.php`
Add constants/getters for new cron keys:
- `isCronEnabled()`
- `getCronExpression()`
- `getCronAllowedHoursRaw()`
- `getCronAllowedHours(): array<int>` (normalized unique sorted values)

### `etc/crontab.xml`
Register scheduled cron job entry for ChaosDonkey.

### `Cron/ChaosDonkeyKickCron.php`
Cron entrypoint that:
1. exits early when module disabled
2. exits early when cron disabled
3. exits early when current hour not in allowed set (if configured)
4. invokes shared kick executor
5. logs skip/execution outcomes to module logger context

### Shared executor (`Model/KickExecutor.php`)
Move command orchestration (roll, reroll disabled actions, action execution, state persistence) into a reusable class callable from CLI and cron.

Command and cron should differ only in presentation context (console output vs logger).

## Data Flow
1. Magento cron triggers job by `cron_expression`.
2. Cron job checks `isEnabled()` and `isCronEnabled()`.
3. Cron job checks current server hour against parsed allowed-hours list.
4. If allowed, invoke shared kick executor.
5. Shared executor performs existing kick flow (including action toggle reroll behavior) and persists final state.

## Error Handling and Guardrails
- Invalid cron expression or invalid allowed-hours config must not crash cron processing.
- On invalid config, skip execution and log a warning with path/key.
- All skip reasons should be explicit:
  - module disabled
  - cron disabled
  - outside allowed hours
  - invalid cron config

## Testing Strategy
### Unit tests
- `Model/ConfigTest.php`
  - cron getters and allowed-hours normalization

- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
  - command delegates to shared executor (after refactor)

- `Test/Unit/Cron/ChaosDonkeyKickCronTest.php`
  - skips when module disabled
  - skips when cron disabled
  - skips outside allowed hours
  - executes when enabled and in allowed hours
  - handles invalid allowed-hours config safely

- `Test/Unit/Model/KickExecutorTest.php` (new)
  - core kick orchestration behavior stays equivalent to existing behavior

### Regression verification
- full suite: `vendor/bin/phpunit`

## Out of Scope
- preset schedule builder UI
- per-action time windows
- randomized cron jitter
- database-backed run history table
- external scheduling services

## Recommendation
Ship Phase 2B with Magento-native cron wiring and shared kick execution service. This keeps behavior consistent across CLI and cron, aligns with Magento patterns, and sets up future admin UX improvements without rewiring runtime logic.
