# Design Decisions --- Discount Coupons Management App

> Working design log for the project. This file records the stable
> project constraints, the main design decisions, and the problems that
> materially changed the implementation. Low-level implementation
> details belong in `technical_spec.md`; repository presentation belongs
> in the future `README.md`.

## 1. Project description and goal

Internal CRUD web application for creating and managing clients, discount 
rules and client-specific discount coupons.

The original idea came from a much simpler spreadsheet-based workflow provided 
by a client. The project goal became turning that concept into a small but 
realistic relational web application, with persistent data, server-side 
validation, authentication and a clearer operational workflow.

The project is intentionally limited in scope, both in terms of functionality
and target market. It is designed around the needs of small to medium-sized 
medical centres, reflecting the type of business of the original client who 
provided the spreadsheet workflow. It is not intended to be a billing, booking 
or coupon-redemption platform, nor to integrate with external management systems. 
Its purpose is to model and manage the data required to create and administer 
discount coupons reliably within this deliberately focused business context.


## 2. Stable project constraints

-   Keep the stack deliberately simple: PHP, MySQL, vanilla JavaScript
    and Tailwind CSS.
-   Keep the application server-rendered rather than turning it into a
    SPA.
-   Use JavaScript mainly for asynchronous API calls, dynamic rendering
    and UI interactions.
-   Use MySQL as the persistent source of truth; no CSV/Excel fallback
    is part of the application workflow.
-   Keep client data, discount rules and generated coupons as separate
    relational entities.
-   Keep database IDs as internal identifiers rather than user-facing
    labels.
-   Perform authoritative validation in the backend even when the
    frontend also constrains or assists input.
-   Generate coupon codes in the backend; the frontend may show a
    preview but must not decide the persisted code.
-   Preserve useful historical records through active/inactive status
    where appropriate, while allowing explicit hard deletion only where
    the application requires it.
-   Prevent logical duplicates with database constraints rather than
    relying exclusively on frontend or PHP checks.
-   Keep API responses JSON-based and predictable for the JavaScript
    frontend.
-   Authentication is session-based. Application users have `admin` or
    `user` roles; user creation is an admin-only operation.
-   No external booking/billing API integration is part of the project
    because no real target integration was available to design against.

## 3. Final domain model

The application settled on the following main entities:

-   **Users** --- staff accounts used to access the application.
-   **Clients** --- either a private person (`PR`) or a company (`CO`).
-   **Client types** --- lookup data for the two client categories.
-   **Service categories** --- Physiotherapy, Medical and All services.
-   **Visit types** --- First visit only and All visits.
-   **Discount rules** --- reusable combinations of service category,
    visit type and discount percentage.
-   **Coupons** --- client-specific instances created from one client
    and one discount rule.

The central relationship is therefore:

`Client + Discount Rule -> Coupon`

A discount rule describes *what discount is available*. A coupon
describes *that rule assigned to a specific client*.

## 4. Key design decisions and reasoning

### 4.1 Replace the spreadsheet-style flat model with relational entities

**Decision:** separate clients, discount rules and coupons instead of
storing all information in one flat record.

**Why:** client information and discount conditions have different
lifecycles. A reusable discount rule should not be duplicated every time
a coupon is issued, and changing client data should not require
rebuilding rule definitions.

**Rejected:** reproducing the original spreadsheet structure directly in
MySQL.

### 4.2 Make the client the starting point of coupon creation

**Decision:** coupons are created from the client detail page, where the
user then selects a discount rule.

**Why:** the operational question is "which coupon do I want to give
this client?". Keeping the client context fixed reduces ambiguity and
avoids a separate form where both client and rule have to be selected
independently.

**Rejected:** rule-first flow or a global coupon form with simultaneous
client/rule selection.

### 4.3 Keep database IDs internal

**Decision:** IDs are used for relations and API requests but are not
the primary human-readable identifiers in the interface.

