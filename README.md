<p align="center">
  <img src="https://raw.githubusercontent.com/itxshakil/laravel-aadhaar-offline/main/art/logo.svg" width="400" alt="Laravel Aadhaar Offline">
</p>

<p align="center">
  <a href="https://packagist.org/packages/itxshakil/laravel-aadhaar-offline">
    <img src="https://img.shields.io/packagist/v/itxshakil/laravel-aadhaar-offline" alt="Latest Version">
  </a>
  <a href="https://packagist.org/packages/itxshakil/laravel-aadhaar-offline">
    <img src="https://img.shields.io/packagist/dt/itxshakil/laravel-aadhaar-offline" alt="Total Downloads">
  </a>
  <a href="https://github.com/itxshakil/laravel-aadhaar-offline/actions">
    <img src="https://github.com/itxshakil/laravel-aadhaar-offline/actions/workflows/ci.yml/badge.svg?branch=main" alt="Tests">
  </a>
  <a href="https://opensource.org/licenses/MIT">
    <img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License">
  </a>
</p>

---

**Laravel Aadhaar Offline** reads and verifies Aadhaar Offline e-KYC files and Aadhaar Secure QR codes in your own Laravel app. It opens the share-code-protected ZIP, **checks UIDAI's digital signature locally**, and gives you a typed object with the resident's name, date of birth, gender, address and photo. It can also confirm a mobile number or email against the hashes in the file, and it never needs the full Aadhaar number.

**Don't trust the XML, verify it.** Many integrations accept whatever XML a KYC vendor returns, or whatever ZIP a user uploads, without checking the signature. Anyone can edit an XML file. This package checks the signature against UIDAI's public certificate before you rely on any of the data.

