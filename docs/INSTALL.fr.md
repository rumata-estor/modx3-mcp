# Installer et connecter MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

Choisissez l’archive adaptée à la version principale de MODX. Le client MCP nécessite Node.js 18 ou une version supérieure.

## 1. MODX 2 / MODX 3

- [Paquet MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip) — MODX 2.8.x
- [Paquet MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) — MODX 3.x
- [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## 2. Démarrage rapide

1. Connectez-vous au gestionnaire MODX et installez le ZIP approprié via la gestion des paquets.
2. Ouvrez « Composants → MODX MCP ». Vérifiez HTTPS, les autorisations et le jeton API ; une nouvelle installation est désactivée par défaut.
3. Activez `modxmcp.enabled` uniquement lorsque l’accès est sécurisé. Placez le jeton dans la configuration privée du client.

## 3. Node.js / MCP

Configurez l’application MCP compatible avec stdio suivant l’exemple. Remplacez le domaine et le jeton fictifs ; épinglez la version publiée v1.2.4.

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/modx3-mcp#v1.2.4"],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

## 4. Vérifier la connexion

Redémarrez le client et testez d’abord une lecture, par exemple `modx_system_info` ou `modx_project_overview`. La réponse GET de santé ne valide pas le jeton.

## Mettre à jour une installation existante

Une mise à jour normale de `modx2mcp` ou `modx3mcp` conserve les paramètres et le jeton. Une ancienne installation MODX 2 utilisant le nom de paquet `modxmcp` doit être traitée comme une autre identité : sauvegardez d’abord la configuration et le jeton avant toute désinstallation.

## Dépannage

- `401`: `MODX_MCP_TOKEN` / `modxmcp.api_token`
- `403`: `modxmcp.enabled`, `modxmcp.require_https`, `modxmcp.allowed_ips`
- `404`: `/assets/components/modxmcp/api.php`
- `413`: `modxmcp.max_payload_bytes`
- `500`: MODX error log and `error_id`.

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.
