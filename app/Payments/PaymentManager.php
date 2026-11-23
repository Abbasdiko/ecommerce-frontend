<?php

namespace App\Payments;

use App\Payments\Gateways\BankTransferGateway;
use App\Payments\Gateways\FlutterwaveGateway;
use App\Payments\Gateways\PaystackGateway;
use App\Payments\Gateways\RemitaGateway;
use App\Services\PaymentConfig;
use InvalidArgumentException;

class PaymentManager
{
    public const GATEWAYS = ['paystack', 'flutterwave', 'remita', 'bank_transfer'];

    public function gateway(string $name): PaymentGateway
    {
        return match ($name) {
            'paystack' => new PaystackGateway(),
            'flutterwave' => new FlutterwaveGateway(),
            'remita' => new RemitaGateway(),
            'bank_transfer' => new BankTransferGateway(),
            default => throw new InvalidArgumentException("Unknown payment gateway [{$name}]."),
        };
    }

    public const CATALOGUE = [
        'paystack' => ['label' => 'Paystack', 'type' => 'redirect'],
        'flutterwave' => ['label' => 'Flutterwave', 'type' => 'redirect'],
        'remita' => ['label' => 'Remita', 'type' => 'redirect'],
        'bank_transfer' => ['label' => 'Bank Transfer', 'type' => 'offline'],
        'pay_on_delivery' => ['label' => 'Pay on Delivery', 'type' => 'offline'],
    ];

    /** Only the methods an admin has enabled in Payment Configuration. */
    public function options(): array
    {
        $out = [];
        foreach (PaymentConfig::checkoutMethods() as $id) {
            $meta = self::CATALOGUE[$id] ?? ['label' => ucfirst($id), 'type' => 'redirect'];
            $out[] = ['id' => $id, 'label' => $meta['label'], 'type' => $meta['type']];
        }

        return $out;
    }
}
