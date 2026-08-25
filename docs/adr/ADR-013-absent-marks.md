# ADR-013: Absent and exempt exam marks

## Context

S3.3 stores per-student `exam_marks`. Absent and exempt are mutually
exclusive with a numeric mark. Averages (S3.4 term grades) need a
school-wide rule for absences.

## Decision

1. **Exempt** is always excluded from averages and rank.
2. **Absent** counts as **0** unless setting `exams_exclude_absent` is
   `1` / `true`. Then absent is excluded like exempt.
3. Setting lives in `settings` (`group=exams`). Default is `0` (count
   as zero). Recorded here so S3.4 does not invent a second rule.
4. Marks must be `<= exam.max_marks`. Absent/exempt force `marks=null`.

## Consequences

`ComputeTermGradesAction` (S3.4) reads `ResolveExamSettingsAction`.
Changing the setting does not rewrite stored marks; it only changes
how published marks are averaged on the next recompute.
