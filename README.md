# Stoneholl Connector

Optional account connection for Stoneholl Magento modules. Shows locally installed modules, cached entitlements and
compatible releases. API outages, expired support or revoked credentials never disable installed modules.

## Install

Requires [Stoneholl Core](https://github.com/Stoneholl/magento2-core) 1.x, Magento cron and outbound HTTPS.
Composer installs Core automatically.

```sh
composer require stoneholl/magento2-connector:^1.0
bin/magento module:enable Stoneholl_Core Stoneholl_Connector
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

Run your normal production deployment steps, including static content deployment when assets change.
Back up the database and preserve Magento's encryption key. During upgrades, pause Connector cron/Admin actions,
finish pending operations, replace files and refresh PHP workers before resuming.

Tested on Magento Open Source **2.3.7-p4 / PHP 7.4.33** and **2.4.9 / PHP 8.5.9**.
Other combinations allowed by Composer and Adobe Commerce have not been runtime validated.

## Connect

Open **Stoneholl > Connector**, review the displayed technical metadata and enter a pairing code from your Stoneholl
Account. Grant staff only the required Connector permissions. Credentials are encrypted with Magento's encryption key
and never displayed. Composer credentials are managed separately.

Automatic checks default to every six hours; configure them under **Stores > Configuration > Stoneholl > Connector**.
A failed or incomplete refresh preserves the last complete snapshot. Connector does not install updates automatically.
No requests run from storefront or checkout paths.

Account links inherit **Marketplace URL** under **Stoneholl > Core**, followed by `/account`. Clear Connector's optional
**Account URL Override** to follow marketplace domain changes. API and Composer endpoints are independent deployment
configuration; changing a website link never redirects credentials. API endpoints require HTTPS on port 443, the
`/api/v1` base path and an exact host in `stoneholl_connector/endpoints/allowed_api_hosts`; redirects are rejected.
The API URL and repository URL use `endpoints/api_url` and `endpoints/repository_url` under the same config section.

The backend must support API v1/schema 2 and the capability required by each operation. Rotation additionally requires
`idempotent_credential_rotation`. Pending operations remain resumable: retry after fixing connectivity; do not delete
local state. Rejected credentials need an account-issued recovery code for the same Installation ID. **Reset after
unlink** is available only after server verification. Development fixture mode is not included in public packages.

## Privacy and removal

Pairing and synchronization send the selected environment, installation domain, Magento edition/version, PHP version
and product codes needed for compatible-release lookup. No customer, order, catalog, payment, session or Composer
credential data is transmitted. There is no hidden telemetry.

Unlink the installation before removal, from Magento or Stoneholl Account. Removing the package does not revoke remote
credentials. Disable `Stoneholl_Connector`, remove it through Composer and run your normal Magento deployment steps.
Keep Core while other modules require it. Encrypted configuration and the `stoneholl_connector_state` snapshot may
remain; there is no automatic data purge. Back up and complete server unlink/verified reset before intentional cleanup.

[MIT License](LICENSE).

## Composer package naming

Connector normalizes the explicitly migrated legacy Stoneholl package names in entitlement and release responses.
Installed-version lookup checks the canonical name first, then its corresponding legacy name. Unknown packages and
upstream Magento identities are preserved. This keeps new Connector installations compatible with a platform that
still returns legacy package names; it does not change pairing, credentials or installed module availability.

The canonical Composer name is `stoneholl/magento2-connector`. The former `stoneholl/module-connector` name is a legacy identity. The renamed package replaces that identity at `self.version`, so Composer installs one copy of the Magento module. Existing immutable releases remain unchanged.

When migrating an existing root requirement, back up the project and remove the old requirement without updating, then require the new package with its dependencies:

```bash
composer remove stoneholl/module-connector --no-update
composer require stoneholl/magento2-connector --with-all-dependencies
```

Run the Magento upgrade, compilation and cache commands documented above according to your deployment mode. Keep native `Stoneholl_*` module names and configuration paths unchanged.
