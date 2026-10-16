# Teacher space

Roadmap step 11f: a teacher (BIA classes, especially in La Réunion, see the letter to teachers in
[project/club.md](project/club.md)) creates a class, gives its link to the pupils and follows where
each of them stands in [Mon parcours](training-path.md) and their flights.

The rules for minors are the most careful ones, chosen on 2026-10-15 while the committee has not
decided (see [open questions](roadmap.md#open-questions)): a pupil is never shown to the others by
their name, nothing they do becomes public without their own choice, and they leave the class when
they want.

## Who is a teacher

`CLASS_TEACH` ("Créer des classes et suivre la progression de ses élèves") is held by no default group:
create an "Enseignant" group with it in "Groupes et droits", or from the console once the group exists:

```bash
make console c="app:group:permissions enseignant --grant=CLASS_TEACH"
```

A teacher who loses the permission no longer opens their classes (`SchoolClassVoter`, `CLASS_FOLLOW`:
the teacher of the class while they hold `CLASS_TEACH`, or an administrator). A teacher is not a
trainer: they do not comment flights nor validate steps (`FLIGHT_REVIEW`).

## Classes

"Mes classes" (`/mon-compte/classes`) appears in the member space for a teacher and for a pupil in a
class (Twig `account_in_class()`).

- **Teacher**: their classes and "Nouvelle classe" (name, school). The page of a class
  (`/mon-compte/classes/{id}`) shows:
  - for each pupil, their display name (and the name the others see), the state of the eight steps
    (done, current with its percentage, not reached), the number of flights, the
    hours and the last flight (`App\Training\ClassProgress`, read from `PilotPath` and the logbook);
    the name opens the pupil's logbook (`LOGBOOK_VIEW` is granted to the teacher of a class the pilot
    joined);
  - the link to join (`/classe/{code}`, 12 letters and digits), "Changer le lien" (the old one stops
    working, the link opens again if it was closed), and "Le lien permet de rejoindre la classe" to
    close it once every pupil joined;
  - "Retirer" a pupil, "Supprimer la classe": the accounts of the pupils stay.
- **Pupil**: the classes they joined, their teacher, "Quitter la classe".

## Joining a class

`/classe/{code}` (not indexed, `Disallow: /classe/`) shows the class, its school and its teacher.

- A visitor is asked to create an account or to log in ("J'ai un compte, je me connecte" goes through
  `/classe/{code}/connexion`, which brings them back to the link after the login).
- A logged-in account reads what joining means, then chooses how the others see them: **first name or
  pseudonym**, never the name (the form of [name shown](accounts.md#name-shown), checked by
  `App\Account\PublicNameCheck`). Joining also makes their next flights private by default
  (`flightsPublicByDefault`); the flights they made public stay so.
- While the account is in a class, "Mon profil" refuses "Mon nom affiché" as the name shown to the
  others. An administrator can still change it in "Comptes".
- An unknown or closed link answers 404 "Ce lien ne sert plus".

## Data

`SchoolClass` (`school_class`: name, school, teacher, code, open, creation date) and its pupils
(`school_class_pupil`). Deleting the teacher's account deletes their classes; deleting a pupil's
account removes them from their classes. The data export of an account lists the classes it joined
and the classes it created.

In the back-office, "Classes" (`/admin/school-class`, `USER_MANAGE`) lists every class: an
administrator gives a class to another teacher, removes a pupil, closes the link or deletes the
class; classes are created by the teachers only.

## To decide with the committee

- Whether a teacher must be a member of the club, and who grants `CLASS_TEACH`.
- The consent of a parent for a pupil under 15 (GDPR): today the pupil joins alone.
- Whether a teacher may write to their pupils in private messages.
