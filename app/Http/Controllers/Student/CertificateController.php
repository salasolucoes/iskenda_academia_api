<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Application\UseCases\Enrollment\IssueCertificateUseCase;
use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infrastructure\Persistence\Eloquent\Models\Certificate;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\User;
use Infrastructure\Services\CertificatePdfService;

class CertificateController extends Controller
{
    public function __construct(
        private EnrollmentRepositoryInterface $enrollmentRepository,
        private IssueCertificateUseCase $issueCertificateUseCase,
        private CertificatePdfService $pdfService,
    ) {}

    public function issue(string $enrollmentId, Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        $enrollment = $this->enrollmentRepository->findById($enrollmentId);

        if ($enrollment === null || $enrollment->getStudentId() !== $studentId) {
            return response()->json(['message' => 'Enrollment not found.'], 404);
        }

        if ($enrollment->getStatus()->value !== 'completed') {
            return response()->json(['message' => 'Course is not yet completed.'], 422);
        }

        $existing = Certificate::where('enrollment_id', $enrollmentId)->first();
        if ($existing !== null) {
            return response()->json([
                'data' => [
                    'certificate_id' => $existing->id,
                    'verification_hash' => $existing->verification_hash,
                    'pdf_url' => $existing->pdf_url,
                ],
            ]);
        }

        $course = Course::find($enrollment->getCourseId());
        $student = User::find($studentId);

        $certificate = $this->issueCertificateUseCase->execute(
            enrollment: $enrollment,
            courseTitle: $course?->title ?? 'Unknown',
            studentName: $student?->name ?? 'Unknown',
        );

        $certificateModel = Certificate::create([
            'id' => $certificate->getId(),
            'enrollment_id' => $certificate->getEnrollmentId(),
            'student_id' => $certificate->getStudentId(),
            'course_id' => $certificate->getCourseId(),
            'issued_at' => $certificate->getIssuedAt(),
            'verification_hash' => $certificate->getVerificationHash()->getValue(),
        ]);

        $pdfUrl = $this->pdfService->generate(
            studentName: $student?->name ?? 'Unknown',
            courseTitle: $course?->title ?? 'Unknown',
            issuedAt: $certificate->getIssuedAt()->format('d/m/Y'),
            verificationHash: $certificate->getVerificationHash()->getValue(),
        );

        $certificateModel->update(['pdf_url' => $pdfUrl]);

        return response()->json([
            'data' => [
                'certificate_id' => $certificateModel->id,
                'verification_hash' => $certificateModel->verification_hash,
                'pdf_url' => $pdfUrl,
            ],
        ], 201);
    }

    public function verify(string $hash): JsonResponse
    {
        $certificate = Certificate::where('verification_hash', $hash)->first();

        if ($certificate === null) {
            return response()->json(['message' => 'Certificate not found.'], 404);
        }

        $student = User::find($certificate->student_id);
        $course = Course::find($certificate->course_id);

        return response()->json([
            'data' => [
                'student_name' => $student?->name ?? 'Unknown',
                'course_title' => $course?->title ?? 'Unknown',
                'issued_at' => $certificate->issued_at->format('Y-m-d'),
                'valid' => true,
            ],
        ]);
    }
}
