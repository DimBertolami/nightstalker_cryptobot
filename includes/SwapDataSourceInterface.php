<?php

namespace NS\DataSources;

interface SwapDataSourceInterface {
    public function testConnection(): bool;
    public function getSwapQuote(string $inputToken, string $outputToken, float $amount): array;
    public function executeSwap(array $quote): array;
}
