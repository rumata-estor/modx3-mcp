# Installer et connecter MODX MCP

[English](INSTALL.md) · [Русский](INSTALL.ru.md) · [Español](INSTALL.es.md) · [Deutsch](INSTALL.de.md) · [Français](INSTALL.fr.md) · [Українська](INSTALL.uk.md) · [Қазақша](INSTALL.kk.md) · [Nederlands](INSTALL.nl.md) · [Polski](INSTALL.pl.md) · [Fonctionnalités](CAPABILITIES.fr.md) · [Référence de l’API](API.fr.md) · [Sécurité](SECURITY.fr.md)

MODX MCP permet à un client IA standard compatible MCP de travailler avec **MODX Revolution 2.8.x et 3.x**. Une installation classique nécessite un paquet de transport MODX, **Node.js 18+** sur la machine exécutant votre client MCP et une application capable de démarrer des serveurs MCP via **stdio**.

**Aucun serveur d’orchestration distinct n’est nécessaire.** Le composant MODX héberge une API HTTP sur votre site ; le processus local `modx-mcp` expose cette API aux clients MCP via `stdio`. Le point de terminaison PHP n’est pas lui-même un serveur MCP stdio direct.

## 1. Choisir le paquet MODX

- **MODX 2.8.x :** `modx2mcp-1.2.5-pl.transport.zip`.
- **MODX 3.x :** `modx3mcp-1.2.5-pl.transport.zip`.

> **Statut :** le code de 1.2.5 est préparé, mais les ZIP de transport ne sont pas encore publiés. Pour installer, utilisez la [version 1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4).

