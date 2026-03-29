# ChaosDonkey Phase 2A Design: Admin Action Toggles

Date: 2026-03-28
Scope: Admin UX/config for per-action enable/disable toggles only

## Goal
Allow administrators to enable/disable each chaos action from Magento admin config, with runtime behavior that avoids selecting disabled actions.

## Architecture
Keep current action framework (`ActionPool`, resolver, action classes) unchanged.

Implement config-driven runtime gating in `chaosdonkey:kick` by filtering eligible outcomes before final execution:
1. Read action toggle config values.
2. Roll D20 and resolve outcome.
3. If resolved action is disabled, reroll until outcome is executable (enabled action or non-action outcome).
4. Execute final outcome and persist final state.

This preserves existing boundaries and minimizes risk.

## Admin Config Model
Add three new toggles under `admin/chaos_donkey`:
- `enable_reindex_all`
- `enable_cache_flush`
- `enable_graphql_pipeline_stress`

Defaults in `etc/config.xml`:
- all three toggles enabled (`1`)

## Components
### `etc/adminhtml/system.xml`
Add yes/no fields for per-action toggles.

### `etc/config.xml`
Add default values for new toggle keys.

### `Model/Config.php`
Add constants/getters:
- `CONFIG_PATH_ENABLE_REINDEX_ALL`
- `CONFIG_PATH_ENABLE_CACHE_FLUSH`
- `CONFIG_PATH_ENABLE_GRAPHQL_PIPELINE_STRESS`

Add helper:
- `isActionEnabled(string $actionCode): bool`

### `Console/Command/ChaosDonkeyKick.php`
Update outcome-selection flow:
- precompute enabled action set
- roll/resolve loop with max-attempt guard
- skip disabled action outcomes by rerolling
- execute only valid final outcome
- persist final roll and final outcome

## Data Flow
1. Check module enabled (`admin/chaos_donkey/enabled`).
2. Build enabled-action map from new toggles.
3. Roll D20.
4. Resolve outcome code.
5. If outcome is disabled action, reroll (bounded loop).
6. Execute final outcome (action or message outcome).
7. Persist final `last_run`, `last_kick`, `last_outcome`.

## Edge Behavior
### All actions disabled
- Command logs warning:
  - `All configured chaos actions are disabled. Rolling non-action outcomes only.`
- Only non-action outcomes are eligible.
- No action execution attempted.

### Max-attempt guard reached
- Fallback to `napping`.
- Log warning about max reroll attempts.

## Testing Strategy
### Unit tests
- `Model/ConfigTest.php`
  - new getters
  - `isActionEnabled()` mapping behavior

- `Test/Unit/Console/Command/ChaosDonkeyKickTest.php`
  - enabled action executes normally
  - disabled action triggers reroll
  - all-actions-disabled warning appears
  - max-attempt guard falls back to `napping`

### Regression verification
- run full suite: `vendor/bin/phpunit`

## Out of Scope
- probability tuning
- admin GraphQL query builder/templates
- per-store overrides
- cron automation

## Recommendation
Ship Phase 2A as minimal config/runtime gating with reroll behavior and clear operator logs, then move to Phase 2B for probability controls and advanced admin UX.
