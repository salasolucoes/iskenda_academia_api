<?php

namespace Infrastructure\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    private string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('filesystems.default', 'minio');
    }

    public function generate(
        string $studentName,
        string $courseTitle,
        string $issuedAt,
        string $verificationHash,
    ): string {
        $pdf = Pdf::loadView('certificates.certificate', [
            'studentName' => $studentName,
            'courseTitle' => $courseTitle,
            'issuedAt' => $issuedAt,
            'verificationHash' => $verificationHash,
        ])->setPaper('a4', 'landscape');

        $fileName = 'certificates/'.$verificationHash.'.pdf';
        Storage::disk($this->disk)->put($fileName, $pdf->output());

        return Storage::disk($this->disk)->url($fileName);
    }
}
