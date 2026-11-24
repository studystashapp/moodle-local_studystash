# StudyStash for Moodle

Connect Moodle to [StudyStash](https://studystash.com) so students and educators can import files from courses they can access into StudyStash notes and flashcards.

This is a Moodle **local plugin**, with the component name `local_studystash`. It provides the Moodle side of StudyStash content ingestion. It works alongside your StudyStash LTI connection; installing this plugin does not add a StudyStash activity to your courses.

## Requirements

- Moodle 4.4 or later. The automated test matrix covers Moodle 4.4 and 5.0.
- Moodle site administrator access to install and configure the plugin.
- A StudyStash account with access to the relevant campus's Admin Dashboard and Moodle connection. The plugin requires the StudyStash service; it does not run as a standalone study tool.
- An HTTPS Moodle site that the StudyStash service can reach. If your site uses a firewall or access gateway, allow the integration's authenticated API requests to reach `/local/studystash/`.

No additional Moodle plugins, Moodle Web Services tokens or Composer installation are required.

## Download

Download the installable package from [the latest release](https://github.com/studystashapp/moodle-local_studystash/releases/latest), or download [v1.0.0 directly](https://github.com/studystashapp/moodle-local_studystash/releases/download/v1.0.0/local_studystash-v1.0.0.zip).

Choose the **`local_studystash-v1.0.0.zip`** release asset for installation. GitHub's automatically generated source archives are intended for source-code downloads.

## Installation and setup

Follow the [**Moodle content ingestion setup guide**](https://help.studystash.com/integrations/moodle-plugin-setup) in the StudyStash Help Centre for installation, connecting the plugin, enabling LTI or OAuth access, testing an import and troubleshooting.

## Supported content

The plugin supports files in Moodle **File** (`resource`) and **Folder** (`folder`) activities. Course enrolment, activity visibility and file access are checked using the connected Moodle user's permissions. Installing or pairing the plugin does not grant users access to every course on the site.

## Support

For setup assistance, [contact StudyStash support](https://help.studystash.com/support). Report reproducible plugin bugs in [GitHub Issues](https://github.com/studystashapp/moodle-local_studystash/issues). Include the plugin version, Moodle version and error message. Do not include installation secrets, access tokens or personal course content.

## Data and access

The plugin stores linked Moodle identities, hashed token records, temporary OAuth authorisation requests and file-scoped import grants. It uses Moodle's Privacy API for user-data export and deletion, and scheduled cleanup for expired credentials.

When a user imports course files, the integration shares the required Moodle identity, course and file metadata, and selected file content with StudyStash. Installation credentials authenticate the paired service; course and file access also require a user-scoped grant and Moodle permission checks.

The plugin records Moodle audit events for configuration changes, secret rotation, deployment connections, OAuth approvals and denials, grant changes and file exports.

## Development

See [the developer notes](docs/development.md) for the API and test commands. GitHub Actions runs PHP lint, Moodle CodeSniffer, PHPDoc, plugin validation, upgrade-savepoint checks and PHPUnit against Moodle 4.4 and 5.0.

## Licence

Copyright © 2026 StudyStash Limited. Licensed under the **GNU General Public License v3.0 or later**. See [LICENSE](LICENSE).
