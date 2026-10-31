# Architecture de compilation — MODX MCP

[English](BUILD-ARCHITECTURE.md) · [Русский](BUILD-ARCHITECTURE.ru.md) · [Español](BUILD-ARCHITECTURE.es.md) · [Deutsch](BUILD-ARCHITECTURE.de.md) · [Français](BUILD-ARCHITECTURE.fr.md) · [Українська](BUILD-ARCHITECTURE.uk.md) · [Қазақша](BUILD-ARCHITECTURE.kk.md) · [Nederlands](BUILD-ARCHITECTURE.nl.md) · [Polski](BUILD-ARCHITECTURE.pl.md)

Un code source unique produit deux paquets de transport, pour MODX 2.8.x et MODX 3.x. Les outils partagés, le registre et l’API se trouvent sous `core/components/modxmcp/src/`.

- MODX 2.8.x: `modx2mcp-1.2.5-pl.transport.zip`
- MODX 3.x: `modx3mcp-1.2.5-pl.transport.zip`

```bash
python3 _build/prepare-release.py --platform modx2 --output /tmp/modxmcp-modx2
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
```

[Complete build reference (English)](BUILD-ARCHITECTURE.md) · [Installation](INSTALL.fr.md)
