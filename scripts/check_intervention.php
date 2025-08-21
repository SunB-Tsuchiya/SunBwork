<?php
// scripts/check_intervention.php
// Usage: php scripts/check_intervention.php [path-to-image]
// Prints JSON with checks about Intervention Image availability and a small make/encode test.

require __DIR__ . '/../vendor/autoload.php';

$out = [];
$out['timestamp'] = date('c');
$out['class_ImageManager_exists'] = class_exists('Intervention\\Image\\ImageManager');
$out['class_ImageManagerStatic_exists'] = class_exists('Intervention\\Image\\ImageManagerStatic');
$out['class_Image_exists'] = class_exists('Intervention\\Image\\Image');
$out['ext_gd'] = extension_loaded('gd');
$out['ext_imagick'] = extension_loaded('imagick');
$out['php_version'] = PHP_VERSION;

$driver = null;
if (extension_loaded('imagick')) {
    $driver = 'imagick';
} elseif (extension_loaded('gd')) {
    $driver = 'gd';
}
$out['selected_driver'] = $driver;

if ($out['class_ImageManager_exists'] && $driver) {
    try {
        if (extension_loaded('imagick') && class_exists(\Intervention\Image\Drivers\Imagick\Driver::class)) {
            $manager = \Intervention\Image\ImageManager::imagick();
        } else {
            $manager = \Intervention\Image\ImageManager::gd();
        }
        $out['manager_construct_ok'] = true;

        if (isset($argv[1]) && $argv[1]) {
            $testPath = $argv[1];
            $out['test_path'] = $testPath;
            if (file_exists($testPath)) {
                try {
                    $img = $manager->read($testPath);
                    $out['image_width'] = $img->width();
                    $out['image_height'] = $img->height();
                    $encoder = new \Intervention\Image\Encoders\JpegEncoder(75);
                    $encoded = $img->encode($encoder);
                    $bin = (string) $encoded->toDataUri() ? base64_decode(preg_replace('#^data:.*?;base64,#', '', $encoded->toDataUri())) : (string) $encoded;
                    $out['encoded_length'] = strlen($bin);
                } catch (Throwable $e) {
                    $out['make_error'] = $e->getMessage();
                }
            } else {
                $out['test_error'] = 'test file not found';
            }
        }
    } catch (Throwable $e) {
        $out['manager_construct_error'] = $e->getMessage();
    }
} else {
    $out['manager_construct_ok'] = false;
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
