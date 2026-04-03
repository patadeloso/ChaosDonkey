# ChaosDonkey
Magento 2 module to cause operational chaos on command.

## Commands

### `bin/magento chaosdonkey:kick`
Rolls a D20 and executes an outcome selected from the active execution profile.

- If `admin/chaos_donkey/enabled` is disabled, exits early with a clear message.
- Before executing an action outcome, checks action toggles:
  - `admin/chaos_donkey/enable_reindex_all`
  - `admin/chaos_donkey/enable_cache_flush`
  - `admin/chaos_donkey/enable_graphql_pipeline_stress`
  - `admin/chaos_donkey/enable_indexer_status_snapshot`
  - `admin/chaos_donkey/enable_cache_backend_health_snapshot`
  - `admin/chaos_donkey/enable_cron_queue_health_snapshot`
- If enabled and an action/probe executes, the command delegates execution to `KickExecutor`.
- Action/probe toggles still gate eligibility. Disabled action/probe outcomes are removed from the eligible pool before selection.
- If all action/probe toggles are disabled, prints `All configured chaos actions/probes are disabled. Rolling non-action outcomes only.`
- If enabled, saves:
  - `admin/chaos_donkey/last_run` (ISO-8601 timestamp)
  - `admin/chaos_donkey/last_kick` (rolled value)
  - `admin/chaos_donkey/last_outcome` (outcome key)

Execution profile setting:
- Config path: `admin/chaos_donkey/execution_profile`
- Applies to both CLI (`chaosdonkey:kick`) and cron execution, because both use the same `KickExecutor` profile-selection pipeline.
- Built-in profiles:
  - `balanced` (default): preserves legacy behavior distribution (mostly `napping`, with one slot each for standard actions/probes and critical outcomes).
  - `chaos`: increases disruptive action frequency and reduces `napping`.
  - `all_gas_no_brakes`: heavily favors disruptive actions and sets probe weights to zero.
- Fallback behavior may cause configured and effective profiles to differ at runtime. `chaosdonkey:status` reports configured profile, effective profile, and fallback reason when applicable.
- v1 output behavior: `chaosdonkey:kick` and cron logs do **not** add profile/effective-profile/fallback lines.

Canonical outcomes:
- `critical_failure`: critical failure message
- `reindex_all`: reindex all indexers
- `cache_flush`: flush cache types
- `graphql_pipeline_stress`: run internal GraphQL pipeline stress
- `indexer_status_snapshot`: indexer status snapshot probe
- `cache_backend_health_snapshot`: cache backend health snapshot probe
- `cron_queue_health_snapshot`: cron/queue health snapshot probe
- `napping`: napping message
- `critical_success`: critical success message

For outcomes, `chaosdonkey:kick` resolves an action code and executes a DI-wired action service from the action pool.

Probe actions return their output as canonical lines in the command result payload:
- `Probe[<outcome>] status=<status> msg="<message>"`
- `ProbeDetail[<outcome>] subsystem=<name> item=<name> status=<status> value="<value>"`

`chaosdonkey:kick` prints each line from the executor payload as-is, without reformatting.

### Task 8 verification evidence

Executed in-repo in this worktree:

- Targeted kick command test:
  - `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
- Targeted status command test:
  - `vendor/bin/phpunit Test/Unit/Console/Command/ChaosDonkeyStatusTest.php`
- Full suite:
  - `vendor/bin/phpunit`
- Manual checks attempted:
  - Attempted `bin/magento` config/status/kick checks, but this worktree is module-only and does not include Magento bootstrap.
  - Limitation: `bin/magento` is unavailable (`zsh: no such file or directory`).

## Cron Automation

ChaosDonkey can also run on Magento cron using the same kick execution pipeline as the CLI command.

Cron settings live under `admin/chaos_donkey`:
- `admin/chaos_donkey/cron_enabled`
- `admin/chaos_donkey/cron_expression`
- `admin/chaos_donkey/cron_allowed_hours`

Magento reads the schedule expression from `admin/chaos_donkey/cron_expression` for the cron job definition.

`admin/chaos_donkey/cron_allowed_hours` accepts comma-separated hour values from `0` to `23`.
Examples:
- `1,2,3`
- `0, 12, 23`
- empty value means no hour restriction

Cron execution skips when:
- the module is disabled
- cron execution is disabled
- `cron_allowed_hours` is invalid
- the current hour is outside the allowed window

When it does run, cron delegates to the same kick execution pipeline as `chaosdonkey:kick`, so execution profile selection, action/probe eligibility gating, and state persistence behave the same way.

Cron log behavior:
- Always logs startup, skip reasons, and completion.
- Logs only probe/probe-detail lines from command output (`Probe[...]`, `ProbeDetail[...]`) and preserves them unchanged.
- Non-probe chatter from the executor result is intentionally not logged by cron to keep logs focused.

### `bin/magento chaosdonkey:status`
Shows the operator-oriented status snapshot for ChaosDonkey.

- Enabled (`Yes`/`No`) — reads `admin/chaos_donkey/enabled` using the command's existing store-scoped behavior.
- Last run (`Never` if unset) — reads `admin/chaos_donkey/last_run` from default scope.
- Last kick (`Never` if unset) — reads `admin/chaos_donkey/last_kick` from default scope.
- Last outcome (`Never` if unset) — reads `admin/chaos_donkey/last_outcome` from default scope.
- Configured profile — reads `admin/chaos_donkey/execution_profile` from default scope.
- Effective profile — resolved profile used at runtime after fallback handling.
- Fallback reason (only when present) — indicates why configured and effective profiles differ.
- Configured Action/Probe Toggles (default scope):
  - `Reindex all: Enabled|Disabled`
  - `Cache flush: Enabled|Disabled`
  - `GraphQL pipeline stress: Enabled|Disabled`
  - `Indexer status snapshot: Enabled|Disabled`
  - `Cache backend health snapshot: Enabled|Disabled`
  - `Cron queue health snapshot: Enabled|Disabled`

This makes the runtime values (last run/kick/outcome) and toggle rows explicit as `default` scope configuration, while module enabled state follows the existing store-scoped status command behavior.

## Reindex Behavior

When `reindex_all` is the selected outcome, `ReindexAll` iterates all Magento indexers and:
- prints per-indexer progress
- runs `reindexAll()` on each indexer
- catches per-indexer exceptions and continues reindexing the rest

## Cache Flush Behavior

When `cache_flush` is the selected outcome, `CacheFlush`:
- enumerates available cache types
- flushes each type individually
- continues on per-type failures and reports a summary

## Internal GraphQL Pipeline Stress

When `graphql_pipeline_stress` is the selected outcome, `GraphQlInternalPipelineStress`:
- creates synthetic in-process requests targeting `/graphql`
- dispatches those requests through Magento's internal HTTP pipeline (no external web HTTP calls)
- uses default built-in GraphQL payloads in Phase 1
- aggregates per-payload success/failure details

## Local Unit Tests

This repository contains standalone unit tests for module logic and command orchestration.

```bash
composer install
vendor/bin/phpunit
```

## Simplified PR Workflow

Use the helper scripts in `scripts/` for a shorter GitHub flow.

Start work on a new branch and open a PR draft/fill flow:

```bash
scripts/pr-start.sh feat/my-change
```

Merge PR with squash, delete branches, and sync local `main`:

```bash
scripts/pr-finish.sh
```
