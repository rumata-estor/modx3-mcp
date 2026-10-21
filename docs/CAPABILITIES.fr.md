# MODX MCP — fonctionnalités et catalogue des outils

[English](CAPABILITIES.md) · [Русский](CAPABILITIES.ru.md) · [Español](CAPABILITIES.es.md) · [Deutsch](CAPABILITIES.de.md) · [Français](CAPABILITIES.fr.md) · [Українська](CAPABILITIES.uk.md) · [Қазақша](CAPABILITIES.kk.md) · [Nederlands](CAPABILITIES.nl.md) · [Polski](CAPABILITIES.pl.md) · [Installation](INSTALL.fr.md) · [Référence de l’API](API.fr.md)

**MODX MCP 1.2.4 · 192 outils publics · 19 groupes fonctionnels · MODX Revolution 2.8.x / 3.x**

MODX MCP fournit à un client MCP doté de capacités d’IA des opérations nommées pour travailler avec les données et les éléments MODX, au lieu d’exiger que chaque tâche soit exprimée sous forme de SQL brut ou de commandes du système de fichiers. Il prend en charge l’inspection du site, la modification du contenu et du code, les valeurs des TV, les paquets, l’administration des accès et certaines extensions tierces. La liste réelle des outils dépend de la version installée et des fonctionnalités activées.

Ce guide répond à la question **« Que puis-je faire avec MODX MCP ? »**. Pour l’ensemble des champs, types, énumérations et détails d’intégration, consultez la [référence de l’API](API.fr.md). Pour vous connecter, commencez par [Installation](INSTALL.fr.md).

**Les étiquettes d’impact sont des explications, pas des contrôles de permissions.** « Lecture » peut tout de même renvoyer des données confidentielles, telles que des valeurs de configuration. « Impact élevé » avertit qu’une commande peut rendre le site inutilisable ou compromettre sa sécurité en cas de mauvaise utilisation.

## Index des outils

