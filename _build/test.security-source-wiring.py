#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Регрессия комментариев и подключений независимой авторизации.
# EN: Regression checks for independent authorization wiring and source comments.
from pathlib import Path
root = Path(__file__).resolve().parents[1]
security = root / "core/components/modxmcp/src/Security"
protected = (security / "ProtectedAuthorization.php").read_text()
crypto = (security / "IndependentPermitV1.php").read_text()
ledger = (security / "PermitNonceLedger.php").read_text()
sources = [(root / "core/components/modxmcp/src/Core/Connector.php").read_text()]
for source in sources:
    assert "ProtectedAuthorization::authorizeMutation(" in source, "missing protected authorization"
    assert "ProtectedAuthorization::required(" in source, "missing policy gate"
    assert "StateSupport::withMutationLock(" in source, "missing mutation lock"
assert "IndependentPermitV1::verifyCryptographicClaims(" in protected
assert "new PermitNonceLedger(" in protected
assert "$ledger->reserve(" in protected
assert "ProcessorAccessPolicy::authorize(" in protected
assert "modxmcp.allow_run_processor" in protected
assert "RU:" in crypto and "EN:" in crypto
assert "RU:" in ledger and "EN:" in ledger
for outdated in ("NOT wired to the API", "Not wired to the MCP endpoint",
                 "Until those components are present"):
    assert outdated not in (crypto + ledger), "obsolete security documentation: " + outdated
print("PROTECTED_SECURITY_SOURCE_WIRING_OK")
