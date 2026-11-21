#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Внутренние PHP-исключения не должны раскрывать секреты даже при debug=1.
# EN: Internal PHP exception payloads must not leak via HTTP or logs in debug mode.
from pathlib import Path
import re
source = (Path(__file__).parent.parent /
          'core/components/modxmcp/endpoint/api.common.php').read_text()
blocks = re.findall(r'catch \((?:Exception|Throwable) \$e\) \{(.*?)(?=\n\} catch \(|\n\}\s*$)',
                    source, re.S)
if len(blocks) != 2:
    raise SystemExit('HTTP_ERROR_REDACTION_FAIL: unexpected exception catches')
for block in blocks:
    if '$e->getMessage()' in block or "'details'" in block:
        raise SystemExit('HTTP_ERROR_REDACTION_FAIL: raw exception disclosure')
    if 'get_class($e)' not in block or "'error_id' => $errorId" not in block:
        raise SystemExit('HTTP_ERROR_REDACTION_FAIL: safe error context missing')
print('HTTP_INTERNAL_ERROR_REDACTION_OK catches=2')
