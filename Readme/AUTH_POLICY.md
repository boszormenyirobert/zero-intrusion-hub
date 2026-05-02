# Authentication and Access Policy

This document describes the current authentication and access-control rules of the repository.

## 1. Policy overview

The repository uses route-type-based protection.

Current route classes:

1. HUB browser routes protected by JWT
2. Machine-facing API routes protected by `X-Client-Auth`
3. Explicit public routes that remain subject to route-specific validation or control logic
4. Initialization-only HUB routes
5. HUB routes available during initialization or with a valid JWT
6. Explicit CSRF-protected mutating routes
7. Credential Hub follow-up routes protected by `X-Extension-Auth`

## 2. Enforcement model

### 2.0 Trust boundary for API-issued QR/HMAC flows

For QR-code-based and machine-facing flows, the HUB must be treated as a controlled proxy/orchestrator, not as the final cryptographic authority for client-originated HMAC values.

Policy:

- QR payloads and client-facing HMAC values may be issued by the upstream API
- when such values return from frontend clients, their final cryptographic validation is performed by the upstream API
- the HUB may enforce route policy, request-shape requirements, header presence, rate limiting, and forwarding rules
- the HUB should not be assumed to have sufficient trust material or state to fully validate every API-issued client HMAC
- absence of full client-HMAC verification in the HUB is therefore not, by itself, a defect when the upstream API is the validation authority

Implication for audits:

- do not classify the HUB as missing client-HMAC validation without first determining whether the value is API-issued and API-validated by design
- evaluate the HUB primarily on forwarding integrity, policy enforcement, access control, and preservation of the authentication chain

### 2.1 JWT-protected browser routes

Protected HUB routes use the `#[JwtRequired]` attribute.

Policy:

- JWT is evaluated before controller business logic
- invalid or missing JWT results in denial
- denial behavior is redirect to the `instance_login` route
- request state may expose `is_jwt_valid` for downstream rendering or context logic

Primary enforcement component:

- `JwtAuthListener`

Operational note:

- HUB/browser JWT admission is no longer driven by Symfony firewall route configuration
- the effective access decision for protected HUB routes is expressed by route attributes and enforced by the repository listeners

### 2.2 Client-auth-protected machine routes

Protected machine/API routes use the `#[ClientAuthRequired]` attribute.

Policy:

- the required incoming header is `X-Client-Auth`
- missing or empty client-auth header results in immediate denial
- denial behavior is HTTP `401` with JSON response
- the validated header may be forwarded downstream as `X-Extension-Auth` where required by the backend flow

Primary enforcement components:

- `ClientAuthListener`
- `ApiClientAuthGuard`

### 2.3 Explicit public routes

Explicit public routes use the `#[PublicRoute]` attribute.

Public route classification does not mean unrestricted execution. Public routes may still require:

- payload validation
- callback validation
- rate limiting
- CSRF protection
- listener-level or controller-level admission checks that are specific to the route flow

Primary expression component:

- `PublicRoute`

Interpretation:

- public means the route is reachable before authentication exists or when the route is intentionally callback/bootstrap oriented
- public does not disable fail-close behavior in secondary protections
- public routes may still deny early because of invalid payload, invalid CSRF token, rate-limit exhaustion, or process-state mismatch

### 2.4 Initialization-only HUB routes

Initialization-only HUB routes use the `#[InitializationOnlyRoute]` attribute.

Policy:

- the route is available only while HUB initialization is active
- once initialization is completed, the route is denied regardless of JWT state
- denial behavior is route-specific and currently redirects away from the management page

Primary expression component:

- `InitializationOnlyRoute`

### 2.5 Initialization-or-JWT HUB routes

Mixed initialization/JWT routes use the `#[InitializationOrJwtRoute]` attribute.

Policy:

- the route remains available during initialization without requiring JWT
- after initialization is completed, the route requires a valid JWT-backed user context
- denial behavior is route-specific and currently redirects to the login route when access is not allowed

Primary expression component:

- `InitializationOrJwtRoute`

### 2.6 Explicit CSRF-protected mutating routes

Mutating browser routes that require CSRF validation use the `#[CsrfProtectedRoute]` attribute.

Policy:

- the route performs a state-changing action
- the route must validate a route-specific CSRF token before applying the mutation
- CSRF validation is additional to, not a replacement for, the main access-control policy
- intended scope is browser-origin HUB routes, especially Twig-backed form actions and other cookie/session-adjacent mutations

Primary expression component:

- `CsrfProtectedRoute`

Primary enforcement component:

- `CsrfRouteListener`

### 2.7 Extension-auth-protected Credential Hub routes

