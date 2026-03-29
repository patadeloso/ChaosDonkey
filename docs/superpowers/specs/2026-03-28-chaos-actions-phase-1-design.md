# ChaosDonkey Phase 1 Design: Action Framework + Cache Flush + Internal GraphQL Pipeline Stress

Date: 2026-03-28
Scope: Sub-project 1 only (future phases will cover admin UX/config expansion and cron automation)

## Goal
Implement Magento-native chaos action extensibility with three operational actions in framework form:
- `reindex_all` (existing behavior, framework-adapted)
- `cache_flush` (new)
- `graphql_pipeline_stress` (new, internal HTTP pipeline path, no outbound HTTP)

## Architecture
Use interface-driven action services and DI action pool wiring:
- `Api/ChaosActionInterface`
- `Model/ChaosActionResult`
- `Model/ActionPool`
- `Model/RollOutcomeResolver`
- Action services under `Action/`
- Thin orchestration in `Console/Command/ChaosDonkeyKick`

`ChaosDonkeyKick` responsibilities:
1. Enabled gate check
2. Roll D20
3. Resolve roll -> outcome code
4. Execute mapped action via pool (if mapped)
5. Print summary/details
6. Persist `last_run`, `last_kick`, `last_outcome`

## Components
### `Api/ChaosActionInterface`
- `public function getCode(): string`
- `public function execute(OutputInterface $output): ChaosActionResult`

### `Model/ChaosActionResult`
Payload object with:
- `outcomeCode`
- `summary`
- `details` (list)
- `success` (bool)

### `Model/ActionPool`
- Inject array of `ChaosActionInterface` actions from `etc/di.xml`
- Resolve by code
- Return explicit failure when code is unknown

### `Model/RollOutcomeResolver`
Initial fixed mapping for Phase 1:
- `2` => `reindex_all`
- `3` => `cache_flush`
- `4` => `graphql_pipeline_stress`
- `1` => `critical_failure`
- `20` => `critical_success`
- default => `napping`

### `Action/ReindexAll`
- Implement `ChaosActionInterface`
- Iterate indexers and continue on per-indexer failure

### `Action/CacheFlush`
- Implement `ChaosActionInterface`
- Use Magento cache management services to flush cache types
- Report flushed types and failures

### `Action/GraphQlInternalPipelineStress`
- Implement `ChaosActionInterface`
- Build synthetic in-process request(s) targeting `/graphql`
- Dispatch through Magento internal HTTP pipeline (front controller path)
- Capture response metadata and GraphQL errors
- Aggregate summary/details

## Data Flow
1. Command starts.
2. If disabled: emit message and return success (no state write).
3. Roll D20.
4. Resolve outcome code.
5. If outcome has action: execute action and print results.
6. If outcome is message-only: print mapped message.
7. Persist run metadata.
8. Return success.

GraphQL stress action path:
1. Create default GraphQL payload set (hardcoded for Phase 1).
2. For each payload, synthesize internal request to `/graphql`.
3. Dispatch via Magento internal HTTP app pipeline.
4. Record status/errors/summary.
5. Return aggregated result.

## Error Handling
- Command does not crash on expected chaos failures; keeps operator-friendly output.
- Persist run metadata even when action reports failures.
- Action-specific behavior:
  - Reindex: continue on indexer errors.
  - Cache flush: continue through all targeted types.
  - GraphQL stress: continue through payload set and aggregate failures.
- Unknown action code: output explicit warning and store `unknown_action`.

## Testing Strategy
### Unit tests
- `ActionPool`: resolve and unknown behavior
- `RollOutcomeResolver`: mapping correctness
- `CacheFlush`: full success and partial failure
- `GraphQlInternalPipelineStress`: internal dispatch orchestration with mocked services
- `ChaosDonkeyKick`: new outcome mappings + state persistence

### Existing tests adapted/kept
- Reindex behavior tests
- Status rendering tests
- Config/state writer tests
- Kick roller bounds tests

### Magento manual verification
- Enable module, run `chaosdonkey:kick` until actions trigger
- Verify output and status fields update correctly
- Validate GraphQL stress action confirms internal pipeline path

## Out of Scope (Future Phases)
- Admin-driven GraphQL query builder/templates
- Admin action toggles and probability controls
- Cron scheduling and automated chaos runs

## Recommendation
Implement this phase with fixed default mappings and hardcoded GraphQL payloads, then expand configurability in Phase 2 and scheduling in Phase 3.
