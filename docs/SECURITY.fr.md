# MODX MCP — sécurité et modèle de confiance

[English](SECURITY.md) · [Русский](SECURITY.ru.md) · [Español](SECURITY.es.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Українська](SECURITY.uk.md) · [Қазақша](SECURITY.kk.md) · [Nederlands](SECURITY.nl.md) · [Polski](SECURITY.pl.md) · [Installation](INSTALL.fr.md) · [Fonctionnalités](CAPABILITIES.fr.md) · [API](API.fr.md)

Ce guide décrit la frontière de confiance de la version publique **MODX MCP 1.2.4**, ses paramètres de sécurité et ses limites importantes. Il ne décrit aucune infrastructure externe de gestion d’agents.

## La règle essentielle

**Traitez le jeton API MODX MCP comme un identifiant d’administration.** La seule connaissance de l’URL du point de terminaison ou des noms d’outils documentés n’autorise pas une requête. En revanche, un `X-MCP-Token` valide peut permettre de vastes modifications du site.

Après avoir authentifié une requête, le serveur sélectionne un compte de service doté des permissions MODX **`sudo`**. `modxmcp.service_user_id` peut désigner un compte `sudo` actif ; à sa valeur par défaut de zéro, le premier utilisateur `sudo` actif est sélectionné. Il ne s’agit **pas** d’une autorisation par utilisateur externe fondée sur les permissions individuelles de l’opérateur dans le gestionnaire.

Un détenteur du jeton peut potentiellement modifier des snippets, plugins, modèles, ressources, paramètres et autres contenus sensibles. Modifier du PHP dans des éléments MODX exécutables peut conduire à l’exécution de code PHP arbitraire avec les privilèges du processus de l’application web.

## Protections du point de terminaison PHP

| Contrôle | Comportement et limites |
| --- | --- |
| Jeton | Exige `X-MCP-Token` ; vérifié par rapport au paramètre `modxmcp.api_token`. |
| HTTPS | `modxmcp.require_https` est activé par défaut ; vérifiez la configuration de terminaison TLS. |
| Liste d’IP autorisées | `modxmcp.allowed_ips` compare `REMOTE_ADDR` à des IP exactes ou à des plages CIDR IPv4 ; une valeur vide autorise toute adresse du pair. |
| Proxy inverse | `modxmcp.trust_proxy_https` est désactivé par défaut ; ne l’activez que derrière un proxy inverse fiable et correctement configuré. |
| Interrupteur principal | `modxmcp.enabled`, lorsqu’il est désactivé, désactive les opérations authentifiées de l’API. |
| Limite de taille | `modxmcp.max_payload_bytes` limite la taille des charges utiles JSON entrantes. |
| Accès direct aux processeurs | `modxmcp.allow_run_processor` est désactivé par défaut. |
| Groupes de fonctionnalités | `modxmcp.disabled_groups` désactive les familles d’outils sélectionnées. |

**Les familles d’outils désactivées ne constituent pas une frontière d’autorisation complète.** Les outils standard de création/mise à jour/suppression des paramètres système refusent les clés commençant par `modxmcp.`, y compris les tentatives de renommer d’autres paramètres avec ce préfixe. La rotation du jeton reste une action privilégiée distincte. Ce n’est pas un environnement isolé : un appelant authentifié capable de modifier du PHP exécutable (ou d’appeler des processeurs explicitement activés) peut toujours affecter la sécurité du site. Des opérateurs différents nécessitent des contrôles d’autorisation indépendants côté serveur.

Un point de terminaison correctement authentifié n’implique pas une limitation intégrée du débit des requêtes. Si votre déploiement en a besoin, imposez cette limitation au niveau du proxy inverse ou du serveur web.

## Client MCP Node.js standard et appels HTTP directs

Le client Node.js fourni comprend des protections supplémentaires pour certaines modifications, telles que des sauvegardes préalables de certains objets, des activations explicites par variables d’environnement pour les opérations risquées et la coordination des appels. **Les appelants personnalisés envoyant directement des requêtes POST à `api.php` n’exécutent pas ces protections côté client.**