Credential Hub follow-up routes use the `#[ExtensionAuthRequired]` attribute.

Policy:

- the required incoming header is `X-Extension-Auth`
- the bootstrap `/qr-identity` step is intentionally exempt because it creates the initial process/session
- every follow-up step in the Credential Hub flow must include the header established by that bootstrap step
- missing or empty extension-auth header results in immediate denial
- denial behavior is HTTP `401` with JSON response

Primary expression component:

- `ExtensionAuthRequired`

Primary enforcement components:

- `ExtensionAuthListener`
- `BackendForwarder`

## 3. Current route policy matrix

### 3.1 JWT-protected HUB routes

The following routes currently require `#[JwtRequired]`:

- `/account`
- `/business`
- `/instance-registration-external`
- `/instance-registration-follow-up`

Policy for this group:

- intended for authenticated HUB/browser usage
- authentication source is JWT
- fail-close behavior is redirect to login

### 3.2 Client-auth-protected machine/API routes

The following routes currently require `#[ClientAuthRequired]`:

- `/api/user-registration`
- `/api/nfc/users`
- `/api/nfc/decrypt`

Policy for this group:

- intended for machine, desktop, mobile, extension, or system-to-system use
- authentication source is `X-Client-Auth`
- fail-close behavior is HTTP `401`
- validated client authentication may be forwarded downstream as `X-Extension-Auth`

### 3.3 Explicit public HUB/browser routes

The following HUB/browser routes are intentionally public and currently use `#[PublicRoute]`:

- `/login`
- `/login/check`
- `/replace-device`
- `/replace-device/{replaceHash}`
- `/user-registration`
- `/user-logout`

Interpretation:

- `/login` is public because it bootstraps the login flow
- `/login/check` is public because it supports the browser polling flow and relies on route-specific state checks
- `/replace-device` is public because device-recovery initiation must remain available before authenticated session recovery is possible
- `/replace-device/{replaceHash}` is public because device-recovery completion relies on the route-specific recovery hash and PIN flow rather than prior JWT authentication
- `/user-registration` is public because it bootstraps the browser-side user registration flow
- `/user-logout` is public at the route layer, but remains meaningful only when the request contains a valid logout CSRF token and an existing JWT cookie

### 3.4 Explicit public Credential Hub bootstrap routes

The following Credential Hub routes are intentionally public and currently use `#[PublicRoute]`:

- `/api/credential-hub/one-touch/qr-identity`
- `/api/credential-hub/shared/registration/qr-identity`
- `/api/credential-hub/domain/read/qr-identity`
- `/api/credential-hub/domain/delete/qr-identity`
- `/api/credential-hub/vault/read/qr-identity`
- `/api/credential-hub/vault/edit/qr-identity`
- `/api/credential-hub/vault/delete/qr-identity`

Interpretation:

- these routes are public because they bootstrap a new Credential Hub process and create the initial QR/session context
- they do not yet have an `X-Extension-Auth` header because that context is created by the bootstrap response itself
- public classification does not weaken the flow because subsequent steps are explicitly bound to the generated process and must carry `X-Extension-Auth`

### 3.5 Initialization-only HUB routes

The following HUB routes are currently marked with `#[InitializationOnlyRoute]`:

- `/instance-registration`
- `/settings`

### 3.6 Initialization-or-JWT HUB routes

The following HUB routes are currently marked with `#[InitializationOrJwtRoute]`:

- `/access`
- `/access/{id}/status`
- `/access/{id}/delete`

### 3.7 Explicit CSRF-protected routes

The following routes are currently marked with `#[CsrfProtectedRoute]`:

- `/user-logout`
- `/access/{id}/status`
- `/access/{id}/delete`
- `/api/user-login/new-qr`
- `/api/user-login/check`

Policy for this group:

- intended for browser-origin routes that mutate state or complete browser-driven flows
- CSRF validation is enforced before controller business logic
- some routes deny with HTTP `403`, while others redirect to a safe browser route after CSRF failure, depending on the declared route policy

### 3.8 Extension-auth-protected Credential Hub follow-up routes

The following Credential Hub routes currently require `#[ExtensionAuthRequired]`:

- `/api/credential-hub/one-touch/identifier`
- `/api/credential-hub/one-touch/state`
- `/api/credential-hub/shared/registration/new/to-encrypt`
- `/api/credential-hub/shared/registration/new`
- `/api/credential-hub/shared/registration/state`
- `/api/credential-hub/domain/read/credential/decrypted`
- `/api/credential-hub/domain/read/credential`
- `/api/credential-hub/domain/read/state`
- `/api/credential-hub/domain/delete/credential`
- `/api/credential-hub/domain/delete/state`
- `/api/credential-hub/vault/read/credential/decrypted`
- `/api/credential-hub/vault/read/credential`
- `/api/credential-hub/vault/read/state`
- `/api/credential-hub/vault/edit/credential`
- `/api/credential-hub/vault/edit/state`
- `/api/credential-hub/vault/delete/credential`
- `/api/credential-hub/vault/delete/state`

