#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Проверка «registry-fail-closed»; тестовый код не запускается на production.
# EN: «registry-fail-closed» regression test; not executed in production.
"""Guard against reintroducing the unprotected legacy dispatcher on either MODX platform."""
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[1]
paths = [root / "core/components/modxmcp/model/modxmcp.class.php"]
legacy_source = root / "core/components/modxmcp/legacy/modx2/modxmcp.class.php"
if legacy_source.is_file():
    paths.append(legacy_source)
for path in paths:
    text = path.read_text(encoding="utf-8")
    start = text.index("public function processRequest(")
    end = text.index("if ($this->modularRuntime !== null)", start)
    prologue = text[start:end]
    assert "CAS_RUNTIME_REQUIRED: modular MODX MCP runtime is unavailable." in prologue, path
    assert "CAS_RUNTIME_REQUIRED: action is not available in the guarded tool registry:" in prologue, path
    assert "$registeredTool = $this->modularRuntime->registry()->get($action);" in prologue, path
    assert "!$registeredTool->supports($this->modularRuntime->context())" in prologue, path
    assert "throw new ModxMCPClientException" in prologue, path
    assert prologue.index("CAS_RUNTIME_REQUIRED: modular") < prologue.index("registry()->get($action)") < prologue.index("CAS_RUNTIME_REQUIRED: action"), path
    finish = text.index("\n    }\n", end)
    guarded = text[end:finish]
    assert "CAS_RUNTIME_REQUIRED: action is not available in the guarded tool registry:" in guarded, path
    assert "throw new ModxMCPClientException" in guarded, path
    for forbidden in ("invokeActionSpec(", "resolveActionSpec(", "switch ($action)", "runCoreProcessor(", "->runProcessor("):
        assert forbidden not in guarded, (path, forbidden)
    assert "function invokeActionSpec(" not in text and "function resolveActionSpec(" not in text, path
    subprocess.run(["php", str(root / "_build/test.registry-fail-closed.php"), str(path)], check=True)
    assert "StateSupport::requireMutationPreconditions(" in guarded, path
    assert "StateSupport::withMutationLock(" in guarded, path
    assert "ProtectedAuthorization::authorizeMutation(" in guarded, path
    assert "StateSupport::assertPreconditions(" in guarded, path
    assert "StateSupport::incrementRevision(" in guarded, path
    print("REGISTRY_FAIL_CLOSED_STATIC_OK", path.relative_to(root))
print(f"REGISTRY_FAIL_CLOSED_STATIC_PASS implementations={len(paths)}")
