<?php

namespace Tests\Unit;

use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget('settings.all');
        parent::tearDown();
    }

    public function test_vendor_billing_mode_defaults_to_free_listing_with_commission(): void
    {
        Cache::put('settings.all', collect());

        $this->assertSame('commission', PlatformSettings::listingBillingMode());
        $this->assertFalse(PlatformSettings::requiresListingSubscription());
        $this->assertTrue(PlatformSettings::chargesSaleCommission());
    }

    public function test_subscription_and_combined_modes_enable_the_expected_charges(): void
    {
        Cache::put('settings.all', collect(['agents.listing_billing_mode' => 'subscription']));
        $this->assertTrue(PlatformSettings::requiresListingSubscription());
        $this->assertFalse(PlatformSettings::chargesSaleCommission());

        Cache::put('settings.all', collect(['agents.listing_billing_mode' => 'subscription_commission']));
        $this->assertTrue(PlatformSettings::requiresListingSubscription());
        $this->assertTrue(PlatformSettings::chargesSaleCommission());
    }
}
