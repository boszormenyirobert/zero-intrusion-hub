# Developer Design Rules

This document defines the expected software design rules for contributors working in this repository.

## 1. Core design principle

Every route must have an explicit protection model.

Do not leave route security implicit in controller body logic.

## 2. Route categories

Use exactly one primary route-policy marker for every endpoint:

- `#[PublicRoute]`
  - Public bootstrap or callback route.
  - Public does not mean unprotected.
  - Still use payload validation, rate limiting, CSRF, callback checks, or process validation where needed.

- `#[JwtRequired]`
  - For authenticated HUB/browser routes.
  - Enforced before controller business logic.

- `#[ClientAuthRequired]`
  - For machine/API routes that require `X-Client-Auth`.
  - Missing or empty header must fail closed.

- `#[ExtensionAuthRequired]`
  - For Credential Hub follow-up routes that require `X-Extension-Auth`.
  - Bootstrap routes may be public, but follow-up steps must carry the extension-auth context.

- `#[InitializationOnlyRoute]`
  - Route is allowed only while HUB initialization is active.

- `#[InitializationOrJwtRoute]`
  - Route is allowed during initialization.
  - After initialization, a valid JWT is required.

Additional marker when needed:

- `#[CsrfProtectedRoute]`
  - For state-changing browser routes.
  - Primarily for Twig/browser-origin flows.
  - CSRF is additional protection, not the primary access-control model.

## 3. Route design rules

1. Every controller method that is a route must declare its security intent explicitly.
2. Do not rely on comments alone to describe protection.
3. Do not hide access-control decisions in controller body logic when they can be expressed centrally.
4. Bootstrap and follow-up endpoints must be separated clearly.
5. If a route is public, document what secondary protections still apply.

## 4. Enforcement rules

Primary enforcement should happen outside business logic whenever possible.

Use the existing central mechanisms:

- `JwtAuthListener` for `#[JwtRequired]`
- `ClientAuthListener` for `#[ClientAuthRequired]`
- `ExtensionAuthListener` for `#[ExtensionAuthRequired]`
- `CsrfRouteListener` for `#[CsrfProtectedRoute]` on browser-origin routes
- `InstanceRouteAccessListener` for initialization-based HUB routes
- `BackendForwarder` as the defensive forwarding layer for Credential Hub payload and header handling

Controllers should express intent.
Listeners, guards, and forwarders should enforce it.

Request admission may be layered across multiple non-business components when a route needs more than one protection type.

Typical order:

1. request-shape and payload validation listeners
2. route-policy listeners for JWT, client-auth, extension-auth, CSRF, or initialization state
3. controller-level rate limiting where the limiter decision depends on route flow semantics
4. controller delegation into service and domain logic

This layering is acceptable as long as:

- each layer has a clear responsibility
- access-control policy is still declared explicitly on the route
- controller bodies do not reimplement deny logic already enforced centrally
- the documentation states which layer owns which admission decision

## 5. Controller rules

Controllers should stay thin.

A controller should mainly do the following:

- declare route intent
- accept the request
- delegate to a service, mapper, or handler
- return the response

Avoid putting these directly into controller bodies unless there is a strong reason:

- duplicated authentication checks
- hidden access-control logic
- repeated header validation already enforced elsewhere
- mixed business and authorization logic

## 6. Service rules

Services may:

- validate domain state
- validate process state
- prepare DTOs or view models
- coordinate backend calls

Services should not be the primary source of route protection policy.

## 7. Public route rules

A public route is allowed only when one of these is true:

- it bootstraps a process
- it receives a callback from an upstream system
- it must be reachable before authentication exists
- it is protected by a different route-specific mechanism

Public routes should usually also have at least one of:

- payload validation
- rate limiting
- callback validation
- CSRF
- process or state validation

For public API flows, it is acceptable that these protections are split across different layers.

Example pattern:

- request listener validates payload shape
- CSRF route listener validates browser-origin token requirements
- controller applies rate limiting tied to flow-specific abuse thresholds

This is still considered policy-complete when the responsibilities are explicit and documented.

## 8. Credential Hub rules

Credential Hub flows follow a strict two-phase model:

1. Bootstrap step
   - usually `/qr-identity`
   - may be `#[PublicRoute]`
   - creates the initial process or session context

2. Follow-up steps
   - all later steps must usually require `#[ExtensionAuthRequired]`
   - must carry `X-Extension-Auth`
   - must fail closed if the header is missing

If a Credential Hub route is not a bootstrap route, assume it should require `X-Extension-Auth` unless there is a documented exception.

## 9. Trust-boundary rules

The HUB is not always the final cryptographic authority.

When QR/HMAC or client-auth material is issued by the upstream API:

- the HUB may enforce route policy, header presence, request shape, and forwarding integrity
- final cryptographic validation may belong exclusively to the upstream API

Do not implement redundant or misleading pseudo-validation in the HUB just to duplicate what only the upstream API can validate correctly.

## 10. Fail-close rules

Protected routes must fail closed.

That means:

- missing required auth header => deny
- empty required auth header => deny
- invalid JWT => deny
- unavailable required route state => deny
- do not silently downgrade to anonymous execution

## 11. Testing rules

Every new or modified route should be covered test-first.

Minimum expected coverage:

1. Reflection test for the route-policy attribute.
2. Behavior test for the allowed path.
3. Behavior test for the denied path when relevant.
4. Forwarding/header behavior test when the route proxies backend calls.

When refactoring policy:

- update tests first
- observe failure
- implement the policy change
- rerun focused tests
- rerun full suite

## 12. Documentation rules

When a controller becomes policy-complete:

- update `AUTH_POLICY.md`
- update `README_WAMP.md` ready list
- keep route intent and enforcement notes consistent
- document layered request-admission responsibilities when more than one protection layer applies to the same route

## 13. Practical checklist for new routes

Before merging a new route, verify:

- What category is this route in?
- Which attribute expresses that category?
- Where is the enforcement performed?
- Is the route bootstrap or follow-up?
- Does it need CSRF?
- Does it need rate limiting?
- Does it need callback validation?
- Are tests added for both intent and behavior?
- Is the documentation updated?

## 14. Short software design summary

This repository uses a layered, policy-first design:

1. Controllers declare route intent.
2. Listeners, guards, and forwarders enforce access rules.
3. Services implement domain and process behavior.
4. DTOs, forms, and mappers structure data flow.
5. Backend/API integrations are centralized and fail closed.

The preferred design style is:

- explicit
- centralized
- layered when necessary, but with clear ownership boundaries
- test-first
- fail-close
- documented
