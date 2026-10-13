<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStorage
{
    public function store($file, string $folder): string
    {
        $raw = file_get_contents($file->getRealPath());
        $im = @imagecreatefromstring($raw);
        if (! $im) {
            throw ValidationException::withMessages(['images' => 'Image could not be processed.']);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1, 1600 / max($w, $h));
        $out = imagecreatetruecolor((int) max(1, $w * $scale), (int) max(1, $h * $scale));
        imagefill($out, 0, 0, imagecolorallocate($out, 245, 245, 245));
        imagecopyresampled($out, $im, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagejpeg($out, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($im);
        imagedestroy($out);
        $path = $folder.'/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path,$bytes);

        return $path;
    }
}
