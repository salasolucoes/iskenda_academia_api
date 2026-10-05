<?php

namespace Domain\Course\ValueObjects;

enum Modality: string
{
    case Online = 'online';
    case Presential = 'presential';
    case Mixed = 'mixed';
}
