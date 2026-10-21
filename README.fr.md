# MODX MCP — gérer MODX avec un assistant IA

[English](README.md) · [Русский](README.ru.md) · [Español](README.es.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Українська](README.uk.md) · [Қазақша](README.kk.md) · [Nederlands](README.nl.md) · [Polski](README.pl.md)

**MODX MCP 1.2.4 · MODX Revolution 2.8.x / 3.x · 193 / 192**

MODX MCP connecte les assistants IA aux sites MODX grâce au Model Context Protocol. Le composant PHP fournit une API HTTP authentifiée, tandis que le client Node.js local l’expose en tant que serveur MCP via stdio. Aucun serveur d’orchestration supplémentaire n’est nécessaire.

## À qui s’adresse ce projet ?

Aux développeurs, agences et administrateurs souhaitant gérer des sites MODX avec des outils IA contrôlés.

## Fonctions et outils

- Rechercher, consulter et modifier des ressources, modèles, chunks, snippets, plugins et TV.
- Analyser les dépendances, les références rompues et le code potentiellement inutilisé grâce au graphe.
- Gérer les paramètres système, sources de médias, paquets et extensions compatibles.
- Protéger les modifications avec des préconditions, des simulations et des sauvegardes.

## Démarrage rapide

Choisissez l’archive adaptée à la version principale de MODX. Le client MCP nécessite Node.js 18 ou une version supérieure.

- **MODX 2.8.x:** [Paquet MODX 2](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx2mcp-1.2.4-pl.transport.zip)
- **MODX 3.x:** [Paquet MODX 3](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/modx3mcp-1.2.4-pl.transport.zip)
- [GitHub v1.2.4](https://github.com/rumata-estor/modx3-mcp/releases/tag/v1.2.4) · [SHA256SUMS](https://github.com/rumata-estor/modx3-mcp/releases/download/v1.2.4/SHA256SUMS)

Connectez-vous au gestionnaire MODX et installez le ZIP approprié via la gestion des paquets.

Ouvrez « Composants → MODX MCP ». Vérifiez HTTPS, les autorisations et le jeton API ; une nouvelle installation est désactivée par défaut.

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

Activez `modxmcp.enabled` uniquement lorsque l’accès est sécurisé. Placez le jeton dans la configuration privée du client.

## Sécurité et modèle de confiance

**Le jeton API est un secret administratif.** Les requêtes authentifiées utilisent un compte de service MODX doté des droits sudo. Un jeton valide peut permettre des modifications étendues, notamment du code PHP exécutable.

**Important :** désactiver des groupes ne remplace pas l’autorisation côté serveur. Les clients HTTP directs ne bénéficient pas automatiquement des protections du client Node.js officiel.

## Documentation

[Installation](docs/INSTALL.fr.md) · [Sécurité](docs/SECURITY.fr.md) · [Paramètres](docs/SETTINGS.fr.md) · [Fonctions](docs/CAPABILITIES.fr.md) · [API](docs/API.fr.md)

**Références techniques complètes :** [paramètres API (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/API.md) · [sécurité (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/docs/SECURITY.md) · [développement (English)](https://github.com/rumata-estor/modx3-mcp/blob/main/DEVELOPMENT.md). Les tables techniques détaillées restent en anglais.
