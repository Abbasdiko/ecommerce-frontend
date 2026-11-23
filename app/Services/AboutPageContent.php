<?php

namespace App\Services;

use App\Models\Setting;

class AboutPageContent
{
    public const DEFAULTS = [
        'eyebrow' => 'A marketplace built around trust',
        'hero_title' => 'A better way to buy, sell and get things done.',
        'hero_body' => 'We bring trusted independent businesses and customers together with one simple promise: clear choices, protected payments and support when it matters.',
        'stats' => [
            ['value' => 'One', 'label' => 'simple marketplace'],
            ['value' => 'Trusted', 'label' => 'independent businesses'],
            ['value' => 'Products + services', 'label' => 'all in one place'],
            ['value' => 'Built for you', 'label' => 'support at every step'],
        ],
        'story_heading' => 'A marketplace designed for real life',
        'story_name' => '',
        'story_role' => 'Our story',
        'story_photo' => '',
        'story_bio' => 'Finding a reliable seller or service provider should not feel like a gamble. We created one marketplace where people can discover independent businesses, compare their offers and complete an order with confidence.',
        'story_quote' => 'Great marketplaces make opportunity and trust part of every transaction.',
        'story_link' => '',
        'values' => [
            ['title' => 'Protection by default', 'description' => 'Clear order details, secure checkout and support that stays with you.'],
            ['title' => 'Independent by design', 'description' => 'Businesses keep their identity and build direct relationships with customers.'],
            ['title' => 'Built for the region', 'description' => 'Local payment choices and an experience shaped around the people we serve.'],
            ['title' => 'Fair on both sides', 'description' => 'A transparent experience for customers and the businesses fulfilling each order.'],
            ['title' => 'Products and services together', 'description' => 'Discover what you need and book trusted services in one place.'],
            ['title' => 'Growth you can measure', 'description' => 'Useful tools help businesses manage listings, orders and their next steps.'],
        ],
        'buyers_title' => 'For buyers',
        'buyer_steps' => [
            'Browse products and services from independent businesses.',
            'Compare options and add what you need to one cart.',
            'Pay securely using an available payment channel.',
            'Follow your order and contact support when you need help.',
        ],
        'agents_title' => 'For agents',
        'agent_steps' => [
            'Register your business and complete verification.',
            'Choose the services you offer and set up your listings.',
            'Manage customer orders from your dashboard.',
            'Track your earnings and request payouts.',
        ],
        'milestones' => [
            ['year' => 'Our beginning', 'title' => 'A simpler marketplace', 'description' => 'We set out to make it easier for customers and independent businesses to find one another.'],
            ['year' => 'Today', 'title' => 'One connected experience', 'description' => 'Products, services, checkout and support come together in a single place.'],
            ['year' => 'What comes next', 'title' => 'Growing with our community', 'description' => 'We continue to improve the tools and protections that help every order go further.'],
        ],
        'team_members' => [],
        'faqs' => [
            ['question' => 'What is this marketplace?', 'answer' => 'It connects customers with independent businesses offering products and services.'],
            ['question' => 'How are payments handled?', 'answer' => 'Available payment methods are shown during checkout. Payment status and order updates are available through your account.'],
            ['question' => 'Who sets listing prices?', 'answer' => 'Each independent business manages the prices and details of its own listings.'],
            ['question' => 'How do I become an agent?', 'answer' => 'Choose Become an Agent, select your services, submit your business details and follow the verification and invoice steps.'],
            ['question' => 'How can I get help with an order?', 'answer' => 'Use the Contact Us page or the support options available in your account, and include your order number.'],
        ],
    ];

    public static function get(): array
    {
        $stored = Setting::value('about.page_content', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return array_replace(self::DEFAULTS, $stored);
    }

    public static function save(array $content): void
    {
        Setting::put('about.page_content', $content, 'about');
    }
}
