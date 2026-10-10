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

1. Find the current certificate on UIDAI's [Data and Downloads page](https://uidai.gov.in/en/data-and-download).
   Offline e-KYC certificates are named `uidai_offline_publickey_*.cer`.
2. Add it, passing the SHA-256 fingerprint:

```bash
php artisan aadhaar-offline:add-cert \
    https://backend.uidai.gov.in/get/files/media/document/2026-07/uidai_offline_publickey_2026.cer \
    --fingerprint="E0:30:4B:9E:61:EE:36:40:EC:DD:AE:2D:B4:B6:17:F2:E2:67:8F:57:DB:C2:82:6C:2F:86:AC:5C:04:F2:77:DF"
```

UIDAI moves files between URLs, so if the link above stops working, take the current one from the Data and Downloads page.

Offline e-KYC certificates on that page, as checked on 2026-10-10:

| File | Subject | Valid to | SHA-256 fingerprint |
|------|---------|----------|---------------------|
| `uidai_offline_publickey_2026.cer` | DS Unique Identification Authority of India 06 | 2029-02-03 | `E0:30:4B:9E:61:EE:36:40:EC:DD:AE:2D:B4:B6:17:F2:E2:67:8F:57:DB:C2:82:6C:2F:86:AC:5C:04:F2:77:DF` |
| `uidai_offline_publickey_17022026.cer` | DS Unique Identification Authority of India 05 | 2026-02-16 | `00:57:3C:69:2C:F0:4F:BE:E1:13:AE:5F:D5:2F:2A:65:4D:36:1B:F8:EE:4D:06:C4:60:08:90:6B:36:DF:81:69` |
| `uidai_offline_publickey_26022021.cer` | DS UNIQUE IDENTIFICATION AUTHORITY OF INDIA 05 | 2024-02-27 | `E0:F0:F8:69:D3:2E:FC:7E:80:FA:E2:22:37:17:A5:6D:CF:8B:61:6F:82:0B:54:2A:49:E5:BD:5A:BF:1C:0F:7D` |

Offline e-KYC downloads made today are signed with the 2026 certificate.
The expired ones only matter for Secure QR codes printed while they were current; add them with `--allow-expired`.

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
