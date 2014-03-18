<?php

// 0 - error
// 1 - failed
// 2 - ok

function testPassword($login,$password)
{
	$session = imap_open("{imap.mail.ru:993/imap/ssl/novalidate-cert}INBOX",$login,$password);
	if ($session)
	{
		return 2;
	}/*elseif(imap_last_error())
	{
		echo imap_last_error() . PHP_EOL;
		return 0;
	}*/
	return 1;
}

?>