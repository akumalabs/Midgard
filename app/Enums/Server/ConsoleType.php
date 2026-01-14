<?php

namespace App\Enums\Server;

/**
 * ConsoleType - Console types following Convoy pattern
 */
enum ConsoleType: string
{
    case NOVNC = 'novnc';
    case XTERM = 'xterm';
    case SPICE = 'spice';

    public function displayName(): string
    {
        return match($this) {
            self::NOVNC => 'noVNC (Web Console)',
            self::XTERM => 'xterm.js (Terminal)',
            self::SPICE => 'SPICE',
        };
    }
}
