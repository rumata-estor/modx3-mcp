# MODX MCP 1.2.8 — блокеры положительных CAS-E2E для optional addons

Дата доказательств: 22 ноября 2026 года. **Состояние тестовой ветки: 108/116**, индивидуально подтверждённых операций; оставшиеся 8 не являются удостоверенными. Ниже только фактически проверенные выводы. Никакие production-сайты не затронуты.

## Матрица воспроизведения

Использовались официальный MODX 2.8.9 и MODX 3.1.2 / 3.2.4 с PHP 8.2, одноразовые базы GitHub Actions и pinned upstream исходники miniShop2 4.4.2-pl (`modx-pro/miniShop2@793bbb8e7a4a2fee07197142be1be57f7e68ab8f`). Через MCP выполнялись реальные мутации и независимое чтение. Таблицы дополнения создавались штатным xPDO только внутри disposable CI. Это *не* проверка полного transport-пакета через MODX Manager.

| Не подтверждённая операция | MODX 2 / PHP 8.2 | MODX 3 / PHP 8.2 | Дальнейшее условие |
|---|---|---|---|
| `ms2_create_category` | Реальное создание, MCP readback и старый CAS прошли | Fatal при загрузке `msCategory` — старый `modprocessor.class.php` | Совместимая native msCategory на MODX 3 |
| `ms2_update_category` | Тест дошёл до штатного processor, упал в `modResource::getAliasPath()`: null-context; отдельно проверить корректность фикстуры и native URI | Не выполнялась после отказа create | Положительный update + readback/CAS на обеих версиях |
| `ms2_assign_option_to_category` | Не выполнялась после ошибки update | Не выполнялась после ошибки create | Создать настоящую категорию/опцию в установленном совместимом addon; прочитать назначение через MCP |
| `ms2_create_product_link` | **Успешный реальный native E2E** с двумя неопубликованными `msProduct`, link type, MCP readback и stale replay | Fatal при загрузке `msProduct` — старый `modprocessor.class.php` | Совместимый msProduct на MODX 3 |
| `ms2_delete_product_link` | **Успешный native E2E** удаления связи и stale replay | Не выполнялась после отказа create product | Полный цикл на обеих версиях |
| `ms2_update_product_options` | **Успешная запись и независимый MCP readback** на настоящем неопубликованном `msProduct`, stale replay | Не выполнялась после отказа create product | Положительный цикл на обеих версиях |
| `ms2_update_order` | Offline fault-injection классификации результата прошёл, реального собственного заказа/смены статуса не создавали | То же; совместимость native order service отдельно не доказана | Полная установка, реальный собственный order, изменение статуса, readback, отдельный fault-after-save |
| `versionx_revert_version` | Offline fault-injection после native revert прошёл, реального VersionX history не создавали | VersionX 3.x имеет иной API; реального native revert не выполняли | Реальная сохранённая версия собственного неопубликованного ресурса, проверенный processor и readback для каждой заявленной major-версии |

## Достоверные логи

- miniShop2 categories: [PR #147](https://github.com/rumata-estor/modx3-mcp/pull/147), [одноразовая матрица с воспроизведёнными ошибками](https://github.com/rumata-estor/modx3-mcp/actions/runs/45062712600). MODX 2 выдаёт `CAS_MS2_CREATE_CATEGORY_NATIVE_READBACK_STALE_OK`, затем ошибка alias context; MODX 3 — missing `core/model/modx/modprocessor.class.php`.
- miniShop2 actual products/links/options: [PR #148](https://github.com/rumata-estor/modx3-mcp/pull/148), [матрица с успешным MODX2, отказом MODX3](https://github.com/rumata-estor/modx3-mcp/actions/runs/45062912347). MODX2 дал явные три маркера `CAS_MS2_UPDATE_PRODUCT_OPTIONS_NATIVE_READBACK_STALE_OK`, `CAS_MS2_CREATE_PRODUCT_LINK_NATIVE_READBACK_STALE_OK`, `CAS_MS2_DELETE_PRODUCT_LINK_NATIVE_READBACK_STALE_OK`.
- Защита пользовательских ID product-link от передачи в нативный interpolated SQL: [PR #142](https://github.com/rumata-estor/modx3-mcp/pull/142) (merged, 12/12); это **не** положительный lifecycle.
- Неопределённый итог native order update: [PR #144](https://github.com/rumata-estor/modx3-mcp/pull/144) (merged, fault injection 12/12).
- Неопределённый итог native VersionX revert: [PR #149](https://github.com/rumata-estor/modx3-mcp/pull/149) (merged, fault injection 12/12). VersionX 3.x использует `mgr/deltas/revert` с `type/principal/delta_id/what`, а имеющийся MCP использует исторический `mgr/resources/revert` и `content_id/version_id`.

## Решение для 1.2.8

1. Не вводить тестовые заглушки MODX 2 processor API в MODX 3 и не интерпретировать зеленые unit/fault тесты как installed E2E. Положительные сценарии входят в реестр только после полного успешного испытания с совместимым установленным дополнением.
2. Не повторять автоматически ни одну мутацию с неопределённым результатом. Вернуть `MUTATION_OUTCOME_UNCERTAIN`, сообщить цель/известные изменения и предложить независимую проверку через MCP; окончательное решение — пользователь.
3. Не обещать универсальную совместимость miniShop2 4.4.2-pl с MODX 3; тест показал обратное для `msCategory` и `msProduct`. Рассматривать другое поколение ecommerce-расширения (например, MiniShop3) **только отдельным архитектурным решением** со своим API и E2E, не как незаметную подмену miniShop2.
4. VersionX 3.x — отдельный несовместимый интерфейс; адаптация новой API требует отдельного проекта и подтверждения на установленном addon. До этого не заявлять положительный revert на MODX 3.
5. **Релизный блокер остаётся открытым для этих 8 optional-мутаторов.** Он не свидетельствует о неработоспособности остальных 108; он означает отсутствие индивидуального доказательства полного requested lifecycle на обеих версиях. PR #67 и прочие общие релизные блокеры проверяются независимо.
