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

To exercise signature checks without the fake, build signed synthetic payloads with `Shakil\AadhaarOffline\Testing\Fixtures` and trust its test certificate:

```php
use Shakil\AadhaarOffline\Testing\Fixtures;

config()->set('aadhaar-offline.certificates', [Fixtures::certificateFile()]);

$this->post('/kyc', [
    'aadhaar_zip' => new UploadedFile(Fixtures::zip(Fixtures::signedXml()), 'offline.zip', 'application/zip', null, true),
    'share_code' => Fixtures::SHARE_CODE,
])->assertRedirect();
```

`Fixtures::signedXml(identity: 'attacker')` signs with a different key, to test that forged files are rejected.
Set the config before the package's services are first resolved, e.g. in `defineEnvironment()` or `setUp()`.
See the [core testing guide](https://github.com/itxshakil/aadhaar-offline/blob/main/docs/testing.md) for Secure QR codes and age limits.

**Never commit a real Offline e-KYC file**, even your own.