| Groupe | Outils |
| --- | ---: |
| [Éléments et ressources](#éléments-et-ressources) | 15 |
| [TV des ressources](#tv-des-ressources) | 4 |
| [Types de champs TV](#types-de-champs-tv) | 2 |
| [Paramètres système](#paramètres-système) | 5 |
| [Sources médias et fichiers](#sources-médias-et-fichiers) | 13 |
| [Composants et fichiers](#composants-et-fichiers) | 4 |
| [Recherche de code et dépendances](#recherche-de-code-et-dépendances) | 5 |
| [VersionX](#versionx) | 3 |
| [VirtualPage](#virtualpage) | 17 |
| [miniShop2](#minishop2) | 22 |
| [MIGX](#migx) | 5 |
| [Utilisateurs et droits d’accès](#utilisateurs-et-droits-daccès) | 47 |
| [ClientConfig](#clientconfig) | 5 |
| [Jeux de propriétés](#jeux-de-propriétés) | 7 |
| [Contextes et paramètres de contexte](#contextes-et-paramètres-de-contexte) | 10 |
| [Paquets et fournisseurs](#paquets-et-fournisseurs) | 7 |
| [Espaces de noms](#espaces-de-noms) | 4 |
| [Lexique et traductions](#lexique-et-traductions) | 4 |
| [Maintenance et diagnostic](#maintenance-et-diagnostic) | 13 |
| **Total** | **192** |

## Éléments et ressources

Gérez les pages, modèles, chunks, snippets, plugins, TV et catégories en tant qu’objets MODX.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_elements` | Liste les éléments MODX d’un type (id + name). | Lecture |
| `modx_make_static` | Convertit un chunk/snippet/template/plugin en fichier statique sous core/elements/ (écrit le fichier, définit static=1 + source=Filesystem). | Impact élevé |
| `modx_get_element` | Lit un élément MODX (renvoie ses champs + son code complet). | Lecture |
| `modx_update_element_property` | Modifie de manière sûre une seule propriété par défaut existante, avec un contrôle CAS de la valeur attendue ; ne remplace jamais le code ni les autres propriétés. | Modifie le site |
| `modx_update_element` | Met à jour un élément MODX en REMPLAÇANT tout son contenu (envoyez le nouveau contenu complet). | Impact élevé |
| `modx_create_element` | Crée un nouvel élément MODX. | Impact élevé |
| `modx_delete_element` | Supprime un élément MODX. Exécutez D’ABORD avec dry_run:true — renvoie ce qui serait supprimé ainsi que les endroits où l’élément est encore référencé (pour les ressources : nombre de ressources enfants), sans rien supprimer. Examinez ce résultat avant la suppression réelle. | Impact élevé |
| `modx_view_element` | Affiche un chunk/snippet/template/plugin sous forme de lignes NUMÉROTÉES (comme cat -n), éventuellement limitées par start_line/end_line. | Modifie le site |
| `modx_edit_element_lines` | Modifie un chunk/snippet/template/plugin PAR LIGNE, en envoyant uniquement les lignes modifiées (inutile de renvoyer l’élément entier). | Impact élevé |
| `modx_bulk_resources` | Applique UNE opération à plusieurs ressources à la fois : publish, unpublish, set_template (nécessite template), move (nécessite parent_to et/ou context_to), ou delete. | Impact élevé |
| `modx_duplicate_resource` | Duplique une ressource. id = ressource source ; paramètres facultatifs : name (nouveau pagetitle), duplicate_children (bool), published_mode (preserve\|publish\|unpublish). | Modifie le site |
| `modx_duplicate_element` | Duplique un élément (chunk/snippet/template/plugin/tv). type + id ; name facultatif pour la copie. | Modifie le site |
| `modx_undelete_resource` | Restaure depuis la corbeille une ressource supprimée logiquement (annule sa suppression). id = id de la ressource. | Modifie le site |
| `modx_empty_recycle_bin` | Purge définitivement TOUTES les ressources de la corbeille (supprimées logiquement). | Impact élevé |
| `modx_reorder_resources` | Réordonne les ressources dans l’arborescence en définissant menuindex (et éventuellement parent). items = [{id, menuindex, parent?}], appliqué à chaque ressource. | Modifie le site |

## TV des ressources

Lisez ou mettez à jour les champs personnalisés des ressources et inspectez les valeurs de TV enregistrées.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_get_resource_tvs` | Récupère les valeurs des variables de modèle (TV) d’une ressource. | Lecture |
| `modx_update_resource_tvs` | Met à jour les valeurs des variables de modèle (TV) d’une ressource. | Modifie le site |
| `modx_list_tv_values` | Liste les valeurs explicitement stockées pour une TV sur toutes les ressources, y compris les valeurs obsolètes de ressources dont le modèle actuel n’utilise peut-être plus cette TV. | Lecture |
| `modx_clear_tv_values` | Prévisualise ou efface toutes les valeurs explicitement stockées pour une TV. | Modifie le site |

## Types de champs TV

Découvrez les types de champs TV disponibles et choisissez-en un selon le besoin.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_suggest_tv_type` | Vous hésitez sur le type de saisie TV adapté à un besoin ? Décrivez-le (en anglais ou en russe) pour obtenir des field_types candidats classés avec leurs justifications + un squelette create_element prêt à modifier pour le meilleur choix (avec les clés supplémentaires nécessaires à ce type). | Lecture |
| `modx_list_tv_input_types` | Liste les types de saisie (widgets) TV disponibles sur ce site. | Lecture |

## Paramètres système

Inspectez et modifiez la configuration susceptible d’affecter l’ensemble du site.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_system_settings` | Liste les paramètres système MODX. | Lecture |
| `modx_get_system_setting` | Récupère un paramètre système MODX par sa clé. | Lecture |
| `modx_create_system_setting` | Crée un paramètre système MODX. | Modifie le site |
| `modx_update_system_setting` | Met à jour un paramètre système MODX par sa clé. | Impact élevé |
| `modx_delete_system_setting` | Supprime un paramètre système MODX par sa clé. | Impact élevé |

## Sources médias et fichiers

Parcourez les sources médias et gérez les fichiers et dossiers qu’elles exposent.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_media_sources` | Liste les sources médias MODX. | Lecture |
| `modx_get_media_source` | Récupère une source média MODX. | Lecture |
| `modx_create_media_source` | Crée une source média (fichiers). class_key vaut par défaut sources.modFileMediaSource. 'properties' est une table {name:value} des paramètres de la source (par exemple {"basePath":"assets/files/","baseUrl":"assets/files/"}), fusionnée avec la source. | Modifie le site |
| `modx_update_media_source` | Met à jour une source média. 'properties' est une table {name:value} fusionnée avec les paramètres de la source (les autres sont conservés) — utilisez-la pour définir basePath/baseUrl/etc. | Modifie le site |
| `modx_delete_media_source` | Supprime une source média par id. | Modifie le site |
| `modx_list_media_source_files` | Liste les fichiers et dossiers d’une source média. | Lecture |
| `modx_read_media_source_file` | Lit un fichier dans une source média. | Lecture |
| `modx_create_media_file` | Crée un fichier dans une source média (écrit le contenu). source = id ou nom de la source, path = répertoire cible (relatif à la base de la source), name = nom du fichier, content = contenu du fichier. | Modifie le site |
| `modx_update_media_file` | Écrase le contenu d’un fichier dans une source média. source = id/nom, path = chemin du fichier dans la source, content = nouveau contenu. | Modifie le site |
| `modx_delete_media_file` | Supprime un fichier dans une source média. source = id/nom, path = chemin du fichier dans la source. | Impact élevé |
| `modx_rename_media_file` | Renomme un fichier dans une source média. source = id/nom, path = chemin actuel du fichier, new_name = nouveau nom du fichier. | Modifie le site |
| `modx_create_media_folder` | Crée un dossier dans une source média. source = id/nom, parent = répertoire parent (racine par défaut), name = nom du dossier. | Modifie le site |
| `modx_delete_media_folder` | Supprime un dossier (et son contenu) dans une source média. source = id/nom, path = chemin du dossier dans la source. | Impact élevé |

## Composants et fichiers

Découvrez les extensions installées et inspectez leurs fichiers autorisés.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_installed_components` | Liste les composants MODX installés dans core/components et assets/components. | Lecture |
| `modx_get_component_files` | Liste les fichiers et dossiers d’un composant MODX installé. | Lecture |
| `modx_read_component_file` | Lit un fichier d’un composant MODX installé. | Lecture |
| `modx_check_integrations` | Indique quelles extensions MODX populaires (miniShop2, MIGX, pdoTools, Tickets, etc.) sont installées sur ce site et ce que modxMCP peut faire avec chacune. | Lecture |

## Recherche de code et dépendances

Localisez les détails d’implémentation, les références et les relations entre les éléments du site.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_search_code` | Recherche plein texte dans le CONTENU des éléments et des ressources (ainsi que leurs noms). | Lecture |
| `modx_find_usages` | Trouve où un élément est utilisé : correspondances de son nom dans le contenu de tout le code, ainsi que — s’il s’agit d’un modèle — les ressources affectées à ce modèle. | Lecture |
| `modx_list_resources` | Liste les ressources (l’arborescence du contenu), éventuellement filtrées par parent, contexte, état de suppression ou requête sur pagetitle/alias/uri. | Lecture |
| `modx_replace_across` | Recherche et remplacement sur tout le site dans les éléments de code (chunk/snippet/template/plugin) : trouve chaque élément dont le CONTENU contient find et remplace TOUTES les occurrences par replacement, en UN appel (sans lectures/écritures par élément de votre côté). | Impact élevé |
| `modx_dependency_graph` | Cartographie les LIENS ENTRE les éléments du site, en un appel : quel modèle utilise quel chunk/snippet/TV, quel snippet rend quel chunk, où chaque TV est rattachée. | Lecture |

## VersionX

Inspectez ou restaurez des révisions historiques lorsque VersionX est installé.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_versionx_list_versions` | Liste les versions VersionX d’une ressource, d’un chunk, d’un snippet, d’un modèle, d’un plugin ou d’une TV MODX. | Lecture |
| `modx_versionx_get_version` | Lit les données d’une version VersionX avant de décider s’il faut y revenir. | Modifie le site |
| `modx_versionx_revert_version` | Rétablit un objet MODX à une version VersionX précise. | Modifie le site |

## VirtualPage

Gérez les associations d’événements, les gestionnaires et les routes fournis par VirtualPage.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_virtualpage_list_events` | Liste les événements VirtualPage qui associent des groupes de routes à des événements MODX. | Modifie le site |
| `modx_virtualpage_get_event` | Lit un événement VirtualPage par id ou nom, avec ses routes. | Modifie le site |
| `modx_virtualpage_create_event` | Crée un événement VirtualPage, généralement OnPageNotFound ou OnHandleRequest. | Modifie le site |
| `modx_virtualpage_update_event` | Met à jour un événement VirtualPage par id ou nom. | Modifie le site |
| `modx_virtualpage_list_handlers` | Liste les gestionnaires VirtualPage qui rendent des ressources, snippets, chunks ou ressources dynamiques. | Modifie le site |
| `modx_virtualpage_get_handler` | Lit un gestionnaire VirtualPage par id ou nom, avec les routes qui l’utilisent. | Modifie le site |
| `modx_virtualpage_create_handler` | Crée un gestionnaire VirtualPage. type peut valoir resource, snippet, chunk, dynamic_resource/template ou 0..3. | Modifie le site |
| `modx_virtualpage_update_handler` | Met à jour un gestionnaire VirtualPage par id ou nom. | Modifie le site |
| `modx_virtualpage_list_routes` | Liste les routes VirtualPage avec les noms de leurs événements et gestionnaires. | Modifie le site |
| `modx_virtualpage_get_route` | Lit une route VirtualPage par id, ou par route avec method facultatif. | Modifie le site |
| `modx_virtualpage_create_route` | Crée une route VirtualPage et associe son plugin d’événement si nécessaire. | Modifie le site |
| `modx_virtualpage_update_route` | Met à jour une route VirtualPage par id. | Modifie le site |
| `modx_virtualpage_resolve_route` | Simule la correspondance des routes VirtualPage et renvoie le gestionnaire ainsi que les espaces réservés vp.*. | Lecture |
| `modx_virtualpage_delete_event` | Supprime un événement VirtualPage par id ou nom (supprime aussi ses routes, conformément au schéma VP). | Modifie le site |
| `modx_virtualpage_delete_handler` | Supprime un gestionnaire VirtualPage par id ou nom. | Modifie le site |
| `modx_virtualpage_delete_route` | Supprime une route VirtualPage par id ou nom. | Modifie le site |
| `modx_virtualpage_clear_cache` | Efface les fichiers de routes/cache VirtualPage et actualise le cache MODX. | Modifie le site |

## miniShop2

Travaillez avec les options de boutique, les catégories, les relations entre produits et les commandes.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_ms2_list_option_types` | Liste les types de champs disponibles pour les options de produits miniShop2. | Modifie le site |
| `modx_ms2_list_options` | Liste les options de produits miniShop2 de l’onglet des options dans les paramètres. | Modifie le site |
| `modx_ms2_get_option` | Lit une option de produit miniShop2 par id. | Modifie le site |
| `modx_ms2_create_option` | Crée une option de produit miniShop2 dans les options des paramètres. | Modifie le site |
| `modx_ms2_update_option` | Met à jour une option de produit miniShop2 dans les options des paramètres. | Modifie le site |
| `modx_ms2_assign_option_to_category` | Affecte une option miniShop2 existante à une msCategory. | Modifie le site |
| `modx_ms2_get_product_options` | Lit les valeurs d’options miniShop2 affectées à un produit. | Modifie le site |
| `modx_ms2_update_product_options` | Crée, met à jour ou supprime les valeurs d’options miniShop2 d’un produit. | Modifie le site |
| `modx_ms2_list_link_types` | Liste les types de liens miniShop2 (msLink) : id, type, name, description. | Modifie le site |
| `modx_ms2_get_link_type` | Récupère un type de lien miniShop2 par id. | Modifie le site |
| `modx_ms2_create_link_type` | Crée un type de lien miniShop2. 'type' est la nature de relation qui détermine la façon dont les produits sont liés. | Modifie le site |
| `modx_ms2_update_link_type` | Met à jour un type de lien miniShop2. | Modifie le site |
| `modx_ms2_delete_link_type` | Supprime un type de lien miniShop2 par id. | Modifie le site |
| `modx_ms2_list_product_links` | Liste les liens entre produits (msProductLink), éventuellement pour un seul produit. | Modifie le site |
| `modx_ms2_create_product_link` | Lie deux produits selon un type de lien. La nature de relation du type de lien détermine si le lien inverse est également créé. | Modifie le site |
| `modx_ms2_delete_product_link` | Supprime un lien entre produits (par type de lien + master + slave). | Modifie le site |
| `modx_ms2_list_categories` | Liste les catégories miniShop2 (msCategory). | Modifie le site |
| `modx_ms2_create_category` | Crée une catégorie miniShop2 (ressource msCategory). | Modifie le site |
| `modx_ms2_update_category` | Met à jour une catégorie miniShop2. | Modifie le site |
| `modx_ms2_list_orders` | Liste les commandes miniShop2 (msOrder), filtrables par statut/client/contexte/plage de dates. | Modifie le site |
| `modx_ms2_get_order` | Récupère une commande miniShop2 par id (avec ses articles/son client). | Modifie le site |
| `modx_ms2_update_order` | Met à jour une commande miniShop2 — généralement pour modifier son statut (déclenche la logique de changement de statut ms2), sa livraison ou son paiement. | Modifie le site |

## MIGX

Lisez ou modifiez les configurations MIGX lorsque l’extension est installée.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_migx_list_configs` | Liste les configurations MIGX (migxConfig) : id, name, category, published. | Modifie le site |
| `modx_migx_get_config` | Récupère une configuration MIGX complète par id (formtabs, columns, buttons, filters, permissions — sous forme de chaînes JSON telles qu’elles sont stockées). | Modifie le site |
| `modx_migx_create_config` | Crée une configuration MIGX. formtabs/columns/contextmenus/actionbuttons/columnbuttons/filters sont des chaînes JSON (même format que celui stocké par MIGX dans le gestionnaire). | Modifie le site |
| `modx_migx_update_config` | Met à jour une configuration MIGX. Seuls les champs transmis sont modifiés. | Modifie le site |
| `modx_migx_delete_config` | Supprime une configuration MIGX par id. | Modifie le site |

## Utilisateurs et droits d’accès

Administrez les utilisateurs, groupes, rôles, politiques et permissions sur les ressources/contextes.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_flush_permissions` | Vide le cache des permissions d’accès (commande « Flush Permissions » du gestionnaire) pour appliquer les modifications des ACL. | Modifie le site |
| `modx_list_users` | Liste les utilisateurs du gestionnaire/web (modUser). | Lecture |
| `modx_get_user` | Récupère un utilisateur par id. | Lecture |
| `modx_create_user` | Crée un utilisateur. Transmettez 'password' pour définir explicitement le mot de passe ; omettez-le pour le générer automatiquement. Les champs de profil (email, fullname, phone…) sont également acceptés. | Modifie le site |
| `modx_update_user` | Met à jour un utilisateur (champs de profil, état d’activation). | Modifie le site |
| `modx_delete_user` | Supprime un utilisateur par id. | Modifie le site |
| `modx_list_user_groups` | Liste les groupes d’utilisateurs MODX (id, name, parent, rank). | Lecture |
| `modx_get_user_group` | Récupère un groupe d’utilisateurs par id. | Lecture |
| `modx_create_user_group` | Crée un groupe d’utilisateurs. | Modifie le site |
| `modx_update_user_group` | Met à jour un groupe d’utilisateurs. | Modifie le site |
| `modx_delete_user_group` | Supprime un groupe d’utilisateurs par id. | Modifie le site |
| `modx_list_user_group_members` | Liste les membres d’un groupe d’utilisateurs. | Lecture |
| `modx_add_user_to_group` | Ajoute un utilisateur à un groupe d’utilisateurs, éventuellement avec un rôle. | Modifie le site |
| `modx_update_group_member` | Met à jour l’appartenance d’un utilisateur à un groupe (par exemple, change son rôle). | Modifie le site |
| `modx_remove_user_from_group` | Retire un utilisateur d’un groupe d’utilisateurs. | Modifie le site |
| `modx_list_roles` | Liste les rôles d’accès (id, name, authority). | Lecture |
| `modx_get_role` | Récupère un rôle par id. | Lecture |
| `modx_create_role` | Crée un rôle. | Modifie le site |
| `modx_update_role` | Met à jour un rôle. | Modifie le site |
| `modx_delete_role` | Supprime un rôle par id. | Modifie le site |
| `modx_list_access_policies` | Liste les politiques d’accès (id, name, description, template). | Lecture |
| `modx_get_access_policy` | Récupère une politique d’accès par id. | Lecture |
| `modx_create_access_policy` | Crée une politique d’accès. | Modifie le site |
| `modx_update_access_policy` | Met à jour une politique d’accès (par exemple sa table de permissions). | Impact élevé |
| `modx_delete_access_policy` | Supprime une politique d’accès par id. | Modifie le site |
| `modx_list_access_policy_templates` | Liste les modèles de politiques d’accès. | Lecture |
| `modx_get_access_policy_template` | Récupère un modèle de politique d’accès par id. | Lecture |
| `modx_create_access_policy_template` | Crée un modèle de politique d’accès. | Modifie le site |
| `modx_update_access_policy_template` | Met à jour un modèle de politique d’accès. | Modifie le site |
| `modx_delete_access_policy_template` | Supprime un modèle de politique d’accès par id. | Modifie le site |
| `modx_list_access_permissions` | Liste les permissions définies par un modèle de politique (catalogue en lecture seule). | Lecture |
| `modx_list_resource_groups` | Liste les groupes de ressources (id, name). | Lecture |
| `modx_get_resource_group` | Récupère un groupe de ressources par id. | Lecture |
| `modx_list_resource_group_resources` | Liste les ressources affectées à un groupe de ressources. | Lecture |
| `modx_create_resource_group` | Crée un groupe de ressources. | Modifie le site |
| `modx_update_resource_group` | Met à jour un groupe de ressources. | Modifie le site |
| `modx_delete_resource_group` | Supprime un groupe de ressources par id. | Modifie le site |
| `modx_assign_resource_to_group` | Ajoute une ressource (document) à un groupe de ressources. | Modifie le site |
| `modx_remove_resource_from_group` | Retire une ressource (document) d’un groupe de ressources. | Modifie le site |
| `modx_list_context_access` | Liste les entrées ACL d’accès aux contextes, éventuellement filtrées par usergroup/context/policy. | Lecture |
| `modx_grant_context_access` | Accorde à un groupe d’utilisateurs l’accès à un contexte avec une politique et une autorité de rôle minimale. | Modifie le site |
| `modx_update_context_access` | Met à jour une entrée ACL d’accès à un contexte par id. | Modifie le site |
| `modx_revoke_context_access` | Supprime une entrée ACL d’accès à un contexte par id. | Modifie le site |
| `modx_list_resourcegroup_access` | Liste les entrées ACL d’accès aux groupes de ressources. | Lecture |
| `modx_grant_resourcegroup_access` | Accorde à un groupe d’utilisateurs l’accès à un groupe de ressources avec une politique et une autorité de rôle minimale. | Modifie le site |
| `modx_update_resourcegroup_access` | Met à jour une entrée ACL d’accès à un groupe de ressources par id. | Modifie le site |
| `modx_revoke_resourcegroup_access` | Supprime une entrée ACL d’accès à un groupe de ressources par id. | Modifie le site |

## ClientConfig

Gérez les paramètres fournis par l’extension ClientConfig.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_clientconfig_list_settings` | Liste les paramètres ClientConfig, y compris les valeurs propres aux contextes. | Modifie le site |
| `modx_clientconfig_get_setting` | Récupère un paramètre ClientConfig par id ou clé. | Modifie le site |
| `modx_clientconfig_create_setting` | Crée un paramètre ClientConfig. | Modifie le site |
| `modx_clientconfig_update_setting` | Met à jour un paramètre ClientConfig par id ou clé. | Modifie le site |
| `modx_clientconfig_delete_setting` | Supprime un paramètre ClientConfig par id ou clé. | Modifie le site |

## Jeux de propriétés

Créez et affectez des jeux de propriétés MODX réutilisables.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_property_sets` | Liste les jeux de propriétés (modPropertySet) : id, name, description, category. | Lecture |
| `modx_get_property_set` | Récupère un jeu de propriétés par id (y compris ses propriétés). | Lecture |
| `modx_create_property_set` | Crée un jeu de propriétés. 'properties' est un objet JSON de définitions de propriétés. | Modifie le site |
| `modx_update_property_set` | Met à jour un jeu de propriétés. | Modifie le site |
| `modx_delete_property_set` | Supprime un jeu de propriétés par id. | Modifie le site |
| `modx_assign_property_set` | Rattache un jeu de propriétés à un élément. Identifiez l’élément par son id + soit element_class (par exemple 'modSnippet'), soit element_type ('snippet'/'chunk'/'template'/'plugin'/'tv'). | Modifie le site |
| `modx_unassign_property_set` | Détache un jeu de propriétés d’un élément (même identification que pour l’affectation). | Modifie le site |

## Contextes et paramètres de contexte

Gérez les métadonnées des contextes et la configuration à portée limitée.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_contexts` | Liste les contextes (modContext) : key, name, description, rank. | Lecture |
| `modx_get_context` | Récupère un contexte par clé. | Lecture |
| `modx_create_context` | Crée un contexte. 'key' est l’identifiant unique (par exemple 'mobile'). | Modifie le site |
| `modx_update_context` | Met à jour un contexte (name/description/rank). 'settings' peut être un tableau JSON de paramètres de contexte (usage avancé). | Modifie le site |
| `modx_delete_context` | Supprime un contexte par clé. | Impact élevé |
| `modx_list_context_settings` | Liste les paramètres d’un contexte (modContextSetting). | Lecture |
| `modx_get_context_setting` | Récupère un paramètre de contexte (par context_key + key). | Lecture |
| `modx_create_context_setting` | Crée un paramètre au niveau d’un contexte (remplace le paramètre système pour ce contexte). | Modifie le site |
| `modx_update_context_setting` | Met à jour un paramètre de contexte (par context_key + key). | Modifie le site |
| `modx_delete_context_setting` | Supprime un paramètre de contexte (par context_key + key). | Modifie le site |

## Paquets et fournisseurs

Effectuez des recherches auprès des fournisseurs de paquets et installez ou désinstallez des extensions MODX.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_install_package` | Installe un paquet de transport depuis un fournisseur (modx.com par défaut) par son nom, par exemple {package:'MIGX'}. | Impact élevé |
| `modx_uninstall_package` | Désinstalle un paquet de transport par sa signature (par exemple 'migx-2.13.0-pl'). | Impact élevé |
| `modx_list_providers` | Liste les fournisseurs de transport (modx.com et les fournisseurs personnalisés, par exemple modstore.pro) : id, name, service_url. | Lecture |
| `modx_search_packages` | Recherche dans le catalogue d’un fournisseur (à utiliser avant install_package). | Lecture |
| `modx_create_provider` | Ajoute un fournisseur de transport (par exemple modstore.pro). | Modifie le site |
| `modx_update_provider` | Met à jour un fournisseur de transport. | Modifie le site |
| `modx_delete_provider` | Supprime un fournisseur de transport par id. | Modifie le site |

## Espaces de noms

Enregistrez et gérez les espaces de noms et les chemins des extensions.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_namespaces` | Liste les espaces de noms (modNamespace) : name, path, assets_path. | Lecture |
| `modx_create_namespace` | Crée un espace de noms. | Modifie le site |
| `modx_update_namespace` | Met à jour un espace de noms (par nom). | Modifie le site |
| `modx_delete_namespace` | Supprime un espace de noms par nom ('core' ne peut pas être supprimé). | Modifie le site |

## Lexique et traductions

Lisez ou surchargez les chaînes traduites et les rubriques du lexique.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_list_lexicon_entries` | Liste les entrées du lexique, filtrées par namespace + topic + language (et une recherche facultative). | Lecture |
| `modx_list_lexicon_topics` | Liste les rubriques du lexique pour un espace de noms/une langue. | Lecture |
| `modx_set_lexicon_entry` | Crée ou surcharge une entrée du lexique (surcharge en base de données de la valeur du fichier). | Modifie le site |
| `modx_revert_lexicon_entry` | Rétablit une entrée du lexique — supprime sa surcharge en base de données pour que la valeur du fichier s’applique. | Modifie le site |

## Maintenance et diagnostic

Inspectez les métadonnées, l’état et les journaux du site, et effectuez la maintenance.

| Outil | Fonction | Impact |
| --- | --- | --- |
| `modx_get_site_state` | Lit la révision du site faisant autorité côté connecteur et la capacité de préconditions atomiques utilisées par les outils externes. | Lecture |
| `modx_describe_object` | Introspection du schéma : liste les champs d’une classe xPDO (name + type php/db, null, default) et sa clé primaire, afin d’utiliser les VRAIS noms de champs plutôt que de les deviner. | Lecture |
| `modx_read_error_log` | Lit la fin du journal d’erreurs MODX (core/cache/logs/error.log) à des fins de diagnostic. | Lecture |
| `modx_refresh_uris` | Régénère toutes les URI des ressources (à exécuter après des modifications en masse des alias/de la structure ayant laissé des URI obsolètes). | Modifie le site |
| `modx_remove_locks` | Supprime les verrous d’édition périmés du gestionnaire (lorsqu’un élément/une ressource est signalé comme verrouillé par une session terminée). | Modifie le site |
| `modx_project_overview` | Fournit une vue d’ensemble de tout le site installé en UN appel compact et peu coûteux. | Lecture |
| `modx_system_info` | Informations d’environnement/de diagnostic : version MODX, version modxMCP, version PHP, type de base de données, chemins principaux. | Lecture |
| `modx_regenerate_token` | Remplace modxmcp.api_token par une nouvelle valeur aléatoire et la renvoie. | Impact élevé |
| `modx_help` | Documentation intégrée. Appelez sans arguments pour lister les rubriques ; transmettez {topic} pour obtenir un guide (par exemple 'tv_input_types', 'study_component', 'minishop2', 'migx', 'acl', 'getting_started'). Lisez la rubrique pertinente avant un travail inhabituel, ou pour apprendre à utiliser un composant nouvellement installé. | Lecture |
| `modx_list_actions` | Liste les noms d’actions pris en charge par cette version du serveur modxMCP, regroupés par domaine. | Lecture |
| `modx_clear_cache` | Actualise le cache MODX. Transmettez partitions (par exemple ['resource','context_settings']) pour n’actualiser que ces partitions ; omettez-le pour une actualisation complète. | Modifie le site |
| `modx_read_audit_log` | Lit le journal d’audit des écritures modxMCP (les plus récentes à la fin). | Lecture |
| `modx_run_processor` | Exécute directement N’IMPORTE QUEL processeur MODX (mécanisme d’accès direct hors des opérations dédiées). | Impact élevé |

## Utiliser le catalogue

Un hôte MCP compatible découvre les outils disponibles et transmet des arguments validés au client MCP local. Vous n’avez pas besoin de mémoriser les noms de commandes. Par exemple, déterminer comment une page est rendue peut nécessiter de lister sa ressource, de lire le modèle qui lui est affecté et d’inspecter le chunk ou le snippet référencé. Il s’agit d’opérations publiques standard, pas d’un processus imposé à l’agent.

Si une action est absente, vérifiez si son groupe de fonctionnalités a été désactivé, si l’extension requise est installée et si les versions du client et du serveur correspondent. `modx_list_actions` indique les actions connues du serveur installé.

## Rappel de sécurité

L’identifiant `X-MCP-Token` autorise un utilisateur de service à privilèges élevés. La seule connaissance des noms de ces outils ne donne pas accès au site. Toutefois, un attaquant qui obtient un jeton valide peut potentiellement modifier les éléments PHP et les paramètres système. Ne considérez pas une étiquette « Lecture » dans ce document comme une frontière de sécurité. Consultez [Sécurité](SECURITY.fr.md).

Pour les détails d’implémentation, consultez les [définitions publiques du client](https://github.com/rumata-estor/modx3-mcp/blob/main/client/index.js).
