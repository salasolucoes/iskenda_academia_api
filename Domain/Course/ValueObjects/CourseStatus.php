<?php

namespace Domain\Course\ValueObjects;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
