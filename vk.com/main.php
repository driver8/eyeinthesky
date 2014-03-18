<?php
require_once('vksession.class.php');

$exitCode = 0;

if(count($argv)<2)
{
	echo ' usage: dir command [params]' . PHP_EOL;
	$exitCode = 1;
	exit($exitCode);
}

array_shift($argv);
$userDir = array_shift($argv);
$command = array_shift($argv);

$s = new VKSession();

switch($command)
{
	case 'newmsg':
		$s->login($userDir);
		
		$lastMessageDatetime = $s->getMaxMessageDateTimeFromBase();

		echo "Last message was at " . date('c',$lastMessageDatetime) . PHP_EOL;

		//init
		$requestCount = 199;
		$messagesCount = $requestCount+1;
		$lastRequestCount = $requestCount;

		foreach(array(0,1) as $out)
		{
			$messagesRead = 0;
			while ($messagesRead<$messagesCount)
			{
				$m = $s->getMessages($out,$messagesRead,$requestCount,time() - $lastMessageDatetime - 1);
				$messagesCount = $m->response->count;
				
				if (count($m->response->items)==0)
					break;
				
				echo "Next portion of out ($out) ".count($m->response->items)." $messagesRead/$messagesCount" .PHP_EOL;

				$s->beginTransaction();
				foreach($m->response->items as $msg)
				{
					$s->saveMessageToFile($msg);
					$s->saveMessageToBase($msg);
					$messagesRead++;
				}
				$s->commitTransaction();
				
			}
		}
		break;
	
	case 'allmsg':
		$s->login($userDir);
	
		$requestCount = 199;
		$messagesCount = $requestCount+1;
		$lastRequestCount = $requestCount;

		foreach(array(0,1) as $out)
		{
			$messagesRead = 0;
			while ($messagesRead<$messagesCount)
			{
				$m = $s->getMessages($out,$messagesRead,$requestCount);
				$messagesCount = $m->response->count;
				
				if (count($m->response->items)==0)
					break;
				
				echo "Next portion of out ($out) ".count($m->response->items)." $messagesRead/$messagesCount" .PHP_EOL;

				$s->beginTransaction();
				foreach($m->response->items as $msg)
				{
					$s->saveMessageToFile($msg);
					$s->saveMessageToBase($msg);
					$messagesRead++;
				}
				$s->commitTransaction();
			}
		}
		break;
		
	case 'restore':
		$s->login($userDir);
		
		foreach($s->getEmptyMessageIdsFromBase($argv[0], $argv[1]) as $id)
		{
			echo $id . "\r";
			
			if ($s->restoreMessage($id))
			{
				echo ' Ok '. PHP_EOL;
			}else
			{
				//echo ' Failed '. PHP_EOL;
			}
			//sleep(1);
		}
		
		
		break;
	
// 	case 'newphoto':
// 		$s->login($userDir);
// 		
// 		$s->beginTransaction();
// 
// 		foreach($s->getAlbumsFromBase() as $aid)
// 		{
// 			$ph = $s->getPhotos($aid);
// 			foreach($ph->response->items as $p)
// 			{
// 				if (!$s->baseHasPhoto($p->id))
// 				{
// 					$s->savePhotoToBase($p);
// 					$s->savePhotoToFile($p);
// 					$s->downloadPhoto($p);
// 				}	
// 				echo ".";
// 			}
// 		}
// 		
// 		$s->commitTransaction();
// 		
// 		echo PHP_EOL;
// 		break;

	case 'updalbums':
		$s->login($userDir);
		$albs = $s->getAlbums();
		$s->beginTransaction();
		foreach($albs->response->items as $a)
		{
			if ($a->id > 0)
				echo $a->title . ':' . $a->description .' : (' . $a->size .') ' . strftime('%x', intVal($a->updated)) . PHP_EOL; 
			else
				echo $a->title . ' : (' . $a->size .') ' .  PHP_EOL; 
				
			$s->saveAlbumToBase($a);
			$s->saveAlbumToFile($a);
		}
		$s->commitTransaction();
		break;
		
	case 'allphoto': $all = true;
 	case 'newphoto':
		$s->login($userDir);
		
		$s->beginTransaction();

		foreach($s->getAlbumsFromBase() as $aid)
		{
			$ph = $s->getPhotos($aid);
			foreach($ph->response->items as $p)
			{
				if (isset($all) || !$s->baseHasPhoto($p->id))
				{
					$s->savePhotoToBase($p);
					$s->savePhotoToFile($p);
					$s->downloadPhoto($p);
					echo ".";
				}
			}
			sleep(1);
		}
		
		$s->commitTransaction();
		
		echo PHP_EOL;
		break;
		
	case 'updusers':
		$s->login($userDir);
		
		$res = $s->getUsers($s->getDialogUsersFromBase());
		
		$s->beginTransaction();
		foreach($res->response as $u)
		{
			$s->saveUserToBase($u);
			$s->saveUserToFile($u);
		}
		$s->commitTransaction();
		
		break;
	
	case 'auth':
		$res = $s->getAuthToken($userDir, $argv[0], $argv[1]);
		var_dump($res);
		break;	

	case 'authtest':
		$res = $s->testRedirectUri($argv[0], $argv[1]);
		var_dump($res);
		break;	
	
	case 'logout':
		$s->login($userDir);
		$res = $s->tryLogout();
		var_dump($res);
		break;	
		
	case 'dialog': 
		$s->login($userDir);
	
		$ids = null;
		
		if (isset($argv[0]))
		{
			$ids = $s->getMessageIdsFromBaseForUser($argv[0]);
		}else
		{
			$ids = $s->getMessageIdsFromBase();
		}
		
		foreach($ids as $id)
		{
			$msg = $s->getMessageFromFile($id);

			echo "[" .date('c',$msg->date). "] ";
			
			$name = $s->getUserNameFromBase($msg->user_id, ' unknown ');
			
			if (intVal($msg->out) == 1)
			{
				echo 'Me -> ' . $name . ': ';
			}else
			{
				echo $name . ' -> Me: ';
			}
			
			echo $msg->body;
			if (isset($msg->attachment))
			{
				echo " <attachment> ";
			}
			echo PHP_EOL;
		}
		
		echo PHP_EOL;
		
		break;
		
	case 'test':
		$s->login($userDir);
	
		$res = $s->getLongPollHistory();
		var_dump($res);
		
		break;
		
	case 'statusloop':
		$lastAudioStatusText = '';
		
		while(true)
		{
			$s->login($userDir);
		
			$status = $s->getStatus($argv[0]);
			if (isset($status->response) && isset($status->response->audio))
			{
				if ($status->response->text != $lastAudioStatusText)
				{
					$lastAudioStatusText = $status->response->text;
					echo $status->response->audio->owner_id . ' ' . $status->response->audio->id . ' ' . $status->response->text . PHP_EOL;
					$s->log($status->response->audio->owner_id . ' ' . $status->response->audio->id . ' ' . $status->response->text);
				}
			}
			sleep(5 + rand(0,8));
		}
		
		break;
}

?>