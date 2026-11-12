<?php

namespace App\Services;

class EscPos
{
    private string $buf = '';

    public function init(): self { $this->buf .= "\x1b\x40"; return $this; }

    public function align(string $where): self
    {
        $map = ['left' => "\x00", 'center' => "\x01", 'right' => "\x02"];
        $this->buf .= "\x1b\x61" . ($map[$where] ?? "\x00");
        return $this;
    }

    public function bold(bool $on): self
    {
        $this->buf .= "\x1b\x45" . ($on ? "\x01" : "\x00");
        return $this;
    }

    public function size(int $w, int $h): self
    {
        $w = max(1, min(8, $w));
        $h = max(1, min(8, $h));
        $this->buf .= "\x1d\x21" . chr((($w - 1) << 4) | ($h - 1));
        return $this;
    }

    public function text(string $s): self { $this->buf .= self::ascii($s); return $this; }
    public function line(string $s = ''): self { $this->buf .= self::ascii($s) . "\n"; return $this; }

    /** Thermal printers only print plain characters: turn accents and symbols into plain ones (Cafe, -, ...). */
    public static function ascii(string $s): string
    {
        $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'É' => 'E', 'á' => 'a', 'à' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ç' => 'c', '·' => '-', '—' => '-', '–' => '-', '‘' => "'", '’' => "'", '“' => '"', '”' => '"', '…' => '...', '×' => 'x', '•' => '*', '✓' => 'OK']);
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/u', '', $s) ?? $s;
    }
    public function feed(int $n = 1): self { $this->buf .= str_repeat("\n", max(1, $n)); return $this; }
    public function cut(): self { $this->buf .= "\x1d\x56\x00"; return $this; }

    public function get(): string { return $this->buf; }
}
