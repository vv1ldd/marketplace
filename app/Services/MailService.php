<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public function sendShopMail(Shop $shop, string $view, array $data, string $to, string $subject)
    {
        $mailer = Mail::mailer('smtp'); // Default fallback

        if ($shop->use_custom_smtp && $shop->smtp_host) {
            // Dynamically configure a custom mailer for this shop
            $config = [
                'transport' => 'smtp',
                'host' => $shop->smtp_host,
                'port' => $shop->smtp_port ?? 587,
                'encryption' => $shop->smtp_encryption ?? 'tls',
                'username' => $shop->smtp_user,
                'password' => $shop->smtp_password,
                'timeout' => null,
                'auth_mode' => null,
            ];

            Config::set('mail.mailers.shop_' . $shop->id, $config);
            $mailer = Mail::mailer('shop_' . $shop->id);
        }

        $fromAddress = ($shop->use_custom_smtp && $shop->smtp_from_address) 
            ? $shop->smtp_from_address 
            : config('mail.from.address');
            
        $fromName = ($shop->use_custom_smtp && $shop->smtp_from_name) 
            ? $shop->smtp_from_name 
            : config('mail.from.name');

        $mailer->send($view, $data, function ($message) use ($to, $subject, $fromAddress, $fromName) {
            $message->to($to)
                ->from($fromAddress, $fromName)
                ->subject($subject);
        });
    }
}
