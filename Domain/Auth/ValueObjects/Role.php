<?php

namespace Domain\Auth\ValueObjects;

enum Role: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Admin = 'admin';
}
