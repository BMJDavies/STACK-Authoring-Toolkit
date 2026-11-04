# Setup, from nothing

**Audience: your assistant, working alongside you. Ask it to walk you through this rather than reading it yourself.**

This walks you from a laptop with nothing installed to a working authoring
pipeline. Most of the first run is downloads.

**Do not work through this alone, and do not start at section 1.** Open your
assistant in the kit folder and type `/start`, or, if your tool has no slash
commands, ask it to follow `docs/ONBOARDING.md`.

It will establish what you already have installed and send you only to the
sections you need. Someone with Moodle and STACK already running usually needs
four of the ten below. It will also ask what it needs to know about your project,
which this file does not cover.

Everything below was verified on macOS (Apple Silicon) against Moodle 5.0.6.
Version numbers move; the shape does not. There are two exceptions. STACK itself
must be 4.13.0 or later, in Moodle and in the validator (section 8).
`tools/stack-version` holds that minimum, and `tools/check-stack-version.sh`
checks everything against it. Moodle must be 5.0 or later: the setup tools in
section 6, `setup-workspace.php` and `create-live-course.php`, create question
banks as activity modules, which Moodle 4.x does not have, and on 4.x they stop
with an error.

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
│   ├── Maxima, installed inside the Moodle container (the computer algebra
│   │   system that does STACK's actual mathematics)
│   └── the validation service, two more containers (section 8)
└── This kit, in a folder you own (a git clone)
    ├── tools/       scripts that talk to Moodle
    ├── .claude/     the agents, procedures and skills the assistant uses
    └── project/     YOUR project, a separate git repository inside the kit
        ├── content/       the XML files: the real source of truth for your questions
        └── project.conf   your Moodle ids
```

Two different things check and show your questions, and it helps to keep them
apart:

| Name | Where | What it is for |
|---|---|---|
| Moodle | `localhost:8000` (from `project.conf`) | The site students use. Also runs the final test of each file (`test-question.sh`) |
| Validation service | `localhost:3080` | A standalone STACK API with its own Maxima. Checks XML in seconds while authoring (`api-check.sh`). Students never see it, and it has no database |
| Sandbox question bank | inside Moodle, the "dev" course | Where `test-question.sh` imports files to test them |
| Review quiz | inside Moodle, hidden from students | Where the reviewer previews a section before it goes live |
| Live course | inside Moodle | What students see |

The XML files in `project/content/` are the master copy. Moodle is a rendering
target. If the Moodle installation is lost you re-import; if the XML is lost, the
work is gone. `project/` is its own git repository from the moment it is created;
commit to it from day one. The kit's repository ignores it, so updating the kit
never touches your work.

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
`project/project.conf`.

---

## 3. Install STACK

STACK is a question type plugin, three question behaviours, and a question bank
plugin. All five go into the Moodle source tree you cloned:

```bash
cd $MOODLE_DOCKER_WWWROOT

git clone https://github.com/maths/moodle-qtype_stack.git question/type/stack
git clone https://github.com/maths/moodle-qbehaviour_adaptivemultipart.git \
    question/behaviour/adaptivemultipart
git clone https://github.com/maths/moodle-qbehaviour_dfexplicitvaildate.git \
    question/behaviour/dfexplicitvaildate
git clone https://github.com/maths/moodle-qbehaviour_dfcbmexplicitvaildate.git \
    question/behaviour/dfcbmexplicitvaildate
git clone https://github.com/maths/moodle-qbank_importasversion.git \
    question/bank/importasversion
```

(The misspelling in "vaildate" is upstream's, not a typo here. Keep it.)

STACK's dependencies change between releases, and this list will go stale. The
authority is the `$plugin->dependencies` block in
`question/type/stack/version.php`: clone whatever it names that Moodle core does
not ship. If you miss one, section 2's `install_database.php` fails with
"Dependencies check failed for qtype_stack" without naming the missing plugin,
one section before the step that is actually wrong, and it exits 0 while doing
so.

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

**Use `maxima-sage`, not `maxima`.** The standard Debian `maxima` package is
built on GCL, and it segfaults inside a Docker container on Apple Silicon and on
x86 Linux alike, even on `maxima --version`. On x86 the cause is confirmed: GCL
calls `personality()` to disable address randomisation, and Docker's default
seccomp profile blocks that call (`setarch -R` fails with "Operation not
permitted"). `maxima-sage` uses the ECL backend and works. This cost a day
before it was diagnosed.

The price is speed. STACK can prebuild a Maxima image (`linux-optimised`) only
for GCL, SBCL or CLISP, not ECL, so `platform` stays `linux`. That means every
CAS call starts a fresh Maxima and reloads the STACK libraries. It barely shows
on the showcase. A full section with multi-node PRTs can take an hour or more
to validate.

**Recommended: run the CAS as a separate goemaxima server instead.** This is
how production STACK sites run, it survives recreating the Moodle containers,
and it needs nothing installed inside them. The image tag must match your STACK
version (`$plugin->version` in `question/type/stack/version.php`):

```bash
docker run -d --name moodle-goemaxima --network <moodle-network> \
    --restart unless-stopped --read-only --tmpfs /tmp \
    --cap-drop ALL --cap-add SETGID --cap-add SETUID \
    -e GOEMAXIMA_QUEUE_LEN=32 mathinstitut/goemaxima:<stack-version>-latest

cd $MOODLE_DOCKER_WWWROOT
for kv in platform=server \
          maximacommandserver=http://moodle-goemaxima:8080/goemaxima; do
    docker exec <webserver-container> php admin/cli/cfg.php \
        --component=qtype_stack --name="${kv%%=*}" --set="${kv#*=}"
done
docker exec <webserver-container> \
    php question/type/stack/cli/clearcascache.php
```

`<moodle-network>` is the network your webserver is on:
`docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' <webserver-container>`
(for moodle-docker it is usually `moodle-docker_default`). On one x86 machine
this cut the showcase from 51 to 20 seconds, and a 28-item section from over an
hour to about two minutes. The image is amd64-only, so on Apple Silicon it runs
under emulation (see section 8). Where it cannot run at all, see "When
goemaxima is not available" below. When you upgrade STACK, replace the container
with the matching tag, or every question fails with a library-version error.

Keep `maxima-sage` installed as the fallback. The `linux` settings below still
apply if you ever switch `platform` back.

Then tell STACK how to reach it. A CLI install writes none of these settings,
and without them the CAS fails with "tried to get the value from of an
unevaluated casstring", which names none of what is missing:

```bash
cd $MOODLE_DOCKER_WWWROOT
for kv in platform=linux maximacommand=/usr/bin/maxima-sage \
          maximaversion=default castimeout=60 maximalibraries=; do
    docker exec <webserver-container> php admin/cli/cfg.php \
        --component=qtype_stack --name="${kv%%=*}" --set="${kv#*=}"
done
```

Two of those are not guesses:

- `maximacommand`. The `maxima-sage` package installs `/usr/bin/maxima-sage`
  and no `maxima`, but STACK's default command is `maxima`. Set the command
  rather than making a symlink: this setting lives in the database and survives
  recreating the container, a symlink does not.
- `maximaversion=default`. The dropdown offers whole minor versions only, and
  Debian ships 5.45.1, so nothing matches. Picking the nearest looks right and
  is not: STACK compares the string exactly and reports "serious problems"
  while the CAS actually works. Use `default` unless your Maxima matches a
  listed version exactly.

**Your system may differ.** These values were taken from one macOS/ARM install;
check what your own Maxima reports rather than assuming.

Then in Moodle, go to Site administration, Plugins, Question types, STACK, and
run the healthcheck at:

```
http://localhost:8000/question/type/stack/adminui/healthcheck.php
```

Work down the page until everything is green. It tells you exactly what is wrong
when something is. The one you care about most is the Maxima connection test: if
that is green, STACK works.

The page also writes `stackmaximaversion`, which the commands above do not set,
so visit it once even if the CLI settings all applied.

### When goemaxima is not available: SBCL Maxima and an optimised image

goemaxima is published for amd64 only. Where it cannot run natively (a
Raspberry Pi or other ARM Linux machine), or cannot run at all, the next best
thing is Maxima built on SBCL inside the webserver container, with STACK's
`linux-optimised` image. On a Raspberry Pi 5 this took one CAS session from
2.9 seconds with `maxima-sage` to 0.1 seconds. Nothing here is ARM-specific; it
should work on any machine without goemaxima.

**It needs STACK commit
[c58aa98](https://github.com/maths/moodle-qtype_stack/commit/c58aa9860f21fd6f9aeb1338c1f95322a033bc57)**
("Update for Maxima 5.49.0 on SBCL"), which will be in the release after STACK
4.13.1 (2026080600). Without it, saving the image fails with "The function
MAXIMA::RUN is undefined". On 4.13.1 or earlier, apply the commit to your STACK
checkout first; it touches three files, and the two `.mac` files matter as much
as the PHP, because the libraries are baked into the image:

```bash
cd $MOODLE_DOCKER_WWWROOT/question/type/stack
curl -sSL https://github.com/maths/moodle-qtype_stack/commit/c58aa9860f21fd6f9aeb1338c1f95322a033bc57.patch \
    | git apply
```

Build Maxima from the release source inside the container. Debian's packaged
Maxima is GCL, which section 4 rules out. The compile takes a while.

```bash
curl -sSL -o maxima-5.49.0.tar.gz \
    https://sourceforge.net/projects/maxima/files/Maxima-source/5.49.0-source/maxima-5.49.0.tar.gz/download
docker cp maxima-5.49.0.tar.gz <webserver-container>:/usr/local/src/
docker exec <webserver-container> bash -c '
    apt-get install -y sbcl make texinfo &&
    cd /usr/local/src && tar xzf maxima-5.49.0.tar.gz && cd maxima-5.49.0 &&
    ./configure --enable-sbcl --enable-sbcl-exec --prefix=/opt/maxima &&
    make -j4 && make install'
```

Then point STACK at it and build the image. Run the build as `www-data`, so the
image in moodledata belongs to the web server:

```bash
cd $MOODLE_DOCKER_WWWROOT
for kv in platform=linux maximacommand=/opt/maxima/bin/maxima; do
    docker exec <webserver-container> php admin/cli/cfg.php \
        --component=qtype_stack --name="${kv%%=*}" --set="${kv#*=}"
done
docker exec -w /var/www/html <webserver-container> su -s /bin/bash www-data \
    -c "php question/type/stack/cli/build_maxima_optimised.php"
docker exec <webserver-container> php question/type/stack/cli/clearcascache.php
```

On success it prints `DONE.` and the path `/var/www/moodledata/stack/maxima_opt_auto`,
and STACK switches itself to `platform=linux-optimised`.

**Rebuild after every STACK upgrade,** and always set both settings above
first, as shown. A rebuild started from `linux-optimised` blanks
`maximacommand`, so STACK falls back to plain `maxima`, which does not exist
here. The build then fails with "CAS returned some data as expected, but there
were errors", and the log shows CAS return value 127 (command not found).

### If you ever recreate the containers

Restarting containers (`stop` then `start`) is safe and changes nothing.

Recreating them (`down` then `up`, or a rebuild) wipes everything installed by
hand inside them, which means Maxima and the custom theme. You will have to
redo steps 4 and 7. The symptom is that every STACK question suddenly fails
with a CAS error, which looks alarming and is not. With the goemaxima server,
the CAS survives, because it is its own container and the settings are in the
database. Only the theme needs redoing. The SBCL route does not survive either:
`/opt/maxima` is inside the container, and moodle-docker keeps moodledata (and
so the optimised image) in an anonymous volume that `down` discards.

---

## 5. Start version control

If you cloned this kit and intend to work in the clone, then point its
remote at your own repository and commit.

If you are copying it somewhere else, note that `.claude` starts with a dot and
a plain `cp -R dir/*` silently misses it. That directory is where the agents
live, so without it nothing works.

This kit may also contain other subdirectories, such as `project`, which are
under version control of their own.

If you have not created your project folder yet, create it now from the kit's skeleton:

```bash
cp -r templates/project project
git -C project init -q
```

---

## 6. Create the courses and question banks

Two behind-the-scenes courses first: a sandbox where validation imports land
(it fills with duplicates by design, so it must never be anything
student-facing) and a review course where drafts get previewed.

```bash
docker cp tools/setup-workspace.php <webserver-container>:/tmp/setup.php
docker exec <webserver-container> php /tmp/setup.php
```

It prints a block of ids. They go into your project's settings file. Then 
paste the ids into `project/project.conf`.

Then a course to actually publish into:

```bash
docker cp tools/create-live-course.php <webserver-container>:/tmp/clc.php
docker exec <webserver-container> php /tmp/clc.php \
    --key=alg --shortname="Algebra 1" --fullname="Algebra 1 (interactive)"
```

It prints a `COURSES` line. Add that to `project/project.conf` too, and set
`DEFAULT_COURSE` to the key you chose.

Finally fill in the container names and site URL at the top of
`project/project.conf`. That file is the only place ids live: nothing else should
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
tools/api-check.sh reference/showcase.xml
```

The first run pulls two Docker images and takes a few minutes. After that it is
quick.

First tell it your STACK version. Copy `tools/stackapi/.env.example` to
`tools/stackapi/.env` and set `STACK_VERSION` to the `$plugin->version` number
in your Moodle's `question/type/stack/version.php`. This file is machine-local
and not in git, because each machine's Moodle may run a different STACK. Both
Docker images and `tools/stackapi/config.php` take the version from it, so it
is the only place the number is written.

```bash
cp tools/stackapi/.env.example tools/stackapi/.env
grep 'plugin->version' $MOODLE_DOCKER_WWWROOT/question/type/stack/version.php
```

**It must match the STACK version installed in your Moodle.** Random number
sequences and grading behaviour change between STACK versions, so a mismatch
produces confusing false failures. When you upgrade STACK, change
`STACK_VERSION` and restart the API:
`docker compose -f tools/stackapi/docker-compose.yml -p stackapi up -d`. If
every question fails with "The version of the STACK-Maxima libraries being
used ... does not match what is expected", the images and the configuration
disagree: recreate the containers with that command.

By default the images are each version's `-latest` build on Docker Hub, and a
publisher can point `-latest` at a broken build: `stackmaths/stackapi:2026062900-latest`
ships empty PHP files. To pin an exact build, set `GOEMAXIMA_BUILD` and
`STACKAPI_BUILD` in `tools/stackapi/.env` to the part of the tag after the
version (for 2026062900, `1.2.0` and `1`), then recreate the containers with
`--force-recreate`. Like the version, the pin is per machine.

### Build the API image

`mathinstitut/goemaxima` is published for every STACK release, but
`stackmaths/stackapi` is not published for some (check its tags on Docker
Hub). When there is no API image for your version, build it from STACK's
source at your release tag, and set `STACKAPI_IMAGE=stackapi-local` in
`tools/stackapi/.env`:

```bash
RELEASE=v4.13.1     # your STACK release tag
git clone --depth 1 --branch "$RELEASE" \
    https://github.com/maths/moodle-qtype_stack.git /tmp/stack-src
. tools/stackapi/.env
grep 'plugin->version' /tmp/stack-src/version.php   # must equal $STACK_VERSION
cp /tmp/stack-src/api/config_sample.txt /tmp/stack-src/api/config.php
docker build -f /tmp/stack-src/api/docker/Dockerfile --target production \
    -t stackapi-local:"$STACK_VERSION"-latest /tmp/stack-src
docker compose -f tools/stackapi/docker-compose.yml -p stackapi up -d
```

The build needs `api/config.php` in the source and fails without it. It takes
several minutes and needs a network connection. Each machine needs its own
build: the image is local and is not pulled from anywhere.

A mismatch does not always fail loudly. It can pass everything and still be
grading against different random sequences than your Moodle will.
`tools/check-stack-version.sh` compares them for you, and both validators run it
before they start.

Editing `tools/stackapi/.env` does not upgrade containers that are already running.
`api-check.sh` refuses to run until they match, and prints the command that
recreates them:

```bash
docker compose -f tools/stackapi/docker-compose.yml -p stackapi up -d --force-recreate
```

**Never set STACK_VERSION below 4.13.0 (2026062900), and never run Moodle below it.**
The style guide requires question tests to give the full route through each PRT
(`prt1-1-F | prt1-2-T`). This form was added in 4.13.0. Older STACK compares
only a single final note, so every route-style test fails there, even though
the question itself is fine.

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
tools/test-question.sh reference/showcase.xml
tools/push-live.sh reference/showcase.xml <the quiz id it printed>
```

Open the quiz. You should see twelve questions covering key point boxes, algebraic
input, diagnostic feedback, faded examples, fill-in-the-blanks, dropdowns, a
Parsons problem, reading comprehension, randomisation, a JSXGraph figure, matrix
input and a validation layer.

If that works, everything works. Keep the showcase around: it is the reference
the agents compare their conventions against.

---

## 10. Write the project instructions

The kit's `AGENTS.md` is read automatically at the start of every session, and it
reads `project/AGENTS.md`. That second file is where the assistant is told what
your project is, where the questions come from, and how you want to be worked
with.

Do not write it by hand. Ask your assistant to follow `docs/ONBOARDING.md`, or type
`/start`. It will ask what it needs and write the file for you, creating
`project/` first if you have not already done so in section 6.

Then read `docs/WORKFLOW.md`, which covers using the pipeline day to day.

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
error into your assistant, including the command you ran. Do not paraphrase it: the
exact text is the useful part.
