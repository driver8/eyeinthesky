<?php
require('root.php');
require_once($ROOT . '/framework/e-mail-helper.php');

if (count($argv)<3)
{
	echo 'usage: e-mail password [dir]' . PHP_EOL;
	exit(1);
}

$login = $argv[1];
$password = $argv[2];
$mailboxDir = (isset($argv[3])) ? $argv[3] : getcwd() . '/' . str_replace('@', '-at-', $login);

if (!file_exists($mailboxDir))
	mkdir($mailboxDir, 0777, true);

$mailDir = null;

$serviceProvider = EMailHelper::guessServiceByEmail($login);

if ($serviceProvider === null)
{
	echo 'e-mail service provider unknown' . PHP_EOL;
	exit(2);
}

$serverSpec = EMailHelper::getDefaultImapServerForService($serviceProvider);
//$defaultMailbox
$defaultMailbox = $serverSpec . 'INBOX';

$session = imap_open($defaultMailbox, $login, $password);


if (!$session)
{
	echo 'can`t open session' . PHP_EOL;
}


$mailboxes = array();

$currentMailboxName = '';

//var_dump(imap_check($session));

$list = imap_list($session,$serverSpec,'*');    //$list = imap_getmailboxes($session,'{imap.mail.ru}','*');

foreach($list as $l)
{
	$mb = new stdClass;
	$mb->name_utf7_full = $l;
	$mb->name_utf7 = substr($l,strlen($serverSpec));
	$mb->name_utf8 = imap_utf7($mb->name_utf7);

	$mailboxes[] = $mb;
}

foreach($mailboxes as $mb)
{
	$mailboxDirOld = $mailboxDir;	
	$mailboxDir .= '/' . $mb->name_utf8;

	if (!file_exists($mailboxDir))
		mkdir($mailboxDir, 0777, true);

	imap_close($session);

	$session = imap_open($mb->name_utf7_full, $login, $password);

	$currentMailboxName = $mb->name_utf8;

	if (!$session)
	{
		die(imap_last_error());	
	}

	$last = imap_num_msg($session);
	
	while($last > 0)
	{
		retriveMail($session, $last);
		$last--;
		//sleep(1);
	}

	$mailboxDir = $mailboxDirOld;
}

function imap_utf7($text)
{
	return mb_convert_encoding($text, "UTF-8", "UTF7-IMAP");
}

