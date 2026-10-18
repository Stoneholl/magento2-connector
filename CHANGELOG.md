# Changelog

## 1.0.0
- Inherit account navigation from Core Marketplace URL when the optional account override is empty.
- Preserve existing explicit account destinations and keep API/repository configuration independent.
- Serialize connection mutations across pairing, refresh, rotation and unlink/reset to prevent state races.
- Report interrupted rotation without incorrectly claiming the server credential was never revoked.
- Complete installation, upgrade, permissions, removal and exact-pair compatibility documentation.
- Document method contracts and remove the global missing-method-docblock suppression.
- Make development fixture recovery agree with its advertised capability and reject mismatched installation IDs.
- Hide the configuration link from roles that only have permission to view Connector status.
- Isolate the configured lock backend instance from Magento cron to support legacy single-lock managers.
- Persist rotation attempt IDs before issuance and use capability-gated replay to recover lost replacement responses.
- Serialize credential rotation requests with a Magento lock to prevent concurrent duplicate issuance.
- Inject the local-module template escaper explicitly for Magento 2.3 compatibility.
- Restrict direct local-forget requests to unfinished pairing without a credential or installation identity.
- Allow a new recovery code after a definitive invalid-code response while preserving installation credentials.
- Display locally registered Stoneholl modules independently of remote entitlements and API availability.
- Correct PHPUnit test doubles without suppressing deprecation or mock-expectation diagnostics.
- Fetch and validate all entitlement pages while preserving legacy API responses and cached state on failures.

- Align the responsive Admin connection page with Magento styling and native confirmation dialogs.
- Add validated Default-scope Stoneholl Account navigation configuration.
- Improve the pairing form spacing and Pairing Code field width.
- Add API v1 schema-version-2 capability negotiation.
- Add recoverable pairing with encrypted attempt state and confirmation retry.
- Add two-phase installation credential rotation.
- Add server-first unlink and limit local-only cleanup to unfinished pairing recovery.
- Add account-issued recovery-code handling for rejected installation credentials.
- Add server-verified local reset for credentials rejected after an Account-side unlink.
- Send the documented technical metadata during manual and cron refresh.
- Add privacy-disclosed installation pairing with encrypted credential storage.
- Add cached entitlement and latest-compatible release checks.
- Add manual and optional cron refresh with offline and stale states.
- Keep synthetic fixture transport and test data in the private development workflow.
