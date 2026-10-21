# Paramètres système — MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md)

Les 16 paramètres se trouvent dans « Système → Paramètres système », espace de noms `modxmcp`. Les valeurs par défaut sont partagées entre les paquets MODX 2 et MODX 3.

**Important :** désactiver des groupes ne remplace pas l’autorisation côté serveur. Les clients HTTP directs ne bénéficient pas automatiquement des protections du client Node.js officiel.

| Clé | Défaut | Description |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | Active ou désactive les actions API authentifiées ; désactivé lors de la première installation. |
| `modxmcp.api_token` | `(generated)` | Jeton secret envoyé dans `X-MCP-Token` ; conservé lors des mises à jour normales. |
| `modxmcp.service_user_id` | `0` | Utilisateur de service MODX ; `0` choisit le premier compte sudo actif. |
| `modxmcp.require_https` | `1` | Impose HTTPS aux requêtes ; à conserver en production. |
| `modxmcp.trust_proxy_https` | `0` | Accepte les en-têtes HTTPS uniquement derrière un proxy inverse de confiance. |
| `modxmcp.allowed_ips` | `(empty)` | Adresses IP et plages CIDR IPv4 autorisées ; vide signifie sans filtrage IP. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Groupes d’outils désactivés séparés par des virgules ; pas une frontière de sécurité complète. |
| `modxmcp.allow_run_processor` | `0` | Autorise l’exécution de processeurs MODX quelconques ; désactivé par défaut. |
| `modxmcp.allow_root_filesystem_read` | `0` | Autorise la lecture de la source de médias racine « Filesystem » ; désactivé par défaut. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Chemins de code autorisés à l’inspection des composants ; pas un bac à sable général. |
| `modxmcp.max_payload_bytes` | `1048576` | Taille maximale du JSON POST en octets ; dépassement : code 413. |
| `modxmcp.max_read_bytes` | `262144` | Nombre maximal d’octets renvoyés lors d’une lecture partielle de fichier. |
| `modxmcp.auto_static` | `0` | Crée automatiquement des fichiers statiques pour les éléments modifiés. |
| `modxmcp.audit_log` | `1` | Journalise certaines opérations de modification, pas nécessairement toutes les requêtes. |
| `modxmcp.debug` | `0` | Expose les erreurs internes détaillées ; à désactiver en production. |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Chemin du cœur du composant ; à modifier uniquement si l’installation l’exige. |

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.
