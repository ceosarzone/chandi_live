<?php

namespace App\Services\Market;

interface Provider
{
    public function latest(): array;

    public function historical(string $from, string $to): array;
}
