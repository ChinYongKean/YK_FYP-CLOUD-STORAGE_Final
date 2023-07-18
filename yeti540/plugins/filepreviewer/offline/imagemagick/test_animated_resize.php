<?php

if(!class_exists("Imagick"))
{
    die('ERROR: Imagemagick not installed!');
}

$image = 'sample_animated.gif';
$samplePath = dirname( __FILE__ ).'/sample_files/'.$image;

$file_dst = dirname( __FILE__ ).'/../../../../cache/animated.gif';

$width = 1100;
$height = 1100;

$image = new Imagick($samplePath); 

$image = $image->coalesceImages(); 

foreach ($image as $frame) { 
  $frame->thumbnailImage($width, $height); 
  $frame->setImagePage($width, $height, 0, 0); 
} 

$image = $image->deconstructImages(); 
$image->writeImages($file_dst, true);

header("Content-Type: image/gif");
echo file_get_contents($file_dst);