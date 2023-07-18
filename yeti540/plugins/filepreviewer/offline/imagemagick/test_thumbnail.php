<?php

if(!class_exists("Imagick"))
{
    die('ERROR: Imagemagick not installed!');
}

$image = trim($_REQUEST['image']);
$image = str_replace(array('/', '\\', '"', '\'', '..'), '', $image);
$image = str_replace(array('..'), '', $image);

$samplePath = dirname( __FILE__ ).'/samples_files/'.$image;

$width = 200;
$height = 200;
try
{
	// start Imagick
	$imagick = new Imagick($samplePath);

	// set the background to white
	$imagick->setImageBackgroundColor('white');

	// flatten the image to remove layers and transparency
	$imagick = $imagick->mergeImageLayers(imagick::LAYERMETHOD_FLATTEN);

	// remove any meta data for privacy
	$imagick->stripImage();

	// resize
	$imagick->scaleImage($width, $height, true);
	$imagick->setImageBackgroundColor('white');
	$w = $imagick->getImageWidth();
	$h = $imagick->getImageHeight();
	$imagick->extentImage($width, $height, ($w-$width)/2, ($h-$height)/2);
	
	// set as jpg
	$imagick->setImageFormat('jpeg');
	
	header("Content-Type: image/jpg");
	echo $imagick;
}
catch(Exception $e)
{
	echo $e->getMessage();
}