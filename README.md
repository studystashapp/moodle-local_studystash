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

## Install

1. Download the plugin ZIP and keep it zipped.
2. In Moodle, open **Site administration → Plugins → Install plugins**.
3. Upload the ZIP under **Install plugin from ZIP file**, then select **Install plugin from the ZIP file**. If prompted for the plugin type, choose **Local plugin (local)**.
4. Review the validation and server checks. Select **Upgrade Moodle database now** when Moodle prompts you, then continue after installation succeeds.

If your hosting provider manages plugins, ask them to install the package. For manual installation, extract the `studystash` directory into Moodle's `local/` directory, then complete Moodle's standard upgrade process. The installed path must be `local/studystash`, regardless of this repository's name.

## Connect to StudyStash

1. In Moodle, open **Site administration → Plugins → Local plugins → StudyStash**.
2. Under **Access controls**, select **Enable StudyStash integration**, then select **Save configuration**.
3. Under **Connection credentials**, copy the **Installation ID**. Select **Reveal setup secret** to copy the one-time **Installation secret**. Keep this page open while you enter the credentials in StudyStash.
4. In the **StudyStash Admin Dashboard**, select the relevant campus and open **Integrations → your Moodle connection → Content ingestion**.
5. Select **Enable**. In **Connect the Moodle plugin**, enter the Moodle **Installation ID** as **Client ID** and the **Installation secret** as **Secret**, then select **Connect and enable**.
6. Enable the access methods your users need, as described below.

The Client ID in the plugin connection dialog is the plugin's Installation ID. Your Moodle LTI tool has a separate Client ID.

The complete installation secret is shown only once. Keep it private. If you lose it, select **Rotate secret** in Moodle and update the plugin connection in StudyStash with the new secret. Rotation disconnects the existing pairing until the new secret is saved.

### LTI access

Use LTI access when users open StudyStash through a Moodle course activity.

1. Configure your StudyStash LTI tool and deployment using the [Moodle LTI setup guide](https://help.studystash.com/integrations/moodle).
2. Keep **Enable StudyStash integration** selected in the Moodle plugin.
3. In StudyStash's **Content ingestion** tab, turn on **LTI access** for the Moodle deployment and check that its status reads **Connected**.
4. Launch the **StudyStash** activity from a Moodle course using an account that can view the course files.

LTI access is connected to the exact client and deployment registered for your Moodle tool. Disconnecting that deployment blocks its existing LTI grants and future LTI content access.

### OAuth access

Use OAuth access when users open StudyStash directly and connect their Moodle account. **All three settings below must remain enabled:**

| Location | Required setting |
| --- | --- |
| Moodle plugin, **Access controls** | **Enable StudyStash integration** |
| Moodle plugin, **Access controls** | **Enable OAuth access** |
| StudyStash, **Content ingestion** | **OAuth access** |

Select both Moodle checkboxes, then select **Save configuration**. Turn on the StudyStash **OAuth access** switch for the same Moodle connection.

Users can then open StudyStash directly, select **Import from Moodle**, connect their Moodle account and approve the connection in Moodle. OAuth access can be enabled independently of LTI access. Enable both access methods if your users need both entry points.

## Import course files

In StudyStash's **My Stash**, open **Create Notes** or **Create Flashcards**, then select **Import from Moodle**. Connect your Moodle account if prompted, choose a course and select files or pages before generating your resources.

The plugin supports files in Moodle **File** (`resource`) and **Folder** (`folder`) activities. Course enrolment, activity visibility and file access are checked using the connected Moodle user's permissions. Installing or pairing the plugin does not grant users access to every course on the site.

## Updating

Back up your Moodle site and database using your normal maintenance process. Upload the newer release ZIP through **Install plugins**, or replace `local/studystash` with the new plugin directory, then complete Moodle's upgrade process. Do not uninstall the plugin as part of a routine update.

## Troubleshooting

- **Installation fails:** check Moodle's validation message, confirm Moodle 4.4 or later and verify that the installed directory is `local/studystash`.
- **Pairing fails:** check that the integration is enabled in Moodle, that StudyStash points to the correct Moodle site and that the Installation ID and secret match.
- **LTI access is unavailable:** check the tool's Client ID and Deployment ID, the deployment connection in StudyStash and the Moodle plugin's integration switch.
- **OAuth connection fails:** verify all three settings in the OAuth table above, then try connecting the user's Moodle account again.
- **Courses or files are missing:** confirm that the same Moodle user can view them in Moodle. Check that the file is in a supported File or Folder activity.
- **Requests are blocked:** check whether your firewall or access gateway is intercepting the plugin API or OAuth authorisation page.

For setup assistance, [contact StudyStash support](https://help.studystash.com/support). Report reproducible plugin bugs in [GitHub Issues](https://github.com/studystashapp/moodle-local_studystash/issues). Include the plugin version, Moodle version and error message. Do not include installation secrets, access tokens or personal course content.

## Data and access

The plugin stores linked Moodle identities, hashed token records, temporary OAuth authorisation requests and file-scoped import grants. It uses Moodle's Privacy API for user-data export and deletion, and scheduled cleanup for expired credentials.

When a user imports course files, the integration shares the required Moodle identity, course and file metadata, and selected file content with StudyStash. Installation credentials authenticate the paired service; course and file access also require a user-scoped grant and Moodle permission checks.

The plugin records Moodle audit events for configuration changes, secret rotation, deployment connections, OAuth approvals and denials, grant changes and file exports.

## Development

See [the developer notes](docs/development.md) for the API and test commands. GitHub Actions runs PHP lint, Moodle CodeSniffer, PHPDoc, plugin validation, upgrade-savepoint checks and PHPUnit against Moodle 4.4 and 5.0.

## Licence

Copyright © 2026 StudyStash Limited. Licensed under the **GNU General Public License v3.0 or later**. See [LICENSE](LICENSE).
