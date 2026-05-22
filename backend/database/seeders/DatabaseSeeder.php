<?php

namespace Database\Seeders;

use App\Models\Counter;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ────────────────────────────────────
        $admin = User::create([
            'name'      => 'Administrator',
            'username'  => 'admin',
            'password'  => Hash::make('password'),
            'role_type' => 'superadmin',
        ]);

        $loket1 = User::create([
            'name'      => 'Petugas Loket 1',
            'username'  => 'loket1',
            'password'  => Hash::make('password'),
            'role_type' => 'loket',
        ]);

        $loket2 = User::create([
            'name'      => 'Petugas Loket 2',
            'username'  => 'loket2',
            'password'  => Hash::make('password'),
            'role_type' => 'loket',
        ]);

        $loket3 = User::create([
            'name'      => 'Petugas Loket 3',
            'username'  => 'loket3',
            'password'  => Hash::make('password'),
            'role_type' => 'loket',
        ]);

        // ── Services ─────────────────────────────────
        $csService = Service::create([
            'name'         => 'Customer Service',
            'prefix_code'  => 'A',
            'digit_length' => 3,
        ]);

        $tellerService = Service::create([
            'name'         => 'Teller',
            'prefix_code'  => 'B',
            'digit_length' => 3,
        ]);

        $infoService = Service::create([
            'name'         => 'Informasi',
            'prefix_code'  => 'C',
            'digit_length' => 3,
        ]);

        // ── Counters ─────────────────────────────────
        $counter1 = Counter::create([
            'name'   => 'Loket 1',
            'status' => true,
        ]);
        $counter1->services()->attach([$csService->id]);
        $counter1->users()->attach([$loket1->id]);

        $counter2 = Counter::create([
            'name'   => 'Loket 2',
            'status' => true,
        ]);
        $counter2->services()->attach([$tellerService->id]);
        $counter2->users()->attach([$loket2->id]);

        $counter3 = Counter::create([
            'name'   => 'Loket 3',
            'status' => true,
        ]);
        $counter3->services()->attach([$infoService->id]);
        $counter3->users()->attach([$loket3->id]);

        // ── Settings ─────────────────────────────────
        Setting::set('app_name', 'Pandai Antrian');
        Setting::set('app_logo', null);
        Setting::set('enable_camera', 'false');
    }
}
