<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Imagick;
use Symfony\Component\Process\Process;

class BatchService extends Service
{
    public static function makeThumbnailBatch(array $imagePaths, array $sizes): void
    {
        foreach ($imagePaths as $imagePath) {
            $imageFiles = Storage::files($imagePath);

            foreach ($imageFiles as $imageFile) {
                $file = sprintf('/%s/%s', 'data2', $imageFile);

                $isImage = @getimagesize($file);
                $isTime = @filemtime($file) > (time() - (60 * 6));
                if ($isImage !== false && $isTime !== false) {
                    $imagick = new Imagick($file);
                    $geometry = $imagick->getImageGeometry();
                    $width = $geometry['width'];
                    $height = $geometry['height'];

                    foreach ($sizes as $size) {
                        $path = str_replace(basename($file), sprintf('thumb_%s/%s', $size, basename($file)), $file);
                        if (is_dir(dirname(str_replace('/data2/', '', $path))) === false) {
                            Storage::makeDirectory(dirname(str_replace('/data2/', '', $path)));
                            @chmod(dirname($path), 0777);
                            @chown(dirname($path), 'nobody');
                            @chgrp(dirname($path), 'nobody');
                        }

                        if ($size > $width) {
                            copy($file, $path);
                        } else {
                            $imgSize = [$size, (int)floor(($height * $size) / $width)];

                            (new Process(['convert', $file, '-quality', '100', '-resize', sprintf('%sx%s', $imgSize[0], $imgSize[1]), $path]))->run();
                        }
                    }
                    echo $path . PHP_EOL;
                    $imagick->destroy();
                }
            }
        }
    }
}
