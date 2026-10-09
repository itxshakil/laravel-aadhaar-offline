<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Signature Verification
    |--------------------------------------------------------------------------
    |
    | When true, verify() checks the UIDAI digital signature on every payload.
    | Only switch this off in local development. A payload that has not been
    | signature-checked can contain anything.
    |
    */

    'verify_signature' => (bool) env('AADHAAR_OFFLINE_VERIFY_SIGNATURE', true),

    /*
    |--------------------------------------------------------------------------
    | Trusted UIDAI Certificates
    |--------------------------------------------------------------------------
    |
    | Absolute paths to the UIDAI public certificate(s) used to sign Offline
    | e-KYC XML, in PEM or DER (.cer) format. Download them from the UIDAI
    | website. List more than one during a certificate rotation.
    |
    | AADHAAR_OFFLINE_CERTIFICATES accepts a comma-separated list.
    |
    */

    'certificates' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('AADHAAR_OFFLINE_CERTIFICATES', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Allowed Signature Algorithms
    |--------------------------------------------------------------------------
    |
    | SignatureMethod algorithms accepted on the XML signature. Remove rsa-sha1
    | once you no longer need to accept older Offline e-KYC downloads.
    |
    */

    'algorithms' => [
        'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
        'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Age
    |--------------------------------------------------------------------------
    |
    | verify() rejects payloads generated more than this many days ago.
    | Set to null to disable the check. Confirm the limit your regulator
    | requires (RBI KYC guidance is commonly read as 3 days).
    |
    */

    'max_age_days' => env('AADHAAR_OFFLINE_MAX_AGE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Secure QR
    |--------------------------------------------------------------------------
    |
    | Secure QR codes printed on Aadhaar letters and PVC cards are signed with
    | the same UIDAI certificates as Offline e-KYC XML. A printed card is often
    | years old, so the age check is off by default. Set a number of days to
    | require a recently downloaded e-Aadhaar instead.
    |
    */

    'secure_qr' => [
        'max_age_days' => env('AADHAAR_OFFLINE_QR_MAX_AGE_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Size Limits
    |--------------------------------------------------------------------------
    |
    | Guards against oversized uploads and ZIP bombs. Genuine Offline e-KYC
    | files are well under 1 MB.
    |
    */

    'limits' => [
        'max_zip_bytes' => 2 * 1024 * 1024,
        'max_xml_bytes' => 2 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resident Photo Storage
    |--------------------------------------------------------------------------
    |
    | Used by AadhaarOffline::storePhoto(). Use a private disk: the photo is
    | personal data. Files get random names, never the Aadhaar number.
    |
    */

    'photo' => [
        'disk' => env('AADHAAR_OFFLINE_PHOTO_DISK', 'local'),
        'directory' => 'aadhaar-offline/photos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Dispatch OfflineKycVerified and OfflineKycRejected. Event payloads contain
    | only the reference ID, masked Aadhaar number and failure reason.
    |
    */

    'events' => [
        'enabled' => (bool) env('AADHAAR_OFFLINE_EVENTS', true),
    ],

];
