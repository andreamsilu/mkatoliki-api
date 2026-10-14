<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MemberSmsSender
{
    public function isPreview(): bool
    {
        return config('member_auth.sms.driver') === 'preview' && ! app()->isProduction();
    }

    public function ensureConfigured(): void
    {
        if ($this->isPreview()) {
            return;
        }

        $url = (string) config('member_auth.sms.url');
        if (config('member_auth.sms.driver') !== 'webhook'
            || ! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ! config('member_auth.sms.token')) {
            throw new HttpException(503, 'Phone login is not available yet. Please contact your parish office.');
        }
    }

    public function send(string $phone, string $code): void
    {
        $this->ensureConfigured();
        if ($this->isPreview()) {
            return;
        }

        try {
            $response = Http::withToken((string) config('member_auth.sms.token'))
                ->acceptJson()
                ->withoutRedirecting()
                ->connectTimeout(3)
                ->timeout(5)
                ->post((string) config('member_auth.sms.url'), [
                    'to' => $phone,
                    'sender' => config('member_auth.sms.sender'),
                    'message' => "Your Mkatolik login code is $code. It expires in 5 minutes. Do not share this code.",
                ]);
            if (! $response->successful()) {
                throw new HttpException(503);
            }
        } catch (Throwable) {
            throw new HttpException(503, 'Unable to send a verification code. Please try again later.');
        }
    }
}
