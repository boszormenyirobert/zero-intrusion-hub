# EasyLogin Caller-Side Documentation (`/` route)

## Overview

This controller handles the registration and authentication of corporate users on the caller side of the EasyLogin system. It serves two forms:
1. Identity Request Form – starts the registration process by requesting data from the EasyLogin service.
2. Follow-up Form – sends the signed contract hash and revoke URL back to EasyLogin.

---

## Route

- **Path**: `/`
- **Name**: `contract_request`

---

## Workflow Summary

### 1. Identity Request Phase

- **Triggered by**: Submitting the IdentityRequester form
- **Action**: Calls `getSubscriptionData()` from `SubscriptionService`
- **Session**: Stores `serviceAuthData` temporarily for use in the next page load

### 2. Follow-up Submission Phase

- **Triggered by**: Submitting the `CorporateType` form
- **Action**: Sends contract hash and revoke URL to EasyLogin via `serviceRegistrationClient()`
- **Note**: Service logic not explicitly triggered in controller – may be intended for future extension

---

## Key Functions

### getSubscriptionData()

- **Endpoint Called**: `ZERO_INTRUSION_DOMAIN/SERVICE_REGISTRATION_PARTNER/IDENTITY`
- **Headers Used**: `Authorization` (HMAC of encrypted data and IV)
- **Returns**: Decrypted identity data (contract hash, revoke URL, etc.)

### serviceRegistrationClient()

- **Data Sent**:
  - `revokeUrl`
  - `contractHash`
- **Endpoint Called**: `ZERO_INTRUSION_DOMAIN/SERVICE_REGISTRATION_PARTNER/REGISTRATION_NEW`
- **Headers**: HMAC Authorization, IV in JSON payload

---

## Security Highlights

- **Encryption**: AES-256-CBC (via `CrypterHelper`)
- **Auth**: HMAC SHA-256 (via `AuthorizationHelper`)
- **Secrets Used**:
  - `SERVICE_API_KEY`
  - `SERVICE_API_SECRET`
  - `DATA_HASH_SECRET`

---

## Session Handling

- Stores and removes `serviceAuthData` between form submissions.
- Allows reading of data via `GET` param `?data=...`

---

## Templates

- Rendered view: `corporate.html.twig`
- Passed data:
  - `form_identity_requester`
  - `form_identity_followup`
  - `serviceAuthData`

---

## Notes

- There is some redundancy with session and query parameter handling of `serviceAuthData`.
- Actual sending of follow-up data is expected in future integration.
"""

# Save to file
output_path = Path("/mnt/data/easylogin_caller_side_doc.md")
output_path.write_text(markdown_content.strip())

output_path.name
