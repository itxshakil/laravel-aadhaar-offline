# Changelog

All notable changes to `laravel-aadhaar-offline` are documented in this file.
The [GitHub releases](https://github.com/itxshakil/laravel-aadhaar-offline/releases) list every change.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-10-10

No code changes; the installed package is the same as 0.2.0.

### Changed

- Releases and `CHANGELOG.md` are maintained by hand; the automatic changelog workflow was removed.
- `CHANGELOG.md` now matches the published releases.

## [0.2.0] - 2026-10-10

Parsing and verification moved to the new framework-agnostic package [itxshakil/aadhaar-offline](https://github.com/itxshakil/aadhaar-offline), which this package now requires.
Class names and namespaces are unchanged, so apps only need `composer update`.

### Added

- `php artisan about` shows signature-check status, certificate counts, the next certificate expiry and the age limits.
- Laravel Boost guidelines in `resources/boost/guidelines/core.blade.php`, so AI coding agents verify before trusting data and keep personal data out of logs.
- `testbench.yaml`, so the Artisan commands can be run from the package repository.
- Composer `funding` and `homepage` metadata.

### Changed

- `AadhaarOffline` now extends the core `OfflineKycReader` and adds `storePhoto()`.
- Data objects, parsers, signature checks, Secure QR support, the Aadhaar number helpers and `Testing\OfflineKycFactory` now come from `itxshakil/aadhaar-offline`.
- `Shakil\AadhaarOffline\Testing\Fixtures` ships with the core package, for building signed synthetic ZIPs, XML and Secure QR codes in tests.
- Static analysis runs at PHPStan max level, with the strict and deprecation rules actually loaded.

### Fixed

- Certificate details no longer show `Array` as the subject for a certificate with several common names.
- Non-string entries in the `certificates` and `algorithms` config are ignored instead of being cast to strings.
- `composer lint` checks style without rewriting files, as described.

### Removed

- The no-op `provides()` method on the service provider, which is not deferred.
- `.env.example` and `art/` from the distributed package.

## [0.1.0] - 2026-10-10

First release.
The Offline e-KYC ZIP path was checked against a real 2026 UIDAI download and UIDAI's published `uidai_offline_publickey_2026.cer`.

### Added

- **Readers**: `AadhaarOffline::fromZip()`, `fromXml()`, `fromXmlFile()` and `fromSecureQr()`, with the `verifyZip()`, `verifyXml()` and `verifySecureQr()` shortcuts.
- **Share-code ZIP reader**: AES-256 and ZipCrypto support, exactly-one-XML-entry rule, size and zip-bomb limits.
- **Hardened XML parser**: DOCTYPE/ENTITY declarations refused, network access disabled.
- **Local signature verification** with `robrichards/xmlseclibs` 4.x: single enveloped signature under the root, single whole-document reference, algorithm allowlist, and verification only against pinned certificates (PEM or DER, several allowed for rotation).
- **Secure QR codes** from Aadhaar letters, PVC cards and e-Aadhaar PDFs, in the legacy and V2/V3/V5 layouts, verified with SHA256withRSA against the same certificates.
- **Freshness checks**: `max_age_days` for Offline e-KYC XML (default 3) and `secure_qr.max_age_days` for Secure QR codes (off by default).
- **`OfflineKyc` value object** with name, date of birth (full or year-only), `Gender` enum, `Address`, `Photo`, reference ID, masked Aadhaar and generation time.
  Privacy-safe `toArray()`, JSON and `__debugInfo()`.
- **Mobile/email matching** using UIDAI's iterated SHA-256 scheme.
- **Validation rules**: `ValidAadhaarNumber` (Verhoeff) and `ValidOfflineKycZip`, which exposes the payload it verified through `kyc()`.
- **`MaskedAadhaar` Eloquent cast**.
- **Events**: `OfflineKycVerified` and `OfflineKycRejected`, with no personal data.
- **`storePhoto()`** to a private disk with random file names.
- **Artisan commands**: `aadhaar-offline:check` (stage-by-stage check of a real file without printing personal data), `aadhaar-offline:add-cert` (fingerprint-checked certificate install) and `aadhaar-offline:certs` (expiry warnings).
- **Upload form**: the `<x-aadhaar-offline::upload-form>` Blade component.
- **Testing helpers**: `AadhaarOffline::fake()` with assertions, and `OfflineKycFactory`.

[Unreleased]: https://github.com/itxshakil/laravel-aadhaar-offline/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/itxshakil/laravel-aadhaar-offline/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/itxshakil/laravel-aadhaar-offline/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/itxshakil/laravel-aadhaar-offline/releases/tag/v0.1.0
