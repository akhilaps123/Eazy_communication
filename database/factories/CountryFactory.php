<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test data only (in-memory / throwaway databases). Never run against the real database.
 *
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;
        $name = 'Testland '.$n;

        return [
            'iso2' => $this->code($n, 2),
            'iso3' => $this->code($n, 3),
            'name' => $name,
            'slug' => Str::slug($name),
            'region' => 'Asia',
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 0,
        ];
    }

    /** Unique letter codes AA, AB, ... / AAA, AAB, ... */
    private function code(int $n, int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code = chr(65 + ($n % 26)).$code;
            $n = intdiv($n, 26);
        }

        return $code;
    }

    public function named(string $name, string $iso2, ?string $iso3 = null): static
    {
        return $this->state(['name' => $name, 'slug' => Str::slug($name), 'iso2' => $iso2, 'iso3' => $iso3]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function popular(): static
    {
        return $this->state(['is_popular' => true]);
    }
}
