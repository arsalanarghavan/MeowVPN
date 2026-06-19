# Laravel Events — architectural note (v24)

Spec §4 lists `app/Events/` alongside Jobs. Runtime v23+ uses:

- **Jobs** in `app/Modules/*/Jobs/` and scheduled via `routes/console.php`
- **Direct service calls** in mutation pipeline and bot handlers

## Decision

No Laravel Event/Listener layer — avoids duplicate indirection for 141 mutate ops and 14 cron jobs already covered by tests.

## Future

If cross-module hooks are needed (e.g. audit fan-out), prefer single `SvpDomainEvent` bus over 50+ Event classes.

Operator / date: 2026-06-13