function retriveMail($session, $num)
{
	global $mailboxDir;
	global $mailDir;
	global $currentMailboxName;

	$header = imap_header($session, $num);


	$mailDate = strftime("%Y-%m-%b-%d_%H-%M-%S", intVal($header->udate)); 
	$mailName = $mailDate .'-'.md5($header->message_id);
	$mailDir = $mailboxDir .'/'. $mailName;

	echo 'retriving ' . $currentMailboxName. ' : ' . $mailName;

	if (!file_exists($mailDir))
	{
		mkdir($mailDir, 0777, true);	
	}else
	{
		echo ' ...skiped' . PHP_EOL;
		return true;
	}

	if (!file_exists($mailDir . '/src'))
	{
		mkdir($mailDir . '/src', 0777, true);	
	}

	file_put_contents($mailDir. '/src/source.text', imap_fetchbody($session, $num, "0", FT_PEEK) . imap_body($session, $num, FT_PEEK));
	
	$mpresource = mailparse_msg_parse_file($mailDir. '/src/source.text');

	$structure = mailparse_msg_get_structure($mpresource);

	$textData = '';
	$htmlData = '';

	foreach($structure as $st)
	{
		$section = mailparse_msg_get_part($mpresource, $st); 
        	/* get content-type, encoding and header information for that section */ 
        	
        	$info = mailparse_msg_get_part_data($section);

        	if (fnmatch('multipart*', $info['content-type']))
        	{
        		continue;
        	}		

        	$data = mailparse_msg_extract_part_file_by_info($info, $mailDir. '/src/message.txt');

        	$convertCharset = false;
        	if (array_key_exists('transfer-encoding', $info))
        	{
        		switch($info['transfer-encoding'])
        		{
        			case '7bit':
        			case '8bit':
        			case 'binary':
        				break;

        			case 'base64':
        				$data = base64_decode($data);
        				break;

        			case 'quoted-printable':
				case 'quoted/printable':        			
        				$data = imap_qprint($data);
        				break;

        			default:
        			{
        				echo 'unknown transfer encoding ' . $info['transfer-encoding'] . PHP_EOL;
        				//echo $data . PHP_EOL;
        			}	
        		}
        	}


        	if (fnmatch('text/plain*', $info['content-type']))
        	{
                	if (array_key_exists('charset', $info))
	        	{
	        		$data = mb_convert_encoding($data, 'UTF-8', $info['charset']);
	        	}

        		$textData .= $data;
        	}else
		if (fnmatch('text/html*', $info['content-type']))
        	{
                	if (array_key_exists('charset', $info))
	        	{
	        		$data = mb_convert_encoding($data, 'UTF-8',  $info['charset']);
	        	}

        		$htmlData .= $data;
        	}else
        	{
        		$fname = '';
        		if (array_key_exists('content-name', $info))
        		{
        			$fname = imap_mimetext($info['content-name']);	
        		}else
        		{
        			$fname = 'mime.' . $st . str_replace('/', '.' , $info['content-type']);
        		}

        		file_put_contents($mailDir. '/' . $fname, $data);
        	}
	}

	// prepairing text header
	//
	$textDataDelimeter = '---------------------------------------------------------------------------------------';
	{
		$textDataHead = $textDataDelimeter. PHP_EOL . PHP_EOL;
       		$textDataHead.= strftime("Date       :   %Y-%b-%d %H-%M-%S", intVal($header->udate)) . PHP_EOL;
		$textDataHead.= "From       :   " . imap_utf8($header->fromaddress) . PHP_EOL;
		$textDataHead.= "To         :   " . (isset($header->toaddress) ? imap_utf8($header->toaddress) : '<unknown>' ) . PHP_EOL;

		if (isset($header->subject))
		{
			$subjectText = imap_mimetext($header->subject);
		}else
		if (isset($header->Subject))
		{
			$subjectText = imap_mimetext($header->Subject);
		}else
		{
			$subjectText = '<no subject>';
		}

		$textDataHead.= 'Subject    :   ' . $subjectText . PHP_EOL;
		$textDataHead = PHP_EOL . $textDataDelimeter. PHP_EOL;
	}
	file_put_contents($mailDir. '/message.text', $textDataHead . $textData . PHP_EOL .$textDataDelimeter . PHP_EOL);
	file_put_contents($mailDir. '/message.html', $htmlData);

	echo ' ...OK' . PHP_EOL;
}

