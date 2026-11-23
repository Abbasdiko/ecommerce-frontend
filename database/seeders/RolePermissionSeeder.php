<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // users & roles
            'user.view', 'user.create', 'user.update', 'user.delete',
            'role.view', 'role.assign',
            // agents
            'agent.view', 'agent.create', 'agent.update', 'agent.delete', 'agent.approve', 'agent.reject', 'agent.suspend',
            // customers
            'customer.view', 'customer.update', 'customer.delete',
            // services & packages
            'service.view', 'service.create', 'service.update', 'service.delete',
            'package.view', 'package.create', 'package.update', 'package.delete',
            // subscriptions
            'subscription.view', 'subscription.create', 'subscription.activate', 'subscription.cancel',
            // categories
            'category.view', 'category.create', 'category.update', 'category.delete',
            // listings
            'listing.view', 'listing.create', 'listing.update', 'listing.delete', 'listing.approve', 'listing.reject',
            // orders
            'order.view', 'order.update',
            'cancellation.review', 'refund.view', 'refund.approve', 'refund.reject',
            // reviews
            'review.view', 'review.moderate',
            // coupons
            'coupon.view', 'coupon.create', 'coupon.update', 'coupon.delete',
            // disputes & support
            'dispute.view', 'dispute.manage',
            'ticket.view', 'ticket.manage',
            // content
            'blog.manage', 'newsletter.view', 'contact.view',
            // payments / wallet / withdrawals / commission (finance, later milestones)
            'payment.view', 'payment.verify',
            'wallet.view', 'withdrawal.view', 'withdrawal.approve', 'withdrawal.reject',
            'commission.view', 'commission.update',
            'settlement.view', 'settlement.approve', 'settlement.reject',
            // platform
            'report.view', 'audit.view', 'setting.view', 'setting.update', 'dashboard.view',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'super-admin' => $permissions, // full access (also bypassed via Gate::before)
            'admin' => [
                'user.view',
                'agent.view', 'agent.create', 'agent.update', 'agent.delete', 'agent.approve', 'agent.reject', 'agent.suspend',
                'customer.view', 'customer.update', 'customer.delete',
                'service.view', 'service.create', 'service.update', 'service.delete',
                'package.view', 'package.create', 'package.update', 'package.delete',
                'subscription.view', 'subscription.activate', 'subscription.cancel',
                'category.view', 'category.create', 'category.update', 'category.delete',
                'listing.create',
                'listing.view', 'listing.update', 'listing.delete', 'listing.approve', 'listing.reject',
                'order.view', 'order.update', 'cancellation.review', 'refund.view', 'refund.approve', 'refund.reject',
                'review.view', 'review.moderate', 'coupon.view', 'coupon.create', 'coupon.update', 'coupon.delete',
                'commission.view', 'commission.update',
                'dispute.view', 'dispute.manage', 'ticket.view', 'ticket.manage',
                'blog.manage', 'newsletter.view', 'contact.view',
                'payment.view', 'payment.verify', 'wallet.view',
                'withdrawal.view', 'withdrawal.approve', 'withdrawal.reject',
                'settlement.view', 'settlement.approve', 'settlement.reject',
                'report.view', 'audit.view', 'setting.view', 'setting.update', 'dashboard.view',
            ],
            'finance' => [
                'agent.view', 'customer.view',
                'payment.view', 'payment.verify',
                'wallet.view', 'withdrawal.view', 'withdrawal.approve', 'withdrawal.reject',
                'commission.view', 'commission.update',
                'settlement.view', 'settlement.approve', 'settlement.reject',
                'refund.view', 'refund.approve', 'refund.reject',
                'subscription.view', 'order.view', 'report.view', 'audit.view', 'dashboard.view',
            ],
            'support' => [
                'agent.view', 'customer.view', 'order.view', 'payment.view',
                'cancellation.review', 'refund.view', 'review.view',
                'dispute.view', 'dispute.manage', 'ticket.view', 'ticket.manage',
                'contact.view',
                'listing.view', 'subscription.view', 'dashboard.view',
            ],
            'agent' => [
                // Reference data for dropdowns only. NOTE: no `listing.view` /
                // `subscription.view` — those are staff-wide "see everyone" grants;
                // an agent's access to their own is enforced by role + ownership.
                'dashboard.view',
                'service.view', 'package.view', 'category.view',
                'subscription.create',
                'listing.create', 'listing.update', 'listing.delete',
                'coupon.create', 'coupon.update', 'coupon.delete',
            ],
            'customer' => [
                'dashboard.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
