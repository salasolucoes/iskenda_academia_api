<?php

namespace Domain\Course\ValueObjects;

enum LessonType: string
{
    case Video = 'video';
    case Pdf = 'pdf';
    case Live = 'live';
}
