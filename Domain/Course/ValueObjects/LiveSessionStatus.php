<?php

namespace Domain\Course\ValueObjects;

enum LiveSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
}
