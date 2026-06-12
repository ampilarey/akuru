# ADR-002: Analytics in Settings domain

## Context

Phase 0 move map item 13 places `AnalyticsService` and analytics controllers in **Settings** or split later.

## Decision

Keep analytics (`AnalyticsService`, `AnalyticsController`, and related metrics models) in the **Settings** domain for Phase 0. Portal dashboards may read analytics via Settings services or shared read models later; no split until multi-tenant reporting needs a dedicated domain.

## Consequences

- Settings owns operational metrics (`DashboardAnalytics`, `SystemMetric`, `UserActivity`, `Report`).
- Cross-domain reads should go through Settings contracts if extracted in a future phase.
