<?php

class EMail
{
	// mail service provider
	const MAIL_RU = 1;
	const YANDEX_RU = 2;
	const GMAIL_COM = 3;
	const RAMBLER_RU = 4;
}

class EMailHelper
{
	static function guessServiceByEmail($email)
	{
		$email = trim(strtolower($email));

		$tmp = explode('@', $email);
		
		if (isset($tmp[1]))
		{
			switch($tmp[1])
			{
				case 'mail.ru':	
				case 'inbox.ru':	
				case 'bk.ru':	
				case 'list.ru':	
					return EMail::MAIL_RU;
					break;

				case 'yandex.ru':
				case 'ya.ru':	
				case 'yandex.com':	
				case 'yandex.kz':
				case 'yandex.ua':	
				case 'yandex.by':	
				case 'narod.ru':
					return EMail::YANDEX_RU;
					break;	

				case 'gmail.com':
					return Email::GMAIL_COM;
					break;
					
				case 'rambler.ru':
					return Email::RAMBLER_RU;
					break;
			}
		}

		return null;
	}

	static function getDefaultImapServerForService($service)
	{	
		switch ($service)
		{
			case EMail::MAIL_RU:
				return '{imap.mail.ru:993/imap/ssl/novalidate-cert}';
				break;

			case EMail::YANDEX_RU:
				return '{imap.yandex.ru:993/imap/ssl/novalidate-cert}';
				break;

			case EMail::GMAIL_COM:
				return '{imap.gmail.com:993/imap/ssl/novalidate-cert}';	

		}
		return null;
	}
}

?>