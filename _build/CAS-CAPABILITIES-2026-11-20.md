# MODX MCP CAS capability-reporting fix (2026-11-20)

## Root cause

In both the MODX 2 and MODX 3 legacy adapter classes, `getCapabilities()` and `getSiteState()` carried static `precondition_version = 1` metadata, even when the modular CAS v2 runtime was initialized. The modular `get_site_state` action independently reported `precondition_version = 2`, creating contradictory information for Site Agent. The protected TV update wrapper uses the authoritative state action and requires CAS v2.

## Patch

- Both platform adapters now advertise CAS version 2 when `modularRuntime` is initialized, otherwise version 1.
- `getCapabilities()` takes its atomic-precondition/version values from `getSiteState()` so their metadata cannot drift independently.
- `StateSupport::siteState()` remains the authoritative modular action and is not altered.
- Static regression assertions cover both MODX generations and are included in CI. GitHub CI also performs PHP syntax checks. No actual site writes are performed by this patch.

## Release and deployment

Do **not** modify an installed package, edit production PHP ad hoc, or alter `modxmcp` settings to force version 2. Release a reviewed, tested transport package and deploy only after the owner approves the site rollout and rollback plan. Current Site Agent runtime already compensates for the old metadata by checking `modx_get_site_state` directly.
