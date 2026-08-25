# ADR-019: Halaqa offering mapping without Hifz cutover

## Context

Qur’an Module A needs to map live Hifz programs/sessions onto Courses
offerings without changing Hifz dashboards or starting dual-write.
Rule 5 forbids other domains from importing `App\Domains\Hifz\*`.

## Decision

Store mapping rows in Offerings (`offering_halaqa_links`,
`offering_halaqa_session_links`) with integer Hifz ids and no foreign
keys. Resolve program/session labels through
`HalaqaReferenceReader`. Do not write Hifz tables. Dual-write / switch
stays a later 3-deploy slice.

## Consequences

Mapping can be agreed and reviewed before cutover. Dropping Hifz
structural tables later does not require an Offerings schema FK drop
in the same deploy.
