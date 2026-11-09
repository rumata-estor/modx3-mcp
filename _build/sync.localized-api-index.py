#!/usr/bin/env python3
"""Create accurate localized 198-tool API directories from translated capabilities.

Source of truth for field types and JSON-schema details remains docs/API.md.
This script never translates technical identifiers or parameters.
"""
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parent.parent
D=ROOT/'docs'
LANGS={
 'es':('Índice de las 198 herramientas MCP','Herramienta','Descripción traducida','Tipo de operación','Esquemas de parámetros (inglés)','Este índice está localizado; para campos obligatorios, tipos, límites y enumeraciones consulte el esquema técnico enlazado en inglés o ruso.','[Guía completa en español](CAPABILITIES.es.md)'),
 'de':('Verzeichnis der 198 MCP-Werkzeuge','Werkzeug','Lokalisierte Beschreibung','Auswirkung','Parameterschemas (Englisch)','Die Beschreibungen sind übersetzt; verbindliche Felder, Typen, Grenzen und Aufzählungen stehen in der verlinkten englischen oder russischen technischen Referenz.','[Ausführliche Funktionen (Deutsch)](CAPABILITIES.de.md)'),
 'fr':('Répertoire des 198 outils MCP','Outil','Description traduite','Impact','Schémas des paramètres (anglais)','Les descriptions sont traduites ; pour les champs requis, types, limites et énumérations, consultez la référence technique anglaise ou russe liée.','[Fonctions détaillées (français)](CAPABILITIES.fr.md)'),
 'uk':('Каталог 198 інструментів MCP','Інструмент','Перекладений опис','Вплив','Схеми параметрів (англійською)','Опис перекладено; обов’язкові поля, типи, обмеження та перелічення перевіряйте в англомовному або російськомовному технічному довіднику за посиланням.','[Докладні можливості українською](CAPABILITIES.uk.md)'),
 'kk':('198 MCP құралының тізімі','Құрал','Аударылған сипаттама','Әсері','Параметрлер схемасы (ағылшынша)','Сипаттамалар аударылған; міндетті өрістер, түрлер, шектер және тізімдер үшін сілтемедегі ағылшын не орыс техникалық анықтамалығын қараңыз.','[Толық мүмкіндіктер (қазақша)](CAPABILITIES.kk.md)'),
 'nl':('Overzicht van 198 MCP-tools','Tool','Vertaald doel','Impact','Parameterschema’s (Engels)','De beschrijvingen zijn vertaald; controleer verplichte velden, typen, limieten en enumeraties in de gekoppelde Engelse of Russische technische referentie.','[Volledige functies (Nederlands)](CAPABILITIES.nl.md)'),
 'pl':('Katalog 198 narzędzi MCP','Narzędzie','Przetłumaczony opis','Wpływ','Schematy parametrów (angielski)','Opisy są przetłumaczone; wymagane pola, typy, limity i wartości wyliczeniowe należy sprawdzać w podlinkowanej dokumentacji technicznej po angielsku lub rosyjsku.','[Pełne możliwości po polsku](CAPABILITIES.pl.md)')
}
table_re=re.compile(r'^\| \u0060(modx_[a-z0-9_]+)\u0060 \|',re.M)
en_tools=table_re.findall((D/'CAPABILITIES.md').read_text())
assert len(en_tools)==198
for lang,labels in LANGS.items():
  api=D/f'API.{lang}.md'
  caps=D/f'CAPABILITIES.{lang}.md'
  doc=caps.read_text()
  chunks=[]
  current=[]
  def flush():
    if current:
      chunks.append('\n'.join(current))
      current.clear()
  count=0
  found=[]
  for line in doc.splitlines():
    if line.startswith('## '):
      flush()
      current.append('### '+line[3:])
    elif line.startswith('| `modx_'):
      cols=[v.strip() for v in line.strip().strip('|').split(' | ')]
      if len(cols)!=3:
        # Markdown table cells can contain escaped pipes; parse by the stable delimiter.
        cols=[v.strip() for v in re.split(r'(?<!\\)\|',line.strip('|'))]
        cols=[c.replace('\\|','|') for c in cols]
      assert len(cols)==3,(lang,line[:180])
      match=re.match(r'\u0060(modx_[a-z0-9_]+)\u0060',cols[0])
      assert match,(lang,line)
      ident=match.group(1)
      found.append(ident)
      details=f'[{"schema" if lang=="en" else labels[4]}](API.md#{ident})'
      if current:
        current.append(f'| [{ident}](API.md#{ident}) | {cols[1]} | {cols[2]} |')
      count+=1
    elif line.startswith('| Tool |'):
      continue
    elif current and line.startswith('| --- |'):
      continue
  flush()
  assert count==198 and found==en_tools,(lang,count,found[:3])
  index=[]
  for block in chunks:
    split=block.splitlines()
    if len(split)<=1:
      continue
    index.append(split[0]+'\n\n| '+labels[1]+' | '+labels[2]+' | '+labels[3]+' |\n| --- | --- | --- |\n'+'\n'.join(split[1:]))
  source=api.read_text()
  # Strip the previously generated 192-tool directory during the migration
  # to 198 tools, then rebuild once. Keep the introductory examples.
  import re as _re
  old=_re.search(r'^## [^\n]*192[^\n]*$',source,_re.M)
  if old:
    source=source[:old.start()].rstrip()+'\n'
  assert '## MCP' in source and '## HTTP' in source,(lang,'missing examples')
  marker='\n## '+labels[0]+'\n'
  if marker in source:
    source=source.split(marker,1)[0].rstrip()+'\n'
  addition='\n'+marker+'\n> '+labels[5]+'\n\n'+labels[6]+' · [English API schemas](API.md) · [Русский API](API.ru.md)\n\n'+'\n\n'.join(index)+'\n'
  # Keep existing 9-language switcher and contextual examples.
  api.write_text(source+addition)
  print('LOCALIZED_API_INDEX_OK',lang,count,'tools',len(index),'groups')
