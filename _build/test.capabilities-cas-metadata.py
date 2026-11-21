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
source_files=[
 ROOT/'core/components/modxmcp/model/modxmcp.class.php',
 ROOT/'core/components/modxmcp/legacy/modx2/modxmcp.class.php',
]
for p in source_files:
 code=p.read_text()
 state=code.split('public function getSiteState()',1)[1].split('public function getCapabilities()',1)[0]
 cap=code.split('public function getCapabilities()',1)[1].split('public function capabilitiesFingerprint()',1)[0]
 assert "'atomic_preconditions' => ($this->modularRuntime !== null)" in state,p
 assert re.search(r"'precondition_version'\s*=>\s*\(\$this->modularRuntime !== null \? 2 : 1\)",state),p
 assert "$siteState = $this->getSiteState();" in cap,p
 assert "'atomic_preconditions' => $siteState['atomic_preconditions']" in cap,p
 assert "'site_revision' => $siteState['atomic_preconditions']" in cap,p
 assert "'precondition_version' => $siteState['precondition_version']" in cap,p
 assert not re.search(r"'precondition_version'\s*=>\s*[12]\s*,",cap),p
 print('CAS_CAPABILITIES_CONTRACT_OK',p.relative_to(ROOT))
modular=(ROOT/'core/components/modxmcp/src/Tools/StateSupport.php').read_text()
assert "'precondition_version' => 2" in modular
print('CAS_MODULAR_STATE_V2_OK')
