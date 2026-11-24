# Developer notes

`local_studystash` is the Moodle-side integration for StudyStash. It exposes a
small server-to-server API without enabling Moodle Web Services.

JSON operations use Moodle External API parameter and return descriptions under
`classes/external`. The scripts under `api/v1` remain the transport layer: they
authenticate the StudyStash installation or user token, invoke the operation,
validate its result against the declared return structure, and send the HTTP
response. These operations are not registered as Moodle Web Service functions,
so they are not available through `webservice/rest/server.php` or a `wstoken`.

The first implementation supports silent user provisioning from a validated
LTI 1.3 launch:

1. Moodle supplies the launch subject and signed LTI identity claims.
2. StudyStash validates the LTI launch.
3. StudyStash sends either the original `id_token` or, when LTIAAS terminates
   the launch, an installation-signed envelope of the verified claims.
4. The plugin validates the Moodle token directly or validates the trusted
   issuer, client, deployment, subject, and native user claims in that envelope.
5. The plugin issues opaque user-scoped access and refresh tokens.
6. Course and file endpoints execute as that Moodle user and re-check current
   enrolment and visibility on every request.

The installation credential authenticates StudyStash, but does not itself
authorize course access.

## Initial API

All endpoints live under `/local/studystash/api/v1`.

- `POST /status.php` validates the installation pairing.
- `POST /lti/registrations.php` reports whether exact StudyStash client and deployment pairs are available and connected.
- `POST /lti/connection.php` connects or disconnects one exact StudyStash client and deployment pair.
- `POST /grants/lti.php` exchanges a verified LTI identity for user tokens.
- `POST /grants/refresh.php` rotates a refresh token.
- `POST /grants/revoke.php` revokes a linked user's tokens.
- `GET /courses.php` lists every currently accessible course.
- `GET /course-content.php?courseid=123` lists visible sections and supported files.
- `GET /file.php?fileid=123` streams an authorized Moodle file.

`resource` and `folder` modules are supported in the first content slice.

User endpoints accept a standard `Authorization: Bearer <token>` header. They
also accept `X-StudyStash-Access-Token` for Moodle installations whose web
server does not forward the Authorization header to PHP.

## Signed installation requests

Status, grant exchange, refresh, and revoke requests use these headers:

- `X-StudyStash-Client`
- `X-StudyStash-Timestamp`
- `X-StudyStash-Nonce`
- `X-StudyStash-Signature`

The signature is lowercase hex HMAC-SHA256 over:

```text
METHOD\n
PATH\n
TIMESTAMP\n
NONCE\n
SHA256_HEX(BODY)
```

Requests are accepted for five minutes and nonces can only be used once.

## Abuse controls and audit events

Authenticated API operations use database-backed, one-minute request windows.
Limits are applied after installation or user credentials have been validated,
so unauthenticated callers cannot consume another institution's allowance. The
counter key is HMAC-derived from the authenticated subject and operation; raw
tokens, installation IDs, and client IP addresses are never stored in the rate
limit table. Exceeded limits return HTTP 429 with a `Retry-After` header.
Course-content search has a separate lower request limit and bounded query and
pagination inputs because each search must re-check current Moodle visibility.

Refresh tokens are rotated under a linked-identity Moodle lock and database
transaction. Rotation revokes the active token family before issuing one
replacement pair, including when duplicate requests arrive concurrently.

The plugin emits Moodle Events API records for:

- integration and OAuth access-control changes;
- installation-secret rotation;
- LTI deployment connection and disconnection;
- Moodle OAuth approval and denial;
- user grant issuance and revocation; and
- file-scoped import grant creation and revocation; and
- successful course file exports to StudyStash.

Routine course-content listing is excluded from the Moodle event log to avoid
recording high-volume refresh traffic or allowing an external caller to amplify
log storage. File exports remain subject to request limits and are recorded once
authorization succeeds and the response starts.

## Development installation

Copy or mount this repository as `local/studystash` inside Moodle, then run the
Moodle upgrade process:

```bash
php admin/cli/upgrade.php --non-interactive
```

Configure the integration under:

`Site administration -> Plugins -> Local plugins -> StudyStash`

The installation ID is public and remains stable. The shared installation
secret is encrypted with Moodle's server encryption key and displayed only
once for a new installation or immediately after rotation. Existing secrets
cannot be revealed from the configuration page; only their final four
characters are shown.

StudyStash resolves the native Moodle user ID from the validated launch and includes
it in the authenticated grant request. The exact claim resolution depends on the
StudyStash application version. After pairing, StudyStash connects the exact
client and deployment pairs already registered for the organisation. The plugin
accepts a launch only when that pair still belongs to an active Moodle LTI 1.3
tool. It does not identify tools by name or URL. StudyStash can exchange the
original Moodle `id_token`, or a signed `verified_claims` envelope after LTIAAS
has validated the launch.

LTI access is derived from the exact client and deployment pairs linked during
deployment connection. Disconnecting a deployment blocks its existing tokens and future
provisioning. OAuth access is controlled separately from the installation
pairing and is provisioned only after the signed authorization request, Moodle
login and consent, and PKCE code exchange have completed.

## Quality checks

GitHub Actions runs the Moodle HQ `moodle-plugin-ci` tool against the oldest
supported Moodle release and Moodle 5.0. The required checks are PHP lint,
Moodle CodeSniffer with zero warnings, Moodle PHPDoc, plugin validation,
upgrade savepoints, and PHPUnit.

The same checks can be run in a local `moodle-plugin-ci` environment:

```bash
moodle-plugin-ci phplint
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpdoc --max-warnings 0
moodle-plugin-ci validate
moodle-plugin-ci savepoints
moodle-plugin-ci phpunit --fail-on-warning
```

## Release packaging

Set the human-readable release, increasing Moodle version number and maturity in
`version.php`, update `CHANGELOG.md`, and commit the release. Run:

```bash
python3 scripts/package-release.py HEAD
```

This builds an installable ZIP containing one top-level `studystash/` directory
and a SHA-256 checksum in `dist/`. The archive is built from committed files;
local configuration, GitHub workflows and development tests are excluded.
Run the CI checks before tagging and publishing the release assets.
