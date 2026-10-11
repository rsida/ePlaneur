# Figma prompts

Prompts given to Figma (Figma Make / AI) to design screens that have no mock-up yet. They are in
French because the generated screens must be. Once a mock-up exists, note its link here and in
[roadmap](roadmap.md).

## Flight analysis: solo flight, head to head, session (2026-10-10)

Status: generated on 2026-10-10 on the page
[Analyse de vol](https://www.figma.com/design/a00zZWdxi7zD4Opbt6gIbn/ePlaneur?node-id=95-2) (15
frames, track A chosen); reviewed on 2026-10-11, second-pass prompt below. Asked by the user to see whether Figma has
UX/UI ideas to make the analysis pages easier to read and understand, now that roadmap steps 7g to
7i have added a lot to them. The pages exist (`templates/flight/show.html.twig`,
`templates/flight/compare.html.twig`, `templates/session/show.html.twig`,
`templates/session/compare.html.twig`): the prompt lists everything they show, so that a redesign
keeps every piece of information.

> **Contexte.** Tu travailles sur le site du Club ePlaneur, une association qui forme au vol en planeur sur le simulateur Condor 3 et organise chaque semaine des « vols en réseau » (tous les pilotes volent la même tâche, en même temps, sur le même serveur). Les membres déposent la trace de leur vol (fichier Condor `.ftr` ou IGC) et le site l'analyse. Base-toi strictement sur les frames existantes du site (« ePlaneur — Grand air adouci · Version 6 · Desktop », « Premier vol · Article desktop », frames de l'espace personnel « Mon carnet de vol » et « Mes vols ») et reprends sans en inventer de nouveaux :
> - **les styles** : palette ink #192630, navy #3e5d83, sand #e7bca7, sand-soft #f1ded2, sky-soft #e5ebf1, cream #f7f5ef, slate #586772, line #d8ddd9, vert #2f6b4f (réussite), rouge #a8322d (danger), orange #d9822b (pilote A dans les comparaisons, ascendance de pente) ; typos Oswald (titres display), Barlow (titres), Inter (texte et chiffres) ;
> - **les composants** : en-tête et pied de page, surtitre, titres, carte, tag, bouton (plein, contour), bouton bascule (pressé / non pressé), liste déroulante compacte, lien fléché, tuile chiffrée (libellé, valeur, unité), tableau de données, message d'alerte ;
> - **la grille** : desktop 1440 px, marges 72 px, contenu 1296 px ; mobile 390 px.
>
> Les couleurs propres aux données (montée des thermiques du rose pâle au bordeaux, vario de la trace du rouge au vert, bleu des descendances, violet de l'onde, jaune des thermiques manqués) restent celles de la carte : tu peux proposer mieux si c'est plus lisible, mais justifie-le dans une note sur la frame.
>
> **Le besoin.** Les trois pages d'analyse fonctionnent mais elles ont beaucoup grandi : une barre d'outils de carte avec une dizaine de boutons et de listes, une colonne de valeurs, une frise d'événements sous la barre de lecture avec une dizaine de symboles, cinq courbes empilées, puis une longue suite de sections. Un élève pilote s'y perd ; un formateur cherche vite « ce qui ne va pas » ; un compétiteur veut voir où il a perdu du temps. **Je te demande une proposition d'ergonomie** pour gagner en lisibilité et en compréhension, sans perdre d'information : hiérarchie, regroupements, divulgation progressive (onglets, panneaux repliables, modes), lecture « du résumé vers le détail », légende intégrée plutôt que séparée, cohérence entre les trois pages. Propose au moins **deux pistes** d'organisation de la page de vol (par exemple « tableau de bord + carte en vedette » et « récit du vol, étape par étape »), puis développe la meilleure sur les trois pages. Ajoute sur chaque frame une courte note qui explique le choix.
>
> **Ce qui ne change pas** (contraintes) :
> - La **carte** est le cœur de chaque page : 2D ou 3D sur le relief, avec rejeu animé ; elle doit rester grande en desktop.
> - Toute courbe et tout repère donnent leur **valeur exacte au survol** (petite étiquette près du curseur) ; un clic sur une courbe, la frise, une phase ou un repère **déplace le rejeu** à cet instant ; la carte, les courbes et la frise montrent toujours le **même instant**.
> - Les **conseils** sont des repères à discuter avec un formateur, pas une note : ton bienveillant, toujours rattachés à une étape du parcours de formation (« Étape 4 · Maîtriser les volets, insigne d'argent », « Étape 5 · Voler à la vitesse MacCready »).
> - Textes en français, réalistes ; heures au format « 1:23:04 » ; nombres à la française (« 2,7 m/s », « 1 094 m »).
> - Contrastes AA, focus visible, tout utilisable au clavier ; la frise et la carte ont un équivalent en liste (la liste des phases) pour le clavier et les lecteurs d'écran.
>
> **Données réalistes à utiliser.** Pilote Romain SIDA, CN « RS2 », planeur Diana 2 (polaire connue, 44,9 kg/m²), paysage AA3, plan de vol « C3-s117 Romans – Tourniol – Bure – Versoud », vol du 15 août 2025, départ en remorqué ; 1 h 22 de vol, 141,9 km de circuit à 121,2 km/h, 7 thermiques à +2,2 m/s de moyenne, 18 % du temps en spirale, finesse 44 en transition à 177 km/h, 3 montées en pente (+555 m), vent de la tâche 20 km/h du sud. Session du 31 mai 2024 « C3-c046a Saint-Auban – Challes » avec les traces de six pilotes (FDU vainqueur à 155,2 km/h, HL4, VB, U8J, JC6, LL3).
>
> ---
>
> **1. Page d'un vol (vol seul)** — `/vols/2`. Aujourd'hui, de haut en bas :
> - **En-tête** : nom du pilote et CN, planeur · paysage · plan de vol · date, tags (« Départ en remorqué », « Public »), liens « Ascendances du club sur AA3 », « Voir la session », « Comparer », « Télécharger la trace (.ftr) / en IGC », « Rendre public / privé », « Supprimer ».
> - **Tuiles chiffrées** : durée, distance, vitesse circuit, vitesse moyenne, altitude max, thermiques, temps en spirale (gauche/droite), finesse en transition.
> - **Le vol en détail** (tableau de faits) : résultat officiel du serveur (rang, statut, temps, vitesse, points), vitesse et altitude au départ comparées aux limites de la tâche, eau au décollage, temps de vol, vitesse max, meilleur gain, élévation cumulée, spirales gauche/droite, bande exploitée, écart des transitions à la ligne droite. **Transition finale** : depuis le dernier thermique, distance, vitesse, hauteur d'arrivée et ce que l'excès a coûté en temps de montée.
> - **Conseils** (5 à 8 cartes, chacune : titre, explication chiffrée, étape du parcours ; type « à travailler », « bien », « info ») : « Un centrage rapide », « Du vol en dauphin : 1 094 m gagnés en ligne droite dans 20 ascendances », « Vitesse de transition adaptée », « Des thermiques de la course manqués », « Repères de l'insigne d'argent »…
> - **Légende** repliable au-dessus de la carte (symboles 3D et frise).
> - **Carte** avec barre d'outils : Carte / 3D, fond (Relief, Satellite, Plan), couleur de trace (vario, altitude, netto, vitesse / MacCready), « Course : thermiques des autres / manqués / masquée », « Pentes », « Terrains », « Fantôme : FDU / mon meilleur vol / sans », « Suivre », « Caméra : derrière / orbite / cinéma », « Instruments », « Vue pilote ». Sur la carte : le planeur avec un drapeau (vitesse, vario, moyenne 30 s, altitude, hauteur sol), la légende de couleur de la trace, en 3D les cylindres de thermiques, les flèches de pente et de descendance, les manches à air, le rideau sous la trace, les terrains (verts à portée), le rayon de plané, les cases orange des pentes prévues, les gros cylindres des thermiques des autres pilotes, le fantôme ; panneau d'**instruments** (horizon artificiel, variomètre à aiguille, vitesse avec « ralentir / accélérer »).
> - **Colonne à droite** : valeurs à l'instant (temps, altitude, vitesse sol, vario, hauteur sol, finesse requise vers l'arrivée, terrain à portée le plus proche, écart avec le fantôme), phase en cours, branches du circuit (temps et vitesse de chaque branche), **radar de centrage** du thermique (vario sur les 8 côtés du cercle + phrase « décalez les cercles vers le sud-est », temps de centrage), liste des phases (thermique, transition, pente, onde, lancement) cliquables avec « Comparer » sur les thermiques.
> - **Barre de lecture** : lecture/pause, vitesse ×1 à ×300, « Spirales × 5 », curseur, lien vers l'instant ; au-dessus les points de virage ; dessous la **frise d'événements** : bandes de thermique colorées par la montée (hachurées pendant le centrage et en haut quand ça faiblit), pente (orange), onde (violet), petits traits des ascendances traversées et des fortes descendances, triangles des points bas, des thermiques manqués et des commentaires, ligne de la transition finale, alertes « trop bas » et « aucun terrain à portée ».
> - **Cinq courbes** synchronisées : altitude (thermiques en vert), vario (avec le netto), vitesse sol, hauteur sol (seuils 300 et 150 m), finesse requise vers l'arrivée.
> - **Analyse par phases** : tuiles, tableau de toutes les phases, cartes « Spirale », « Taux de montée par altitude », « Vent par altitude ».
> - **Le planeur et sa polaire** (courbe de polaire avec les transitions en points), **Points de virage** (tableau), **Deux thermiques côte à côte**, **Commentaires** (formateurs et pilote, à un instant du vol, avec étape).
>
> Montre la page aux moments clés : chargement, vol seul sans tâche, vol en tâche avec polaire, rejeu en cours en 3D (instruments ouverts, fantôme visible), un thermique sélectionné (radar), et la **vue mobile** (la carte, la lecture et les valeurs doivent rester utilisables d'une main).
>
> **2. Comparaison de deux vols (« VS »)** — `/vols/11/comparer/8`, LL3 contre FDU sur la même tâche, comparés **le long du circuit** (à la même distance parcourue, pas au même instant). Aujourd'hui :
> - En-tête « LL3 contre FDU », liste pour choisir l'autre pilote de la session.
> - **Tableau des chiffres** des deux pilotes avec l'écart : temps et vitesse circuit, temps en spirale, thermiques, vario moyen, vitesse et finesse en transition, distance.
> - **Moments clés** : « Où LL3 perd du temps » / « où il en gagne » (tronçons de 10 km : temps, spirale, thermiques et montée, vitesse de transition, altitudes d'entrée et de sortie de chacun).
> - **Carte** des deux traces aux couleurs des pilotes (orange / navy), rejeu où les deux planeurs restent à la même distance du circuit, caméra derrière le premier en 3D.
> - **Colonne** : distance du circuit, écart, altitude de chacun ; branches avec les temps et l'écart (rouge perdu, vert gagné).
> - **Courbes** : écart selon la distance (au-dessus de zéro : retard), altitudes des deux selon la distance.
> - **Cascade** « Où LL3 a perdu ou gagné du temps sur FDU » : par branche, une barre flottante de l'écart avant à l'écart après, et ce qui l'a fait (en spirale / en ligne droite ; thermiques, montée et vitesse de chacun). Exemple : Lautaret → Glandon, +17 min 58 s, tout en ligne droite.
> - **Thermiques pris par chacun** (deux colonnes).
>
> Cherche comment rendre **l'écart lisible d'un coup d'œil** (qui mène, de combien, où ça s'est joué) et comment relier la cascade, la courbe d'écart et la carte.
>
> **3. Session** — `/sessions/144`, une soirée de vol en réseau. Aujourd'hui :
> - En-tête : tâche, date, organisateur, liens « Ce plan de vol au club », « Comparer toute la session », « Carte des thermiques ».
> - **Informations pratiques** (heure, serveur, réglages de la tâche : départ, hauteurs mini/maxi, vitesse au départ, pénalités, météo par zone avec vent et plafond).
> - **Résultats officiels** du serveur (rang, pilote, CN, planeur, statut, temps, vitesse, points).
> - **Podium par compétence** (tuiles : le plus rapide, meilleur grimpeur, meilleure finesse, le moins en spirale).
> - **Rejeu de toutes les traces** sur une carte : tous les planeurs avec leur drapeau et leur traîne, « Heure de course » ou « Départ du circuit ensemble », « Thermiques : tous les pilotes / pilote suivi » (en 3D, cylindres regroupés « 5 pilotes · +3,8 m/s »), liste des pilotes à l'instant (rang provisoire, altitude, vario, distance restante), clic pour suivre un pilote.
> - **Classement des traces déposées** (vitesse, temps, distance, vario, finesse, temps en spirale, lien « Comparer »).
> - **Carte des thermiques de la course** avec curseur de temps (un thermique apparaît quand un pilote y monte).
> - **Comparer toute la session** (`/sessions/144/comparer`) : retard de chacun sur le « meilleur virtuel » le long du circuit (une courbe par pilote), altitudes, tableau des branches (meilleur temps en gras), « Où chacun a perdu le plus de temps ».
>
> Cherche comment faire de la session **le récit d'une course** : qui a mené, quand, grâce à quoi, et comment un pilote se situe dans le peloton.
>
> **À livrer.** Pour chaque page, desktop 1440 px puis mobile 390 px, avec des frames nommées en français (« Vol · piste A · desktop », « Vol · piste B · desktop », « Vol · rejeu 3D », « Vol · mobile », « Comparaison · desktop », « Session · desktop », « Session · comparer tout »…) ; les nouveaux composants (barre d'outils de carte regroupée, frise d'événements, carte de conseil, tuile de comparaison, ligne de cascade, panneau d'instruments, légende intégrée) dans une frame « Composants analyse de vol » ; une note par frame sur les choix d'ergonomie et ce qui a été regroupé, replié ou déplacé.

### Second pass: what to keep, what to fix (2026-10-11)

Not generated: no Figma credits left (2026-10-11). The user asked to build what this prompt asks
directly, see roadmap step 7j.

Review of the first generation: the grouped toolbar, the debrief cards, the column that follows the
current phase, the main chart with "all charts", the detail tabs and the race story of the session
are kept; the event strip, the legend, the instruments, the session toolbar, the comparison table,
the charts of "compare all" and the mobile replay go back to what the site does today or get fixed.

> **Deuxième passe sur la page « Analyse de vol ».** Merci pour cette première proposition : la piste A est retenue. Reprends les frames existantes de la page (même nommage, mêmes données réalistes, mêmes styles et composants du site) et corrige-les selon les points ci-dessous. Ne repars pas de zéro : garde tout ce qui est listé dans « À conserver ». Ajoute sur chaque frame modifiée une note courte « Modifié en deuxième passe : … ».
>
> **À conserver tel quel (c'est très bien)**
> - La barre d'outils de carte réduite à cinq entrées : `Carte · 3D · Affichage ▾ · Analyse ▾ · Comparer`, avec les réglages regroupés par thème dans « Affichage » (fond, couleur de trace, course, environnement, fantôme, caméra, instruments, lecture).
> - Le bloc « Quelques repères pour votre débrief » en tête : trois cartes avec un instant cliquable (« 0:36:12 · Thermique n°4 → ») et l'étape du parcours.
> - La colonne de droite qui suit le moment du vol : « Phase en cours · Thermique n°4 », le conseil en titre (« Un centrage rapide »), les valeurs, puis le radar de centrage et « Décaler vers le sud-est » quand un thermique est sélectionné, « Comparer ce thermique ».
> - La « Courbe principale » avec le choix de la métrique et le bouton « Toutes les courbes », la ligne de valeurs à l'instant sous les courbes.
> - La navigation en onglets du détail (Synthèse, Phases, Détails du vol, Thermiques, Conseils et échanges).
> - En session, le « Récit de course résumé » : tuiles (vainqueur, traces, qui mène à l'instant, moment clé), la phrase de récit et les boutons-chapitres (« 0:18:00 · HL4 mène », « 0:40:00 · FDU passe »…).
> - Dans « Comparer tout », le nom du pilote écrit au bout de sa courbe, le motif qui double la couleur, le bouton « Isoler avec FDU ».
> - Dans la comparaison, la branche sélectionnée qui se retrouve dans le tableau et l'« Inspection de branche ».
>
> **À corriger**
> 1. **Frise d'événements** : la largeur de chaque bande doit être **proportionnelle à sa durée** (ou à la distance en comparaison) ; c'est tout son intérêt (un thermique de 4 min se voit plus large qu'un de 40 s). Les petits repères (ascendances traversées, fortes descendances, points bas ▼, thermiques manqués △, commentaires) sont posés à leur instant sur la même frise, pas dans une ligne de texte. Montre les points de virage comme des traits verticaux nommés au-dessus.
> 2. **Légende** : une légende **graphique au-dessus de la carte** (pastilles de couleur, motifs et pictogrammes dessinés, pas des caractères typographiques comme « ▧ » ou « ↗ »), repliable, ouverte par défaut à la première visite. Une seule légende pour la carte et la frise, groupée en « Ascendances », « Descendances », « Repères ». Supprime les deux lignes de texte sous la carte et sous la frise.
> 3. **Instruments** : horizon, vario et vitesse sont **par-dessus la carte**, dans un coin, petits et semi-transparents, visibles pendant le rejeu ; pas une section sous la frise.
> 4. **Panneau « Affichage »** : il s'ouvre **par-dessus la carte** (popover ou tiroir à droite de la carte), sans pousser la carte vers le bas ; réglages en boutons bascule par groupe, pas un tableau « Groupe / Choix actif / Autres options ».
> 5. **Valeurs à l'instant** : une liste clé → valeur compacte (libellé à gauche, chiffre en Inter semi-gras à droite), sans tableau à en-tête « Mesure / Valeur » ; vario en vert ou rouge selon le signe ; hauteur sol en orange sous 300 m, en rouge sous 150 m.
> 6. **Les trois repères du débrief** : quand le vol en a, au moins un des trois est « À travailler » (ex. « Des thermiques de la course manqués · 0:52:30 »), pas seulement « Bien ».
> 7. **Session** : la carte de session a **sa propre barre**, pas celle du vol seul (pas de « Spirales ×5 », pas de « Romans → Versoud », pas de « Thermique n°4 ») : « Heure de course / Départ du circuit ensemble », « Thermiques : tous les pilotes / pilote suivi », vitesse de lecture ; à droite la liste des pilotes à l'instant (rang provisoire, altitude, vario, distance restante, pilote suivi en surbrillance). La frise de session montre les changements de leader (les chapitres du récit) le long du temps de course. Les traces des six pilotes sont dessinées sur la carte avec leur couleur et un drapeau CN.
> 8. **Comparaison (VS)** : commence par **une phrase de tête** et un visuel « qui mène » : « FDU termine 28 min devant LL3 ; l'essentiel se joue entre Lautaret et Glandon (+17 min 58 s, tout en ligne droite) ». Dans « Ce qui fait la différence », colore l'écart selon qu'il est favorable ou non à LL3 (vert / rouge, avec le mot « mieux » / « moins bien » pour ne pas dépendre de la couleur) et ajoute une petite barre par ligne ; LL3 en orange et FDU en navy partout (tableau, carte, courbes), pas ● et —. La carte de comparaison montre le parcours Saint-Auban → Lautaret → Glandon → Challes et les deux traces.
> 9. **Cascade** : un seul graphique en cascade horizontal avec un axe en minutes (écart cumulé de 0 à +28 min), une barre par branche, la branche sélectionnée mise en avant et ses causes à droite (spirale / ligne droite, thermiques, montée, vitesse) ; le tableau des temps par branche passe dans un repli « Voir le tableau » au lieu de répéter les mêmes chiffres.
> 10. **Graphiques de « Comparer tout »** : l'axe vertical couvre toutes les valeurs (aucune courbe ne sort du cadre sous zéro) ; l'étiquette de survol est sur fond plein, au-dessus des courbes, jamais barrée ; le graphique des altitudes montre bien les deux courbes LL3 et FDU (ou toutes, avec le pilote isolé mis en avant) et son étiquette donne des altitudes, pas un retard.
> 11. **Axes de toutes les courbes** : graduations lisibles et complètes (« 3 000 m », « 900 m », pas « 3 0… » ou « 9… »), heures en abscisse toutes les 10 ou 15 min.
> 12. **Mobile (390 px)** : pendant le rejeu, un **bandeau de 3 valeurs** (altitude, vario, hauteur sol) superposé en haut de la carte et une **barre de lecture fixe en bas d'écran** (lecture, curseur, vitesse) ; la barre d'outils tient sur une ligne (icônes + libellés courts ou menu « Options ») ; le reste en onglets et sections repliées pour que la page ne dépasse pas l'équivalent de 4 à 5 écrans avant le pied de page.
> 13. **Retire le texte de maquette** qui n'irait pas dans le site : « Choix disponibles : … », « Vitesse de lecture ×1 à ×300 · ← / → : pas de 5 s », « Données de démonstration… », « Liste des phases · accès clavier » (le lien s'appelle « Liste des phases »), et le tag « Remorqué » qui répète « Départ remorqué ».
>
> **Une idée à garder pour plus tard** : la piste B (récit en chapitres) devient un **mode « Débrief guidé »** que l'on active depuis la page A (bouton dans la barre de lecture) : « Chapitre précédent / suivant » déplacent le même curseur, la colonne de droite affiche le texte du chapitre. Fais-en une frame « Vol · débrief guidé · desktop ».
>
> **À livrer** : les frames existantes mises à jour (piste A desktop, rejeu 3D, thermique sélectionné, mobile, comparaison desktop et mobile, session desktop et mobile, comparer tout desktop et mobile), la nouvelle frame « Vol · débrief guidé · desktop », et dans « Composants analyse de vol » les composants revus : frise proportionnelle, légende graphique, instruments sur la carte, panneau Affichage en superposition, liste de valeurs, barre de session, ligne de cascade avec axe, bandeau mobile de valeurs, barre de lecture mobile.

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
