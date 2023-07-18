<?php

if(!class_exists("Imagick"))
{
    die('ERROR: Imagemagick not installed!');
}

$sampleFile = dirname ( __FILE__ ).'/samples_files/1100x1100_cropped.jpg';




// start Imagick
$imagick = new Imagick($sampleFile);

// reduce the amount of colors to 6
$imagick->quantizeImage( 6, Imagick::COLORSPACE_RGB, 0, true, false );

// only save one pixel of each color
$imagick->uniqueImageColors();

// extract the colors
$colors = [];

// get ImagickPixelIterator
$it = $imagick->getPixelIterator();

// reset the iterator
$it->resetIterator();

// loop through rows
while( $row = $it->getNextIteratorRow() )
{
	// loop through columns
	foreach ( $row as $pixel )
	{      
		// covert pixel to hex color
		$color = $pixel->getColor();		
		$colors[] = '#'.sprintf('%02x', $color['r']) . sprintf('%02x', $color['g']) . sprintf('%02x', $color['b']);
	}
}

print_r($colors);
