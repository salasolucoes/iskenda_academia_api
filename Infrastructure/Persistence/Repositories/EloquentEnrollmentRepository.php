<?php

namespace Infrastructure\Persistence\Repositories;

use Domain\Enrollment\Contracts\EnrollmentRepositoryInterface;
use Domain\Enrollment\Entities\Enrollment as EnrollmentEntity;
use Domain\Enrollment\ValueObjects\EnrollmentStatus;
use Infrastructure\Persistence\Eloquent\Models\Enrollment as EnrollmentModel;

class EloquentEnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function findById(string $id): ?EnrollmentEntity
    {
        $model = EnrollmentModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByStudentId(string $studentId): array
    {
        return EnrollmentModel::where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (EnrollmentModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findByStudentAndCourse(string $studentId, string $courseId): ?EnrollmentEntity
    {
        $model = EnrollmentModel::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findActiveByStudent(string $studentId): array
    {
        return EnrollmentModel::where('student_id', $studentId)
            ->where('status', EnrollmentStatus::Active->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (EnrollmentModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(EnrollmentEntity $enrollment): EnrollmentEntity
    {
        $model = EnrollmentModel::updateOrCreate(
            ['id' => $enrollment->getId()],
            [
                'student_id' => $enrollment->getStudentId(),
                'course_id' => $enrollment->getCourseId(),
                'order_id' => $enrollment->getOrderId(),
                'status' => $enrollment->getStatus()->value,
                'enrolled_at' => $enrollment->getEnrolledAt(),
                'completed_at' => $enrollment->getCompletedAt(),
            ]
        );

        return $this->toEntity($model->fresh());
    }

    public function delete(string $id): void
    {
        EnrollmentModel::findOrFail($id)->delete();
    }

    private function toEntity(EnrollmentModel $model): EnrollmentEntity
    {
        return new EnrollmentEntity(
            id: $model->id,
            studentId: $model->student_id,
            courseId: $model->course_id,
            orderId: $model->order_id,
            status: EnrollmentStatus::from($model->status),
            enrolledAt: $model->enrolled_at->toImmutable(),
            completedAt: $model->completed_at?->toImmutable(),
        );
    }
}
