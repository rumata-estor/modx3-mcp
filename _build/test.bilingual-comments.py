#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Контроль полноты двуязычной документации исходников MODX MCP.
# EN: Ensures all first-party MODX MCP source files have bilingual documentation.
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CODE = {".php", ".py", ".js", ".cjs", ".sh", ".css", ".tpl"}
MARKER = "MODX MCP SOURCE / ИСХОДНИК MODX MCP"
paths = sorted(
    p for directory in ("core", "assets", "client", "_build")
    for p in (ROOT / directory).rglob("*")
    if p.is_file() and p.suffix in CODE
    and "node_modules" not in p.parts and "__pycache__" not in p.parts
)
paths.extend(sorted((ROOT / ".github/workflows").glob("*.yml")))
errors = []
for p in paths:
    body = p.read_text(encoding="utf-8")
    beginning = body[:1000]
    if MARKER not in beginning or "RU:" not in beginning or "EN:" not in beginning:
        errors.append(str(p.relative_to(ROOT)))
    if p.suffix == ".php" and not body.startswith("<?php"):
        errors.append("PHP opening tag moved: " + str(p.relative_to(ROOT)))
    if p.suffix == ".sh" and not body.startswith("#!/usr/bin/env bash"):
        errors.append("Shell shebang moved: " + str(p.relative_to(ROOT)))
    if p == ROOT / "client/index.js" and not body.startswith("#!/usr/bin/env node"):
        errors.append("Node shebang moved: " + str(p.relative_to(ROOT)))
if errors:
    raise SystemExit("BILINGUAL_SOURCE_DOCUMENTATION_FAIL: " + ", ".join(errors[:25]))
print("BILINGUAL_SOURCE_DOCUMENTATION_OK files=" + str(len(paths)))
