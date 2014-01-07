<?php

$depot = '../../builds/';

shell_exec("rm $depot/*.zip 2> /dev/null");;

$svn = scandir("../");

foreach($svn as $ext)
{
	$build_dir = "../{$ext}/builds";
	if (is_dir($build_dir) && $ext != '.' && $ext != '..') {
		$zips = scandir($build_dir);
		$most_recent = '';
		$version = '0.0.0';
		foreach($zips as $zip) {
			if (strpos($zip, '.zip') !== FALSE) {
				$cur_version = getVersion($zip);
				$f = "$build_dir/$zip";
				if ( version_compare($cur_version, $version) == 1) {
					$version = $cur_version;
					$most_recent = $f;
				}
			}
		}
		$name = end(explode('/',$most_recent));
		
		if (!is_dir($depot)) {
			mkdir($depot);
		}
		copy ($most_recent, $depot.$name);
	}
}

die("Done.\r\n");


// utils
function getVersion($str) {
	if (preg_match('/v([0-9\.]+(-.+)?).zip$/', $str, $matches)) {
		return $matches[1];
	}
	
	return FALSE;
}