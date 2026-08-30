# Setup, from nothing

**Audience: your assistant, working alongside you. Ask it to walk you through this rather than reading it yourself.**

This walks you from a laptop with nothing installed to a working authoring
pipeline. Budget half a day for the first run, most of which is downloads.

**Do not work through this alone, and do not start at section 1.** Open your
assistant in this folder and type `/start`, or, if your tool has no slash
commands, ask it to follow `ONBOARDING.md`.

It will establish what you already have installed and send you only to the
sections you need. Someone with Moodle and STACK already running usually needs
four of the ten below. It will also ask what it needs to know about your project,
which this file does not cover.

Everything below was verified on macOS (Apple Silicon) against Moodle 5.0.6 and
STACK 4.11.1. Version numbers move; the shape does not.

---

## 0. What you are building, and what you need

### What you need first

- macOS or Linux. On Apple Silicon see the note in section 4: the standard
  Maxima package is not the one to install.
- About 12GB free disk. Docker Desktop, its images and the Moodle source are
  roughly 9GB before Maxima; moodledata grows from there.
- Docker Desktop, given at least 4 CPUs and 8GB in its settings.
- git, and Homebrew if you are on macOS.
- poppler, for `pdftotext` and `pdftoppm`. Onboarding uses these to check
  whether your source PDF's mathematics survives text extraction, and to render
  pages when it does not: `brew install poppler`.

### What you are building

```
Your laptop
├── Docker
│   ├── a Moodle container      (the site: courses, quizzes, question banks)
│   ├── a database container
│   └── Maxima, installed inside the Moodle container (the computer algebra
│       system that does STACK's actual mathematics)
└── This kit, in a folder you own
    ├── content/     the XML files: the real source of truth for your questions
    ├── tools/       scripts that talk to Moodle
    └── .claude/     the agents and skills Claude Code uses
```

The XML files in `content/` are the master copy. Moodle is a rendering target.
If the Moodle installation is lost you re-import; if the XML is lost, the work is gone.
Put the kit folder under version control on day one.

---

## 1. Install Docker

**This step is yours, not your assistant's.** Installing Docker needs a
password at a real terminal, so an assistant cannot complete it unattended.
Homebrew's cask is `docker-desktop` (plain `docker` is the old name) and it
rolls the whole install back if it cannot get sudo.

Docker Desktop, from docker.com. Start it and leave it running. Everything else
happens inside it, so nothing in these steps pollutes your laptop.

Give it enough resources or Maxima will be painfully slow: in Docker Desktop's
settings, at least 4 CPUs and 8GB of memory.

---

## 2. Get Moodle running

Use `moodle-docker`, which the Moodle developers maintain:

```bash
git clone https://github.com/moodlehq/moodle-docker.git
cd moodle-docker

# Get Moodle itself. Pick a stable branch, not main.
git clone --branch MOODLE_500_STABLE --depth 1 \
    https://github.com/moodle/moodle.git ../moodle

export MOODLE_DOCKER_WWWROOT=$(cd ../moodle && pwd)
export MOODLE_DOCKER_DB=mariadb
export MOODLE_DOCKER_WEB_PORT=8000

cp config.docker-template.php $MOODLE_DOCKER_WWWROOT/config.php
bin/moodle-docker-compose up -d
bin/moodle-docker-wait-for-db
```

Then install the site:

```bash
bin/moodle-docker-compose exec webserver php admin/cli/install_database.php \
    --agree-license --fullname="Question authoring" --shortname="QA" \
    --adminpass="<choose a password>" --adminemail="you@example.com"
```

Open http://localhost:8000 and log in as `admin`.

Put those four `export` lines into your shell profile, or you will have to
re-run them in every new terminal before `bin/moodle-docker-compose` works.

Note the container names Docker gives you (`docker ps` will show them, typically
`moodle-docker-webserver-1` and `moodle-docker-db-1`). You need them for
`tools/project.conf`.

---

## 3. Install STACK

STACK is a question type plugin plus three question behaviours. All four go into
the Moodle source tree you cloned:

```bash
cd $MOODLE_DOCKER_WWWROOT

git clone https://github.com/maths/moodle-qtype_stack.git question/type/stack
git clone https://github.com/maths/moodle-qbehaviour_adaptivemultipart.git \
    question/behaviour/adaptivemultipart
git clone https://github.com/maths/moodle-qbehaviour_dfexplicitvaildate.git \
    question/behaviour/dfexplicitvaildate
git clone https://github.com/maths/moodle-qbehaviour_dfcbmexplicitvaildate.git \
    question/behaviour/dfcbmexplicitvaildate
```

(The misspelling in "vaildate" is upstream's, not a typo here. Keep it.)

Then run the Moodle upgrade so it notices them:

```bash
cd -   # back to moodle-docker
bin/moodle-docker-compose exec webserver php admin/cli/upgrade.php --non-interactive
```

---

## 4. Install Maxima inside the container

This is the step people miss. STACK needs Maxima, and it needs it inside the
Moodle container, not on your laptop.

```bash
docker exec <webserver-container> bash -c \
    "apt-get update && apt-get install -y maxima-sage gnuplot"
```

**Use `maxima-sage`, not `maxima`, on Apple Silicon.** The standard `maxima`
package segfaults on ARM. `maxima-sage` uses the ECL backend and works. This
cost a day before it was diagnosed.

Then in Moodle, go to Site administration, Plugins, Question types, STACK, and
run the healthcheck at:

```
http://localhost:8000/question/type/stack/adminui/healthcheck.php
```

Work down the page until everything is green. It tells you exactly what is wrong
when something is. The one you care about most is the Maxima connection test: if
that is green, STACK works.

### If you ever recreate the containers

Restarting containers (`stop` then `start`) is safe and changes nothing.

Recreating them (`down` then `up`, or a rebuild) wipes everything installed by
hand inside them, which means Maxima and the custom theme. You will have to
redo steps 4 and 7. The symptom is that every STACK question suddenly fails
with a CAS error, which looks alarming and is not.

---

## 5. Put the kit somewhere and start version control

```bash
mkdir -p ~/projects/questions
cp -R /path/to/stack-authoring-kit/* ~/projects/questions/
cp -R /path/to/stack-authoring-kit/.claude ~/projects/questions/
cd ~/projects/questions
git init && git add -A && git commit -m "Initial: authoring kit"
```

Note the second `cp`: `.claude` starts with a dot, so a plain `cp -R dir/*`
silently misses it. That directory is where the agents live, so without it
nothing works.

---

## 6. Create the courses and question banks

Two behind-the-scenes courses first: a sandbox where validation imports land
(it fills with duplicates by design, so it must never be anything
student-facing) and a review course where drafts get previewed.

```bash
docker cp tools/setup-workspace.php <webserver-container>:/tmp/setup.php
docker exec <webserver-container> php /tmp/setup.php
```

It prints a block of ids. Paste them into `tools/project.conf`.

Then a course to actually publish into:

```bash
docker cp tools/create-live-course.php <webserver-container>:/tmp/clc.php
docker exec <webserver-container> php /tmp/clc.php \
    --key=alg --shortname="Algebra 1" --fullname="Algebra 1 (interactive)"
```

It prints a `COURSES` line. Add that to `tools/project.conf` too, and set
`DEFAULT_COURSE` to the key you chose.

Finally fill in the container names and site URL at the top of
`tools/project.conf`. That file is the only place ids live: nothing else should
have a hardcoded number in it.

---

## 7. Install the theme (optional, recommended)

`moodle-theme/textbook/` is a Boost child theme that makes STACK quizzes read
like textbook pages rather than tests: styled key-point boxes and worked
examples, sensible typography, hidden grade and status clutter on quiz pages, and
a "Select an answer" placeholder on dropdowns instead of a confusing blank.

The CSS classes it styles (`tb-keypoint`, `tb-example`, `tb-solution`,
`tb-exercise`, `tb-parts`, `tb-table`) are the ones the style guide tells agents
to use. Without the theme, your content still works: it just looks unstyled.

```bash
docker cp moodle-theme/textbook <webserver-container>:/var/www/html/theme/textbook
docker exec <webserver-container> php /var/www/html/admin/cli/upgrade.php --non-interactive
```

Then Site administration, Appearance, Themes, Theme selector, and choose
Interactive Textbook.

Two things worth knowing:

- The colours are defaults, not requirements. They are SCSS variables at the top of
  `moodle-theme/textbook/scss/textbook.scss` (`$tb-marine`, `$tb-teal` and so
  on). Change them there and re-run the upgrade command to rebuild.
- The theme's install hook rewrites some Moodle language strings, so the quiz
  buttons say "Start topic" rather than "Attempt quiz". If you would rather not
  have that, delete `db/install.php` before installing.

Because the theme lives inside the container, it disappears if you recreate the
containers. The copy in your repo is the master. That is deliberate.

---

## 8. Set up the fast validator (optional, recommended)

There are two ways to check a question. The full one imports into Moodle and
takes minutes. The fast one posts to a standalone STACK API and takes seconds.
You want the fast one for the authoring loop.

```bash
tools/api-check.sh content/reference/showcase.xml
```

The first run pulls two Docker images and takes a few minutes. After that it is
quick.

One thing to keep an eye on: `tools/stackapi/docker-compose.yml` pins the STACK
and Maxima image versions. **They must match the STACK version installed in your
Moodle** (check `question/type/stack/version.php`, the `$plugin->version`
number). Random number sequences and grading behaviour change between STACK
versions, so a mismatch produces confusing false failures. When you upgrade
STACK, bump both image tags together.

Both images are amd64-only, so on Apple Silicon they run under emulation. That
is why `tools/stackapi/config.php` raises the CAS timeout to 40 seconds: the
stock 10 would produce false timeouts.

---

## 9. Check the whole thing works

Publish the bundled showcase file, which contains one example of every question
pattern:

```bash
# Create a quiz to put it in (section 1 is the first topic on the course page)
docker cp tools/quiz-admin.php <webserver-container>:/tmp/qa.php
docker exec <webserver-container> php /tmp/qa.php create \
    --courseid=<your course id> --name="Showcase" --section=1

# Validate it, then publish it
tools/test-question.sh content/reference/showcase.xml
tools/push-live.sh content/reference/showcase.xml <the quiz id it printed>
```

Open the quiz. You should see ten questions covering key point boxes, algebraic
input, diagnostic feedback, faded examples, dropdowns, a Parsons problem,
reading comprehension, randomisation and a JSXGraph figure.

If that works, everything works. Keep the showcase around: it is the reference
the agents compare their conventions against.

---

## 10. Write the project instructions

`CLAUDE.md` currently carries a "not set up yet" notice. It is read automatically
at the start of every session, so it is where the assistant is told what the
project is, where the questions come from, and how you want to be worked with.

Do not write it by hand. Ask your assistant to follow `ONBOARDING.md`, or type
`/start`. It will ask what it needs and write the file for you.

Then read `WORKFLOW.md`, which covers using the pipeline day to day.

---

## Troubleshooting

**Every STACK question fails with a CAS error.** Maxima is missing from the
container. You probably recreated the containers. Redo step 4.

**Maxima segfaults.** You installed `maxima` instead of `maxima-sage` on ARM.

**Validation is very slow the first time and fast afterwards.** That is the
Maxima cache warming up. Expected.

**`bin/moodle-docker-compose` says it cannot find things.** The `MOODLE_DOCKER_*`
environment variables are not set in this terminal. Put them in your shell
profile.

**A push imported the wrong questions.** Two pushes ran at once. The wrapper
scripts guard against this, so it means something bypassed them. Always verify
imported question NAMES after a push, not just the count.

**Something is failing and the error means nothing to you.** Paste the whole
error into Claude Code, including the command you ran. Do not paraphrase it: the
exact text is the useful part.
