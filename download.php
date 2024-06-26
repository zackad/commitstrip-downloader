#!/usr/bin/env php
<?php

/* -- CONFIGURATION -----------------------------------*/

// MUST have a trailing slash! - also will try to create the dir if not existing (recursive, can be relative)
$path_for_images = './images/';

// set your timezone
$my_timezone = 'Europe/Berlin'; # https://php.net/timezones

//Set the language to download comics
$language = 'en'; //Can only be «fr» or «en»

/**
 * everything below is the script. you can edit it of cause ...
 * ... but maybe you will break it. Or improve it! - make a pull request! :)
 */

/* -- some settings for running in cli mode. -----------------------------------*/
set_time_limit(0);
date_default_timezone_set($my_timezone);
$script_start = microtime(true);

// DOMDocument throws alot of Notices and Warning because it don't knows HTML5 really good...
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

/* -- functions start. -----------------------------------*/
function curl_url_get_contents($url)
{
    $ch = curl_init();
    $options = array(
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL            => $url,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => true,
    );
    curl_setopt_array($ch, $options);
    $html = curl_exec($ch);

    if ($html === false) {
        echo 'ERROR: '.curl_error($ch). PHP_EOL;
    }

    curl_close($ch);
    return $html;
}

function get_item_count()
{
    global $language;
    $dom = new DOMDocument();
    // load html page
    $dom->loadHTML(curl_url_get_contents("https://www.commitstrip.com/".$language."/"));
    $dom->preserveWhiteSpace = false;

    // get href of anchor that has the class "last"
    $finder    = new DomXPath($dom);
    $classname = "last";
    $nodes     = $finder->query("//a[contains(@class, '$classname')]");

    if (!empty($nodes)) {
        // get the last page number
        $lastpage = basename(rtrim($nodes->item(0)->getAttribute('href'), '/'));

        // download last page
        $domx = new DOMDocument();
        $domx->loadHTML(curl_url_get_contents($nodes->item(0)->getAttribute('href')));
        $domx->preserveWhiteSpace = false;

        // find the count of comics on this page
        $finder    = new DomXPath($domx);
        $nodes     = $finder->query("//section");

        // 20 comics per page + the count of comic on last page
        $items = (20 * $lastpage) + $nodes->length;
        return $items;

    }
    return false;

}

function get_next_post_url($url)
{
    $dom = new DOMDocument();
    // load html page
    $dom->loadHTML(curl_url_get_contents($url));
    $dom->preserveWhiteSpace = false;

    // get href of anchor that has the class "last"
    $finder    = new DomXPath($dom);
    $classname = "nav-next";
    $nodes     = $finder->query("//*[contains(@class, '$classname')]/a");

    if (!empty($nodes) && !is_null($nodes->item(0))) {
        return $nodes->item(0)->getAttribute('href');
    }
    return false;
}
/* -- functions end. -----------------------------------*/

// start the script!
// check there is an dir to save to
if (!is_dir($path_for_images)) {
    $dir = mkdir($path_for_images, 0777, true);
    if ($dir === false) {
        exit('Error: You need to set the permission correctly!.');
    }
}

//Check language
if($language != 'fr' && $language != 'en') {
    exit('Error: language can only be FR or EN');
}

$url       = "";
$i         = 0;
$last_item = get_item_count();
// loop!
for (;;) {

    $url = (empty($url)) ? "https://www.commitstrip.com/".$language."/2012/02/22/interview/" : get_next_post_url($url);
    if (empty($url)) {
        break;
    }

    // download website to string
    $html = curl_url_get_contents($url);

    $doc = new DOMDocument();

    $doc->loadHTML($html);
    $doc->preserveWhiteSpace = false;

    // get all image-elements
    $images = $doc->getElementsByTagName('img');

    // get all urls of all images
    $urls = [];
    foreach ($images as $key => $image) {
        $urls[] = $image->getAttribute('src');
    }

    $the_posted_image = false;

    // filter the main url...
    foreach ($urls as $key => $value) {
        if (preg_match('/\/uploads\/.+\.jpg$/', $value)) {
            $the_posted_image = $value; // thats the image i've searched for .... i hope
        }
    }

    if ($the_posted_image === false) {
        echo "($i/$last_item) No image found..." . PHP_EOL;
        continue;
    }
    $i++;

    // now create a good filename...
    $url_parts = explode('/', $the_posted_image);
    $url_parts = array_reverse($url_parts); // .. done!
    $parsedUrl = parse_url($url, PHP_URL_PATH);
    preg_match('/\d{4}\/\d{2}\/\d{2}/', $parsedUrl, $matches);
    // posted date
    $prefix = str_replace('/', '-', $matches[0]);
    $filename = "$prefix-$url_parts[0]";

    // check file exists already
    if (file_exists($path_for_images.$filename)) {
        echo "($i/$last_item) File skipped: $the_posted_image" . PHP_EOL;
        continue;
    }

    // download it...
    $res = file_put_contents($path_for_images.$filename, curl_url_get_contents($the_posted_image));

    // I hope it does not fail - Why should it fail at all?!
    if ($res !== false) {
        echo "($i/$last_item) Downloaded: $the_posted_image" . PHP_EOL;
    } else {
        echo "($i/$last_item) Failed downloading: $the_posted_image" . PHP_EOL;
    }
}

/* -- script end info -----------------------------------*/
$script_duration = microtime(true) - $script_start;
$script_duration = number_format($script_duration, 2, ',', '.');
echo PHP_EOL;
echo 'Memory Usage: ' . memory_get_usage(true) / 1024  . ' kB' . PHP_EOL;
echo 'Script Duration: ' . $script_duration . ' second(s)' . PHP_EOL;
