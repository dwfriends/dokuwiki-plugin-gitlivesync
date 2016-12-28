<?php

include("mount_mapper.php");

$map_config = array(
	":web"=> "git:git_web",
	":web:projekte:uhren"=>"git:projekte_karsten/web/uhren",
);

$p1 = map_from_wiki($map_config, ':rgsg');
echo "p1: ':rgsg'\n";
print_r($p1);
echo "\n";
$p2 = map_from_wiki($map_config, ':web:projekte:hallo_welt:start.txt');
echo "p2: ':web:projekte:hallo_welt:start.txt'\n";
print_r($p2);
echo "\n";
$p3 = map_from_wiki($map_config, ':web:projekte:uhren:start.txt');
echo "p3: ':web:projekte:uhren:start.txt'\n";
print_r($p3);
echo "\n";

$p4 = map_from_git($map_config, 'git:git_web/projekte/hallo_welt/start.txt');
echo "p4: 'git:git_web/projekte/hallo_welt/start.txt'\n";
print_r($p4);
echo "\n";
$p5 = map_from_git($map_config, 'git:projekte_karsten/web/uhren/start.txt');
echo "p5: 'git:projekte_karsten/web/uhren/start.txt'\n";
print_r($p5);
echo "\n";
