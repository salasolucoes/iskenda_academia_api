<?php

namespace Infrastructure\Persistence\Repositories;

use Domain\Course\Contracts\CourseRepositoryInterface;
use Domain\Course\Entities\Course as CourseEntity;
use Domain\Course\ValueObjects\CourseStatus;
use Domain\Course\ValueObjects\Modality;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Persistence\Eloquent\Models\Course as CourseModel;

class EloquentCourseRepository implements CourseRepositoryInterface
{
    public function findById(string $id): ?CourseEntity
    {
        $model = CourseModel::with(['category', 'instructor'])->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(string $slug): ?CourseEntity
    {
        $model = CourseModel::where('slug', $slug)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findAll(): array
    {
        return CourseModel::with(['category', 'modules.lessons'])->get()
            ->map(fn (CourseModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findPublished(array $filters = []): array
    {
        $cacheKey = 'courses.published:'.md5(json_encode($filters));

        return Cache::tags(['courses'])->remember($cacheKey, 300, function () use ($filters) {
            $query = CourseModel::with(['category', 'instructor'])
                ->where('status', CourseStatus::Published->value);

            if (! empty($filters['modality'])) {
                $query->where('modality', $filters['modality']);
            }

            if (! empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            if (! empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%');
                });
            }

            return $query->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (CourseModel $model) => $this->toEntity($model))
                ->all();
        });
    }

    public function findByInstructor(string $instructorId): array
    {
        return CourseModel::where('instructor_id', $instructorId)
            ->with(['category', 'modules.lessons'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CourseModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(CourseEntity $course): CourseEntity
    {
        $data = [
            'instructor_id' => $course->getInstructorId(),
            'category_id' => $course->getCategoryId(),
            'title' => $course->getTitle(),
            'slug' => $course->getSlug(),
            'description' => $course->getDescription(),
            'modality' => $course->getModality()->value,
            'price_cents' => $course->getPriceCents(),
            'status' => $course->getStatus()->value,
            'thumbnail_url' => $course->getThumbnailUrl(),
        ];

        $model = CourseModel::updateOrCreate(
            ['id' => $course->getId()],
            $data,
        );

        Cache::tags(['courses'])->flush();

        return $this->toEntity($model);
    }

    public function delete(string $id): void
    {
        CourseModel::findOrFail($id)->delete();

        Cache::tags(['courses'])->flush();
    }

    private function toEntity(CourseModel $model): CourseEntity
    {
        return new CourseEntity(
            id: $model->id,
            instructorId: $model->instructor_id,
            categoryId: $model->category_id,
            title: $model->title,
            slug: $model->slug,
            description: $model->description,
            modality: Modality::from($model->modality),
            priceCents: $model->price_cents,
            status: CourseStatus::from($model->status),
            thumbnailUrl: $model->thumbnail_url,
            categoryName: $model->relationLoaded('category') && $model->category ? $model->category->name : null,
            instructorName: $model->relationLoaded('instructor') && $model->instructor ? $model->instructor->name : null,
            createdAt: $model->created_at?->toImmutable(),
        );
    }
}
