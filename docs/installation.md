# Installation

## 1. Install the package

```bash
composer require itxshakil/laravel-aadhaar-offline
```

The service provider and the `AadhaarOffline` facade are auto-discovered.

PHP needs the `dom`, `openssl`, `zip` and `zlib` extensions. Check with:

```bash
php -m | grep -E "dom|openssl|zip|zlib"
```

## 2. Publish the config (optional)

```bash
php artisan vendor:publish --tag=aadhaar-offline-config
```

## 3. Add the UIDAI certificate

The package needs the UIDAI public certificate that signs Offline e-KYC XML and Secure QR codes.
It doesn't ship one, for two reasons: UIDAI rotates certificates, and you should get trust anchors from the source yourself.

1. Download the Offline e-KYC public certificate (`uidai_offline_publickey_*.cer`) from the UIDAI website (developer / Offline e-KYC section).
2. Add it, passing the SHA-256 fingerprint UIDAI publishes:

```bash
php artisan aadhaar-offline:add-cert /path/to/uidai_offline_publickey.cer --fingerprint="E0:30:4B:..."
```

The command accepts a path or an `https://` URL and PEM or DER files.
It shows the certificate's subject, expiry and fingerprint, and refuses to continue if the fingerprint differs or the certificate has expired.
Without `--fingerprint` it asks you to confirm the fingerprint yourself.
It then stores the certificate in `storage/app/aadhaar-offline/certs` and appends its path to `AADHAAR_OFFLINE_CERTIFICATES` in `.env`, leaving every other line alone.
If your configuration is cached, run `php artisan config:cache` afterwards.

You can also set the variable by hand.
During a rotation, list both certificates, comma-separated:

```env
AADHAAR_OFFLINE_CERTIFICATES=/path/old.cer,/path/new.cer
```

If you verify Secure QR codes, keep older certificates listed even after they expire.
A printed card stays signed with whichever certificate was current when it was printed.

3. Confirm:

```bash
php artisan aadhaar-offline:certs
```

The command warns about certificates that have expired or expire within 30 days (change it with `--warn-days`).

**Don't know which certificate you need?**
Run `aadhaar-offline:check` on a real file (next step).
When the signature check fails, it shows the subject and fingerprint of the certificate the file claims, so you know which one to download.

## 4. Test with a real file

Before going live, download your own Offline e-KYC ZIP from UIDAI and run:

```bash
php artisan aadhaar-offline:check /path/to/offlineaadhaar.zip
```

The command asks for the share code, so it never ends up in your shell history.
It reports each stage (open and parse, signature, freshness) as PASS or FAIL and prints only masked or structural data.

If the signature stage fails, it also shows the subject and SHA-256 fingerprint of the certificate the file claims to be signed with.
That certificate is never trusted, because a forged file can carry any certificate.
Use it only to find the matching certificate on uidai.gov.in, then compare the fingerprints before adding it to `AADHAAR_OFFLINE_CERTIFICATES`.

If verification fails on a genuine file, please [open an issue](https://github.com/itxshakil/laravel-aadhaar-offline/issues) with the command output.
**Do not attach the file.**

## Local development without a certificate

```env
AADHAAR_OFFLINE_VERIFY_SIGNATURE=false
```

`aadhaar-offline:certs` warns when this is off. Never deploy with it disabled.