**Why:** numeric IDs are useful to the database, not to the staff member
using the application. The UI instead shows names, client codes and
descriptive discount-rule labels.

**Rejected:** exposing IDs as the only way to identify or select
records.

### 4.4 Introduce a permanent client code

**Decision:** every client receives a unique, manually chosen
`client_code`, normalized to uppercase and kept immutable after
creation.

**Why:** names are not unique or stable enough to become part of a
coupon identifier. A short descriptive client code gives staff a
recognizable reference and provides a stable component for coupon
generation.

The code is deliberately not regenerated when other client information
changes.

**Rejected:** using client name, city/province or database ID as the
visible client component of the coupon code.

### 4.5 Redesign the coupon-code format

The early design used a database ID as the final uniqueness component.
That approach was discarded.

**Final format:**

`[SERVICE_CATEGORY]-[VISIT_TYPE]-[DISCOUNT_PERCENTAGE]-[CLIENT_CODE]`

Examples:

-   `MED-ALL-15-NINAFLUFFY`
-   `PHYS-FIRST-20-PHARMASMITH`

Current backend mappings:

-   Physiotherapy -\> `PHYS`
-   Medical -\> `MED`
-   All services -\> `ALL`
-   First visit only -\> `FIRST`
-   All visits -\> `ALL`

**Why:** the final code remains readable without using a database ID as
visible data. Logical uniqueness is guaranteed separately by the
database relationship between client and discount rule.

**Rejected:** timestamp/random suffixes, IDs in the visible code, or
sending the final code from JavaScript.

### 4.6 Enforce one coupon per client/rule pair

**Decision:** a client cannot have two coupons based on the same
discount rule.

**Implementation rule:** `UNIQUE (client_id, discount_rule_id)`.

**Why:** allowing duplicates would create two records representing the
same logical entitlement and make status/expiry management ambiguous.

**Rejected:** application-only duplicate checks or multiple historical
coupons for the same client/rule pair.

### 4.7 Use database constraints as the final integrity layer

**Decision:** important relationships and uniqueness rules are enforced
in MySQL as well as validated in PHP where useful for better feedback.

Examples include:

-   unique client code;
-   unique user email;
-   unique discount-rule combination;
-   unique client/rule coupon pair;
-   foreign keys connecting clients, rules and coupons to their
    lookup/parent records.

**Why:** frontend controls can be bypassed and application checks alone
do not protect against every invalid or concurrent write.

### 4.8 Keep discount rules reusable and effectively immutable

**Decision:** users can create and list discount rules, but the project
does not provide rule editing/deletion workflows.

**Why:** a rule can already be referenced by coupons. Creating a new
rule for a new set of conditions is simpler and avoids silently changing
the meaning of existing coupon records.

**Rejected:** full CRUD editing for discount rules and a separate
rule-detail page.

### 4.9 Make `usage_cap` conditional

**Decision:** `usage_cap` applies only to rules whose visit type is
**First visit only**. For **All visits**, it is stored as `NULL`.

For first-visit rules, the minimum depends on client type:

-   `PR` -\> minimum `1`
-   `CO` -\> minimum `5`

**Why:** the field has no useful meaning for an unrestricted all-visits
rule. The backend retrieves the actual rule and client type from the
database rather than trusting frontend state.

### 4.10 Keep expiration explicit

**Decision:** every coupon requires a future expiration date. The
backend stores the selected day as a `DATETIME` ending at `23:59:59`.

**Why:** expiry is part of the coupon itself rather than of the reusable
discount rule, because different client assignments may have different
validity periods.

### 4.11 Use status for operational deactivation

**Decision:** clients and coupons have active/inactive status controls.
Coupon hard deletion remains available as a separate explicit action.

**Why:** normal operational deactivation should not automatically
destroy the record. Status and deletion represent different intentions
and therefore remain separate actions.

### 4.12 Separate frontend pages from JSON endpoints

**Decision:** PHP pages under the application frontend render the
interface; dedicated files under `/api` perform data operations and
return JSON to JavaScript `fetch()` calls.

