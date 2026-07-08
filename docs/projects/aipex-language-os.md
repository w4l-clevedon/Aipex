# Aipex Language OS

## Status

ACTIVE project.

## Purpose

Aipex Language OS is a personalised language learning system that converts the user's existing MP3 language lessons into an adaptive recall, speaking and retention engine.

The product should behave like a personal coach, not a generic lesson app. It should know what the learner has heard, what they remember, what they forget, and what to bring back next.

## Design principles

- Short sessions beat long study blocks.
- Speaking and recall come before heavy reading.
- The user should not need to decide what to study next.
- The system should restart gracefully after gaps.
- Recall prompts should feel varied, not repetitive.
- Progress should be visible quickly.
- Mistakes are scheduling data, not failure.
- The system should show only the next useful action.

## MVP goal

Build the smallest useful version around one language and one course first.

The MVP should support:

1. Register a language.
2. Register a course.
3. Register MP3 lesson files.
4. Store lesson metadata.
5. Store or generate a lesson transcript.
6. Extract vocabulary, phrases and prompts from the transcript.
7. Generate simple recall cards.
8. Track recall attempts.
9. Schedule spaced reviews.
10. Provide a simple dashboard showing the next action.

## Suggested first pilot

German is the recommended first pilot language because transcription, phrase extraction and dashboard testing should be cleaner before moving into more complex scripts or lower-resource languages.

## Initial data model

### Languages

- id
- name
- code
- native_name
- script
- active

### Courses

- id
- language_id
- title
- provider
- source_type
- source_location
- active

### Lessons

- id
- course_id
- lesson_number
- title
- audio_source
- duration
- transcript_status
- transcript_text
- processed_status

### Phrases

- id
- lesson_id
- language_id
- source_text
- english_text
- pronunciation_hint
- phrase_type
- difficulty

### Recall items

- id
- phrase_id
- prompt_type
- prompt
- answer
- interval_days
- ease_score
- due_at
- last_reviewed_at

### Recall attempts

- id
- recall_item_id
- result
- response_text
- reviewed_at
- notes

## WordPress plugin direction

Initial implementation can be a WordPress plugin module because it fits the existing Aipex development pattern and can later become part of the broader Aipex OS dashboard.

Possible plugin folder:

- `aipex-language-os`

Possible custom post types:

- `aipex_language`
- `aipex_language_course`
- `aipex_language_lesson`
- `aipex_language_phrase`

Recall scheduling and attempts should use custom database tables because they are transactional and will need efficient due-item queries.

## Processing pipeline

1. Audio lesson is registered.
2. Transcription job is queued or transcript is manually pasted.
3. Transcript is saved against the lesson.
4. Phrase candidates are extracted.
5. Recall prompts are generated.
6. Items enter review schedule.
7. User completes short recall sessions.
8. Recall performance updates the schedule.

## MVP screens

### Dashboard

Shows:

- current language;
- current course;
- next lesson;
- due review count;
- start sprint button;
- continue lesson button.

### Lesson screen

Shows:

- audio file;
- transcript;
- extracted phrases;
- generated recall items;
- processing status.

### Recall sprint screen

Shows one prompt at a time:

- English to target language;
- target language to English;
- listening prompt later;
- pronunciation prompt later.

Buttons:

- remembered;
- nearly;
- forgot;
- skip.

## First build issues

1. Scaffold plugin structure.
2. Add CPTs and database tables.
3. Add admin dashboard and language/course/lesson screens.
4. Add MP3 lesson registration.
5. Add transcript storage fields.
6. Add phrase extraction placeholder service.
7. Add recall item generator.
8. Add review scheduler.
9. Add sprint UI.
10. Add first coding-agent prompt file.

## Later modules

- Dropbox direct import.
- Multi-language dashboard.
- Speech recognition for spoken answers.
- Pronunciation scoring.
- Memory graph.
- Cross-language comparisons.
- Tutor chat mode.
- Mobile-first PWA.
- Commercial onboarding.
- Bring-your-own-audio import.
- YouTube, podcast, book and PDF imports.

## Commercial angle

Potential positioning:

Turn any language audio course into a personalised AI recall coach.

Potential markets:

- adult learners who fail with generic apps;
- learners who need short focused practice;
- polyglots;
- course creators wanting an AI companion layer;
- language tutors who want automated review systems.

## Immediate next action

Create the WordPress plugin scaffold and MVP issues.