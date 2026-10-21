# Sécurité et modèle de confiance — MODX MCP

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md)

**Le jeton API est un secret administratif.** Les requêtes authentifiées utilisent un compte de service MODX doté des droits sudo. Un jeton valide peut permettre des modifications étendues, notamment du code PHP exécutable.

**Important :** désactiver des groupes ne remplace pas l’autorisation côté serveur. Les clients HTTP directs ne bénéficient pas automatiquement des protections du client Node.js officiel.

## Sécurité et modèle de confiance

1. Utilisez HTTPS et vérifiez la configuration TLS du proxy inverse.
2. Traitez le jeton API comme un identifiant administrateur et ne le divulguez jamais.
3. Limitez l’accès aux adresses IP connues ou à un réseau sécurisé si possible.
4. Gardez `modxmcp.allow_run_processor=0` et `modxmcp.allow_root_filesystem_read=0` sauf nécessité explicite.
5. Les groupes de fonctionnalités désactivés ne constituent pas des permissions individuelles.
6. Conservez `modxmcp.debug=0` en production pour éviter les erreurs détaillées.
7. Maintenez des sauvegardes indépendantes et utilisez `dry_run` avant les opérations à risque.

## HTTP / MCP

- `POST` JSON uses `X-MCP-Token` over HTTPS.
- `GET` health/version is **not** proof that the client token is valid.
- `modxmcp.disabled_groups` is **not** an independent authorization boundary.
- External scripts calling `api.php` directly do not inherit Node.js client safeguards such as some backups and confirmations.

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.
