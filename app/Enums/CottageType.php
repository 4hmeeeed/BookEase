<?php

namespace App\Enums;

enum CottageType: string
{
    case Cottage = 'cottage';
    case Room = 'room';
    case Villa = 'villa';
    case Cabana = 'cabana';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<string, string> value => label, for <select> options
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
