# Paquet MODX 3 — MODX MCP 1.2.4

[English](modx3mcp-1.2.4.md) · [Русский](modx3mcp-1.2.4.ru.md) · [Español](modx3mcp-1.2.4.es.md) · [Deutsch](modx3mcp-1.2.4.de.md) · [Français](modx3mcp-1.2.4.fr.md) · [Українська](modx3mcp-1.2.4.uk.md) · [Қазақша](modx3mcp-1.2.4.kk.md) · [Nederlands](modx3mcp-1.2.4.nl.md) · [Polski](modx3mcp-1.2.4.pl.md)

[Installation](../INSTALL.fr.md) · [Sécurité](../SECURITY.fr.md) · [Paramètres](../SETTINGS.fr.md) · [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

**MODX Revolution 3.x · 1.2.4-pl**

MODX MCP connecte les assistants IA aux sites MODX grâce au Model Context Protocol. Le composant PHP fournit une API HTTP authentifiée, tandis que le client Node.js local l’expose en tant que serveur MCP via stdio. Aucun serveur d’orchestration supplémentaire n’est nécessaire.

## Télécharger

[modx3mcp-1.2.4-pl.transport.zip](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

## Démarrage rapide

1. Connectez-vous au gestionnaire MODX et installez le ZIP approprié via la gestion des paquets.
2. Ouvrez « Composants → MODX MCP ». Vérifiez HTTPS, les autorisations et le jeton API ; une nouvelle installation est désactivée par défaut.
3. Activez `modxmcp.enabled` uniquement lorsque l’accès est sécurisé. Placez le jeton dans la configuration privée du client.

## Mettre à jour une installation existante

Une mise à jour normale de `modx2mcp` ou `modx3mcp` conserve les paramètres et le jeton. Une ancienne installation MODX 2 utilisant le nom de paquet `modxmcp` doit être traitée comme une autre identité : sauvegardez d’abord la configuration et le jeton avant toute désinstallation.

## Sécurité et modèle de confiance

**Le jeton API est un secret administratif.** Les requêtes authentifiées utilisent un compte de service MODX doté des droits sudo. Un jeton valide peut permettre des modifications étendues, notamment du code PHP exécutable.

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.