**Why:** this kept the project within a familiar PHP stack while still
creating a clear frontend/backend responsibility boundary.

**Rejected:** putting every form action and database operation directly
inside its page, or rebuilding the project as a SPA.

### 4.13 Standardize dynamic lookup data

**Decision:** database-backed `<select>` options for service categories,
visit types and discount rules are loaded through one dedicated dropdown
endpoint.

**Why:** options stay aligned with database records and the frontend
does not need separate hardcoded copies of lookup data. The endpoint
uses a whitelist of allowed query types rather than accepting arbitrary
table names.

Static boolean/status options and the fixed `admin`/`user` role selector
remain exceptions.

### 4.14 Add real staff accounts instead of a shared login

**Decision:** the initial shared-login idea was replaced by individual
users identified by company email, password hash, role and active
status.

**Why:** once the project included user creation and role-dependent
actions, a shared credential no longer represented the intended
application model.

Passwords are hashed; login uses PHP sessions; inactive accounts cannot
authenticate. Only admins can create additional application users.

### 4.15 Keep error handling useful but simple

**Decision:** APIs return structured JSON with HTTP status codes,
field-specific validation errors where the UI needs them, and global
error messages for request/database failures.

**Why:** this is sufficient for a small CRUD application and lets
JavaScript distinguish form errors from general failures without
introducing a custom error taxonomy.

**Rejected:** raw strings, frontend-only validation, custom error enums,
retry systems and other unnecessary error infrastructure.

## 5. UX and implementation problems resolved during development

-   **Ambiguous client identification:** solved with a unique client
    code plus descriptive client information instead of exposing DB IDs.
-   **Mixing client and discount data:** solved by separating reusable
    rules from client-specific coupons.
-   **Coupon-code collision concerns:** moved away from trying to encode
    uniqueness in the visible string; integrity is enforced by
    relational constraints.
-   **Stale hardcoded dropdowns:** moved service/visit/rule options to
    database-backed generation.
-   **Conditional coupon fields:** the frontend adapts the form, but the
    backend re-queries the rule before deciding whether `usage_cap` is
    valid.
-   **Editing clients without corrupting coupon history:** `client_code`
    is immutable; editable profile data can change without rewriting
    existing coupon records.
-   **Large result sets:** client, coupon and discount-rule lists use
    server-side pagination rather than rendering an unlimited table.
-   **Search:** client search is performed through the backend/API and
    covers identifying client information rather than filtering only the
    already-rendered DOM.
-   **Role-dependent user management:** the navigation and user-creation
    workflow distinguish admins from normal users, with authorization
    enforced on the protected operation itself.

## 6. Ideas deliberately rejected

The following options were considered during development but are not
part of the final design:

-   reproducing the original Excel structure as a flat database model;
-   using names, city or province to guarantee identity/uniqueness;
-   exposing database IDs as user-facing identifiers;
-   using the coupon string itself as the database identifier;
-   putting client and rule selection in one global coupon form;
-   allowing multiple coupons for the same client/rule pair;
-   generating or trusting the persisted coupon code in JavaScript;
-   keeping critical validation only in the browser;
-   relying only on PHP duplicate checks without DB constraints;
-   editing existing discount rules and retroactively changing coupon
    meaning;
-   introducing a dedicated discount-rule detail page with no additional
    useful actions;
-   using a modal for client search;
-   combining client and discount-rule search into one dashboard search;
-   adding external service integrations without an actual API contract
    to implement against;
-   introducing a modern frontend/backend framework only for
    architectural appearance.

## 7. Current project state

The core application model is now defined and implemented around
authenticated staff users, clients, reusable discount rules and
client-specific coupons. The original design assumptions that depended
on a shared login, ID-based coupon generation, flat data or
frontend-owned business logic are obsolete and should not be used as
project references.

`technical_spec.md` is the reference for the current implementation
structure, data model, API responsibilities and business rules.
