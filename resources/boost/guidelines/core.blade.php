## Aadhaar Offline (itxshakil/laravel-aadhaar-offline)

- Read Aadhaar data only through the `Shakil\AadhaarOffline\Facades\AadhaarOffline` facade: `fromZip($file, $shareCode)`, `fromXml($xml)`, `fromXmlFile($path)` and `fromSecureQr($digits)`. Never parse Offline e-KYC XML or Secure QR bytes yourself.
- Every reader returns an unverified `OfflineKyc`. Call `->verify()`, or use `verifyZip()` / `verifyXml()` / `verifySecureQr()`, before trusting any field. `isVerified()` tells you whether it ran.
- Verification uses only the UIDAI certificates in `AADHAAR_OFFLINE_CERTIFICATES`. Never trust the certificate inside a document (its `KeyInfo`), and never set `AADHAAR_OFFLINE_VERIFY_SIGNATURE=false` outside local development.
- Add certificates with `php artisan aadhaar-offline:add-cert <path-or-https-url> --fingerprint=...`, taking the file and fingerprint from UIDAI's Data and Downloads page. Check them with `php artisan aadhaar-offline:certs`.
- For uploads, validate with `$rule = new ValidOfflineKycZip('share_code')` and reuse `$rule->kyc()` after validation instead of reading the ZIP again. Use `ValidAadhaarNumber` for typed Aadhaar numbers.
- Treat everything in `OfflineKyc` as personal data. Never log, dump, cache or queue it; never put it in exception messages or event payloads. `toArray()` and JSON already leave out hashes, share codes and raw payloads; keep the photo out unless it is needed (`toArray(withPhoto: true)`).
- Never store a full Aadhaar number. Store `maskedAadhaar()`, `lastFourDigits()` or `referenceId()`, or cast the column with `Shakil\AadhaarOffline\Casts\MaskedAadhaar`.
- Store photos only with `AadhaarOffline::storePhoto($kyc)`, which uses a private disk and a random file name.
- `matchesMobile()` / `matchesEmail()` only mean something after `verify()`. Secure QR codes from version 2 on carry only `mobileLastFourDigits()`, not a hash.
- Catch `Shakil\AadhaarOffline\Exceptions\AadhaarOfflineException` subclasses: `InvalidShareCodeException`, `MalformedPayloadException`, `InvalidSignatureException`, `StaleKycException`. Let `ConfigurationException` surface: it means the app is misconfigured, not that the user's file is bad.
- Offline e-KYC XML must be fresh (`max_age_days`, default 3). Secure QR codes have a separate limit, `secure_qr.max_age_days`, off by default because printed cards are old.
- Listen to `OfflineKycVerified` / `OfflineKycRejected` for audit logs; they carry no personal data.
- In tests, never use real Aadhaar files, QR codes or numbers. Use `AadhaarOffline::fake([...])` with `rejectSignature()`, `rejectShareCode()`, `rejectAsStale()` or `rejectAsMalformed()`, assert with `assertZipRead()`, `assertSecureQrRead()` and `assertVerified()`, and build payloads with `Shakil\AadhaarOffline\Testing\OfflineKycFactory::make([...])`.
- To debug a real file without exposing personal data, run `php artisan aadhaar-offline:check <file>`: it prompts for the share code and prints only masked, structural output.
