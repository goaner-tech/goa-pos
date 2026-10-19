<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Station;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Database\Seeder;

/**
 * Demo data for trying the terminals. Prices are made-up SSP amounts in piasters (x100).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Admin', Role::Admin, '1000'],
            ['Grace (manager)', Role::Manager, '1111'],
            ['Deng (server)', Role::Server, '2222'],
            ['Akol (bartender)', Role::Bartender, '3333'],
            ['Mary (cashier)', Role::Cashier, '4444'],
            ['Kitchen', Role::Kitchen, '5555'],
        ] as [$name, $role, $pin]) {
            Employee::create(['name' => $name, 'role' => $role, 'pin' => $pin]);
        }

        foreach (range(1, 8) as $n) {
            DiningTable::create(['name' => (string) $n, 'seats' => $n <= 4 ? 2 : 4, 'section' => 'Main', 'sort' => $n]);
        }
        foreach (['P1', 'P2', 'P3'] as $i => $name) {
            DiningTable::create(['name' => $name, 'seats' => 6, 'section' => 'Patio', 'sort' => 20 + $i]);
        }

        $doneness = $this->group('Doneness', 1, 1, ['Rare' => 0, 'Medium' => 0, 'Well done' => 0]);
        $sides = $this->group('Side', 1, 1, ['Chips' => 0, 'Rice' => 0, 'Kisra' => 0, 'Salad' => 0]);
        $size = $this->group('Size', 1, 1, ['Small' => 0, 'Large' => 100000]);
        $milk = $this->group('Milk', 0, 1, ['No milk' => 0, 'Oat milk' => 50000]);
        $extras = $this->group('Extras', 0, 3, ['Extra shot' => 50000, 'Sugar' => 0, 'Honey' => 20000]);
        $mixer = $this->group('Mixer', 0, 1, ['Coke' => 50000, 'Tonic' => 50000, 'Soda' => 50000]);

        $menu = [
            ['Food', Station::Kitchen, [
                ['Grilled beef steak', 2500000, [$doneness, $sides]],
                ['Grilled tilapia', 2200000, [$sides]],
                ['Chicken stew', 1800000, [$sides]],
                ['Ful medames', 900000, []],
                ['Vegetable samosa (3)', 600000, []],
            ]],
            ['Bar', Station::Bar, [
                ['White Bull lager', 500000, []],
                ['Nile Special', 550000, []],
                ['House red wine (glass)', 1200000, []],
                ['Whisky', 1000000, [$mixer]],
                ['Gin', 900000, [$mixer]],
            ]],
            ['Coffee & juice', Station::Barista, [
                ['Espresso', 400000, [$extras]],
                ['Cappuccino', 600000, [$size, $milk, $extras]],
                ['Spiced tea', 300000, [$size]],
                ['Fresh mango juice', 500000, [$size]],
            ]],
        ];

        foreach ($menu as $c => [$categoryName, $station, $items]) {
            $category = Category::create(['name' => $categoryName, 'sort' => $c]);
            foreach ($items as $i => [$name, $price, $groups]) {
                $item = MenuItem::create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'price' => $price,
                    'station' => $station,
                    'sort' => $i,
                ]);
                $item->modifierGroups()->attach(collect($groups)->pluck('id'));
            }
        }
    }

    /**
     * @param  array<string, int>  $options  name => price change in piasters
     */
    private function group(string $name, int $min, int $max, array $options): ModifierGroup
    {
        $group = ModifierGroup::create(['name' => $name, 'min_select' => $min, 'max_select' => $max]);
        foreach ($options as $option => $delta) {
            $group->modifiers()->create(['name' => $option, 'price_delta' => $delta]);
        }

        return $group;
    }
}
