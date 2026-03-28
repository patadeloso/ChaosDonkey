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
- `1`: critical failure message
- `2`: reindex all indexers
- `20`: critical success message
- default: napping message

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

## Local Unit Tests

This repository contains standalone unit tests for module logic and command orchestration.

```bash
composer install
vendor/bin/phpunit
```
