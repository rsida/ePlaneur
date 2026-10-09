# Figma prompts

Prompts given to Figma (Figma Make / AI) to design screens that have no mock-up yet. They are in
French because the generated screens must be. Once a mock-up exists, note its link here and in
[roadmap](roadmap.md).

## Member space and personal data (step 9a, 2026-10-09)

Status: frames generated (2026-10-09) on the "Dashboard" page of the Figma file
`a00zZWdxi7zD4Opbt6gIbn` (node 85-2): dashboard (active member, registered without membership, new
account, trainer), my flights and flight deletion, logbook with the 8-step path, membership and its
follow-up, messages (inbox, conversation, new message, empty), profile, privacy and data (archive
ready, deletion summary and final confirmation, account deleted), contact the club and message sent;
desktop 1440 px, mobile 390 px still in progress. Generated before the choice for the flights was
added to the prompt: the deletion summary shows it in the implementation only.

> **Contexte.** Tu travailles sur le site du Club ePlaneur, une association qui forme au vol en planeur sur le simulateur Condor. Base-toi strictement sur les frames existantes du site (« ePlaneur — Grand air adouci · Version 6 · Desktop », « Premier vol · Article desktop », pages « Le Club », formulaires de connexion et d'inscription) et reprends sans en inventer de nouveaux :
> - **les styles** : palette ink #192630, navy #3e5d83, sand #e7bca7, sand-soft #f1ded2, sky-soft #e5ebf1, cream #f7f5ef, slate #586772, line #d8ddd9, vert #2f6b4f (réussite), rouge #a8322d (danger) ; typos Oswald (titres display), Barlow (titres), Inter (texte) ;
> - **les composants** : en-tête et pied de page, surtitre, titres, carte, tag, bouton (plein, contour, danger), lien fléché, tuile chiffrée (libellé, valeur, unité), champ de formulaire, case à cocher, message d'alerte ;
> - **la grille** : desktop 1440 px, marges 72 px, contenu 1296 px ; mobile 390 px.
>
> **Le besoin.** Conçois l'**espace personnel** d'un compte du site (`/mon-compte`), qui remplace la simple page « Mon compte ». Il réunit ce qu'un pilote fait sur le site : son adhésion au club, ses vols Condor analysés, sa progression dans le parcours de formation en 8 étapes, ses échanges avec les formateurs, son profil et ses données personnelles (RGPD). Les comptes sont : inscrit (gratuit, non adhérent), membre (adhésion validée par le club), comité, formateur, administrateur.
>
> **Navigation de l'espace** : une colonne de navigation à gauche en desktop, un menu déroulant ou des onglets défilants en mobile : « Tableau de bord », « Mes vols », « Mon carnet de vol », « Mon adhésion », « Messages », « Mon profil », « Confidentialité et données ». En bas, « Se déconnecter ».
>
> Tous les textes sont en français, réalistes et tirés de la vie du club (vols en réseau du mercredi, plans de vol comme « C3-c060 Laragne – Ventoux – Fayence », planeur Diana 2, paysage AA3, CN « RS2 »). Crée les écrans suivants, en desktop 1440 px puis en mobile 390 px :
>
> 1. **Tableau de bord** (membre actif).
>    - Bonjour et nom affiché, rôle (« Membre 2026 »).
>    - **Carte adhésion** : saison, état (validée / demande envoyée / paiement attendu / à renouveler), date de fin (31 décembre), liens « Mon attestation » ou « Renouveler » ou « Payer en ligne ».
>    - **Derniers vols** (3 cartes : plan de vol, date, durée, distance, vitesse circuit, type de départ en tag) et bouton « Déposer une trace ».
>    - **Ma progression** : mini-courbe de la vitesse circuit sur les derniers vols, badges récents (« 300 km », « Vol de 3 h »), conseil qui revient le plus (« Des thermiques faibles acceptés · Étape 5 »).
>    - **Nouveautés** : commentaires de formateurs reçus sur mes vols (non lus en évidence), réponses aux messages.
>    - Raccourcis : « Records du club », « Vols en réseau », « Parcours de formation ».
> 2. **Tableau de bord, autres états** : compte inscrit sans adhésion (encart d'invitation à adhérer avec les tarifs 10 € moins de 25 ans / 25 € adulte), compte tout neuf sans vol (états vides qui expliquent quoi faire), compte formateur (encart « Pilotes suivis » : derniers vols à commenter).
> 3. **Mes vols** : liste filtrable (période, paysage, plan de vol, circuit terminé ou non, public ou privé), tri par date ; chaque vol avec ses chiffres, son tag de départ, un repère « Public » et les actions « Voir », « Rendre public / privé », « Supprimer ».
> 4. **Mon adhésion** : adhésion en cours et historique des saisons, tarifs de la saison ouverte, formulaire de demande (type, identité, date de naissance, adresse, téléphone WhatsApp, soutien volontaire, acceptation des statuts et du règlement intérieur), suivi du paiement (HelloAsso ou virement), attestations à télécharger.
> 5. **Messages** (à étudier) : boîte de réception avec les conversations (formateurs, comité), fil de conversation, message lié à un vol (vignette du vol et instant précis « 1:12:30 »), nouveau message, pièces jointes interdites ; état vide. Montre aussi la vue mobile.
> 6. **Mon profil** : nom affiché, adresse e-mail (changement avec confirmation par e-mail), mot de passe, **CN Condor** et nom de pilote Condor (pour rattacher les traces), versions de Condor utilisées (2, 3), **préférences de notification par e-mail** (commentaire sur un de mes vols, nouveau message, rappel de renouvellement, actualités du club), messages de succès et d'erreur.
> 7. **Confidentialité et données** (RGPD).
>    - Ce que le club conserve et pourquoi, en langage simple, avec un lien vers la politique de confidentialité.
>    - **Visibilité** : mes vols publics ou non par défaut, mon nom dans le classement de saison ou non.
>    - **Télécharger mes données** : bouton qui prépare une archive (compte, adhésions, vols et traces, commentaires, messages) et état « archive prête ».
>    - **Supprimer mon compte** : zone séparée en rouge. Elle explique ce qui sera supprimé (compte, profil, commentaires, messages) et ce qui sera conservé (les adhésions et paiements, gardés 10 ans pour la comptabilité de l'association, avec le seul nom et la date). **Choix pour les vols** : « Supprimer aussi mes vols et mes traces » ou « Garder mes vols de façon anonyme » (sans nom, sans CN, pour que les sessions et les records du club restent complets), avec le nombre de vols concernés. Confirmation en deux étapes : fenêtre qui récapitule, y compris le choix fait pour les vols, saisie du mot de passe et case « Je comprends que c'est définitif », bouton danger « Supprimer définitivement mon compte ». Puis l'écran de fin (« Votre compte a été supprimé », retour à l'accueil).
> 8. **Contacter le club** depuis l'espace : petit formulaire (objet parmi « Adhésion », « Formation », « Vols en réseau », « Site », « Autre », message) prérempli avec le nom et l'e-mail du compte, et confirmation d'envoi.
>
> Contrastes AA, focus visible, tout est utilisable au clavier ; les actions destructrices sont toujours séparées et confirmées. Nomme clairement chaque frame et chaque composant en français, comme dans les frames existantes.

## News list and home news section (step 4, 2026-10-06)

Status: frames generated (2026-10-06) on page 4 of the Figma file `a00zZWdxi7zD4Opbt6gIbn` (node
75-4): news list, filtered list ("Vie du club · 2025"), no result, home section with three posts and
with one post, each in desktop and mobile. Implemented as described in
[content](content.md#news-list-and-home-page).

> **Contexte.** Tu travailles sur le site du Club ePlaneur, une association qui forme au vol en planeur sur le simulateur Condor. Base-toi strictement sur la frame « ePlaneur — Grand air adouci · Version 6 · Desktop » de la page Accueil (en particulier sa section « 06 / À lire entre deux vols — Ça bouge au club. »), sur la frame « Premier vol · Article desktop » (bloc « À lire entre deux vols » en bas d'article, cartes d'articles) et sur les frames « Le Club » / « Statuts » / « Contenu réservé ». Reprends sans en inventer de nouveaux :
> - **les styles** : palette ink #192630, navy #3e5d83, sand #e7bca7, sand-soft #f1ded2, sky-soft #e5ebf1, cream #f7f5ef, slate #586772, line #d8ddd9 ; typos Oswald (titres display), Barlow (titres), Inter (texte) ;
> - **les composants** : en-tête et pied de page, fil d'Ariane, surtitre, titre display, chapô, carte d'article (image, catégorie, titre, extrait, temps de lecture, « Lire l'article → »), carte « À la une » sur fond navy, tag, repère d'accès réservé (cadenas + « Comité »), boutons, lien fléché ;
> - **la grille** : desktop 1440 px, marges 72 px, contenu 1296 px.
>
> **Contenu.** Les actualités du club : guides pratiques, bonnes pratiques, vie du club (championnat ePlaneur, vœux, sorties), et des comptes rendus du Comité Directeur réservés aux membres du Comité. Catégories : « Guide pratique », « Bonnes pratiques », « Vie du club ». Certains articles sont **à la une** (choisis par le comité). Environ 35 articles depuis 2022 ; un article a une date de publication, une catégorie, un temps de lecture, souvent une image de couverture (visuels d'ambiance, pas des captures de Condor), parfois un badge de niveau (« Débutant »).
>
> Tous les textes sont en français, réalistes, tirés du site du club. Crée les écrans suivants, en desktop 1440 px puis en mobile 390 px :
>
> 1. **Page « Actualités »** (`/actualites`).
>    - Fil d'Ariane (Accueil › Actualités), surtitre, titre display « Actualités », chapô court.
>    - En tête, l'article **à la une** le plus récent, en grand (image ou affiche navy, catégorie, titre, extrait, date, lien).
>    - **Filtres** : catégories en tags cliquables (« Tous », « Guide pratique », « Bonnes pratiques », « Vie du club ») avec le nombre d'articles, et **période** (année puis mois, ex. « 2026 › Octobre ») ; filtre actif visible, lien « Effacer les filtres ».
>    - **Liste** des autres articles en grille de cartes (3 colonnes desktop, 1 mobile), du plus récent au plus ancien ; date de publication visible sur chaque carte ; un compte rendu réservé visible avec son repère « Comité » pour un visiteur.
>    - **Pagination** (12 articles par page) : précédent / numéros / suivant, et le nombre d'articles.
>    - État **sans résultat** pour un filtre : message et lien pour revenir à tous les articles.
> 2. **Page filtrée** « Vie du club · 2025 » : même page, filtres actifs, titre de la liste « Vie du club en 2025 · 6 articles ».
> 3. **Section d'accueil « Ça bouge au club »** branchée sur les vrais articles : la carte large reprend l'article à la une (image de couverture ou affiche navy quand il n'y a pas d'image), les deux autres cartes sont les derniers articles publiés ; lien « Toutes les actualités ». Montre aussi le cas où un seul article existe.
>
> Nomme clairement chaque frame et chaque composant en français, comme dans les frames existantes.

## Visual block editor for posts and pages (step 3b, 2026-10-05)

Status: frames generated (2026-10-05) on the "Admin" page of the Figma file `a00zZWdxi7zD4Opbt6gIbn`
(node 37-3), nodes `71:3` to `71:2740`: article editor (light and dark), structure and selected
callout, drag and drop, "/" command, image, table, tabs, FAQ and video blocks, media window and
upload, publication (scheduling, success, address already used), page editor, post list, page tree,
tablet. Implemented as described in [admin](admin.md#block-editor).

> **Contexte.** Tu travailles sur le back-office du site du Club ePlaneur, une association qui forme au vol en planeur sur le simulateur Condor. Le back-office est réalisé avec **EasyAdmin 5** et a déjà son thème (frames de la page « Admin » : barre latérale, barre du haut, listes, formulaires, planche de styles) : reprends-le tel quel pour tout ce qui entoure l'éditeur. Conçois l'**éditeur visuel des articles et des pages**, sur le modèle de l'éditeur de blocs de WordPress (Gutenberg) :
> - le contenu est une **suite de blocs** ; le canevas les affiche **avec les styles du site** (frame « Premier vol · Article desktop » et pages « Le Club » / « Statuts ») et non comme des champs de formulaire ;
> - on **ajoute** un bloc de deux façons : en le **glissant** depuis une liste de blocs vers le canevas, ou en tapant **« / »** dans un paragraphe vide puis en **cherchant** le bloc par son nom ;
> - les textes se **modifient sur place** (titre, paragraphes, titres d'encadré…) ; les réglages qui ne se voient pas dans le canevas sont dans un **panneau de réglages** à droite.
>
> **Blocs disponibles**, regroupés dans la liste :
> - *Texte* : Texte, Sous-titre, Section (titre numéroté « 01 / … », entrée du sommaire), Liste, Citation, Extrait référencé, Encadré (À retenir / Conseil / Vigilance) ;
> - *Médias* : Image, Images côte à côte, Carrousel d'images, Vidéo (YouTube ou Vimeo), Document PDF intégré, Fichiers à télécharger ;
> - *Mise en forme* : Étapes numérotées, Onglets, Checklist, Tableau, Questions fréquentes, Glossaire, Notes, Liens ;
> - *Club* : Documents officiels, Document mis en avant, Sous-pages (pages seulement), Ressource mise en avant, L'essentiel à retenir.
>
> **Zones de contenu.** Un article a trois zones : *Corps* (colonne de lecture), *Barre latérale* (sous le sommaire) et *Bande finale* (pleine largeur, après le corps). Une page en a deux : *Corps* et *Colonne latérale*. Chaque zone est visible dans le canevas avec son nom discret et un bouton « + » quand elle est vide.
>
> **Identité.** Styles du thème admin pour l'interface (Barlow, Inter, navy #3e5d83, gris chauds, rayon 4 px) ; styles du site dans le canevas (ink #192630, navy #3e5d83, sand #e7bca7, sand-soft #f1ded2, sky-soft #e5ebf1, cream #f7f5ef, slate #586772, line #d8ddd9 ; Oswald, Barlow, Inter). Pictos fins au trait comme ceux du site. Contrastes AA, focus visible, tout est utilisable au clavier.
>
> Tous les textes sont en français, avec des données réalistes du club (article « Votre premier vol », page « Statuts »). Crée les écrans suivants en desktop 1440 px :
>
> 1. **Éditeur d'article**, vue d'ensemble.
>    - Barre du haut de l'éditeur : retour à la liste, titre de l'article, état (« Brouillon », « Programmé le 12/10 à 20:30 », « Publié »), indicateur « Modifications non enregistrées » / « Enregistré », boutons « Aperçu », « Enregistrer » et « Publier ».
>    - À gauche, la **liste des blocs** (repliable) : champ de recherche, groupes ci-dessus, chaque bloc avec son picto et son nom ; un onglet « Structure » liste les blocs de l'article dans l'ordre (plan du document, cliquable).
>    - Au centre, le **canevas** : surtitre, titre et chapô modifiables sur place, image de couverture, puis les blocs des zones. Le survol d'un bloc montre son contour ; un bloc **sélectionné** a un contour navy et une **barre d'outils flottante** : poignée de déplacement, picto du type, monter / descendre, gras / italique / lien / liste pour les textes, menu « … » (Dupliquer, Insérer avant, Insérer après, Supprimer).
>    - À droite, le **panneau de réglages** à deux onglets : « Article » (adresse, catégorie, mots-clés, auteur, couverture et légende, phrase d'accroche, à la une, visibilité et groupes) et « Bloc » (réglages du bloc sélectionné, ex. pour un Encadré : variante, afficher l'icône).
> 2. **Glisser-déposer** : un bloc « Encadré » glissé depuis la liste, avec la ligne d'insertion entre deux blocs du canevas ; et un bloc du canevas déplacé par sa poignée.
> 3. **Commande « / »** : un paragraphe vide contenant « /enc », sous lequel une liste filtrée propose « Encadré » (surligné), « Documents officiels »… avec picto, nom et une ligne d'explication ; navigation au clavier ; état « Aucun bloc trouvé ».
> 4. **Blocs à éditer** sélectionnés dans le canevas, avec leur onglet « Bloc » : Image (vide : « Choisir dans la médiathèque » / « Téléverser » ; remplie : légende sur place, crédit et texte alternatif dans le panneau), Tableau (ajout / suppression de lignes et colonnes), Onglets (onglet actif modifiable, ajout d'onglet), Questions fréquentes (ajout d'une question), Vidéo (champ d'adresse et aperçu).
> 5. **Sélecteur de médias** (fenêtre) : grille de la médiathèque avec recherche et filtre par type, onglet « Téléverser » avec zone de dépôt, fichier sélectionné avec ses informations (nom, dimensions, texte alternatif, crédit), bouton « Utiliser ce média ».
> 6. **Publication** : panneau « Publier » ouvert (maintenant ou à une date et heure, heure de France métropolitaine), rappel de la visibilité, bouton de confirmation ; bandeau de succès après publication ; erreur de validation (« Cette adresse est déjà utilisée par un autre article. ») avec le champ concerné mis en évidence dans le panneau.
> 7. **Éditeur de page** (« Statuts ») : même éditeur ; onglet « Page » du panneau : page parente (arbre), adresse avec le chemin complet (/le-club/textes-officiels/statuts), libellé du lien, phrase d'accroche et note, date de mise à jour, visibilité ; zones *Corps* et *Colonne latérale*.
> 8. **Listes** dans le thème existant : « Articles » (titre, catégorie, badge d'état Brouillon / Programmé / Publié, auteur, date, badge de visibilité, bouton « Créer un article ») et « Pages » sous forme d'arbre indenté (titre, chemin, état, visibilité, ajout d'une sous-page).
> 9. **Tablette 1024 px** : l'éditeur d'article avec les panneaux repliés derrière deux boutons, ouverts en surimpression.
>
> Fais aussi l'écran 1 en **mode sombre** (interface sombre comme les frames du thème, canevas inchangé). Nomme clairement chaque frame et chaque composant en français, comme dans les frames existantes.

## Back-office theme on EasyAdmin 5 (step 3, 2026-10-05)

Status: done (2026-10-05). Frames on the "Admin" page of the Figma file `a00zZWdxi7zD4Opbt6gIbn`
(node 37-3): dashboard, media library, accounts, group form, document form, upload, interface states,
mobile dashboard and the style sheet, each in light and dark (the mobile media library was not
generated). Applied as described in [admin](admin.md#theme).

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
