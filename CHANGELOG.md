# Changelog

All notable changes to `laravel-aadhaar-offline` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - Unreleased

First release. The format handling follows UIDAI's public Offline e-KYC specification and is tested against synthetic, self-signed fixtures. 1.0.0 will follow once it has been checked against real UIDAI downloads.

### Added

- **Readers**: `AadhaarOffline::fromZip()`, `fromXml()`, `fromXmlFile()` and the `verifyZip()` / `verifyXml()` shortcuts.
- **Share-code ZIP reader**: AES-256 and ZipCrypto support, exactly-one-XML-entry rule, size and zip-bomb limits.
- **Hardened XML parser**: DOCTYPE/ENTITY declarations refused, network access disabled.
- **Local signature verification** with `robrichards/xmlseclibs` 4.x: single enveloped signature under the root, single whole-document reference, algorithm allowlist, and verification only against pinned certificates (PEM or DER, several allowed for rotation).
- **Freshness check** via `max_age_days` (default 3).
- **`OfflineKyc` value object** with name, DOB (full or year-only), `Gender` enum, `Address`, `Photo`, reference ID, masked Aadhaar and generation time. Privacy-safe `toArray()`, JSON and `__debugInfo()`.
- **Mobile/email matching** using UIDAI's iterated SHA-256 scheme.
- **Validation rules**: `ValidAadhaarNumber` (Verhoeff) and `ValidOfflineKycZip`.
- **`MaskedAadhaar` Eloquent cast**.
- **Events**: `OfflineKycVerified` and `OfflineKycRejected`, with no personal data.
- **`storePhoto()`** to a private disk with random file names.
- **`aadhaar-offline:certs`** Artisan command.
- **Testing helpers**: `AadhaarOffline::fake()` with assertions, and `OfflineKycFactory`.
- Laravel-free core (`Data`, `Parsers`, `Security`, `Support`) usable outside Laravel.
