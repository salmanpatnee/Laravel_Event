<?php

namespace App;

enum RoleEnum: string
{
    case Organizer = 'organizer';
    case Attendee = 'attendee';

    public function label(): string
    {
        return match ($this) {
            self::Organizer => 'Organizer',
            self::Attendee => 'Attendee',
        };
    }
}