Les deux variantes utilisent le même contrat public d’outils et le même client Node.js. Téléchargez le paquet approprié depuis [GitHub Releases](https://github.com/rumata-estor/modx3-mcp/releases) ou un fournisseur de paquets MODX pris en charge.

**Installations MODX 2 existantes :** lors de la migration de l’ancienne identité de paquet `modxmcp` vers `modx2mcp`, il est recommandé de désinstaller proprement l’ancien paquet, puis d’installer `modx2mcp`. Une désinstallation complète supprime les anciens paramètres `modxmcp.*` et le jeton API. Conservez la configuration utile selon vos besoins et mettez ensuite à jour les identifiants de votre client. Les mises à niveau normales ultérieures de `modx2mcp` conservent le jeton actuel.

### Tableau de bord du gestionnaire

Dans **Composants → MODX MCP**, copiez l’URL de l’API, affichez/copiez le jeton existant uniquement si votre compte du gestionnaire possède la permission **settings**, activez ou désactivez les groupes de fonctionnalités facultatifs et configurez **Auto-static** / **Journal d’audit**. Les changements de paramètres et de fonctionnalités ne prennent effet qu’après avoir appuyé sur leurs boutons d’enregistrement. Le **Graphe des dépendances** est accessible par le lien du graphe ; sa zone de dessin se zoome avec la molette de la souris, tandis que le panneau environnant défile pour afficher les filtres et les détails. **Régénérer le jeton** invalide tous les identifiants clients existants ; les mises à jour ordinaires du paquet ne le font pas. L’indication « MCP activé » sur la page ne constitue pas une vérification en direct de la connexion d’un client particulier.

## 2. Installer le paquet de transport

1. Connectez-vous au gestionnaire MODX avec un compte autorisé à installer des paquets.
2. Ouvrez la gestion des paquets, téléversez le ZIP de transport correspondant à votre version majeure de MODX et installez-le.
3. Ouvrez le composant MODX MCP ou les paramètres système avec l’espace de noms `modxmcp`.
4. Vérifiez `modxmcp.api_token` et les restrictions réseau. Les nouvelles installations démarrent avec `modxmcp.enabled = 0` ; activez manuellement le composant après avoir sécurisé l’accès.
5. Copiez le jeton dans la **configuration de votre client MCP**. Traitez-le comme un secret, jamais comme un exemple public.

L’installateur génère un jeton aléatoire cryptographiquement sûr si le paramètre est vide. Une mise à niveau ordinaire conserve un jeton existant.

## 3. Localiser le point de terminaison du site

L’URL standard est :

```text
https://example.com/assets/components/modxmcp/api.php
```

Utilisez votre propre domaine ; les déploiements personnalisés peuvent modifier le chemin réel. **HTTPS** est recommandé et obligatoire par défaut.

`GET` renvoie une réponse limitée, non authentifiée, sur l’état de fonctionnement et la version du composant. Les requêtes administratives utilisent des requêtes JSON `POST` avec `X-MCP-Token`.

## 4. Configurer votre hôte MCP

Sur la machine exécutant votre application IA, installez **Node.js 18+** avec npm/`npx`. Ajoutez le serveur MCP à la configuration de votre hôte (l’emplacement et la structure de configuration acceptée dépendent de votre application) :

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.2.4"
      ],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "YOUR_SITE_PRIVATE_TOKEN"
      }
    }
  }
}
```

`MODX_MCP_SITE_URL` est le point de terminaison de l’API HTTP PHP. `MODX_MCP_TOKEN` est le jeton propre à ce site. Sur les systèmes de production, fixez le **tag d’une version publiée** au lieu de suivre `main`. Assurez-vous que votre hôte permet de lancer un serveur MCP local via `stdio`.

## 5. Confirmer la connectivité

Redémarrez l’intégration MCP ou actualisez les serveurs de l’hôte. Recherchez le serveur `modx` et les outils préfixés par `modx_`. Commencez par une requête en lecture seule, par exemple :

```text
Show the MODX version and an overview of the site structure. Make no changes.
```

Cela peut utiliser `modx_system_info` et `modx_project_overview`. Votre hôte peut afficher moins de 192 outils, selon la version du serveur, les groupes désactivés et les extensions installées.

La [référence complète des paramètres système](SETTINGS.fr.md) décrit **les 16 paramètres**, leurs valeurs par défaut, leur comportement et leurs implications de sécurité.

## 6. Paramètres de sécurité à vérifier

| Paramètre | Signification |
| --- | --- |
| `modxmcp.enabled` | Active ou désactive l’API. |
| `modxmcp.api_token` | Identifiant secret de l’API. |
| `modxmcp.require_https` | Impose HTTPS ; activé par défaut. |
| `modxmcp.allowed_ips` | Liste facultative d’IP et de plages CIDR IPv4 autorisées ; vide, elle autorise toute source. |
| `modxmcp.trust_proxy_https` | Ne faire confiance à l’en-tête HTTPS du proxy inverse que derrière un proxy correctement configuré. |
| `modxmcp.disabled_groups` | Désactive certaines familles d’outils ; **ne constitue pas une frontière de sécurité entre rôles**. |
| `modxmcp.allow_run_processor` | Autorise l’appel direct de processeurs MODX ; désactivé par défaut. |
| `modxmcp.max_payload_bytes` | Limite de taille des requêtes. |
| `modxmcp.debug` | Détails des erreurs ; à laisser désactivé en production. |

**Important :** les requêtes sont exécutées sous l’identité d’un utilisateur de service MODX disposant de privilèges `sudo`. La divulgation d’un jeton valide peut permettre des modifications administratives et la modification de PHP exécutable dans les éléments. Lisez le [guide de sécurité](SECURITY.fr.md).

## Dépannage

| Symptôme | Vérifications |
| --- | --- |
| Le serveur MCP ne démarre pas | Node.js 18+, `npx`, résolution du paquet et configuration de l’hôte. |
| `401 Unauthorized` | `MODX_MCP_TOKEN` doit correspondre à `modxmcp.api_token` ; vérifiez la rotation du jeton. |
| `403` | Paramètre d’activation du composant, HTTPS, configuration du proxy inverse et IP autorisée. |
| `404` | Chemin du point de terminaison, installation du composant et routage du serveur web. |
| `413` | La requête a dépassé `modxmcp.max_payload_bytes`. |
| Outil indisponible | Versions du client/serveur, groupes désactivés, installation de l’extension requise. |
| `500` | Journaux d’erreurs MODX et `error_id` ; évitez d’afficher publiquement les messages de débogage. |

Ne placez pas les jetons API dans les chaînes de requête des URL ni dans les signalements de problèmes. Les clients HTTP personnalisés doivent envoyer `X-MCP-Token` via TLS.

## Étapes suivantes

- [Fonctionnalités](CAPABILITIES.fr.md) — ce que MODX MCP peut faire.
- [Référence de l’API](API.fr.md) — outils et paramètres d’entrée.
- [Sécurité](SECURITY.fr.md) — risques et configuration de protection.
- [README du projet](../README.fr.md) — présentation du projet.
- [Validation](VALIDATION.fr.md) — notes sur les tests de compatibilité.
