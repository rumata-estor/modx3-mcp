from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
paths = [
    root / 'core/components/modxmcp/src/Tools/ClientConfigSettingListTool.php',
    root / 'core/components/modxmcp/model/modxmcp.class.php',
    root / 'core/components/modxmcp/legacy/modx2/modxmcp.class.php',
]
errors=[]
for p in paths:
    s=p.read_text(encoding='utf-8')
    if "sortby('cgSetting.key', 'ASC')" not in s:
        errors.append(f'missing qualified ClientConfig key sort in {p}')
    if "sortby('key', 'ASC')" in s:
        errors.append(f'bare reserved key sort remains in {p}')
if errors:
    print('CLIENTCONFIG_LIST_SORT_FAIL')
    for e in errors:
        print('-',e)
    sys.exit(1)
print('CLIENTCONFIG_LIST_SORT_OK')
