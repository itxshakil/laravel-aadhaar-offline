# Testing

## Faking the facade

```php
use Shakil\AadhaarOffline\Facades\AadhaarOffline;

$fake = AadhaarOffline::fake([
    'name' => 'Ravi Kumar',
    'dob' => '01-04-1988',
    'gender' => 'M',
    'mobile' => '9123456789',     // plain value, hashed for you
    'email' => 'ravi@example.com',
    'share_code' => '1234',
    'last_four' => '4321',
    'generated_at' => now()->subDay(),
]);
```

Every `fromZip()`, `fromXml()` and `fromXmlFile()` call returns a synthetic `OfflineKyc` built from these attributes. Files are not read. `verify()` on that payload succeeds unless you tell it otherwise.

### Simulating failures

| Method | Effect |
|--------|--------|
| `rejectShareCode()` | Reads throw `InvalidShareCodeException` |
| `rejectAsMalformed()` | Reads throw `MalformedPayloadException` |
| `rejectSignature()` | `verify()` throws `InvalidSignatureException` |
| `rejectAsStale()` | `verify()` throws `StaleKycException` |

The `ValidOfflineKycZip` rule uses the facade, so it sees the fake too.

### Assertions

| Assertion | Checks |
|-----------|--------|
| `assertRead(?int $times)` | A payload was read (optionally an exact number of times) |
| `assertZipRead()` | A ZIP was read |
| `assertSecureQrRead()` | A Secure QR code was read with `fromSecureQr()` or `verifySecureQr()` |
| `assertNothingRead()` | Nothing was read |
| `assertVerified(?Closure $callback)` | A payload passed `verify()` (optionally matching the callback) |
| `assertNotVerified()` | Nothing passed `verify()` |
| `assertPhotoStored()` / `assertNoPhotoStored()` | `storePhoto()` was or wasn't called with a photo |

## Building payloads directly

```php
use Shakil\AadhaarOffline\Testing\OfflineKycFactory;

$kyc = OfflineKycFactory::make(['name' => 'Asha Verma', 'photo' => null]);
```

## Testing the real verification path

The package's own suite (`tests/Support/Fixtures.php`) shows how to do this without real Aadhaar data:

1. Generate an RSA key and self-signed certificate at runtime with `openssl_pkey_new()` / `openssl_csr_sign()`.
2. Build XML in the UIDAI shape and sign it with `XMLSecurityDSig` (enveloped, `force_uri`).
3. ZIP it with `ZipArchive::setEncryptionName()` using a share code.
4. Point `aadhaar-offline.certificates` at the generated certificate.

**Never commit a real Offline e-KYC file**, even your own.
