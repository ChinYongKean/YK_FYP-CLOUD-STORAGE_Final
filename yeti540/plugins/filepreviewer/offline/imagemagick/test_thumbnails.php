<?php

if(!class_exists("Imagick"))
{
    die('ERROR: Imagemagick not installed!');
}

$samplePath = dirname ( __FILE__ ).'/samples_files';
$fileListing = scandir($samplePath);

foreach($fileListing AS $fileListingItem)
{
	if(in_array($fileListingItem, array('.', '..')))
	{
		continue;
	}

	echo '<div style="width: 220px; display: inline-block; float: left; text-align: center; margin: 10px;">';
	echo '<img src="test_thumbnail.php?image='.$fileListingItem.'" style="width: 200px; height: 200px;" title="'.$fileListingItem.'"/><br/>';
	echo $fileListingItem.'<br/>';
	echo '</div>';
}