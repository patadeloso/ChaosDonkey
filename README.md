# ChaosDonkey
Magento 2 module to cause operational chaos on command.

## Commands

### `bin/magento chaosdonkey:kick`
Rolls a D20 and executes a mapped outcome.

- If `admin/chaos_donkey/enabled` is disabled, exits early with a clear message.
- Before executing an action outcome, checks action toggles:
  - `admin/chaos_donkey/enable_reindex_all`
  - `admin/chaos_donkey/enable_cache_flush`
  - `admin/chaos_donkey/enable_graphql_pipeline_stress`
  - `admin/chaos_donkey/enable_indexer_status_snapshot`
  - `admin/chaos_donkey/enable_cache_backend_health_snapshot`
  - `admin/chaos_donkey/enable_cron_queue_health_snapshot`
- If enabled and an action/probe executes, the command delegates execution to `KickExecutor`.
- If a disabled action outcome is rolled, rerolls up to 20 times.
- If all action/probe toggles are disabled, prints `All configured chaos actions/probes are disabled. Rolling non-action outcomes only.`
- If reroll attempts are exhausted, falls back to `napping`.
- If enabled, saves:
  - `admin/chaos_donkey/last_run` (ISO-8601 timestamp)
  - `admin/chaos_donkey/last_kick` (rolled value)
  - `admin/chaos_donkey/last_outcome` (outcome key)

Current outcome mapping:
- `1`: critical failure message (`critical_failure`)
- `2`: reindex all indexers (`reindex_all`)
- `3`: flush cache types (`cache_flush`)
- `4`: run internal GraphQL pipeline stress (`graphql_pipeline_stress`)
- `5`: indexer status snapshot probe (`indexer_status_snapshot`)
- `6`: cache backend health snapshot probe (`cache_backend_health_snapshot`)
- `7`: cron/queue health snapshot probe (`cron_queue_health_snapshot`)
- `20`: critical success message (`critical_success`)
- default: napping message (`napping`)

Rolls `5`, `6`, and `7` are the fixed probe outcomes for the new read-only probes.

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

When it does run, cron delegates to the same kick execution pipeline as `chaosdonkey:kick`, so rerolls, action toggles, and state persistence behave the same way.

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
- Configured Action/Probe Toggles (default scope):
  - `Reindex all: Enabled|Disabled`
  - `Cache flush: Enabled|Disabled`
  - `GraphQL pipeline stress: Enabled|Disabled`
  - `Indexer status snapshot: Enabled|Disabled`
  - `Cache backend health snapshot: Enabled|Disabled`
  - `Cron queue health snapshot: Enabled|Disabled`

This makes the runtime values (last run/kick/outcome) and toggle rows explicit as `default` scope configuration, while module enabled state follows the existing store-scoped status command behavior.

## Reindex Behavior

On roll `2`, `ReindexAll` iterates all Magento indexers and:
- prints per-indexer progress
- runs `reindexAll()` on each indexer
- catches per-indexer exceptions and continues reindexing the rest

## Cache Flush Behavior

On roll `3`, `CacheFlush`:
- enumerates available cache types
- flushes each type individually
- continues on per-type failures and reports a summary

## Internal GraphQL Pipeline Stress

On roll `4`, `GraphQlInternalPipelineStress`:
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
