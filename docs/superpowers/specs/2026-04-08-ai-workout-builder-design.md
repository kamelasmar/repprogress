# AI Workout Builder — Design Spec

## Overview

Add an AI-powered plan generator to Repprogress that creates a starting training plan from a short questionnaire. Users review a preview of the generated plan, then accept it into the existing plan builder for fine-tuning. Powered by the OpenAI API (GPT-4o).

## User Flow

1. **Plan Manager** — User clicks "Create Plan" and chooses between "Blank Plan" or "AI Generated."
2. **AI Form** — User fills out a simple form (see Form Fields below).
3. **Loading** — Form submits to PHP. Page displays "Generating your plan..." while the server calls OpenAI.
4. **Preview Page** — Displays the AI-suggested plan: days, sections, exercises with sets/reps, and YouTube links. Each exercise is labelled "Library" (matched) or "New" (AI-created). User can **Accept**, **Regenerate** (back to pre-filled form), or **Cancel** (back to plan manager).
5. **On Accept** — Plan, days, and exercises are inserted into the database. New exercises are added to the public library. User is redirected to the plan builder.

## Form Fields

### Structured

| Field             | Type         | Options                                                        |
|-------------------|--------------|----------------------------------------------------------------|
| Plan name         | text         | Free text                                                      |
| Goal              | select       | Strength, Hypertrophy, Mobility, General Fitness               |
| Experience level  | select       | Beginner, Intermediate, Advanced                               |
| Days per week     | select       | 1-7                                                            |
| Equipment         | select       | Full Gym, Home Gym, Minimal/Bodyweight                         |
| Session duration  | select       | 30 min, 45 min, 60 min, 90 min                                |
| Focus areas       | multi-select | Upper Body, Lower Body, Core, Full Body, Mobility              |

### Free-text

| Field              | Type     | Description                                                    |
|--------------------|----------|----------------------------------------------------------------|
| Additional details | textarea | Injuries, preferences, focus areas, etc.                       |

## Architecture

### New file

- `ai_builder.php` — Handles the form, OpenAI API call, preview rendering, and plan creation on accept.

### No new database tables

Everything fits into the existing schema: `plans`, `plan_days`, `plan_exercises`, `exercises`.

### OpenAI integration

Direct HTTP call from PHP using `curl`. No SDK required.

- API key stored in `includes/_config.php` as `OPENAI_API_KEY`.
- Model: `gpt-4o` (configurable constant).

### AI Prompt Design

**System prompt instructs the AI to:**

- Act as a professional fitness coach.
- Return only a strict JSON object — no markdown, no conversational text, no medical advice.
- Use neutral, professional language in all exercise names and coach tips.
- Follow the exact JSON schema provided (see below).

**User prompt is assembled from:** goal, experience level, days/week, equipment, session duration, focus areas, and the additional details free-text field.

**Exercise library context:** The full exercise list (name + muscle_group) is included in the system prompt so the AI prefers existing exercises. The AI may suggest new exercises when the library doesn't cover what's needed.

### AI Response JSON Schema

```json
{
  "days": [
    {
      "day_label": "Day 1",
      "day_title": "Upper Body Push",
      "sections": [
        {
          "name": "Warm-Up",
          "exercises": [
            {
              "name": "Serratus Wall Slide",
              "muscle_group": "Serratus Anterior",
              "sets": 3,
              "reps": "10-12",
              "coach_tip": "Slow controlled protraction."
            }
          ]
        }
      ]
    }
  ]
}
```

## Exercise Matching

For each exercise name returned by the AI:

1. **Exact match** — Case-insensitive lookup against `exercises.name`.
2. **Fuzzy match** — Case-insensitive `LIKE '%name%'` partial match. If exactly one result, use it. If multiple matches, pick the shortest name (closest match). If zero partial matches, proceed to auto-create.
3. **No match — auto-create** — Insert a new exercise into the `exercises` table:
   - `name`: from AI response
   - `muscle_group`: from AI response
   - `youtube_url`: auto-generated as `https://www.youtube.com/results?search_query=<URL-encoded name>+tutorial+form`
   - `coach_tip`: from AI response
   - `status`: `approved`
   - `is_suggested`: `0`
   - `created_by`: the current user's ID
   - Available to all users (public library)

## Preview Page

- Plan name and summary header (goal, days, equipment, duration).
- Each day rendered as a collapsible card showing day title, sections, and exercises with sets/reps.
- Each exercise displays:
  - A label: **Library** (green badge) for matched exercises, **New** (yellow badge) for AI-created.
  - A clickable YouTube link.
  - Coach tip (if present).
- Three action buttons: **Accept**, **Regenerate**, **Cancel**.
- Preview data stored in `$_SESSION` — no database writes until the user clicks Accept.

## Content Safety

### User input (inbound)

- Basic profanity word filter applied to the free-text "Additional details" field before form submission.
- If profanity is detected, reject with a flash error and return to the form with fields pre-filled.

### AI output (outbound)

- System prompt instructs the AI to use only neutral, professional language.
- System prompt prohibits medical advice, disclaimers, and conversational text.
- AI must respond strictly in the required JSON format.
- PHP sanitizes all AI output: strip HTML tags, validate JSON structure, verify values are within expected types and ranges.
- Coach tips and exercise names are sanitized before display and database insertion.

## Error Handling

| Scenario                    | Behavior                                                                                     |
|-----------------------------|----------------------------------------------------------------------------------------------|
| API key not configured      | "AI Generated" option hidden from the create form. Message shown if accessed directly.       |
| API call fails              | Flash error, redirect to pre-filled form. No partial data saved.                             |
| Invalid JSON from AI        | Flash "AI returned an unexpected response, please try again." Redirect to pre-filled form.   |
| Empty/nonsensical plan      | Validate response has >= 1 day with >= 1 exercise. Otherwise treat as failed response.       |
| Profanity in user input     | Flash error, reject submission, return to pre-filled form.                                   |

## Integration Points

- **`plan_manager.php`** — Modify the "Create Plan" flow to offer "Blank" vs "AI Generated" choice.
- **`includes/_config.php`** — Add `OPENAI_API_KEY` constant.
- **`includes/functions.php`** — Add helper functions for OpenAI API call, exercise matching, profanity filter, and input sanitization.
- **Existing CSS/layout** — Reuse existing dark theme, card components, and button styles from `layout.php`.
