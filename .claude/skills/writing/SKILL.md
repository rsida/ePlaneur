---
name: writing
description: Language and style rules for everything written in the ePlaneur project. Use it for ANY writing task — documentation in doc/, README.md, CLAUDE.md, AGENTS.md, code comments and docblocks, commit messages, pull request descriptions, changelogs, skills, agent prompts, config file comments, Twig/translation content — and whenever composing a reply to the user. Replies to the user are in French; written artifacts are in English unless the user explicitly asks for another language; user-facing site content is in French.
---

# Writing in the ePlaneur project

## 1. Language rules (mandatory)

| What | Language |
|---|---|
| Replies in the chat to the user | **French**, always, including summaries, questions and error reports |
| Documentation (`doc/`, `README.md`, `CLAUDE.md`, `AGENTS.md`), code, comments, docblocks, commit messages, PR descriptions, changelogs, skills, agent prompts, config comments | **English** |
| User-facing content of the website (Twig templates, translation catalogues, fixtures shown to visitors, e-mails sent to members) | **French** (the audience is French-speaking). Prefer translation keys in English with French values |

The user's explicit instruction overrides this table: if they ask for a document in French (or any
other language), write that document in the requested language, and only that one.

When quoting French domain terms in an English document, keep them as they appear on the site and
explain them once (e.g. *fiche FPL*, *ePilote*, *Pause estivale*); the reference list is
`doc/project/glossary.md`.

## 2. Before writing

- Read the related existing documents and match their structure, tone and level of detail.
- For anything about the club, its activities or its users, rely on `doc/project/` (functional
  reference) instead of assumptions. If a fact is missing or outdated there, say so to the user and
  update the reference after checking the source (https://club.eplaneur.fr).
- For anything about the technical stack, rely on `doc/` and the code, not on memory.

## 3. Style for English documents

- Lead with what the reader needs; no preamble ("This document aims to...") beyond one sentence.
- Short sentences, active voice, present tense. Concrete over abstract: commands, paths, values.
- Use tables for comparisons and reference data, numbered lists for procedures, code blocks for
  anything to type or paste.
- Every command shown must have been run or verified; every path and variable must exist.
- Date facts that change (schedules, versions, prices, members): "as of 2026-03".
- No marketing tone, no emojis, no filler conclusions.
- Markdown: one `#` title per file, sentence-case headings, relative links between docs, wrap lines
  around 100 characters.

## 4. Style for French replies

- Natural, direct French; tutoiement if the user uses it (they do).
- Start with the outcome, then what the user needs to know or decide.
- Code identifiers, commands and file paths stay as-is (in backticks), not translated.

## 5. Commit messages and PRs

- English, imperative mood, subject ≤ 72 characters, blank line, then a body explaining *why* when
  it is not obvious.
- Add the attribution lines required by the current session instructions.

## 6. Keep documentation in sync

When a change affects behaviour, commands, configuration or the functional scope, update the matching
file in the same change:

| Change | File(s) |
|---|---|
| Docker, env variables, Makefile targets | `doc/installation.md`, `doc/configuration.md`, `doc/development.md`, `doc/production.md` |
| Tests or quality tooling | `doc/tests.md` |
| Design tokens, Twig components, icons | `doc/design-system.md` + the `/_toolkit` style guide |
| Functional knowledge about the club or site | `doc/project/*.md` |
| Working rules for Claude | `CLAUDE.md` |
| Entry points / quick start | `README.md` |

## 7. Final check

Before finishing, verify: correct language for each artifact (section 1), no broken relative links,
no unverified command, facts consistent with `doc/project/`, and the reply to the user is in French.
