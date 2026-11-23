<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::role('admin')->first();

        $posts = [
            [
                'title' => 'How escrow protection works on MegaMarket',
                'category' => 'Buying Guides',
                'excerpt' => 'Your money is held safely until you confirm delivery. Here is the full flow, step by step.',
                'body' => "When you pay for an order on MegaMarket, the funds do not go straight to the agent.\n\nInstead they are held in escrow by the platform. The agent is notified and ships your items. Once the courier marks the order delivered and the return window passes, the funds are released to the agent's wallet.\n\nIf something goes wrong you can open a dispute or request a refund before the funds are released. This protects both sides: buyers are not left out of pocket, and honest agents always get paid.",
            ],
            [
                'title' => '5 things to check before buying electronics online',
                'category' => 'Buying Guides',
                'excerpt' => 'Warranty, seller rating, return policy and more — a quick checklist for phones, laptops and TVs.',
                'body' => "Buying electronics online in Nigeria is convenient, but a little diligence goes a long way.\n\nFirst, check the agent's rating and review history. A store with hundreds of positive reviews is a safer bet.\n\nSecond, read the return policy. MegaMarket offers a 7-day return window on most products.\n\nThird, confirm the warranty. Fourth, compare the price against the compare-at price to see the real discount. Fifth, message the agent a question before you buy — response speed tells you a lot.",
            ],
            [
                'title' => 'Meet the agents: Lagos Fashion House',
                'category' => 'Vendor Stories',
                'excerpt' => 'From a market stall to thousands of orders a month — how one Lagos tailor scaled on the marketplace.',
                'body' => "Lagos Fashion House started as a single stall in Balogun Market.\n\nAfter joining MegaMarket and completing KYC verification, the team listed their ready-to-wear Ankara pieces and started receiving orders from across the country.\n\n\"The wallet and settlement system means we always know exactly what we have earned,\" says the founder. Today they run a Premium subscription with over a hundred active listings.",
            ],
            [
                'title' => 'Marketplace update: faster payouts for verified agents',
                'category' => 'Marketplace News',
                'excerpt' => 'We have shortened the fund-hold window and added same-day settlement for top-rated stores.',
                'body' => "We have been listening to agent feedback about cash flow.\n\nStarting this month, the hold window after delivery is shorter, and agents with a strong track record are eligible for expedited settlement.\n\nYou can track every naira in your wallet: pending earnings, available balance and withdrawn totals, each backed by an immutable transaction ledger.",
            ],
            [
                'title' => 'A beginner’s guide to booking services on MegaMarket',
                'category' => 'How-To',
                'excerpt' => 'Home repairs, cleaning, photography and more — booking a service works just like buying a product.',
                'body' => "MegaMarket is not only for products. You can book vetted service providers too.\n\nBrowse the Services section, pick a provider, and add the service to your cart just like a product. Payment is held in escrow until the job is done.\n\nAfter the service is completed you can leave a review, and the provider can respond — building trust for the next customer.",
            ],
        ];

        foreach ($posts as $i => $p) {
            BlogPost::firstOrCreate(
                ['title' => $p['title']],
                array_merge($p, [
                    'author' => 'MegaMarket Editorial',
                    'author_id' => $author?->id,
                    'status' => 'published',
                    'published_at' => now()->subDays(($i + 1) * 5),
                    'cover_image' => "https://picsum.photos/seed/mmblog{$i}/1200/700",
                ])
            );
        }

        // ---- newsletter subscribers ----
        foreach (range(1, 40) as $n) {
            NewsletterSubscriber::firstOrCreate(
                ['email' => "subscriber{$n}@example.com"],
                [
                    'name' => $n % 3 ? "Subscriber {$n}" : null,
                    'source' => ['storefront', 'footer', 'checkout'][$n % 3],
                    'status' => $n % 11 === 0 ? 'unsubscribed' : 'subscribed',
                    'confirmed_at' => now()->subDays(random_int(1, 90)),
                    'created_at' => now()->subDays(random_int(1, 90)),
                ]
            );
        }
        foreach (User::role('customer')->get() as $c) {
            NewsletterSubscriber::firstOrCreate(
                ['email' => strtolower($c->email)],
                ['name' => $c->full_name, 'source' => 'checkout', 'status' => 'subscribed', 'confirmed_at' => now()],
            );
        }

        // ---- contact messages ----
        $contacts = [
            ['Chidi Okafor', 'chidi@example.com', 'Delivery delay on ORD-XXXX', 'My order has been in transit for 6 days, can someone check?', 'new'],
            ['Aisha Bello', 'aisha@example.com', 'Becoming an agent', 'I run a skincare business and want to sell on the platform. What are the fees?', 'read'],
            ['Tunde Adé', 'tunde@example.com', 'Refund not received', 'A refund was approved 3 days ago but my wallet still shows nothing.', 'new'],
            ['Grace Effiong', 'grace@example.com', 'Partnership enquiry', 'We are a logistics company interested in a delivery partnership.', 'replied'],
            ['Musa Ibrahim', 'musa@example.com', 'App feedback', 'The search filters are great but I would love a "near me" option.', 'closed'],
        ];
        foreach ($contacts as [$name, $email, $subject, $message, $status]) {
            ContactMessage::firstOrCreate(
                ['email' => $email, 'subject' => $subject],
                [
                    'name' => $name,
                    'message' => $message,
                    'status' => $status,
                    'handled_by' => in_array($status, ['replied', 'closed']) ? $author?->id : null,
                    'handled_at' => in_array($status, ['replied', 'closed']) ? now()->subDay() : null,
                    'created_at' => now()->subDays(random_int(0, 14)),
                ]
            );
        }
    }
}
