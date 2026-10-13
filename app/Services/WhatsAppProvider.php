<?php

namespace App\Services;

interface WhatsAppProvider
{
    public function sendOptedIn(string $phone, string $template, array $parameters): string;
}
