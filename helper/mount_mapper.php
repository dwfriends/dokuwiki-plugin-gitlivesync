<?php

function sep_add($path, $sep) {
	return preg_replace(
		'/'.preg_quote($sep, '/').'{2,}$/',
		$sep,
		$path.$sep);
}

function sep_map($path, $oldsep, $sep) {
	return preg_replace(
		'/'.preg_quote($oldsep, '/').'+/',
		$sep,
		$path
	);
}


function check_path($path, $from, $oldsep) {
	$from = sep_add($from, $oldsep);
	if (preg_match(
				'/^('.
				preg_quote($from, '/').
				')(.*)$/',
			$path, $matches)) {
		return array(
			strlen($matches[1]),
			$matches[2]
		);
	}
	return array(false, "", "");
}

function check_path_minlength ($path, $from, $to,
		&$mapped_path, &$mapped_path_length,
		$oldsep, $sep) {
	list($tmp_mpl, $tmp_path) =
			check_path($path, $from, $oldsep);
	if ($tmp_mpl && $tmp_mpl > $mapped_path_length) {
		//$mapped_path = array($to, $tmp_path);
		$mapped_path =
			sep_add($to, $sep).
			sep_map($tmp_path, $oldsep, $sep);
		$mapped_path_length = $tmp_mpl;
	}
}


function map_from_wiki($map_config, $path) {
	$mapped_path = null;
	$mapped_path_length = 0;
	foreach ($map_config as $ns_wiki=>$path_git) {
		check_path_minlength($path, $ns_wiki, $path_git,
			$mapped_path, $mapped_path_length, ':', '/');
	}
	return $mapped_path;
}

function map_from_git($map_config, $path) {
	$mapped_path = null;
	$mapped_path_length = 0;
	foreach ($map_config as $ns_wiki=>$path_git) {
		check_path_minlength($path, $path_git, $ns_wiki,
			$mapped_path, $mapped_path_length, '/', ':');
	}
	return $mapped_path;
}
