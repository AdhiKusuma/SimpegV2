<?php

return [
    'profile_photo' => [
        // Batas file sebelum diproses agar request tidak menerima file besar.
        'max_upload_kb' => (int) env('PROFILE_PHOTO_MAX_UPLOAD_KB', 1024),

        // Ukuran target file hasil simpan. Nilai ini adalah target, bukan jaminan
        // mutlak karena kualitas gambar tidak boleh dibuat terlalu rendah.
        'target_kb' => (int) env('PROFILE_PHOTO_TARGET_KB', 250),
        'max_dimension' => (int) env('PROFILE_PHOTO_MAX_DIMENSION', 600),
        'quality' => (int) env('PROFILE_PHOTO_QUALITY', 78),
    ],

    'data_diri_pdf' => [
        // Dokumen disimpan di filesystem private, bukan sebagai BLOB di database.
        'max_upload_kb' => (int) env('DATA_DIRI_PDF_MAX_UPLOAD_KB', 1024),
    ],

    'mutation_document' => [
        // SK disimpan di filesystem; database hanya menyimpan nama file.
        'max_upload_kb' => (int) env('MUTATION_DOCUMENT_MAX_UPLOAD_KB', 3072),
    ],
];
