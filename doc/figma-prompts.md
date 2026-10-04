# Figma prompts

Prompts given to Figma (Figma Make / AI) to design screens that have no mock-up yet. They are in
French because the generated screens must be. Once a mock-up exists, note its link here and in
[roadmap](roadmap.md).

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
