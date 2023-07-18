<?php

if(!class_exists("Imagick"))
{
    die('ERROR: Imagemagick not installed!');
}

function render()
{
	$output = "";
	$input = \Imagick::queryformats();
	echo COUNT($input);
	$columns = 6;

	$output .= "<table border='2'>";

	for ($i=0; $i < count($input); $i += $columns) {
		$output .= "<tr>";
		for ($c=0; $c<$columns; $c++) {
			$output .= "<td>";
			if (($i + $c) <  count($input)) {
				$output .= $input[$i + $c];
			}
			$output .= "</td>";
		}
		$output .= "</tr>";
	}

	$output .= "</table>";

	return $output;
}

echo render();