/*
function retriveMail2($session, $num)
{
	global $mailboxDir;
	global $mailDir;
	$header = imap_header($session, $num);
	
	// Fri, 30 May 2014 00:10:58 +0400
	//$mailDateArray = strptime($header->date, '%a, %d %b %Y %H:%M:%S %z');
	//$mailDate = sprintf("%04d-%02d-%02d_%02d-%02d-%02d",$mailDateArray['tm_year']+1900, $mailDateArray['tm_mon']+1,$mailDateArray['tm_mday'],$mailDateArray['tm_hour'], $mailDateArray['tm_min'], $mailDateArray['tm_sec']);
	$mailDate = strftime("%Y-%b-%d_%H-%M-%S", intVal($header->udate)); 
	//echo $mailDate . PHP_EOL;
	$mailDir = $mailboxDir .'/'. $mailDate .'-'.md5($header->message_id);

	if (file_exists($mailDir))
	{
		// TODO
	}

	mkdir($mailDir, 0777, true);	
	mkdir($mailDir . '/src', 0777, true);	
	
	$header2 = imap_headerinfo($session, $num);
	
	$header3 = imap_fetchstructure($session, $num);
	// var_dump($header);
	// var_dump($header2);
	// var_dump($header3);


	// return false;
	//var_dump($header3);
	//echo json_encode($header3);

	// put header
	file_put_contents($mailDir. '/src/header.txt', imap_fetchbody($session, $num, "0")) ;
	file_put_contents($mailDir. '/src/body.txt', imap_body($session, $num)) ;
	//file_put_contents($mailDir. '/src/header2.txt', var_export($header2, true)) ;
	//file_put_contents($mailDir. '/src/header3.txt', var_export($header3, true)) ;
	//file_put_contents($mailDir. '/src/header4.txt', var_export(imap_fetchheader($session, $num), true)) ;
	
	return true;

	ob_start();

	echo "From       :   " . imap_utf8($header->fromaddress) . PHP_EOL;
	echo "To         :   " . imap_utf8($header->toaddress) . PHP_EOL;
	//$header->subject
	echo "Subject    :   " . imap_mimetext($header->subject) . PHP_EOL;
	echo PHP_EOL;

	//fetchSection($session, $num, $header3);
	
	$cont = ob_get_contents() . PHP_EOL;
	ob_end_clean();

	file_put_contents($mailDir. '/message.txt', $cont);


	//echo $cont;
	//echo imap_base64(imap_fetchbody($session, $num, "1.1")) . PHP_EOL;
	//echo imap_utf8($header->subject) . PHP_EOL;
	//echo imap_utf8($header->Subject) . PHP_EOL;
	//echo imap_utf8($header->toaddress) . PHP_EOL;
	//echo PHP_EOL;

	// FT_PEEK - leave unseen
	//$body = imap_body($session, $num, FT_PEEK);
	//echo $body . PHP_EOL;
}

function fetchSection ($session, $num, $sectionObj, $prefix = array())
{
	global $mailboxDir;
	global $mailDir;

	if (isset($sectionObj->parts))
	{	
		$idx = 1;
		foreach($sectionObj->parts as $part)
		{
			$nextPrefix = array_merge($prefix, array($idx));
			//echo implode('.', $nextPrefix) . PHP_EOL;
			fetchSection($session, $num, $part, $nextPrefix);	
			$idx++;
		}
	}else
	{
		$sectBody = imap_fetchbody($session, $num, implode('.', $prefix), FT_PEEK);

		//echo  $sectionObj->subtype . PHP_EOL;
		//return;
		$parameters = array();
		$charset = null;
		$fname = null;

		if (isset($sectionObj->parameters))
		{
			$parameters = array_merge($parameters, $sectionObj->parameters);
		}	
		if (isset($sectionObj->dparameters))
		{
			$parameters = array_merge($parameters, $sectionObj->dparameters);
		}

		foreach($parameters as $param)
		{
			if(empty($param->attribute)) echo json_encode($sectionObj) . PHP_EOL;
			if ($param->attribute == 'charset')
			{
				$charset = $param->value;
			}	
		}


		if ($charset !== null)
		{ 
			//echo 'CHARSET ' . $charset . PHP_EOL;

			if (strtolower(trim($charset) == 'koi8'))
			{
				$charset = 'koi8-r';
			}
		}

		switch ($sectionObj->subtype)
		{
			case 'PLAIN':
			case 'HTML':
				$sectBody = imap_base64($sectBody);
				if ($charset !== null)
				{
					$sectBody = mb_convert_encoding($sectBody, 'UTF-8', $charset);
				}

				echo $sectBody . PHP_EOL;
				break;
			default:	
				$fname = '';
				
				foreach($parameters as $param)
				{
					if ($param->attribute == 'name' ||  $param->attribute == 'filename')
					{
						$value = $param->value;

						if (substr($value,0,2) == '=?')
						{
							$elements = imap_mime_header_decode($value);

							$mimeValue = '';
							foreach($elements as $el)
							{
								if (strtolower(trim($el->charset) == 'koi'))
								{
									$el->charset = 'koi8-r';
								}

								$mimeValue .= mb_convert_encoding($el->text, 'UTF-8', $el->charset);
							}

							$value = $mimeValue; 
						}

						if ($fname != $value)
							$fname .= $value;
					}	
				}
				
				if ($fname == '')
				{
					$fname = 'mime.' . implode('.', $prefix) . '.' . $sectionObj->subtype;	
				}
				file_put_contents($mailDir. '/' . $fname, imap_base64($sectBody)) . PHP_EOL;
				break;
		}
		//$sectionObj->	
	}
};

*/
function imap_mimetext($value)
{
	if (substr($value,0,2) == '=?')
	{
		$elements = imap_mime_header_decode($value);

		$mimeValue = '';
		foreach($elements as $el)
		{
			if (strtolower(trim($el->charset) == 'koi'))
			{
				$el->charset = 'koi8-r';
			}

			$mimeValue .= mb_convert_encoding($el->text, 'UTF-8', $el->charset);
		}

		return $mimeValue; 
	}
	return $value;
}

function mailparse_msg_extract_part_file_by_info($info, $fname)
{
	$f = fopen($fname, 'r');

	fseek($f, intVal($info['starting-pos-body']));

	$length = intVal($info['ending-pos-body']) - intVal($info['starting-pos-body']);
	
	if ($length <= 0)
	{	
		$length = filesize($fname);
	}

	$str = fread($f, $length);

	fclose($f);

	return $str;
}


?>