<?php

header("Content-type: text");

require_once "sql4array.class.php";

for ($i = 0; $i < 20; $i++){
  $array[$i][id] = rand(0, 20);
  $array[$i][foo] = md5(rand(0, 10000));
}

$sql4array = new sql4array();
$a = $sql4array->query("SELECT id, foo FROM array");
$b = $sql4array->query("SELECT id, foo FROM array WHERE id > 10");
$c = $sql4array->query("SELECT id AS i, foo AS f FROM array WHERE i > 10");
$d = $sql4array->query("SELECT id AS i, foo AS f FROM array WHERE i > 10 AND f LIKE '%a%'");
$e = $sql4array->query("SELECT id AS iiiiii, foo AS fooooooooo FROM array WHERE iiiiii != 10");

var_dump($a);
var_dump($b);
var_dump($c);
var_dump($d);
var_dump($e);

?> 
