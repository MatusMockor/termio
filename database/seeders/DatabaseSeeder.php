<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkingHours;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create tenant
        $tenant = Tenant::create([
            'name' => 'Demo Barbershop',
            'slug' => 'demo-barbershop',
            'business_type' => 'barbershop',
            'address' => 'Hlavná 123, Bratislava',
            'phone' => '+421 900 123 456',
            'timezone' => 'Europe/Bratislava',
            'status' => 'active',
        ]);

        // Create owner user
        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        // Create services
        $services = [
            ['name' => 'Strihanie pánske', 'duration_minutes' => 30, 'price' => '15.00', 'category' => 'Strihanie'],
            ['name' => 'Strihanie dámske', 'duration_minutes' => 45, 'price' => '25.00', 'category' => 'Strihanie'],
            ['name' => 'Úprava brady', 'duration_minutes' => 20, 'price' => '10.00', 'category' => 'Brada'],
            ['name' => 'Holenie', 'duration_minutes' => 30, 'price' => '15.00', 'category' => 'Brada'],
            ['name' => 'Detské strihanie', 'duration_minutes' => 20, 'price' => '10.00', 'category' => 'Strihanie'],
        ];

        $createdServices = [];
        foreach ($services as $index => $service) {
            $createdServices[] = Service::create([
                'tenant_id' => $tenant->id,
                'name' => $service['name'],
                'duration_minutes' => $service['duration_minutes'],
                'price' => $service['price'],
                'category' => $service['category'],
                'sort_order' => $index,
                'is_active' => true,
                'is_bookable_online' => true,
            ]);
        }

        // Create staff
        $staff = StaffProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'display_name' => 'Jano Barber',
            'bio' => 'Skúsený barber s 10 ročnou praxou',
            'specializations' => ['Fade', 'Beard trim', 'Classic cuts'],
            'is_bookable' => true,
            'sort_order' => 0,
        ]);

        // Attach services to staff
        $staff->services()->attach(array_map(static fn (Service $s): int => $s->id, $createdServices));

        // Create working hours for staff (Mon-Fri 9:00-18:00)
        for ($day = 1; $day <= 5; $day++) {
            WorkingHours::create([
                'tenant_id' => $tenant->id,
                'staff_id' => $staff->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_active' => true,
            ]);
        }

        // Create clients
        $clients = [
            ['name' => 'Peter Novák', 'phone' => '+421 901 111 111', 'email' => 'peter@example.com'],
            ['name' => 'Ján Kováč', 'phone' => '+421 902 222 222', 'email' => 'jan@example.com'],
            ['name' => 'Mária Horváthová', 'phone' => '+421 903 333 333', 'email' => 'maria@example.com'],
            ['name' => 'Eva Szabóová', 'phone' => '+421 904 444 444', 'email' => 'eva@example.com'],
        ];

        $createdClients = [];
        foreach ($clients as $client) {
            $createdClients[] = Client::create([
                'tenant_id' => $tenant->id,
                'name' => $client['name'],
                'phone' => $client['phone'],
                'email' => $client['email'],
                'status' => 'active',
            ]);
        }

        // Create some appointments for today and tomorrow
        $today = now()->setTime(10, 0);

        Appointment::create([
            'tenant_id' => $tenant->id,
            'client_id' => $createdClients[0]->id,
            'service_id' => $createdServices[0]->id,
            'staff_id' => $staff->id,
            'starts_at' => $today,
            'ends_at' => $today->copy()->addMinutes(30),
            'status' => 'confirmed',
            'source' => 'manual',
        ]);

        Appointment::create([
            'tenant_id' => $tenant->id,
            'client_id' => $createdClients[1]->id,
            'service_id' => $createdServices[1]->id,
            'staff_id' => $staff->id,
            'starts_at' => $today->copy()->addHours(2),
            'ends_at' => $today->copy()->addHours(2)->addMinutes(45),
            'status' => 'confirmed',
            'source' => 'online',
        ]);

        Appointment::create([
            'tenant_id' => $tenant->id,
            'client_id' => $createdClients[2]->id,
            'service_id' => $createdServices[2]->id,
            'staff_id' => $staff->id,
            'starts_at' => $today->copy()->addDay()->setTime(9, 0),
            'ends_at' => $today->copy()->addDay()->setTime(9, 20),
            'status' => 'pending',
            'source' => 'online',
        ]);
    }
}
