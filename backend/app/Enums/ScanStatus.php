<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /** Un scan actif empêche d'en lancer un autre pour le même utilisateur. */
    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Queued, self::Running];
    }
}
