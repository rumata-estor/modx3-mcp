# Source code comments / Комментарии к исходному коду

## Русский

Это обязательное правило для **нового и изменяемого собственного кода MODX MCP**.

1. Каждый исходный файл PHP, JavaScript, Python, shell, CSS, Smarty и CI YAML должен иметь краткий заголовок **на русском и английском**, поясняющий реальное назначение файла.
2. Сложная бизнес-логика, CAS, Ed25519, авторизация, взаимодействие с процессорами MODX, транзакции и обработка сбоев должны иметь **парные русские и английские комментарии рядом с соответствующей логикой**.
3. Комментарии обязаны описывать фактическое поведение и ограничения. Не писать «безопасно», если это не подтверждено тестом. Не добавлять комментарии ради количества и не дублировать тривиальные строки кода.
4. Нельзя изменять выполняемый код, схемы данных, пути, URL, переводимые строки, автозагрузку или сигнатуры файлов только ради комментариев. Сохранять синтаксис, shebang и особые правила каждого формата.
5. Не модифицировать сторонние зависимости или генерируемые файлы. Изменения проверять PHP lint, Python/Node/shell syntax, двуязычным контрактом и применимыми регрессионными тестами.
6. Тест для заголовков: `python3 _build/test.bilingual-comments.py`. Он включён в CI. Парные комментарии возле критических функций пока оцениваются в ходе code review.

## English

These rules are mandatory for **new and modified first-party MODX MCP source code**.

1. Every PHP, JavaScript, Python, shell, CSS, Smarty and CI YAML source file must have a concise **Russian and English** header explaining its actual purpose.
2. Complex business logic, CAS, Ed25519, authorization, MODX processor execution, transactions and failure handling require **paired Russian and English comments next to the relevant logic**.
3. Comments must accurately describe behavior and limitations. Do not claim a property is secure without supporting evidence. Avoid filler and comments that merely repeat obvious code.
4. Do not alter executable behavior, data schemas, paths, URLs, localization strings, autoloading or file signatures just to add comments. Preserve syntax, shebangs and format-specific constraints.
5. Do not modify third-party dependencies or generated files. Run PHP lint, Python/Node/shell syntax checks, the bilingual contract and applicable regression tests.
6. Header contract: `python3 _build/test.bilingual-comments.py`, executed in CI. Comments near critical functions currently require code review.