> **Status: 0.x.** The test suite uses synthetic, self-signed fixtures.
> The Offline e-KYC ZIP path has also been checked against a real 2026 UIDAI download and UIDAI's published `uidai_offline_publickey_2026.cer`.
> Secure QR follows UIDAI's published layout and well-maintained open-source decoders, but has not yet been checked against a real card.
> Before relying on it in production, run `php artisan aadhaar-offline:check` on a real file with the current UIDAI certificate.
> Please [open an issue](https://github.com/itxshakil/laravel-aadhaar-offline/issues) if anything differs.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Reading Payloads](#reading-payloads)
- [Secure QR Codes](#secure-qr-codes)
- [Verification](#verification)
- [Matching Mobile and Email](#matching-mobile-and-email)
- [Privacy and Storage](#privacy-and-storage)
- [Validation Rules](#validation-rules)
- [Upload Form](#upload-form)
- [Events](#events)
- [Testing](#testing)
- [Artisan Commands](#artisan-commands)
- [Error Handling](#error-handling)
- [AI Assistants](#ai-assistants)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- 🔐 **Local signature verification**: checks the UIDAI XML signature with a certificate you pin. The key is never read from the document itself, so a forged file can't bring its own key.
- 📷 **Secure QR codes**: reads and verifies the signed QR printed on Aadhaar letters, PVC cards and e-Aadhaar PDFs, including the photo.
- 🗜️ **Share-code ZIP support**: opens AES-256 and ZipCrypto archives, with zip-bomb, size and entry-count guards.
- 🧾 **Typed result**: `OfflineKyc` with name, DOB (full or year-only), `Gender` enum, `Address`, `Photo` and generation time.
- 📱 **Mobile/email matching**: implements UIDAI's iterated SHA-256 scheme, accepting formats like `+91 98765 43210`.
- ⏱️ **Freshness rule**: rejects files older than a configurable number of days (default 3).
- 🙈 **Privacy-safe by default**: only the last four digits are exposed, `toArray()` and `dd()` hide hashes and the share code, and photos stay in memory unless you store them on a private disk.
- ✅ **Validation rules**: `ValidAadhaarNumber` (Verhoeff checksum) and `ValidOfflineKycZip` (opens and verifies the upload).
- 🗄️ **`MaskedAadhaar` cast**: assigning a full number to a model stores only `XXXX XXXX 1234`.
- 🔔 **Events**: `OfflineKycVerified` / `OfflineKycRejected`, carrying no personal data.
- 🧪 **Fake & assert**: `AadhaarOffline::fake()` plus a factory for synthetic payloads.
- 🛡️ **XXE-hardened**: DOCTYPE/ENTITY declarations are refused and network access is disabled.
- 🔍 **PHPStan level 8**: fully typed, no baseline.

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.3 with `ext-dom`, `ext-openssl`, `ext-zip`, `ext-zlib` |
| Laravel | ^11.0 \| ^12.0 \| ^13.0 |
| UIDAI public certificate | Download from the UIDAI website ([see below](#configuration)) |

---

## Installation

```bash
composer require itxshakil/laravel-aadhaar-offline
```

Publish the config file:

```bash
php artisan vendor:publish --tag=aadhaar-offline-config
```

Add the UIDAI public certificate that signs Offline e-KYC XML and Secure QR codes.
UIDAI lists it on its [Data and Downloads page](https://uidai.gov.in/en/data-and-download) as `uidai_offline_publickey_2026.cer`:

```bash
php artisan aadhaar-offline:add-cert \
    https://backend.uidai.gov.in/get/files/media/document/2026-07/uidai_offline_publickey_2026.cer \
    --fingerprint="E0:30:4B:9E:61:EE:36:40:EC:DD:AE:2D:B4:B6:17:F2:E2:67:8F:57:DB:C2:82:6C:2F:86:AC:5C:04:F2:77:DF"
```

This stores it in `storage/app/aadhaar-offline/certs` and adds it to `AADHAAR_OFFLINE_CERTIFICATES` in `.env`.

Check it loaded:

```bash
php artisan aadhaar-offline:certs
```

> **Full installation guide:** [docs/installation.md](docs/installation.md)

---

## Quick Start

```php
use Shakil\AadhaarOffline\Rules\ValidOfflineKycZip;

public function store(Request $request)
{
    $request->validate([
        'aadhaar_zip' => ['bail', 'required', 'file', 'max:2048', $rule = new ValidOfflineKycZip('share_code')],
        'share_code'  => ['required', 'digits:4'],
    ]);

    $kyc = $rule->kyc(); // already read and verified by the rule

    $kyc->name();                         // "Asha Verma"
    $kyc->dob()?->format('Y-m-d');        // "1990-08-15"
    $kyc->gender()?->label();             // "Female"
    $kyc->address()->pincode;             // "110024"
    $kyc->maskedAadhaar();                // "XXXX XXXX 5678"
    $kyc->matchesMobile($request->phone); // true / false
}
```

---

## Configuration

| Key | Env Variable | Default | Description |
|-----|-------------|---------|-------------|
| `verify_signature` | `AADHAAR_OFFLINE_VERIFY_SIGNATURE` | `true` | Check the UIDAI signature in `verify()`. Only disable locally. |
| `certificates` | `AADHAAR_OFFLINE_CERTIFICATES` | - | Comma-separated paths to UIDAI certificate(s), PEM or DER. List two during a rotation. |
| `algorithms` | - | rsa-sha256, rsa-sha1 | Accepted `SignatureMethod` algorithms. |
| `max_age_days` | `AADHAAR_OFFLINE_MAX_AGE_DAYS` | `3` | Reject older files. `null` disables. Confirm your regulator's limit. |
| `secure_qr.max_age_days` | `AADHAAR_OFFLINE_QR_MAX_AGE_DAYS` | `null` | Age limit for Secure QR codes. Off by default because printed cards are old. |
| `limits.max_zip_bytes` | - | 2 MB | Largest ZIP accepted. |
| `limits.max_xml_bytes` | - | 2 MB | Largest uncompressed XML accepted (zip-bomb guard). |
| `photo.disk` | `AADHAAR_OFFLINE_PHOTO_DISK` | `local` | Disk used by `storePhoto()`. Keep it private. |
| `photo.directory` | - | `aadhaar-offline/photos` | Directory used by `storePhoto()`. |
| `events.enabled` | `AADHAAR_OFFLINE_EVENTS` | `true` | Dispatch verification events. |

---

## Reading Payloads

Every reader returns an **unverified** `OfflineKyc`. Call `verify()`, or use the `verify*` shortcuts.

```php
// 1. The ZIP the resident downloaded from UIDAI, plus their 4-digit share code
$kyc = AadhaarOffline::fromZip($request->file('aadhaar_zip'), '1234')->verify();

// 2. Signed XML returned by a KYC vendor's OTP flow. Still verified locally.
$kyc = AadhaarOffline::fromXml($vendorResponse['raw_xml'], shareCode: '1234')->verify();

// 3. An XML file on disk
$kyc = AadhaarOffline::fromXmlFile(storage_path('app/kyc/offline.xml'), '1234')->verify();

// One-step shortcuts
$kyc = AadhaarOffline::verifyZip($file, '1234');
$kyc = AadhaarOffline::verifyXml($xml, '1234');
```

### What you get

| Method | Returns |
|--------|---------|
| `name()` | `?string` |
| `dob()` | `?DateTimeImmutable`, or `null` when only the year is present |
| `dateOfBirthRaw()` / `birthYear()` / `age()` | `?string` / `?int` / `?int` |
| `gender()` | `?Gender` (`Male`, `Female`, `Transgender`) |
| `address()` | `Address` with `careOf`, `house`, `street`, `landmark`, `locality`, `vtc`, `postOffice`, `subDistrict`, `district`, `state`, `country`, `pincode`, `formatted()` |
| `photo()` | `?Photo` with `toDataUri()`, `contents()`, `mimeType()`, `extension()` |
| `referenceId()` | UIDAI reference ID (last 4 digits + timestamp) |
| `lastFourDigits()` / `maskedAadhaar()` | `"5678"` / `"XXXX XXXX 5678"` |
| `generatedAt()` / `ageInDays()` / `isFresh($days)` | When UIDAI generated the file |
| `hasMobile()` / `hasEmail()` | Whether a mobile/email is linked |
| `source()` | `PayloadSource::Xml` or `PayloadSource::SecureQr` |
| `mobileLastFourDigits()` | Last four mobile digits, from Secure QR version 2 and later. Not in `toArray()` |
| `isVerified()` | Whether `verify()` succeeded on this instance |
| `toArray($withPhoto = false)` | Privacy-safe array |

---

## Secure QR Codes

The QR code on Aadhaar letters, PVC cards and e-Aadhaar PDFs is signed by UIDAI with the same certificate as Offline e-KYC XML, so the same configuration verifies both.
Scan it with any QR reader and pass the text, a long string of digits:

```php
$kyc = AadhaarOffline::verifySecureQr($request->input('qr'));

$kyc->name();                  // "Asha Verma"
$kyc->photo()?->mimeType();    // "image/jp2"
$kyc->mobileLastFourDigits();  // "3210"
```

Version 2 and later QR codes carry the last four digits of the mobile number instead of a hash, so `matchesMobile()` and `matchesEmail()` only work on older codes.
Unknown QR versions are rejected instead of being guessed at, so a layout change can't produce wrong data that still verifies.
A printed card is often years old, so the age check is off for QR codes unless you set `AADHAAR_OFFLINE_QR_MAX_AGE_DAYS`.

The photo is usually JPEG 2000, which most browsers can't display.
Convert it server-side (for example with Imagick) if you need to show it.

---

## Verification

`verify()` runs two checks and throws on the first failure:

1. **Signature.** The XML must contain exactly one enveloped signature, directly under the root, with a single reference covering the whole document (`URI=""`). The signature is checked against **your configured certificates only**, never a key embedded in the file. Any edit, even one character in the name, fails.
2. **Freshness.** The timestamp in the reference ID must be within `max_age_days`.

```php
use Shakil\AadhaarOffline\Exceptions\{InvalidSignatureException, StaleKycException};

try {
    $kyc = AadhaarOffline::verifyZip($file, $shareCode);
} catch (InvalidSignatureException) {
    // Forged, edited, or signed by an untrusted key
} catch (StaleKycException) {
    // Ask the resident to download a fresh file
}
```

> **How it works in detail:** [docs/verification.md](docs/verification.md)

---

## Matching Mobile and Email

The file doesn't contain the mobile number or email, only salted hashes. Ask the user for their number and check it:

```php
$kyc->matchesMobile('+91 98765 43210'); // bool
$kyc->matchesEmail('asha@example.com');  // bool
```

UIDAI's scheme is SHA-256 of `value + shareCode`, repeated *N* times, where *N* is the last digit of the Aadhaar number (0 and 1 both mean once). Matching needs the share code, so pass it to `fromXml()` when you use vendor XML.

> Only trust a match **after** `verify()`. A forged file can contain any hash.

---

## Privacy and Storage

Aadhaar data is regulated. The defaults are chosen so you don't leak it by accident:

- The full Aadhaar number is **never** part of the result. Only the last four digits are, from the reference ID.
- `toArray()`, `json_encode()`, `dd()` and `dump()` leave out the share code, mobile/email hashes and raw XML. The photo is only included with `toArray(withPhoto: true)`.
- Exception messages and event payloads contain no personal data.
- The photo stays in memory. To keep it, use a private disk and a random file name:

```php
$path = AadhaarOffline::storePhoto($kyc);               // config disk + directory
$path = AadhaarOffline::storePhoto($kyc, 'kyc/photos', 's3-private');
```

- Store Aadhaar numbers masked with the cast:

```php
use Shakil\AadhaarOffline\Casts\MaskedAadhaar;

protected function casts(): array
{
    return ['aadhaar' => MaskedAadhaar::class];
}

$user->aadhaar = '2345 6789 9012'; // saved as "XXXX XXXX 9012"
```

> **More:** [docs/privacy.md](docs/privacy.md)

---

## Validation Rules

```php
use Shakil\AadhaarOffline\Rules\ValidAadhaarNumber;
use Shakil\AadhaarOffline\Rules\ValidOfflineKycZip;

$request->validate([
    // 12 digits, no leading 0/1, valid Verhoeff check digit. Spaces and hyphens allowed.
    'aadhaar_number' => ['required', new ValidAadhaarNumber()],

    // Opens with the share code and passes signature + freshness checks
    'aadhaar_zip' => ['required', 'file', new ValidOfflineKycZip(shareCodeField: 'share_code')],

    // Only check that it opens, without verifying
    'draft_zip' => [new ValidOfflineKycZip('share_code', verify: false)],
]);
```

`ValidOfflineKycZip` keeps the payload it read, so you don't open and verify the ZIP twice.
Keep a reference to the rule and call `kyc()` after validation passes:

```php
$request->validate(['aadhaar_zip' => ['bail', 'required', 'file', $rule = new ValidOfflineKycZip()]]);

$kyc = $rule->kyc();
```

For format-only checks without Laravel, use `Shakil\AadhaarOffline\Support\AadhaarNumber::isValid()`, `::mask()` and `Verhoeff::validate()`.

---

## Upload Form

A ready-made, unstyled form for the ZIP and share code:

```blade
<x-aadhaar-offline::upload-form :action="route('kyc.store')" class="space-y-4" />
```

It posts `multipart/form-data` with CSRF, accepts only `.zip`, and masks the share code with `inputmode="numeric"`, `maxlength="4"` and `autocomplete="off"`.
Validation errors appear under each field and are linked with `aria-describedby`.
Options: `file-field`, `share-code-field` and `submit-label`; anything in the slot is placed before the submit button.
Style it through the `aadhaar-offline-*` classes, or publish the view with `php artisan vendor:publish --tag=aadhaar-offline-views`.

---

## Events

| Event | When | Properties |
|-------|------|------------|
| `OfflineKycVerified` | `verify()` succeeded | `referenceId`, `maskedAadhaar`, `generatedAt` |
| `OfflineKycRejected` | `verify()` failed | `referenceId`, `reason`, `exception` (class name) |

A good fit for an audit trail, e.g. with [`itxshakil/laravel-audit-trail`](https://github.com/itxshakil/laravel-audit-trail):

```php
// AppServiceProvider::boot()
use Illuminate\Support\Facades\Event;
use Shakil\AadhaarOffline\Events\OfflineKycRejected;
use Shakil\AadhaarOffline\Events\OfflineKycVerified;
use Shakil\AuditTrail\Facades\AuditTrail;

Event::listen(function (OfflineKycVerified $event): void {
    if ($user = auth()->user()) {
        AuditTrail::on($user)->by($user)->event('aadhaar_verified')
            ->changes(null, ['reference_id' => $event->referenceId, 'masked_aadhaar' => $event->maskedAadhaar])
            ->log();
    }
});

Event::listen(function (OfflineKycRejected $event): void {
    if ($user = auth()->user()) {
        AuditTrail::on($user)->by($user)->event('aadhaar_rejected')
            ->changes(null, ['reference_id' => $event->referenceId, 'reason' => $event->exception])
            ->log();
    }
});
```

Both events carry no personal data, so the audit log stays free of it too.

---

## Testing

```php
use Shakil\AadhaarOffline\Facades\AadhaarOffline;

public function test_kyc_upload(): void
{
    $fake = AadhaarOffline::fake(['name' => 'Ravi Kumar', 'mobile' => '9123456789']);

    $this->post('/kyc', [...])->assertRedirect();

    $fake->assertZipRead();
    $fake->assertVerified(fn ($kyc) => $kyc->name() === 'Ravi Kumar');
}

public function test_forged_file_is_rejected(): void
{
    AadhaarOffline::fake()->rejectSignature(); // also: rejectShareCode(), rejectAsStale(), rejectAsMalformed()

    $this->post('/kyc', [...])->assertSessionHasErrors('aadhaar_zip');
}
```

Build payloads directly with `Shakil\AadhaarOffline\Testing\OfflineKycFactory::make([...])`.

> **Full testing guide:** [docs/testing.md](docs/testing.md)

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `aadhaar-offline:add-cert {certificate}` | Takes a path or `https://` URL, shows the certificate's SHA-256 fingerprint and checks it against `--fingerprint` (or asks you). Then stores it privately and appends it to `AADHAAR_OFFLINE_CERTIFICATES` without touching other `.env` lines |
| `aadhaar-offline:certs` | Lists trusted certificates with SHA-256 fingerprints and expiry dates. Warns about expired ones, ones expiring within `--warn-days` (default 30), and disabled signature checks |
| `aadhaar-offline:check {file}` | Checks a real ZIP or XML stage by stage (open, parse, signature, freshness). Prompts for the share code and prints no personal data. When the signature fails, shows the fingerprint of the certificate the file claims, so you can find the right one on uidai.gov.in |

---

## Error Handling

Every exception extends `Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException`.

| Exception | Meaning |
|-----------|---------|
| `InvalidShareCodeException` | Share code isn't 4 digits or doesn't open the ZIP |
| `MalformedPayloadException` | Not a ZIP, wrong number of entries, too large, not well-formed XML, DOCTYPE present, or not an Offline e-KYC document. For QR codes: not a Secure QR number, corrupt, truncated, or an unsupported version |
| `InvalidSignatureException` | Missing, duplicated, edited or untrusted signature, on XML or QR |
| `StaleKycException` | Older than `max_age_days` (XML) or `secure_qr.max_age_days` (QR) |
| `ConfigurationException` | No certificate configured, certificate unreadable, or share code missing for hash matching |

---

## AI Assistants

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines in `resources/boost/guidelines/core.blade.php`.
They tell AI coding agents to verify before trusting data, never trust the certificate inside a document, keep personal data out of logs and use `AadhaarOffline::fake()` in tests.
Run `php artisan boost:install` and select `itxshakil/laravel-aadhaar-offline` when Boost lists third-party packages.

---

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md). Report security issues privately as described in [SECURITY.md](SECURITY.md).

**Never commit real Aadhaar data, Offline e-KYC files or private keys.** The test suite generates its own keys and synthetic payloads.

---

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

This package is not affiliated with or endorsed by UIDAI. "Aadhaar" is used only to describe compatibility.