Policy for this group:

- intended for Credential Hub follow-up steps after the initial QR bootstrap
- authentication chain is the `X-Extension-Auth` header established by the bootstrap response and preserved across subsequent steps
- fail-close behavior is HTTP `401`
- enforcement is centralized in `ExtensionAuthListener`, while `BackendForwarder` retains defensive validation and controller attributes keep the route intent explicit and auditable

## 4. Additional public API routes

The following API routes are intentionally public and currently use `#[PublicRoute]`:

- `/api/secret/new`
- `/api/secret/recovery-settings`

Interpretation:

- this route bootstraps the device registration flow and must be reachable before any per-device secret exists
- the HUB forwards the bootstrap request without requiring or forwarding `X-Client-Auth`
- the recovery-settings step remains public in this flow and the HUB forwards the decoded payload without requiring or forwarding `X-Client-Auth`

## 5. Login flow policy

The login-related API flow is intentionally public at the route layer and now uses `#[PublicRoute]` to make that policy explicit.

Current controls in the login flow include:

- request payload validation in `InputUserValidationListener`
- route-level CSRF validation in `CsrfRouteListener` for browser-initiated QR refresh and login status polling endpoints
- controller-level rate limiting via `ApiRateLimitService`
- callback-specific request handling and process logic in controller/service code

Routes in this area include:

- `/api/user-login`
- `/api/user-login/callback`
- `/api/user-login/new-qr`
- `/api/user-login/check`

Interpretation:

- these routes are public at the route-policy layer because they bootstrap or complete the browser/device login flow
- request admission for this flow is intentionally layered across validation listener, CSRF listener, and controller-level rate limiter
- public classification does not weaken their controls; they still rely on route-specific validation, callback handling, rate limiting, and CSRF where applicable

## 6. Registration callback policy

The registration callback route is public at the route-policy layer and now uses `#[PublicRoute]` to make that intent explicit.

Current policy:

- it is not JWT-protected
- it is not `X-Client-Auth`-protected by attribute
- it is expected to rely on callback-specific request handling and validation logic

Route:

- `/api/registration/callback`

Interpretation:

- the route is intentionally public because it receives upstream registration completion callbacks
- public classification does not weaken the route because callback payload validation, request-shape checks, and rate limiting still apply

## 7. Fail-close rules

The repository currently applies the following fail-close rules:

- JWT-protected browser routes deny access when the JWT is invalid or missing
- client-auth-protected machine routes deny access when `X-Client-Auth` is missing or empty
- extension-auth-protected Credential Hub routes deny access when `X-Extension-Auth` is missing or empty
- protected routes do not intentionally downgrade to anonymous execution

## 8. Service-layer responsibility

Services may validate domain state, user context, or process context.

Primary access-control decisions are expected to happen before business logic execution through:

- route attributes
- listeners
- guards
- route-specific validation logic

Services are not the primary source of route protection policy.

## 9. Logging policy for authentication-related decisions

Authentication-related logging may record:

- route name or route context
- authentication validity or denial result
- hashed or fingerprinted client identifiers when needed for diagnostics

Raw client identifiers should not be treated as the default logging format.

## 10. Source of truth

The current policy is defined across the following layers:

- controller attributes expressing route intent
- event listeners enforcing JWT and client-auth rules
- route-specific validation listeners
- controller-level rate limiting for selected public and callback flows
- route-specific browser protection logic for selected flows

`security.yaml` is no longer used as the primary source of route access policy for these flows.

The remaining Security Bundle configuration is infrastructure support only, not the route-policy decision point.

For audits and future changes, prefer reading policy in this order:

1. route attributes
2. event listeners bound to those attributes or paths
3. flow-specific controller rate limiting
4. service/process rules

## 11. Operating rules

- Use `#[JwtRequired]` for authenticated HUB/browser routes
- Use `#[ClientAuthRequired]` for machine-facing routes that require `X-Client-Auth`
- Use `#[ExtensionAuthRequired]` for Credential Hub follow-up routes that require `X-Extension-Auth`
- Require route-specific validation for public callbacks
- Document layered request-admission responsibilities for public browser/API flows
- Preserve fail-close behavior on protected routes
- Keep primary access-control policy outside business services
