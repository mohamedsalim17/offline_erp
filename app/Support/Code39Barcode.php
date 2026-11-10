<?php

namespace App\Support;

use InvalidArgumentException;

class Code39Barcode
{
    private const PATTERNS = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
    ];

    public function svg(string $value): string
    {
        if ($value === '' || ! ctype_digit($value)) {
            throw new InvalidArgumentException('Code 39 barcode values must contain digits only.');
        }

        $narrow = 2;
        $wide = 6;
        $quietZone = 10 * $narrow;
        $height = 48;
        $x = $quietZone;
        $bars = '';

        foreach (str_split('*'.$value.'*') as $character) {
            $pattern = self::PATTERNS[$character] ?? 'nwnnwnwnn';

            foreach (str_split($pattern) as $index => $width) {
                $elementWidth = $width === 'w' ? $wide : $narrow;

                if ($index % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$elementWidth.'" height="'.$height.'" fill="#000"/>';
                }

                $x += $elementWidth;
            }

            $x += $narrow;
        }

        $width = $x + $quietZone;

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="باركود رقم الفاتورة" viewBox="0 0 '.$width.' '.$height.'" width="'.$width.'" height="'.$height.'">'.$bars.'</svg>';
    }
}
