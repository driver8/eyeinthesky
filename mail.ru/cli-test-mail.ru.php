<?php
require_once('test-mail.ru.php');

echo $argv[1] . PHP_EOL;
echo $argv[2] . PHP_EOL;

if (testPassword($argv[1],$argv[2]) == 2)
{
	echo 'Yes' . PHP_EOL;
}else
{
	echo 'No' . PHP_EOL;
}

?>