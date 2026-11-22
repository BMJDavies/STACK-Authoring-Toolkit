# Course: <course name, or the source's title if there is no course>

<!-- Template from the kit (templates/project/course.md). Onboarding copies it
to project/course.md and replaces every <...>. It holds facts about the
students and the material that every tool in the kit uses: the STACK roles and
any extension. Facts about how this project is run belong in project/AGENTS.md.
An extension may add sections of its own at the end; its template says what
they are. -->

**Audience: the assistant.** Facts about the students and the material, read by
every workflow in this project. Where this file and a workflow's own
instructions disagree, this file wins; a job file for one task wins over both.

## Students

<Who the questions are for: their level, the course they are taking, and what
it follows on from.> Locale: as in `project/AGENTS.md`.

## Scope

Every task has one of two scopes. Its workflow, or its job file, says which.

- **book**: everything in the source, whether or not the course teaches it.
  The course (next sections) is background only: never use it to leave
  anything out, and never mention how the course is taught or assessed in
  student text.
- **course**: only what the course teaches: the syllabus mapping, less the
  excluded sections.

STACK questions use scope <book | course>.

## Standard background

What a student may be assumed to know without its being stated: <e.g. "material
from earlier courses (a first course in linear algebra: vector space, subspace,
basis, dimension, linear map, injective, surjective, matrices, determinants of
small matrices), and from chapters of the source before the one the material is
in">. Under scope `course`, excluded sections are not background. Anything else
a question, proof or solution relies on is given to the student.

## The course

<If there is no course, write "None: scope is always book." and delete the
subsections.>

### Outline

<What the course covers and the skills it develops. The course description from
the handbook is a good start.>

### Learning objectives

On completion of this course, the student will be able to:

1. <objective>

### Syllabus mapping

<Each teaching unit (week, lecture) and the source sections and pages it
covers.>

### Excluded sections

<Sections of the source the course does not teach or does not assess, and
which. "None" if there are none.>
