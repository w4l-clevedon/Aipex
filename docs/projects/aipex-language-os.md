# Aipex Language OS

## Status

ACTIVE project.

## Purpose

Aipex Language OS is a personalised ADHD-friendly language learning system that converts user-supplied language course packs into an adaptive recall, speaking, listening and retention platform.

The system should behave like a personal coach, not a generic lesson app. It should know what the learner has heard, what they remember, what they nearly remember, what they forget, and what to bring back next.

## Copyright and branding rule

Do not mention any third-party commercial course brand in code, UI labels, documentation, comments, GitHub issues, prompts or product copy.

Use only generic terms such as:

- language course packs
- user-supplied MP3 lessons
- supporting PDFs
- reference images
- imported learning material

## Design principles

- Short sessions beat long study blocks.
- Speaking and recall come before heavy reading.
- The learner should not need to decide what to study next.
- The system should restart gracefully after gaps.
- Recall prompts should feel varied, not repetitive.
- Progress should be visible quickly.
- Mistakes are scheduling data, not failure.
- The interface should show only the next useful action.
- Avoid clutter, guilt language and decision fatigue.

## MVP goal

Build the smallest useful version around one pilot language dataset and one course first.

The MVP should support:

1. Register one language.
2. Register one course.
3. Upload one or more ZIP course packs.
4. Extract ZIPs safely into protected storage.
5. Classify MP3, PDF, JPG and JPEG files.
6. Ignore irrelevant files unless later whitelisted.
7. Detect duplicates using checksum/hash.
8. Store import logs.
9. Register MP3 files as lesson candidates.
10. Store or generate lesson transcripts.
11. Extract vocabulary, phrases and prompts from transcripts.
12. Generate simple recall cards.
13. Track recall attempts.
14. Schedule spaced reviews.
15. Provide a simple dashboard showing the next action.
16. Provide a 5-minute sprint UI.

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
- source_type
- source_location
- active

### Course packs

- id
- language_id
- course_id
- zip_filename
- stored_zip_path
- import_status
- imported_at
- import_log

### Course assets

- id
- language_id
- course_id
- course_pack_id
- original_filename
- stored_file_path
- detected_type
- mime_type
- file_size
- checksum
- import_status
- notes

### Lessons

- id
- course_id
- primary_asset_id
- lesson_number
- title
- audio_source
- duration
- transcript_status
- transcript_text
- processed_status

### Supporting materials

- id
- course_id
- asset_id
- material_type
- title
- extracted_text
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

## ZIP import rules

Each language/course can have one or more ZIP uploads.

ZIPs may contain:

- MP3 audio files
- PDF manuals, guides or booklets
- JPEG/JPG images
- nested folders
- irrelevant files to ignore

Importer must:

- accept multiple ZIPs per language/course;
- extract safely into protected storage;
- prevent path traversal;
- detect duplicates using checksum/hash;
- classify files by type;
- log imported, skipped, duplicate, ignored and failed files;
- preserve original filenames;
- store extracted file paths;
- associate assets with language, course and course pack;
- allow manual review of imported assets.

File classification:

- `.mp3` = audio lesson
- `.pdf` = supporting document
- `.jpg` / `.jpeg` = supporting image
- all others = ignored unless later whitelisted

## Admin screens

### Language management

Admin can add/edit languages, set active language, set language code, set script type, and view counts for courses, lessons and due reviews.

### Course management

Admin can create a course under a language, upload ZIP packs, view import status, view detected files, map files to lessons, rename lessons, reorder lessons, and mark a course active/inactive.

### Import screen

Shows language selector, course selector, ZIP uploader, upload status, detected audio files, detected PDFs, detected images, ignored files, duplicate files and errors.

### Lesson screen

Shows lesson title, lesson number, audio player, transcript field, transcript status, extracted phrases, generated recall items and processing status.

### Phrase review

Admin can approve, edit or delete phrases; add translations; add pronunciation hints; set difficulty; and create recall items.

## Learner dashboard

The learner dashboard should show:

- current language;
- current course;
- next lesson;
- lessons completed;
- due review count;
- fragile words or phrases;
- start 5-minute sprint button;
- continue lesson button.

The learner should not need to decide what to do next.

## Recall sprint UI

A 5-minute sprint should show one prompt at a time, ask for recall, reveal the answer, then let the learner mark the result.

Buttons:

- Remembered
- Nearly
- Forgot
- Skip

Prompt types:

- English to target language
- target language to English
- phrase completion
- listening prompt later
- spoken answer later

## Basic spaced repetition logic

- Remembered: increase interval.
- Nearly: short review interval.
- Forgot: reset or review soon.
- Skip: no penalty or minor delay.

Store last reviewed date, next due date, ease score and result history.

## Processing pipeline

1. Create language.
2. Create course.
3. Upload ZIP course packs.
4. Extract ZIPs safely.
5. Classify files.
6. Register MP3s as lesson candidates.
7. Attach PDFs/images as supporting materials.
8. Create/edit lesson order.
9. Add/generate transcript.
10. Extract phrases.
11. Generate recall prompts.
12. Schedule reviews.
13. Run recall sprints.
14. Update learning model from recall attempts.

## Later modules

- Dropbox direct import.
- Multi-language dashboard.
- Automatic transcription.
- PDF text extraction.
- OCR for images.
- Pronunciation scoring.
- Spoken answer recognition.
- AI tutor chat.
- Memory graph.
- Cross-language comparisons.
- Mobile/PWA version.
- Commercial onboarding.
- Bring-your-own-course import.
- Podcast, YouTube, book and PDF imports.

## Commercial angle

Potential positioning:

Turn any language course pack into a personalised AI recall coach.

Potential markets:

- adult learners;
- ADHD learners;
- people who struggle with generic apps;
- polyglots;
- tutors;
- course creators.

## Current GitHub issues

- #10: scaffold plugin module
- #11: data model and storage
- #12: transcript and phrase extraction workflow
- #13: 5-minute recall sprint UI
- #14: multi-ZIP course pack importer

## Immediate next action

Test the importer with one pilot course pack, then add manual lesson review, lesson reordering and transcript entry.