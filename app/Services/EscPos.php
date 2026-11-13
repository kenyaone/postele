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

    /** Native printer QR code (GS ( k), model 2, error level M. */
    public function qr(string $data, int $size = 6): self
    {
        $len = strlen($data) + 3;
        $this->buf .= "\x1d(k\x04\x00\x31\x41\x32\x00" . "\x1d(k\x03\x00\x31\x43" . chr(max(1, min(16, $size))) . "\x1d(k\x03\x00\x31\x45\x31"
            . "\x1d(k" . chr($len % 256) . chr(intdiv($len, 256)) . "\x31\x50\x30" . $data . "\x1d(k\x03\x00\x31\x51\x30" . "\n";
        return $this;
    }

    public function lineIf(?string $s): self { return $s ? $this->line($s) : $this; }

    /** Print the cafe logo as a black-and-white picture (GS v 0), when switched on in Settings. */
    public function logo(int $width = 256): self
    {
        $p = config('pos.logo');
        if (! config('pos.print_logo') || ! $p) return $this;
        $f = public_path(ltrim($p, '/'));
        $src = is_file($f) ? @imagecreatefromstring((string) file_get_contents($f)) : false;
        if (! $src) return $this;
        $w = imagesx($src); $h = imagesy($src);
        $nw = min($width, $w); $nw -= $nw % 8; if ($nw < 8) return $this;
        $nh = max(1, (int) round($h * $nw / $w));
        $img = imagecreatetruecolor($nw, $nh);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $bytes = ''; $wb = intdiv($nw, 8);
        for ($y = 0; $y < $nh; $y++) {
            for ($x = 0; $x < $wb; $x++) {
                $b = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $c = imagecolorat($img, $x * 8 + $bit, $y);
                    if ((($c >> 16 & 255) * 0.3 + ($c >> 8 & 255) * 0.59 + ($c & 255) * 0.11) < 140) $b |= 0x80 >> $bit;
                }
                $bytes .= chr($b);
            }
        }
        $this->buf .= "\x1d\x76\x30\x00" . chr($wb % 256) . chr(intdiv($wb, 256)) . chr($nh % 256) . chr(intdiv($nh, 256)) . $bytes . "\n";
        return $this;
    }
}
