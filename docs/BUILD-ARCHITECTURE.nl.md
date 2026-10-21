# Buildarchitectuur — MODX MCP

[English](BUILD-ARCHITECTURE.md) · [Русский](BUILD-ARCHITECTURE.ru.md) · [Español](BUILD-ARCHITECTURE.es.md) · [Deutsch](BUILD-ARCHITECTURE.de.md) · [Français](BUILD-ARCHITECTURE.fr.md) · [Українська](BUILD-ARCHITECTURE.uk.md) · [Қазақша](BUILD-ARCHITECTURE.kk.md) · [Nederlands](BUILD-ARCHITECTURE.nl.md) · [Polski](BUILD-ARCHITECTURE.pl.md)

Eén broncodebasis levert twee aparte transportpakketten voor MODX 2.8.x en MODX 3.x. De gedeelde tools, registry en API bevinden zich in `core/components/modxmcp/src/`.

- MODX 2.8.x: `modx2mcp-1.2.4-pl.transport.zip`
- MODX 3.x: `modx3mcp-1.2.4-pl.transport.zip

```bash
python3 _build/prepare-release.py --platform modx2 --output /tmp/modxmcp-modx2
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
```

[Complete build reference (English)](BUILD-ARCHITECTURE.md) · [Installatie](INSTALL.nl.md)
