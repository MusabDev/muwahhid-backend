<?php

namespace App\Enums;

enum BookStatus: string
{
    case ReadLater = 'read_later';
    case Reading = 'reading';
    case OnHold = 'on_hold';
    case Dropped = 'dropped';
    case Completed = 'completed';
}
