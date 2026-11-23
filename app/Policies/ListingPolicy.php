<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('listing.view') || $user->hasRole('agent');
    }

    public function view(User $user, Listing $listing): bool
    {
        if ($user->can('listing.view')) {
            return true;
        }

        return $this->ownsListing($user, $listing);
    }

    public function create(User $user): bool
    {
        return $user->can('listing.create');
    }

    public function update(User $user, Listing $listing): bool
    {
        if ($user->can('listing.update') && ! $user->hasRole('agent')) {
            return true;
        }

        return $user->can('listing.update') && $this->ownsListing($user, $listing);
    }

    public function delete(User $user, Listing $listing): bool
    {
        if ($user->can('listing.delete') && ! $user->hasRole('agent')) {
            return true;
        }

        return $user->can('listing.delete') && $this->ownsListing($user, $listing);
    }

    public function approve(User $user, Listing $listing): bool
    {
        return $user->can('listing.approve');
    }

    public function reject(User $user, Listing $listing): bool
    {
        return $user->can('listing.reject');
    }

    protected function ownsListing(User $user, Listing $listing): bool
    {
        return $user->agent && $user->agent->id === $listing->agent_id;
    }
}
