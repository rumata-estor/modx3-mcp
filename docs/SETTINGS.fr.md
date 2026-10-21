# Paramètres système de MODX MCP

[English](SETTINGS.md) · [Русский](SETTINGS.ru.md) · [Español](SETTINGS.es.md) · [Deutsch](SETTINGS.de.md) · [Français](SETTINGS.fr.md) · [Українська](SETTINGS.uk.md) · [Қазақша](SETTINGS.kk.md) · [Nederlands](SETTINGS.nl.md) · [Polski](SETTINGS.pl.md) · [Installation](INSTALL.fr.md) · [Sécurité](SECURITY.fr.md)

Pour **MODX MCP 1.2.4** sur MODX 2.8.x et 3.x. Ce document couvre les **16 paramètres système** définis par les deux paquets de transport. Les valeurs par défaut proviennent de `_build/data/transport.settings.php` ; le comportement a été vérifié par rapport à l’implémentation PHP.

Dans le gestionnaire MODX : **Système → Paramètres système**, espace de noms `modxmcp`. **Composants → MODX MCP** permet aussi de modifier le journal d’audit, Auto-static et les groupes de fonctionnalités, ainsi que d’afficher/copier le jeton API en toute sécurité. Modifiez les autres options dans les paramètres système. Actualisez les caches MODX lorsque nécessaire et actualisez les fonctionnalités du client MCP après les changements.

**Sécurité :** les opérations autorisées par le jeton s’exécutent sous l’identité d’un **utilisateur de service MODX doté de privilèges sudo**. Gardez le jeton secret. Les interrupteurs de fonctionnalités **n’établissent pas une frontière de permissions**.

## Connexion et identité

| Paramètre | Valeur par défaut | Valeurs et comportement |
| --- | --- | --- |
| `modxmcp.enabled` | `0` | `0` : les actions POST de l’API sont refusées. `1` : API activée. Le point de terminaison `GET` limité à l’état de fonctionnement et à la version fonctionne encore avec `0` lorsque les conditions HTTPS sont satisfaites. N’activez l’API qu’une fois les contrôles d’accès prêts. |
| `modxmcp.api_token` | Valeur vide dans le paquet ; un jeton aléatoire est généré à l’installation **s’il est absent** | Chaîne secrète envoyée dans l’en-tête `X-MCP-Token`. Un jeton absent/incorrect entraîne `401`. Les mises à niveau normales conservent le jeton. Les administrateurs ayant la permission `settings` peuvent utiliser **Afficher** / **Copier**. La rotation du jeton invalide les identifiants clients existants. |
| `modxmcp.service_user_id` | `0` | `0` sélectionne le premier **utilisateur MODX sudo actif**, par ordre d’ID ; un ID positif exige un compte sudo actif existant. Pour un comportement prévisible, choisissez un compte de service dédié. Ce n’est pas l’identité du client MCP externe. |

## Réseau et sécurité

| Paramètre | Valeur par défaut | Valeurs et comportement |
| --- | --- | --- |
| `modxmcp.require_https` | `1` | `0`/`1`. Exige HTTPS, y compris pour la réponse d’état `GET`, sinon renvoie `403`. Conservez `1` sur les sites déployés. |
| `modxmcp.trust_proxy_https` | `0` | `0`/`1`. Si activé, `X-Forwarded-Proto: https` est accepté comme preuve de HTTPS. Ne l’activez que derrière un proxy inverse de confiance qui écrase les en-têtes fournis par le client. |
| `modxmcp.allowed_ips` | Vide | Adresses IP exactes et **CIDR IPv4** séparés par des virgules, par exemple `203.0.113.5,198.51.100.0/24`. Une valeur vide autorise toute source possédant un jeton valide. Vérifie `REMOTE_ADDR`, **pas** `X-Forwarded-For`. Les correspondances IPv6 exactes fonctionnent ; le CIDR IPv6 n’est pas pris en charge. Des valeurs incorrectes provoquent `403`. |
| `modxmcp.disabled_groups` | `versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon` | Groupes désactivés séparés par des virgules : `versionx`, `virtualpage`, `minishop2`, `migx`, `clientconfig`, `access`, `property_sets`, `contexts`, `package_management`, `namespaces`, `lexicon`. Une valeur vide active tous les groupes par ailleurs pris en charge. **Ne constitue pas une frontière de sécurité entre rôles** ; les actions de base peuvent toujours modifier un site. |
| `modxmcp.allow_run_processor` | `0` | `0`/`1`. Autorise l’opération générale `run_processor`, y compris les processeurs MODX personnalisés. Elle élargit la portée des opérations administratives : laissez-la désactivée sauf besoin précis. |
| `modxmcp.allow_root_filesystem_read` | `0` | `0`/`1`. Autorise la navigation et la lecture dans la source média racine `Filesystem`. Peut exposer davantage de fichiers du serveur. Laissez désactivé sauf nécessité. |
| `modxmcp.component_code_roots` | `core/components,assets/components` | Répertoires sources des composants séparés par des virgules, relatifs à `base_path` ou absolus. Accepte les espaces réservés `{core_path}`, `{assets_path}`, `{base_path}`. Limite **uniquement l’inspection du code des composants**, et non toutes les actions MCP sur le système de fichiers. Évitez d’étendre cette portée à la racine du système de fichiers du serveur. |

