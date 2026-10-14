# Mon parcours

The training path drawn as a path of progression (roadmap step 11, asked on 2026-10-13): the eight
steps of the training path one after the other, each made of items. Completing every item of a
step, or the validation of the step by a trainer, opens the next one. The aim is to attract young
pilots and to stir up the competition that drives progression, while rewarding good piloting
(safeguards in the [roadmap](roadmap.md)).

## Pages

| URL | Route | Access | Content |
|---|---|---|---|
| `/parcours` | `app_path` | everyone | The eight steps as a path (a line, the cards alternate from tablets on): state of each step for a logged-in pilot (done, current, to unlock, coming soon), items done, qualification of the club; visitors get a registration call |
| `/parcours/etape/{1-8}` | `app_path_step` | everyone; actions for logged-in users | Introduction, trainer's note, the items with their state and actions, previous and next step |
| `/parcours/element/{id}/fait` | `app_path_done` | logged-in, step reached | POST: a reading, or an exercise without flight plan, said done by the pilot (CSRF `path-done-{id}`) |
| `/parcours/quiz/{id}` | `app_path_quiz` | logged-in, step reached | The questions, then the score, the right answers and their explanation; the pilot can try again, the best score is kept |
| `/parcours/exercice/{id}` | `app_path_exercise` | logged-in | An exercise on a flight plan: the pilot's finished tasks on it (personal best, rank), the comparison of their best with the best flight, the ranking of the best pilots |

The member dashboard (`/mon-compte`) shows the current step with its progress, and the training path
of the logbook links to `/parcours`. The pages are `noindex` like every page outside
`SearchEngines::INDEXABLE` ([SEO](seo.md)).

## Steps and items

`App\Training\PilotPath` computes the path of a pilot:

- **Steps** (`PathStep`, one per case of `TrainingStep`, created when missing): an introduction and
  whether the step is **open**. The path stops at the first step that is not open: it and every
  step after it are "Bientôt", so the club opens the steps as their content gets ready.
- A step is **done** when every one of its items is done, or when a trainer validated it in the
  training path of the logbook (`StepProgress`, [flights](flights.md#training-path)). The first open
  step not done is the **current** one; the open steps after it are **to unlock**: visitors and
  pilots can read their items, not do them.
- **Items** (`PathItem`, in their order), of four types (`PathItemType`):

| Type | Done when | Stored |
|---|---|---|
| Lecture ou vidéo | The pilot says so ("C'est fait") | `PathCompletion` |
| Exercice en vol, with a flight plan | A flight of the pilot on the flight plan (task name with its code, as in [sessions](sessions.md)) meets every criterion of the exercise (below) | Read from the flights |
| Exercice en vol, without flight plan | The pilot says so | `PathCompletion` |
| Quiz | The best score reaches the pass mark of the quiz | `PathCompletion` (best score, attempts) |
| Validation par un formateur | The step is validated in the training path | `StepProgress` |

A done step can be done again: exercises and quizzes keep the best result.

## Exercises checked by the analysis

Roadmap 11b. An exercise in flight sets, in the back-office, a glider (as Condor names it, case
ignored) and **criteria** that `App\Training\ExerciseCheck` measures on each flight of the pilot on
its flight plan (`ExerciseCriterion`):

| Criterion | Kind | Measured from |
|---|---|---|
| Circuit terminé | fact | A task speed (task completed) |
| Posé sur un terrain | fact | The track ends stopped (under 10 km/h) within half the runway plus 200 m of an airfield of the landscape (`Airfield`, from the landscape files of Condor) |
| Points de pénalité | at most | Condor's points (IGC exported by Condor), otherwise the estimate of `Penalties` against the settings of the session or of the flight plan; not measured without settings |
| Récupérations d'altitude | at most | Height recoveries of the track |
| Vitesse circuit, vitesse maximale, durée de vol, distance parcourue | at least / at most | Flight |
| Gain d'altitude depuis un point bas | at least | `FlightMarks` |
| Thermiques exploités, vario moyen en thermique, temps passé à spiraler | at least / at most | Statistics of the analysis |
| Spirales du côté le moins pratiqué | at least (%) | Share of circling time in the direction turned the least |
| Altitude la plus basse | at least | Statistics of the analysis |

Without criteria, a finished task passes the exercise. A criterion that cannot be measured is not
met. The exercise page lists every flight of the pilot on the flight plan with each criterion met
or missed and the value flown, so that the pilot knows what to work on. The settings Condor does not
write in the track (weather, realism) are not checked: the flight plan of the exercise sets them.

In the trainer's view of a logbook (training path), each step says how many items of "Mon parcours"
the pilot did and links to the best flight of each exercise passed: the proof to validate the step.

## Rankings

The ranking of an exercise lists the best flight **that passed it** (every criterion met) of each
pilot (pilots who left the rankings in their privacy settings are left out), ordered by the
measure chosen for the exercise ("Classement par": task speed by default, or duration, penalties,
climb rate...; the highest first, the lowest for penalties, recoveries, circling time and maximum
speed), the 10 best, with the place of the pilot. Quality comes first, since only clean flights are
ranked. The ranking shows once as many pilots as the "Classement à partir de" of the item (5 by
default) passed the exercise; before, the page says how many did.

Every check is computed when a page is shown (the analysis of a flight is loaded for the criteria
that need it): enough for the club today; store the results if pages get slow.

Not built yet (roadmap 11, 11c): badges of the path, rankings by level and season, pseudonyms for
minors, the moment of the flight that misses a criterion.

## Back-office

`TRAINING_MANAGE` ("Configurer « Mon parcours »"), held by no default group: administrators only.

- **Mon parcours** (`/admin/path-step`): the eight steps (no creation or deletion), "Ouverte" as a
  switch on the list; the form gives the introduction and the items (order, type, title,
  instructions, address of a resource, flight plan, glider and criteria of an exercise, ranking
  measure, quiz, ranking threshold).
- **Quiz** (`/admin/quiz`): title, introduction, pass mark (80 % by default) and the questions: the
  answers one per line, the number of the right one (from 1) and the explanation shown after the
  answer. A quiz is placed in a step from the step form.

Deleting an item deletes what the pilots did on it; deleting a quiz leaves its item without quiz.
