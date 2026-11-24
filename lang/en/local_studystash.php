<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * StudyStash Moodle integration.
 *
 * @package    local_studystash
 * @copyright  2026 StudyStash Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accesscontrols'] = 'Access controls';
$string['accesscontrols_desc'] = 'Disable the full integration or control whether users may connect Moodle for browser access. Linked LTI deployments are managed separately above.';
$string['configurationintro'] = 'Securely connect this Moodle site to StudyStash and manage each access method independently.';
$string['configurationsaved'] = 'StudyStash configuration saved.';
$string['configurationtitle'] = 'StudyStash integration';
$string['connectioncredentials'] = 'Connection credentials';
$string['connectioncredentials_desc'] = 'Use these credentials when pairing this Moodle site in the StudyStash administration dashboard.';
$string['detectedregistrationdetails'] = 'Client {$a->clientid} · Deployment {$a->deploymentid}';
$string['detectedregistrations'] = 'Synced LTI registrations';
$string['enabled'] = 'Enable StudyStash integration';
$string['enabled_desc'] = 'Allow the paired StudyStash installation to connect to this Moodle site.';
$string['eventconfigurationupdated'] = 'StudyStash configuration updated';
$string['eventfileexported'] = 'Course file exported to StudyStash';
$string['eventimportgrantupdated'] = 'StudyStash import grant updated';
$string['eventinstallationsecretrotated'] = 'StudyStash installation secret rotated';
$string['eventlticonnectionupdated'] = 'StudyStash LTI connection updated';
$string['eventoauthauthorizationupdated'] = 'StudyStash OAuth authorization updated';
$string['eventusergrantupdated'] = 'StudyStash user grant updated';
$string['installationid'] = 'Installation ID';
$string['installationid_desc'] = 'Public identifier sent by StudyStash when signing installation requests.';
$string['installationsecret'] = 'Installation secret';
$string['installationsecret_desc'] = 'Encrypted using Moodle\'s server key. The complete value is shown only once and cannot be recovered from this page.';
$string['invalidoauthrequest'] = 'This StudyStash connection request is invalid or has expired.';
$string['ltiaccess'] = 'LTI access';
$string['ltiaccess_desc'] = 'Allow validated LTI launches from registrations explicitly connected by the paired StudyStash organisation.';
$string['noregistrationslinked'] = 'No LTI registration has been connected yet. Pair this site, then connect a Moodle deployment in StudyStash.';
$string['oauthaccess'] = 'OAuth access';
$string['oauthaccess_desc'] = 'Allow Moodle users to connect their own account to StudyStash for browser access.';
$string['oauthapprove'] = 'Connect to StudyStash';
$string['oauthconsentdescription'] = 'StudyStash is requesting access to the Moodle courses and files available to this account.';
$string['oauthconsentheading'] = 'Connect your Moodle account';
$string['oauthconsentscope'] = 'StudyStash will only be able to access content that Moodle already allows this account to view. You can disconnect it from StudyStash at any time.';
$string['oauthconsenttitle'] = 'Connect StudyStash';
$string['oauthdeny'] = 'Cancel';
$string['oauthdisabled'] = 'OAuth access to StudyStash is not enabled for this Moodle site.';
$string['oauthenabled'] = 'Enable OAuth access';
$string['oauthenabled_desc'] = 'Allow signed StudyStash authorization requests and issue user-scoped grants after Moodle consent.';
$string['oauthinvaliduser'] = 'Sign in with an active Moodle account before connecting StudyStash. Guest access cannot be connected.';
$string['pluginname'] = 'StudyStash';
$string['privacy:metadata:local_studystash_imports'] = 'Short-lived grants for files selected for import into StudyStash.';
$string['privacy:metadata:local_studystash_imports:fileids'] = 'The Moodle file identifiers selected for import.';
$string['privacy:metadata:local_studystash_imports:linkid'] = 'The linked Moodle identity that authorized the import.';
$string['privacy:metadata:local_studystash_links'] = 'Links a StudyStash LTI identity to a Moodle user.';
$string['privacy:metadata:local_studystash_links:clientid'] = 'The LTI client identifier.';
$string['privacy:metadata:local_studystash_links:deploymentid'] = 'The LTI deployment identifier.';
$string['privacy:metadata:local_studystash_links:issuer'] = 'The LTI platform issuer.';
$string['privacy:metadata:local_studystash_links:subject'] = 'The opaque LTI subject identifier.';
$string['privacy:metadata:local_studystash_links:userid'] = 'The Moodle user linked through LTI.';
$string['privacy:metadata:local_studystash_oauth'] = 'Temporary OAuth authorization requests used to connect Moodle to StudyStash.';
$string['privacy:metadata:local_studystash_oauth:userid'] = 'The Moodle user who approved the OAuth connection.';
$string['privacy:metadata:local_studystash_tokens'] = 'Hashed access and refresh tokens for the linked user.';
$string['privacy:metadata:studystash'] = 'StudyStash receives Moodle identity and course content selected by the user so it can import learning materials.';
$string['privacy:metadata:studystash:contentmetadata'] = 'Section, activity, folder and file metadata visible to the linked Moodle user.';
$string['privacy:metadata:studystash:coursemetadata'] = 'Course identifiers, names and availability dates visible to the linked Moodle user.';
$string['privacy:metadata:studystash:filecontent'] = 'The contents of files that the linked Moodle user explicitly selects for import.';
$string['privacy:metadata:studystash:grantcredentials'] = 'Opaque user and file-scoped credentials used to authorize StudyStash access.';
$string['privacy:metadata:studystash:ltiidentity'] = 'The LTI issuer, client, deployment and opaque subject used to link the Moodle identity.';
$string['privacy:metadata:studystash:userid'] = 'The Moodle user identifier associated with the StudyStash connection.';
$string['revealsecret'] = 'Reveal setup secret';
$string['rotatesecret'] = 'Rotate secret';
$string['rotatesecretconfirm'] = 'Rotating the installation secret immediately disconnects StudyStash until the new value is saved there. Continue?';
$string['saveconfiguration'] = 'Save configuration';
$string['savesecretnow'] = 'Save this secret now';
$string['secretalreadyrevealed'] = 'The setup secret has already been displayed. Rotate it if you need a new value.';
$string['secretonetimewarning'] = 'This is the only time the complete secret will be displayed. Store it in StudyStash before leaving or refreshing this page.';
$string['statusdisabled'] = 'Disabled';
$string['statusenabled'] = 'Enabled';
$string['taskcleanupcredentials'] = 'Clean up expired StudyStash credentials';
