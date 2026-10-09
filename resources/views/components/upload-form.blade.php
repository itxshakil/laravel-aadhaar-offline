@props([
    'action',
    'fileField' => 'aadhaar_zip',
    'shareCodeField' => 'share_code',
    'submitLabel' => 'Verify Aadhaar',
])

{{-- $errors is only shared by the web middleware group; don't fail without it. --}}
@php($errors ??= new \Illuminate\Support\ViewErrorBag())

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" {{ $attributes->merge(['class' => 'aadhaar-offline-form']) }}>
    @csrf

    <div class="aadhaar-offline-field">
        <label for="{{ $fileField }}">Offline e-KYC ZIP file</label>
        <input
            type="file"
            id="{{ $fileField }}"
            name="{{ $fileField }}"
            accept=".zip,application/zip"
            required
            @error($fileField)
                aria-describedby="{{ $fileField }}-help {{ $fileField }}-error"
                aria-invalid="true"
            @else
                aria-describedby="{{ $fileField }}-help"
            @enderror
        >
        <p id="{{ $fileField }}-help" class="aadhaar-offline-help">
            Download it from the UIDAI website (myaadhaar.uidai.gov.in, Offline e-KYC).
        </p>
        @error($fileField)
            <p id="{{ $fileField }}-error" class="aadhaar-offline-error" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="aadhaar-offline-field">
        <label for="{{ $shareCodeField }}">Share code</label>
        <input
            type="password"
            id="{{ $shareCodeField }}"
            name="{{ $shareCodeField }}"
            inputmode="numeric"
            pattern="[0-9]{4}"
            minlength="4"
            maxlength="4"
            autocomplete="off"
            required
            @error($shareCodeField)
                aria-describedby="{{ $shareCodeField }}-help {{ $shareCodeField }}-error"
                aria-invalid="true"
            @else
                aria-describedby="{{ $shareCodeField }}-help"
            @enderror
        >
        <p id="{{ $shareCodeField }}-help" class="aadhaar-offline-help">
            The 4-digit code you chose when downloading the file.
        </p>
        @error($shareCodeField)
            <p id="{{ $shareCodeField }}-error" class="aadhaar-offline-error" role="alert">{{ $message }}</p>
        @enderror
    </div>

    {{ $slot }}

    <button type="submit">{{ $submitLabel }}</button>
</form>
