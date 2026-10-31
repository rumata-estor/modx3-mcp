# Paquet MODX 2 — MODX MCP 1.2.5

[English](modx2mcp-1.2.5.md) · [Русский](modx2mcp-1.2.5.ru.md) · [Español](modx2mcp-1.2.5.es.md) · [Deutsch](modx2mcp-1.2.5.de.md) · [Français](modx2mcp-1.2.5.fr.md) · [Українська](modx2mcp-1.2.5.uk.md) · [Қазақша](modx2mcp-1.2.5.kk.md) · [Nederlands](modx2mcp-1.2.5.nl.md) · [Polski](modx2mcp-1.2.5.pl.md)

[Installation](../INSTALL.fr.md) · [Sécurité](../SECURITY.fr.md) · [Paramètres](../SETTINGS.fr.md) · [GitHub source](https://github.com/rumata-estor/modx3-mcp/tree/main)

**MODX Revolution 2.8.x · 1.2.5-pl**

MODX MCP connecte les assistants IA aux sites MODX grâce au Model Context Protocol. Le composant PHP fournit une API HTTP authentifiée, tandis que le client Node.js local l’expose en tant que serveur MCP via stdio. Aucun serveur d’orchestration supplémentaire n’est nécessaire.

## Télécharger

**Statut :** les ZIP de transport 1.2.5 et les sommes SHA-256 ne sont pas encore publiés. [Dernière version publiée : 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4)

## Démarrage rapide

1. Connectez-vous au gestionnaire MODX et installez le ZIP approprié via la gestion des paquets.
2. Ouvrez « Composants → MODX MCP ». Vérifiez HTTPS, les autorisations et le jeton API ; une nouvelle installation est désactivée par défaut.
3. Activez `modxmcp.enabled` uniquement lorsque l’accès est sécurisé. Placez le jeton dans la configuration privée du client.

## Mettre à jour une installation existante

Une mise à jour normale de `modx2mcp` ou `modx3mcp` conserve les paramètres et le jeton. Une ancienne installation MODX 2 utilisant le nom de paquet `modxmcp` doit être traitée comme une autre identité : sauvegardez d’abord la configuration et le jeton avant toute désinstallation.

## Sécurité et modèle de confiance

**Le jeton API est un secret administratif.** Les requêtes authentifiées utilisent un compte de service MODX doté des droits sudo. Un jeton valide peut permettre des modifications étendues, notamment du code PHP exécutable.

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.

## Nouveautés de 1.2.5-pl

Six nouveaux outils MCP gèrent les catégories des éléments MODX (modCategory) : lister, consulter, créer, renommer ou déplacer, supprimer en sécurité et affecter une catégorie aux chunks, snippets, modèles, TVs et plugins.

La suppression vérifie les sous-catégories, les éléments et les ensembles de propriétés. Les ressources et miniShop2 restent inchangés.

**199 actions serveur / 198 outils MCP publics**. MODX Revolution 2.8.x / 3.x. Les mises à jour normales conservent les paramètres et le jeton API.
