<?php

namespace App\Services\Wealth;

class ScreenshotTextParser
{
    // [£€] because OCR sometimes misreads the £ symbol as € on large/stylised digits.
    // The /u (UTF-8) modifier is required -- without it, PCRE splits each
    // multi-byte character in the class into individual bytes, which can
    // match half of the £ symbol's own UTF-8 encoding and corrupt the result.
    private const CURRENCY_PATTERN   = '/[£€]\s?[\d,]+\.\d{2}/u';
    private const CURRENCY_K_PATTERN = '/[£€]\s?[\d,]+(?:\.\d+)?k/iu';

    /**
     * Returns a [$sourceId => $amount] map of updates to apply, extracting
     * amounts by locating known label text rather than relying on fixed
     * line numbers (which break whenever the OCR output shifts by a line).
     */
    public function parse(string $text, ?string $app): array
    {
        $lines = explode("\n", $text);

        return match ($app) {
            'Trading 212' => $this->tradingTwoOneTwo($lines, $text),
            // "Cash ISA" is followed by "Easy access" then the balance; the
            // headline "My savings" figure is truncated (no pence) by OCR.
            'Tembo'       => $this->amountFor(12, $lines, 'Cash ISA'),
            'MyAviva'     => $this->amountFor(9, $lines, 'YOUR WEALTH PORTFOLIO'),
            // "SAVINGS" is the section header above the Regular Saver
            // account -- the app also shows Joint/Personal current accounts,
            // which we deliberately don't want to pick up here.
            'NatWest'     => $this->amountFor(1, $lines, 'SAVINGS'),
            'Chrome'      => $this->retiready($lines, $text),
            'HL'          => $this->firstAmount(7, $text),
            default       => $this->courtiers($text),
        };
    }

    private function tradingTwoOneTwo(array $lines, string $text): array
    {
        // "Invest" and "Cash ISA" are the only two Trading 212 accounts, and
        // each screen uses a different value label ("ACCOUNT VALUE" vs
        // "Total value"), observed from real screenshots of both.
        $sourceId = stripos($text, 'isa') !== false ? 4 : 3;
        $amount   = $this->amountAfterLabel($lines, 'ACCOUNT VALUE')
            ?? $this->amountAfterLabel($lines, 'Total value');

        return $amount !== null ? [$sourceId => $amount] : [];
    }

    private function retiready(array $lines, string $text): array
    {
        if (!str_contains($text, 'retiready.co.uk')) {
            return [];
        }

        $updates = [];

        // source 5 = "Aegon Pension (Windsor)", source 6 = "Aegon ISA" --
        // must read the individual "Aegon SIPP Pension" / "Retiready ISA"
        // figures, not "Total savings" (the combined total of both, which
        // isn't a real source and was the original bug being reported).
        if (($amount = $this->amountAfterLabel($lines, 'Aegon SIPP Pension')) !== null) {
            $updates[5] = $amount;
        }

        if (($amount = $this->amountAfterLabel($lines, 'Retiready ISA')) !== null) {
            $updates[6] = $amount;
        }

        return $updates;
    }

    private function courtiers(string $text): array
    {
        if (!str_contains($text, 'COURTIERS')) {
            return [];
        }

        if (!preg_match(self::CURRENCY_K_PATTERN, $text, $match)) {
            return [];
        }

        // "£15.06k" means £15,060 -- x1000, not the original code's x100
        // (which would store 1506, an implausible ~90% drop from the
        // existing balance of ~13300).
        $amount = (float) str_replace(['£', '€', 'k', ' '], '', strtolower($match[0])) * 1000;

        return $amount ? [8 => $amount] : [];
    }

    private function amountFor(int $sourceId, array $lines, string $label): array
    {
        $amount = $this->amountAfterLabel($lines, $label);

        return $amount !== null ? [$sourceId => $amount] : [];
    }

    private function firstAmount(int $sourceId, string $text): array
    {
        if (preg_match(self::CURRENCY_PATTERN, $text, $match)) {
            return [$sourceId => $this->parseAmount($match[0])];
        }

        return [];
    }

    /**
     * Finds a line containing $label, then returns the amount on that same
     * line (e.g. "Balance: £6,000.00") or one of the couple of lines after
     * it (e.g. "ACCOUNT VALUE" / "£19,657.94" on separate lines), to
     * tolerate both label styles and stray blank/noise lines from OCR.
     */
    private function amountAfterLabel(array $lines, string $label): ?float
    {
        foreach ($lines as $i => $line) {
            if (!str_contains($line, $label)) {
                continue;
            }

            for ($j = $i; $j <= $i + 3 && $j < count($lines); $j++) {
                if (preg_match(self::CURRENCY_PATTERN, $lines[$j], $match)) {
                    return $this->parseAmount($match[0]);
                }
            }
        }

        return null;
    }

    private function parseAmount(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $clean = str_replace(['£', '€', ',', ' '], '', $raw);

        return is_numeric($clean) ? (float) $clean : null;
    }
}
