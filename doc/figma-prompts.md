# Figma prompts

Prompts given to Figma (Figma Make / AI) to design screens that have no mock-up yet. They are in
French because the generated screens must be. Once a mock-up exists, note its link here and in
[roadmap](roadmap.md).

## Back-office theme on EasyAdmin 5 (step 3, 2026-10-05)

Status: waiting for the mock-up. The theme must stay within what EasyAdmin 5 can change without
rewriting its pages: tokens (`Dashboard::setTheme()`, CSS variables), fonts, logo, icons and a few
template overrides (see [admin](admin.md)).

> **Contexte.** Tu travailles sur le back-office du site du Club ePlaneur, une association qui forme au vol en planeur sur le simulateur Condor. Il est réalisé avec **EasyAdmin 5** (bundle Symfony, interface Bootstrap) : propose un **thème** pour cette interface, pas une nouvelle application. Garde sa structure telle quelle :
> - **barre latérale gauche** : logo, menu en sections (Contenus, Comptes), lien « Voir le site » ; repliée derrière un bouton en mobile ;
> - **barre du haut** : recherche, menu utilisateur (nom, Mon compte, Se déconnecter) ;
> - **zone de contenu** : titre de page, actions globales à droite (« Créer… », « Filtres »), puis tableau, formulaire ou fiche ;
> - **tableau de liste** : case à cocher par ligne, colonnes triables, badges, menu d'actions « … » en fin de ligne (Modifier, Supprimer), compteur de résultats, pagination ;
> - **formulaire** : groupes de champs titrés, libellé au-dessus, astérisque des champs obligatoires, aide sous le champ, message d'erreur, cases à cocher, interrupteurs, listes déroulantes avec recherche, champ fichier ; boutons « Sauvegarder les modifications » et « Sauvegarder et modifier » en haut à droite ;
> - **messages** : bandeaux succès / erreur / info en haut du contenu, fenêtre de confirmation de suppression.
>
> Base-toi sur la frame « ePlaneur — Grand air adouci · Version 6 · Desktop » de la page Accueil pour l'identité, sans en reprendre la mise en page éditoriale : c'est un outil de travail dense et lisible, pas une vitrine.
> - **Styles disponibles** : palette ink #192630, navy #3e5d83, sand #e7bca7, sand-soft #f1ded2, sky-soft #e5ebf1, cream #f7f5ef, slate #586772, line #d8ddd9 ; typos Barlow (titres) et Inter (interface, tableaux). Pas d'Oswald ici sauf éventuellement le titre du tableau de bord.
> - **Ce que le thème peut régler** (reste dans ce cadre) : couleur principale et ses états, gamme de gris (chaude de préférence, proche du crème), rayon des angles, densité des espacements, polices, logo, jeu d'icônes (pictos fins au trait, comme ceux du site), couleurs des badges.
> - **Mode clair** en priorité ; propose aussi le **mode sombre** des mêmes écrans, à partir de ink.
> - **Accessibilité** : contrastes AA pour le texte, les badges et les boutons ; focus visible.
>
> Tous les textes sont en français, avec des données réalistes du club. Crée les écrans suivants en desktop 1440 px, et les deux premiers aussi en mobile 390 px :
>
> 1. **Tableau de bord** : « Administration du Club ePlaneur », message d'accueil, cartes de raccourci (Médiathèque, Documents officiels, Menus, Catégories d'articles, Comptes, Groupes et droits) avec icône, titre et une ligne d'explication.
> 2. **Liste « Médiathèque »** : vignette (image ou pastille « PDF »), nom du fichier, type, taille, date d'ajout, badge de visibilité (« Tout le monde », « Utilisateurs connectés », « Groupes choisis ») ; boutons « Filtres » et « Téléverser ».
> 3. **Liste « Comptes »** : nom, e-mail, interrupteur « E-mail confirmé », groupes en tags (Membre, Comité, Administrateur), date d'inscription ; filtres ouverts.
> 4. **Formulaire « Modifier groupe »** (Comité) : nom, description, interrupteur « Tous les droits », liste de droits en cases à cocher (Accéder à l'administration, Gérer les comptes, Écrire des articles…).
> 5. **Formulaire « Créer document »** : titre, catégorie, fichier choisi dans la médiathèque (liste avec recherche), version, mentions datées (liste de lignes ajoutables), groupe « Visibilité » avec choix et cases des groupes ; un champ en erreur.
> 6. **Page « Téléverser des fichiers »** : zone de dépôt multi-fichiers, visibilité, groupes, boutons « Téléverser » et « Annuler ».
> 7. **États** : bandeau de succès, bandeau d'erreur, fenêtre « Supprimer cet élément ? », liste vide, menu « … » ouvert sur une ligne.
>
> Ajoute une **planche de styles** qui liste chaque valeur choisie (couleurs avec leur rôle : principale, fond, surfaces, bordures, texte, texte secondaire, badges ; rayon ; espacements ; typos et tailles) pour la transposer en variables EasyAdmin.
>
> Nomme clairement chaque frame et chaque composant en français, comme dans les frames existantes.

## Pages, multi-level menu, official documents, restricted content (step 2b, 2026-10-04)

Status: done. Frames in the Figma file `a00zZWdxi7zD4Opbt6gIbn`, nodes 33-17798 to 33-20172 (desktop
header, hover, mega-menu visitor/logged-in, "Le Club" section, "Statuts", "Autres documents" list and
cards, "Contenu réservé" visitor/committee, then the mobile versions).

> **Contexte.** Tu travailles sur le site du Club ePlaneur, une association qui forme au vol en planeur sur le simulateur Condor. Base-toi strictement sur la frame « ePlaneur — Grand air adouci · Version 6 · Desktop » de la page Accueil, et sur la frame « Premier vol · Article desktop » si elle est présente. Reprends sans en inventer de nouveaux :
> - **les styles** : palette ink #192630, navy #3e5d83, sand #e7bca7, sand-soft #f1ded2, sky-soft #e5ebf1, cream #f7f5ef, slate #586772, line #d8ddd9 ; typos Oswald (titres display), Barlow (titres), Inter (texte) ;
> - **les composants** : bouton, lien fléché, surtitre, tag, cartes, fil d'Ariane, encadrés ;
> - **la grille** : desktop 1440 px, marges 72 px, contenu 1296 px.
>
> Tous les textes sont en français, réalistes, tirés du site du club. Crée les écrans suivants, en desktop 1440 px puis en mobile 390 px :
>
> 1. **En-tête avec menu à plusieurs niveaux.**
>    - Rubriques de premier niveau : Accueil, Formation, Voler, Le Club, Actualités. Garde la même hauteur (92 px) et le même bouton « Espace membre ».
>    - Desktop : panneau déroulant au survol/clic. Pour « Le Club », une colonne par sous-rubrique (Présentation, Objectifs, Textes officiels, Communication, Adhésions, Listes des membres, Activités, Contact), avec leurs pages de 3e niveau. Exemple sous Textes officiels : Statuts, Règlement intérieur, Autres documents.
>    - États à montrer : survol, rubrique ouverte, page courante.
>    - Un petit repère discret (cadenas + « Membres » ou « Comité ») sur les liens réservés.
>    - Variante connectée : le bouton devient « Mon compte ».
>    - Mobile : menu plein écran avec accordéons par niveau.
> 2. **Page de rubrique « Le Club ».**
>    - Fil d'Ariane, surtitre, titre display, chapô.
>    - Les sous-pages en cartes : titre, résumé, lien. Un repère sur celles qui sont réservées.
> 3. **Page de contenu « Statuts »** (niveau 3).
>    - Fil d'Ariane (Accueil › Le Club › Textes officiels › Statuts), titre, chapô, mention « Mis à jour le … ».
>    - Colonne latérale avec la navigation entre pages sœurs (Statuts, Règlement intérieur, Autres documents), page courante en surbrillance.
>    - Corps fait des mêmes blocs que l'article : texte, sous-titre, encadré, tableau.
> 4. **Bloc « Documents officiels »** sur la page « Autres documents ».
>    - Une liste de documents, chacun avec : badge de format (PDF), titre, description courte, version (ex. « V13 »), date d'adoption, taille, lien « Télécharger ».
>    - Regroupement par catégorie : Textes fondateurs, Décisions, Communication.
>    - Une variante compacte et une variante cartes.
> 5. **Page « Contenu réservé »** en deux variantes.
>    - Visiteur non connecté : titre de la page demandée, message « Contenu réservé aux membres du club », boutons « Se connecter » et « Créer un compte gratuit ».
>    - Connecté sans le bon groupe : « Cette page est réservée au Comité. » et un lien de retour.
>
> Nomme clairement chaque frame et chaque composant en français, comme dans les frames existantes.
