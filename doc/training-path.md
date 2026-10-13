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
| Exercice en vol, with a flight plan | The pilot has a finished task on the flight plan (task name with its code, as in [sessions](sessions.md)) | Read from the flights |
| Exercice en vol, without flight plan | The pilot says so | `PathCompletion` |
| Quiz | The best score reaches the pass mark of the quiz | `PathCompletion` (best score, attempts) |
| Validation par un formateur | The step is validated in the training path | `StepProgress` |

A done step can be done again: exercises and quizzes keep the best result.

## Rankings

The ranking of an exercise lists the fastest finished task of each pilot on its flight plan (pilots
who left the rankings in their privacy settings are left out), the 10 best, with the place of the
pilot. It shows once as many pilots as the "Classement à partir de" of the item (5 by default)
finished the exercise; before, the page says how many did.

Not built yet (roadmap 11, 11b, 11c): scores rewarding the quality of the flight rather than speed
alone, criteria read from the track (11b), badges of the path, rankings by level and season,
pseudonyms for minors.

## Back-office

`TRAINING_MANAGE` ("Configurer « Mon parcours »"), held by no default group: administrators only.

- **Mon parcours** (`/admin/path-step`): the eight steps (no creation or deletion), "Ouverte" as a
  switch on the list; the form gives the introduction and the items (order, type, title,
  instructions, address of a resource, flight plan of an exercise, quiz, ranking threshold).
- **Quiz** (`/admin/quiz`): title, introduction, pass mark (80 % by default) and the questions: the
  answers one per line, the number of the right one (from 1) and the explanation shown after the
  answer. A quiz is placed in a step from the step form.

Deleting an item deletes what the pilots did on it; deleting a quiz leaves its item without quiz.
