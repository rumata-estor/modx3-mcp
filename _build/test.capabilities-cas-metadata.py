#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «capabilities-cas-metadata»; тестовый код не запускается на production.
# EN: «capabilities-cas-metadata» regression test; not executed in production.
"""CAS metadata contract for MODX 2 and 3: legacy fallback v1, modular v2.

This static test intentionally complements the PHP and live integration tests:
it ensures the two entrypoints remain bound to one site-state decision.
"""
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
source = (ROOT/'core/components/modxmcp/src/Registry/CapabilityService.php').read_text()
state=source.split('public static function state(',1)[1].split('public static function report(',1)[0]
cap=source.split('public static function report(',1)[1].split('public static function fingerprint(',1)[0]
assert "'atomic_preconditions' => ($ready)" in state
assert "'precondition_version' => ($ready ? 2 : 1)" in state
assert "$siteState = self::state($modx, $ready);" in cap
for key in ('atomic_preconditions','precondition_version','cas_required'):
    assert "'"+key+"' => $siteState['"+key+"']" in cap
assert "'site_revision' => $siteState['atomic_preconditions']" in cap
connector=(ROOT/'core/components/modxmcp/src/Core/Connector.php').read_text()
assert 'CapabilityService::report($this->modx, $this->modularRuntime !== null)' in connector
assert 'CapabilityService::state($this->modx, $this->modularRuntime !== null)' in connector
print('CAS_CAPABILITIES_CONTRACT_OK shared connector')
modular=(ROOT/'core/components/modxmcp/src/Tools/StateSupport.php').read_text()
assert "'precondition_version' => 2" in modular
print('CAS_MODULAR_STATE_V2_OK')
