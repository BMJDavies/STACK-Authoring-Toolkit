---
name: quiz-settings
description: Default Moodle quiz settings for interactive-textbook style quizzes, and how to override them. Wraps tools/quiz-admin.php.
---

# Quiz settings

## Defaults

These are the settings `quiz-admin.php` applies on creation. They are chosen for
formative, work-through-it material rather than assessment.

| Setting | Default | Why |
|---------|---------|-----|
| Question behaviour | `immediatefeedback` | The student checks each answer as they go, which is the whole point of STACK feedback |
| Shuffle questions | off | Pedagogic order matters |
| Shuffle answers | off | Same |
| Attempts allowed | unlimited | Formative use, let them retry |
| Grading method | highest | Keep the best attempt |
| Questions per page | 1 at creation | Page grouping is then set by `push-live.sh` |
| Show description on course page | off by default | Turn it on where two quizzes in a row need telling apart |
| Review options | show everything after submission | Students need the worked solutions |
| Time limit | none | Formative use |

The alternative behaviour worth knowing about is `adaptivenopenalty`, which lets
a student retry within a single attempt. Older material of ours uses it. If you
are starting fresh, `immediatefeedback` is the better default.

## Using `tools/quiz-admin.php`

```bash
docker cp tools/quiz-admin.php <container>:/tmp/qa.php

# Create a quiz. ALWAYS pass --section.
docker exec <container> php /tmp/qa.php create \
    --courseid=<n> --name="5A: Linear inequalities" --section=5

# Override a default at creation time
docker exec <container> php /tmp/qa.php create \
    --courseid=<n> --name="Timed mock" --section=1 --timelimit=3600

# Update an existing quiz
docker exec <container> php /tmp/qa.php update --quizid=<n> --attempts=2

# Show current settings
docker exec <container> php /tmp/qa.php show --quizid=<n>
```

Unknown keys are rejected rather than silently ignored, so a typo fails loudly
instead of quietly doing nothing.

## `--section` is not optional

Omitting it puts the quiz in section 0 (General), at the top of the course page,
above everything. This is the single most common mistake when creating quizzes
from the command line. Find the right section number from the course page (the
first topic is section 1) before you run the command.

## Page grouping

`quiz-admin.php` only creates the shell. Grouping questions onto pages happens
automatically when you run `push-live.sh`, which keeps descriptions with the
questions that follow them and avoids stranding a single item on its own page.

If you need bespoke grouping beyond that, do it in Moodle's Edit Quiz screen and
flag it, because the next push will regroup and undo your work. If you have hand
built a page layout you care about, record the slot-to-page map before
re-pushing so you can reapply it.

## Naming convention

Pick one and stick to it, because `push-live.sh` matches questions to quiz slots
by name and the page grouper looks for multi-part questions by name pattern. A
workable scheme:

```
[Book]_Ch[N]_[Abbrev]_[Section]_[Description]
```

For example `C1_Ch5_Ineq_A_LinearInequalities`, `C1_Ch5_Ineq_TestYourself`.

Multi-part questions should share a stem and differ by a trailing letter
(`Ch1_Q06a_...`, `Ch1_Q06b_...`), which is what keeps them on the same page.

Names are admin-only and never shown to students, so they can be as
machine-friendly as you like. But treat them as immutable once published.
