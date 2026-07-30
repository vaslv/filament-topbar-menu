# TODOS

## Visibility

### Generalized domain visibility (`visibility.domains`)

**What:** A `domains` key in the `visibility` JSON column holding
`hide_on: [...]` / `only_on: [...]` host lists with a `current` sentinel, so an
item can be shown or hidden on any named service of the fleet. UI: a mode
Select plus a TagsInput of hosts.

**Why:** The next step of the shared-menu-database scenario — different menus
on different services without duplicating tables. Today's
`hide_on_current_domain` flag was deliberately designed as future sugar over
`domains.hide_on: ['current']`, so adopting this needs no data migration.

**Context:** Considered and deliberately deferred as YAGNI while designing
`hide_on_current_domain` (2026-07-29): one generic mechanism would cover every
future domain-visibility request and stays inside the same JSON column (no
migration on a fleet-shared table), but it costs a noticeable UI, host-list
validation as a new import trust boundary (`MenuTransfer`), and documentation —
for a need no real case has confirmed yet.

Entry point is the same layer the current flag uses:
`TopbarMenuItem::isVisibleTo()` and `normalizeHost()`. Host-matching semantics
are already settled and would carry over unchanged: subdomains are significant,
`www.` and a trailing dot are stripped, the scheme is not compared, ports 80/443
equal an absent port while a non-standard port is part of the identity, punycode
and unicode spellings are aligned (non-transitional UTS #46), and IPv6 literals
are canonicalized. The rule applies to URL items only; route items are exempt.

**Effort:** M
**Priority:** P3
**Depends on:** the `hide_on_current_domain` release; only worth starting on a
real request (an own fleet that needs it, or an issue from a package user).
