#!/usr/bin/env python3
# MODX MCP SOURCE / ИСХОДНИК MODX MCP
# RU: Воспроизводимый список CAS-доказательств. Не повышать статус по имени файла.
# EN: Reproducible CAS evidence report; filenames do not imply verified safety.
"""Generate the explicit, evidence-limited 1.2.8 mutation tracker."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
manifest = json.loads((ROOT / '_build/cas-mutation-inventory.json').read_text())
actions = manifest['actions']
priorities = ('critical', 'high', 'review')
titles = {'critical':'P0 — критические операции',
          'high':'P1 — операции повышенного риска',
          'review':'P2 — остальные операции'}
known = [key for key,value in actions.items() if value['verified_scenarios']]
missing = [key for key,value in actions.items() if not value['verified_scenarios']]
lines = [
    '# Контроль индивидуальных CAS-сценариев — MODX MCP 1.2.8',
    '',
    '> Автоматически формируется из `_build/cas-mutation-inventory.json`.',
    '> Источник истины — **имена проверенных сценариев**, а не наличие вызова',
    '> в тесте. Этот список не является сертификатом безопасности.',
    '',
    f'**Всего мутаций:** {len(actions)}. **Есть хотя бы один индивидуальный',
    f'сценарий:** {len(known)}. **Не имеют индивидуальных сценариев:** {len(missing)}.',
    '',
    'Общее тестирование CAS, подписей и раннего отказа действует отдельно.',
    'Оно не гарантирует rollback внешних эффектов, защиту от Manager или',
    'безопасный повтор после обрыва связи. Подтверждённый сценарий отмечает',
    'только указанное в его имени свойство. PR #67 остаётся блокером ACL.',
    '',
]
for risk in priorities:
    group = [(key,value) for key,value in sorted(actions.items())
             if value['risk']==risk and not value['verified_scenarios']]
    lines.extend([f'## {titles[risk]} — {len(group)} без сценария','',
                  '| Действие | CAS-граница | Следующая проверка |',
                  '|---|---|---|'])
    for action,spec in group:
        boundary=spec['cas_boundary']
        if boundary=='database_transaction':
            next_test='Реальная запись, rollback при сбое, readback'
        elif risk=='critical':
            next_test='Readback + stale/replay + независимые права и Manager race'
        elif risk=='high':
            next_test='Readback + stale/replay + целевые настройки/разрешения'
        else:
            next_test='Readback + stale/replay + отказ/восстановление'
        lines.append(f'| `{action}` | `{boundary}` | {next_test} |')
    lines.append('')
lines.extend(['## Уже зарегистрированные индивидуальные сценарии','',
              '| Действие | Риск | Подтверждённые сценарии (не полная гарантия) |',
              '|---|---|---|'])
for action in sorted(known):
    spec=actions[action]
    scenarios=', '.join('`'+name+'`' for name in spec['verified_scenarios'])
    lines.append(f'| `{action}` | {spec["risk"]} | {scenarios} |')
lines.extend(['','## Правило учёта','',
              'Нельзя добавлять сценарий в `verified_scenarios` без реального',
              'исполняемого теста на одноразовых MODX 2/3, читаемого маркера',
              'и успешного GitHub Actions. Если подтверждён только ранний',
              'отказ по ревизии, это не отдельный бизнес-сценарий.',
              ''])
print('\n'.join(lines))
