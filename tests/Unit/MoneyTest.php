<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: string|int, 1: string}>
     */
    public static function formats(): array
    {
        return [
            'zero' => ['0', '₱0.00'],
            'hundreds' => ['950', '₱950.00'],
            'thousands' => ['1500.5', '₱1,500.50'],
            'exact group' => ['100000', '₱100,000.00'],
            'millions' => ['12345678.99', '₱12,345,678.99'],
            'int input' => [2500, '₱2,500.00'],
            'negative' => ['-1250.00', '-₱1,250.00'],
        ];
    }

    #[DataProvider('formats')]
    public function test_format(string|int $input, string $expected): void
    {
        $this->assertSame($expected, Money::format($input));
    }

    public function test_arithmetic_has_no_floating_point_drift(): void
    {
        // 0.1 + 0.2 !== 0.3 in floats; bcmath keeps it exact.
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('7500.00', Money::mul('2500.00', 3));
        $this->assertSame('1999.99', Money::sub('3333.33', '1333.34'));
        $this->assertSame(0, Money::compare('1500', '1500.00'));
    }

    public function test_rejects_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::normalize('12abc');
    }
}
