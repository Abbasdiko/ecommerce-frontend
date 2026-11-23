<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentSubscription;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StorefrontSeeder extends Seeder
{
    private const CATALOG = [
        'Electronics Vendor' => [
            ['Samsung Galaxy A15 128GB', 'Samsung', 185000, 165000],
            ['Oraimo FreePods 4 Earbuds', 'Oraimo', 22000, 17500],
            ['HP Pavilion 15 Core i5 Laptop', 'HP', 720000, null],
            ['Anker 20W USB-C Charger', 'Anker', 14000, null],
            ['Hisense 43" Smart TV', 'Hisense', 265000, 239000],
            ['Sony WH-CH520 Headphones', 'Sony', 58000, null],
            ['JBL Go 4 Bluetooth Speaker', 'JBL', 32000, 27000],
            ['Redmi Note 13 256GB', 'Xiaomi', 245000, null],
        ],
        'Fashion Vendor' => [
            ['Men\'s Oxford Cotton Shirt', 'Ankara Co', 12500, 9500],
            ['Ladies Ankara Maxi Dress', 'Lagos Threads', 18000, null],
            ['Leather Chelsea Boots', 'Kano Leather', 42000, 36000],
            ['Unisex Canvas Sneakers', 'StreetLagos', 16000, null],
            ['Kids 2-Piece Denim Set', 'MiniStyle', 9000, 7200],
            ['Adire Silk Head Wrap', 'Lagos Threads', 6500, null],
            ['Wax Print Tote Bag', 'Ankara Co', 11000, null],
        ],
        'Food Vendor' => [
            ['Jollof Rice Party Pack (10)', 'Mama\'s Pot', 25000, null],
            ['Premium Ofada Rice 5kg', 'Farm Fresh NG', 12000, 10500],
            ['Cold-Pressed Groundnut Oil 2L', 'Farm Fresh NG', 8500, null],
            ['Chin Chin Family Jar 1kg', 'SweetTooth', 4500, 3800],
            ['Zobo Drink 1.5L (Pack of 6)', 'Naija Sips', 6000, null],
        ],
        'Professional Services' => [
            ['Home Deep Cleaning (3 Bedroom)', '', 35000, null],
            ['AC Installation & Servicing', '', 20000, 17000],
            ['Event Photography (Half Day)', '', 120000, null],
            ['Generator Repair Call-out', '', 15000, null],
            ['Interior Painting per Room', '', 28000, 25000],
            ['Plumbing Emergency Visit', '', 12000, null],
        ],
    ];

    public function run(): void
    {
        // ---- department / service presentation ----
        $icons = [
            'electronics' => 'Smartphone', 'fashion' => 'Shirt', 'home' => 'Sofa',
            'food' => 'UtensilsCrossed', 'beauty' => 'Sparkles',
        ];
        foreach (Category::whereNull('parent_id')->get() as $cat) {
            $key = collect($icons)->keys()->first(fn ($k) => Str::contains(Str::lower($cat->name), $k));
            $cat->update(['icon' => $icons[$key] ?? 'Package']);
        }
        foreach (Service::all() as $service) {
            $service->update([
                'icon' => match (true) {
                    Str::contains($service->name, 'Electronics') => 'Smartphone',
                    Str::contains($service->name, 'Fashion') => 'Shirt',
                    Str::contains($service->name, 'Food') => 'UtensilsCrossed',
                    default => 'Wrench',
                },
                'tagline' => $service->description,
            ]);
        }
        foreach (Agent::all() as $agent) {
            $agent->update([
                'about' => "{$agent->business_name} is a verified vendor on the marketplace serving customers across Nigeria with fast delivery and responsive support.",
                'followers' => random_int(40, 4200),
            ]);
        }

        // ---- catalog ----
        foreach (self::CATALOG as $serviceName => $products) {
            $service = Service::where('name', $serviceName)->first();
            if (! $service) {
                continue;
            }
            $agent = Agent::where('status', 'active')
                ->whereHas('subscriptions', fn ($q) => $q->where('service_id', $service->id)->where('status', 'active'))
                ->first();
            if (! $agent) {
                continue;
            }
            $subscription = AgentSubscription::where('agent_id', $agent->id)->where('service_id', $service->id)->where('status', 'active')->first();
            $category = Category::where('service_id', $service->id)->whereNotNull('parent_id')->inRandomOrder()->first();
            $isService = $serviceName === 'Professional Services';

            foreach ($products as $i => [$name, $brand, $price, $sale]) {
                Listing::firstOrCreate(
                    ['agent_id' => $agent->id, 'name' => $name],
                    [
                        'service_id' => $service->id,
                        'category_id' => $category?->id,
                        'subscription_id' => $subscription?->id,
                        'description' => "$name — sold by {$agent->business_name}. Genuine, quality-checked and backed by marketplace buyer protection.",
                        'sku' => 'SKU-' . strtoupper(Str::random(8)),
                        'brand' => $brand ?: null,
                        'price' => $price,
                        'sale_price' => $sale,
                        'deal_ends_at' => $sale ? now()->addDays(random_int(2, 20)) : null,
                        'quantity' => $isService ? 999 : random_int(5, 120),
                        'sales_count' => random_int(0, 400),
                        'type' => $isService ? 'service' : 'product',
                        'featured' => $i < 2,
                        'status' => 'active',
                        'approval_status' => 'approved',
                        'published_at' => now()->subDays(random_int(0, 40)),
                    ]
                );
            }
        }

        // Publish the older seeded demo listings too so the catalog isn't sparse.
        Listing::where('approval_status', 'approved')->whereNull('published_at')
            ->update(['published_at' => now()->subDays(10), 'status' => 'active']);
    }
}