Le serveur PHP met aussi en œuvre des verrouillages et des contrôles de préconditions pour certaines modifications, ainsi que des enregistrements d’audit. Cela ne rend pas l’accès HTTP direct équivalent au passage par le client MCP standard.

Si vous écrivez votre propre intégration HTTP, mettez en œuvre vos propres sauvegardes, autorisations, confirmations et procédures de récupération.

## Configuration recommandée en production

1. Utilisez HTTPS avec un certificat valide.
2. Conservez le jeton dans une configuration client protégée ou un gestionnaire de secrets. Ne l’intégrez jamais à des URL, du code public, des captures d’écran, des tickets d’assistance ou des journaux.
3. Lorsque c’est possible, limitez l’accès au point de terminaison à des IP clientes connues ou à un réseau/proxy sécurisé. Vérifiez l’adresse que PHP voit réellement dans `REMOTE_ADDR`.
4. Conservez `modxmcp.allow_run_processor = 0`, sauf besoin précis.
5. Désactivez les groupes de fonctionnalités inutilisés, tout en reconnaissant qu’il ne s’agit pas d’un contrôle d’accès par utilisateur.
6. Laissez `modxmcp.debug` désactivé en production.
7. Maintenez des sauvegardes indépendantes et testées du site MODX et de la base de données.
8. Utilisez `dry_run` sur les outils destructifs en masse lorsqu’il est disponible ; approuvez manuellement les modifications à fort impact du code, des permissions et des paramètres.
9. Renouvelez le jeton API si une compromission est suspectée ; mettez rapidement à jour la configuration de chaque client autorisé.

## Secrets et informations privées dans les réponses des outils

`modx_list_system_settings` et `modx_get_system_setting` peuvent renvoyer des valeurs contenant des **jetons API et d’autres secrets**. Ne transmettez pas les résultats complets à des journaux publics, des signalements de problèmes, des chaînes de traitement analytique ou un modèle externe sans évaluer le risque pour la confidentialité.

La lecture de fichiers, les journaux d’erreurs, les enregistrements utilisateurs et les données de commandes de commerce électronique peuvent aussi contenir des informations privées ou sensibles.

## Portée de la protection

La documentation ne peut pas empêcher un client IA de mal comprendre une requête. Une seule modification d’un snippet, plugin ou modèle partagé peut affecter plusieurs pages. Toute application accordant des droits différents à plusieurs opérateurs doit faire respecter ces droits dans son périmètre serveur de confiance, et non uniquement à l’aide d’instructions dans les prompts ou d’une liste d’outils masquée.

Si vous découvrez une vulnérabilité, ne publiez pas d’identifiants fonctionnels ni de données clients dans un ticket GitHub public. Révoquez les secrets exposés et fournissez suffisamment d’informations expurgées pour que les mainteneurs puissent reproduire le problème en toute sécurité.

## Concurrence des TV de ressources et CAS v2

Depuis le paquet de transport publié en version 1.2.3 pour MODX 2 et MODX 3, `get_site_state` indique `precondition_version=2` pour le CAS des TV côté Connector. Une requête protégée `modx_update_resource_tvs` inclut des `_runtime_preconditions` gérées par le Runtime : la révision observée du site, l’application stricte de la révision et la table `expected_tvs` contenant exactement les noms des TV modifiées. Le Connector vérifie ces conditions sous son verrou de modification du site et rejette les valeurs obsolètes au lieu de les écraser silencieusement.

**Limite :** le schéma MCP brut n’exige pas cette protection interne ; un appel direct non protégé ne doit donc **pas** être présenté comme sûr face à des agents concurrents. Les utilisateurs de Site Agent doivent emprunter le parcours Runtime `modx-tv-change` ou Executor ayant fait l’objet d’une revue. Un échec CAS exige de nouvelles lectures et un nouveau plan, pas des tentatives répétées à l’aveugle.

## Implémentation

- [Point de terminaison PHP et validation du jeton](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/endpoint/api.common.php)
- [Paramètres par défaut](https://github.com/rumata-estor/modx3-mcp/blob/main/_build/data/transport.settings.php)
- [Client MCP standard](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js)
- [Répartiteur d’actions côté serveur](https://github.com/rumata-estor/modx3-mcp/blob/main/core/components/modxmcp/model/modxmcp.class.php)
