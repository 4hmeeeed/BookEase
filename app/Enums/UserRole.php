<?php

namespace App\Enums;

enum UserRole: string
{
    case Guest = 'guest';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Guest => 'Guest',
            self::Staff => 'Staff',
            self::Admin => 'Owner / Admin',
        };
    }

    /**
     * Named route each role lands on after logging in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Guest => 'guest.dashboard',
            self::Staff => 'staff.dashboard',
            self::Admin => 'admin.dashboard',
        };
    }
}
