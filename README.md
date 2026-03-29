# ChaosDonkey
Magento 2 module to cause operational chaos on command.

## Commands

### `bin/magento chaosdonkey:kick`
Rolls a D20 and executes a mapped outcome.

- If `admin/chaos_donkey/enabled` is disabled, exits early with a clear message.
- If enabled, saves:
  - `admin/chaos_donkey/last_run` (ISO-8601 timestamp)
  - `admin/chaos_donkey/last_kick` (rolled value)
  - `admin/chaos_donkey/last_outcome` (outcome key)

Current outcome mapping:
- `1`: critical failure message (`critical_failure`)
- `2`: reindex all indexers (`reindex_all`)
- `3`: flush cache types (`cache_flush`)
- `4`: run internal GraphQL pipeline stress (`graphql_pipeline_stress`)
- `20`: critical success message (`critical_success`)
- default: napping message (`napping`)

For action outcomes, `chaosdonkey:kick` resolves an action code and executes a DI-wired action service from the action pool.

### `bin/magento chaosdonkey:status`
Prints real module status values from config/state:
- Enabled (`Yes`/`No`)
- Last run (`Never` if unset)
- Last kick (`Never` if unset)
- Last outcome (`Never` if unset)

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
