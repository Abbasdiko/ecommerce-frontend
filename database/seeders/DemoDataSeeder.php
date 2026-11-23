<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\Category;
use App\Models\CustomerProfile;
use App\Models\Listing;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Staff users ----
        $staff = [
            ['super-admin', 'Super', 'Admin', 'superadmin@marketplace.test'],
            ['admin', 'Ada', 'Admin', 'admin@marketplace.test'],
            ['finance', 'Femi', 'Finance', 'finance@marketplace.test'],
            ['support', 'Sade', 'Support', 'support@marketplace.test'],
        ];

        foreach ($staff as [$role, $first, $last, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([$role]);
        }

        // ---- Services & packages ----
        $servicesData = [
            ['Electronics Vendor', 'Sell phones, laptops and gadgets', 'retail'],
            ['Fashion Vendor', 'Clothing, footwear and accessories', 'retail'],
            ['Food Vendor', 'Prepared meals and groceries', 'food'],
            ['Professional Services', 'Consulting, repairs and skilled labour', 'services'],
        ];

        $packageTiers = [
            ['Basic', 5000, 30, 10, 0, 12.5],
            ['Standard', 12000, 30, 40, 3, 10.0],
            ['Premium', 25000, 30, 150, 10, 7.5],
        ];

        $services = collect();
        foreach ($servicesData as [$name, $desc, $type]) {
            $service = Service::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc, 'service_type' => $type, 'status' => 'active', 'requires_approval' => true]
            );
            foreach ($packageTiers as [$pname, $price, $days, $limit, $featured, $rate]) {
                ServicePackage::firstOrCreate(
                    ['service_id' => $service->id, 'name' => $pname],
                    [
                        'description' => "$pname plan for {$service->name}",
                        'price' => $price,
                        'duration_days' => $days,
                        'listing_limit' => $limit,
                        'featured_listing_limit' => $featured,
                        'commission_rate' => $rate,
                        'status' => 'active',
                    ]
                );
            }
            $services->push($service);
        }

        // ---- Categories ----
        $categoryTree = [
            'Electronics Vendor' => ['Electronics', ['Phones & Tablets', 'Computers', 'Audio']],
            'Fashion Vendor' => ['Fashion', ['Men', 'Women', 'Children']],
            'Food Vendor' => ['Food & Groceries', ['Meals', 'Groceries', 'Drinks']],
            'Professional Services' => ['Services', ['Home Repair', 'Consulting', 'Events']],
        ];
        foreach ($categoryTree as $serviceName => [$deptName, $children]) {
            $service = $services->firstWhere('name', $serviceName);
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($deptName)],
                ['name' => $deptName, 'service_id' => $service->id, 'status' => 'active']
            );
            foreach ($children as $i => $child) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($deptName . ' ' . $child)],
                    ['name' => $child, 'parent_id' => $parent->id, 'service_id' => $service->id, 'status' => 'active', 'sort_order' => $i]
                );
            }
        }

        // ---- Agents ----
        $agentsData = [
            ['Bright', 'Electronics', 'bright.electronics@marketplace.test', 'Bright Electronics Ltd', 'active', 'verified', 'Electronics Vendor', 'Standard'],
            ['Lagos', 'Fashion', 'lagos.fashion@marketplace.test', 'Lagos Fashion House', 'active', 'verified', 'Fashion Vendor', 'Premium'],
            ['Mama', 'Kitchen', 'mama.kitchen@marketplace.test', "Mama's Kitchen", 'pending', 'pending', 'Food Vendor', null],
            ['Fix', 'It', 'fixit.services@marketplace.test', 'FixIt Professional Services', 'active', 'verified', 'Professional Services', 'Basic'],
        ];

        foreach ($agentsData as [$first, $last, $email, $business, $status, $verification, $serviceName, $packageName]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['agent']);

            $agent = Agent::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $business,
                    'description' => "$business — demo vendor",
                    'phone' => '080' . random_int(10000000, 99999999),
                    'email' => $email,
                    'state' => 'Lagos',
                    'status' => $status,
                    'verification_status' => $verification,
                    'approved_at' => $status === 'active' ? now() : null,
                ]
            );

            if ($packageName && $agent->status === 'active') {
                $service = $services->firstWhere('name', $serviceName);
                $package = $service->packages()->where('name', $packageName)->first();

                $sub = AgentSubscription::firstOrCreate(
                    ['agent_id' => $agent->id, 'service_id' => $service->id, 'package_id' => $package->id, 'status' => 'active'],
                    [
                        'amount' => $package->price,
                        'start_date' => now()->subDays(5),
                        'end_date' => now()->addDays($package->duration_days - 5),
                        'payment_status' => 'successful',
                        'payment_reference' => 'PAY-' . strtoupper(Str::random(10)),
                    ]
                );

                // ---- Listings for this agent ----
                $category = Category::where('service_id', $service->id)->whereNotNull('parent_id')->inRandomOrder()->first();
                $states = [
                    ['approved', 'active', now()],
                    ['approved', 'active', now()],
                    ['pending', 'draft', null],
                    ['rejected', 'inactive', null],
                ];
                foreach ($states as $idx => [$approval, $status2, $publishedAt]) {
                    $listing = Listing::firstOrCreate(
                        ['agent_id' => $agent->id, 'name' => "{$business} Item " . ($idx + 1)],
                        [
                            'service_id' => $service->id,
                            'category_id' => $category?->id,
                            'subscription_id' => $sub->id,
                            'description' => 'Demo listing generated by seeder.',
                            'sku' => 'SKU-' . strtoupper(Str::random(8)),
                            'price' => random_int(2000, 500000),
                            'quantity' => random_int(1, 50),
                            'type' => $serviceName === 'Professional Services' ? 'service' : 'product',
                            'status' => $status2,
                            'approval_status' => $approval,
                            'rejection_reason' => $approval === 'rejected' ? 'Images not clear enough.' : null,
                            'published_at' => $publishedAt,
                        ]
                    );
                    if ($approval === 'approved') {
                        $sub->increment('listings_used');
                    }
                }
            }
        }

        // ---- Customers ----
        foreach (range(1, 6) as $i) {
            $user = User::firstOrCreate(
                ['email' => "customer{$i}@marketplace.test"],
                [
                    'first_name' => 'Customer',
                    'last_name' => "No{$i}",
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['customer']);
            CustomerProfile::firstOrCreate(['user_id' => $user->id], ['gender' => $i % 2 ? 'male' : 'female']);
        }
    }
}
