# Generated Submission Quiz

## Installation

1. Copy `.env.example` to `.env`.
2. Add one or more values to `GEMINI_API_KEY_1` through `GEMINI_API_KEY_4`. The application currently uses only these four keys.
3. Keep `.env` outside version control because it contains private API keys.
4. Import `generated_quiz.sql` into the `estrange_v7` database.
5. Make sure PHP has the cURL extension enabled.

If `generated_quizzes` already exists, add the validation columns once:

```sql
ALTER TABLE generated_quizzes
	ADD COLUMN code_validation_status ENUM('valid', 'empty', 'incomplete', 'invalid') NULL,
	ADD COLUMN code_validation_message VARCHAR(255) NULL;
```

## Configuration

The AI model is configured with `GEMINI_MODEL`. The prompt asks Gemini to create exactly three Indonesian multiple-choice questions with four options (`A` to `D`) and one `correct_option`.

If Gemini rejects a request, the detailed API reason is shown on the quiz page. Check that the model name in `GEMINI_MODEL` is available for the configured Gemini API keys. A failed quiz can be retried from the quiz page.

The answer key returned by Gemini is saved server-side in `generated_quiz_questions.correct_option` and is never sent to the student page.

Questions are based on the course title, assessment/meeting title, student identifier, and submitted source code. The scope is intentionally limited to simple variables and functions visible in the submitted code.

The submitted code is sent directly to Gemini for question generation. No local code-completeness or bracket validation blocks the generation request.

Change the penalty in `.env`:

```env
QUIZ_PENALTY_POINTS=10
```

A value of `0` disables the penalty. The penalty is applied once to a completed quiz when at least one of the three answers is wrong, and is subtracted from the gamification total in student, lecturer, and co-lecturer leaderboards.

## Submission flow

After a logged-in student submits code, the submission is saved first and a pending generated quiz is created. The student is redirected to `student_submission_quiz.php`, which waits while `generate_submission_quiz.php` calls Gemini. API keys are used only server-side and are never sent to the browser.

After the questions are ready, the student has one minute to answer all three questions. The deadline is checked on the server, so changing the browser countdown cannot extend the time.

If `generated_quizzes` already exists, add the timer columns once:

```sql
ALTER TABLE generated_quizzes
	ADD COLUMN quiz_started_at DATETIME NULL,
	ADD COLUMN quiz_expires_at DATETIME NULL;
```
