#!/usr/bin/env php

<?php

/**
 * Fix date metadata using filename as source of truth.
 * Require `exiftool` to be available in your PATH
 */

$images = scandir('./images');
foreach ($images as $filename){
    $date = substr($filename, 0, 10);
    $date = str_replace('-', ':', $date);
    $datetimeString = sprintf('%s 12:00:00.00+01:00', $date);
    $command = sprintf(
        'exiftool ./images/%s -overwrite_original_in_place -SubSecCreateDate="%s" -SubSecModifyDate="%s" -SubSecDateTimeOriginal="%s"'
        , $filename
        , $datetimeString
        , $datetimeString
        , $datetimeString
    );
    $result = exec($command);
    print_r($result.PHP_EOL);
}