## Limites

| Paramètre | Valeur par défaut | Valeurs et comportement |
| --- | --- | --- |
| `modxmcp.max_payload_bytes` | `1048576` (1 MiB) | Taille maximale en octets du corps JSON des requêtes POST de l’API HTTP ; un dépassement entraîne `413`. Les valeurs `<=0` suppriment ce contrôle. Conservez une limite supérieure positive. |
| `modxmcp.max_read_bytes` | `262144` (256 KiB) | Taille maximale du bloc renvoyé lors de la lecture de fichiers ; `<=0` rétablit 262144. Ce n’est **pas** une limite de taille du fichier entier : les lectures peuvent utiliser `offset` et `bytes`/`length`. |

## Comportement et diagnostic

| Paramètre | Valeur par défaut | Valeurs et comportement |
| --- | --- | --- |
| `modxmcp.auto_static` | `0` | `0`/`1`. Si activé, les chunks, snippets, modèles et plugins créés/mis à jour via MCP deviennent des fichiers statiques sous `core/elements/`. Nécessite des permissions d’écriture, des sauvegardes et des pratiques de déploiement appropriées. |
| `modxmcp.audit_log` | `1` | `0`/`1`. Consigne les actions de modification prises en charge dans les journaux MODX et dans `core/components/modxmcp/logs/audit.log`. Ce n’est **pas** un enregistrement exhaustif de chaque requête. Laissez activé en production, avec des contrôles d’accès et de taille. |
| `modxmcp.debug` | `0` | `0`/`1`. Si activé, des détails sur les exceptions internes peuvent apparaître dans les réponses d’erreur HTTP. Si désactivé, les appelants reçoivent une erreur générique et un `error_id`. Conservez `0` en production. |

## Chemin du cœur du composant

| Paramètre | Valeur par défaut | Valeurs et comportement |
| --- | --- | --- |
| `modxmcp.core_path` | `{core_path}components/modxmcp/` | Répertoire du cœur du composant (accepte aussi `[[++core_path]]`). Utilisé par l’API HTTP pour charger l’implémentation et la version. Ne le modifiez que pour des installations non standard vérifiées ; des valeurs incorrectes rendent l’API inutilisable. |

## Première connexion sécurisée

1. Installez le paquet de transport correspondant à votre version majeure de MODX. Les nouvelles installations démarrent avec `modxmcp.enabled=0` ; le jeton est généré automatiquement et les jetons existants sont conservés lors des mises à niveau ordinaires.
2. Configurez HTTPS, éventuellement les restrictions IP, les paramètres du proxy inverse et `service_user_id`.
3. Utilisez **Copier** dans le tableau de bord du gestionnaire pour placer le jeton API dans le stockage de secrets de votre client MCP. Ne le divulguez pas.
4. N’activez que les fonctionnalités nécessaires. Laissez `allow_run_processor` et `allow_root_filesystem_read` désactivés, sauf justification.
5. Activez l’API et vérifiez une **action authentifiée en lecture seule**. Une réponse `GET` de version/d’état ne prouve **pas** que le jeton du client est valide.
