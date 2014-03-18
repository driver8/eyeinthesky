<?php
require_once("mycurl.class.php");

//https://oauth.vk.com/token?grant_type=password&scope=nohttps&client_id=2274003&client_secret=hHbZxrka2uZ6jB1inYsH&username=%1$s&password=%2$s"

//define('CLIENT_SECRET','hHbZxrka2uZ6jB1inYsH');
//define('CLIENT_ID','2274003');

//https://oauth.vk.com/token?grant_type=password&client_id=2273&client_secret=hHbZx&username=" + "textBox2.Text" + "&password=" + "textBox1.Text");

//https://oauth.vk.com/token?grant_type=password&client_id=3140623&client_secret=VeWdmVclDCtn6ihuP1nt&username=user@domain.ru&password=mypassword


// ssword = Uri.EscapeUriString(this.pass.Password);
//             auth = "https://api.vk.com/oauth/token";
//             auth += "?grant_type=password" + "&client_id=2846945&client_secret=wCCY5i7yUOL92yNB2N9a&username=" + login + "&password=" + password + "&scope=notify,friends,messages";
//             //auth = "https://oauth.vk.com/token?grant_type=password&client_id=2846945&client_secret=wCCY5i7yUOL92yNB2N9a&username=1&password=1&scope=notify,friends,messages";
//             //auth = "https://api.vk.com/";
//             HttpWebRequest request = (HttpWebRequest)WebRequest.Create(auth);
//             request.Method = "GET";
//             request.BeginGetRequestStream(R

//android
define('CLIENT_SECRET','hHbZxrka2uZ6jB1inYsH');
define('CLIENT_ID','2274003');

//iphone
// define('CLIENT_SECRET','VeWdmVclDCtn6ihuP1nt');
// define('CLIENT_ID','3140623');

// unimproved
// define('CLIENT_SECRET','wCCY5i7yUOL92yNB2N9a');
// define('CLIENT_ID','2846945');


// "https://oauth.vk.com/token?grant_type=password&client_id=2985083&client_secret=powUs3vuKhIYNrgsLif1&username
// unimproved
// define('CLIENT_SECRET','powUs3vuKhIYNrgsLif1');
// define('CLIENT_ID','2985083');


class VKSession
{
	private $userId = 0;
	private $userLogin = '';
	private $userPassword = '';
	private $accessToken = '';
	private $secret = null;
	private $cwd = null;
	private $userDirName = '';
	private $curl = null;
	private $lastError = '';
	private $sqlite = null;
	
	public function __construct()
	{
		$this->curl = new myCurl();
		$this->cwd = getcwd();
	}
	
	public function __destruct()
	{
		if ($this->sqlite)
		{
			$this->sqlite->close();
		}
	}
	
	public function setLogin($login)
	{
		$this->userLogin = $login;
	}
	
	public function setPassword($password)
	{
		$this->userPassword = $password;
	}
	
	public function setLoginPassword($login,$password)
	{
		$this->setLogin($login);
		$this->setPassword($password);
	}
	
	public function beginTransaction()
	{
		$this->sqlite->exec('BEGIN TRANSACTION');
	}
	
	public function commitTransaction()
	{
		$this->sqlite->exec('COMMIT');
	}
	
	
	private function doAPIRequest($method, $getParams, $postParams = null, $useToken = true)
	{
		$url = '/method/' . $method . '?' . $this->getCommonUrlPart();
		foreach($getParams as $param => $value)
		{
			$url .= "&{$param}={$value}";
		}
		
		
		$urlPostPart = ''; // нужно только для подсчета sig
		if ($postParams && $this->secret)
		{
			foreach($postParams as $param => $value)
			{
				$urlPostPart .= "&{$param}={$value}";
			}
		}
		
		// вычисление sig c пост параметрами
		// /method/{Название метода}?{GET параметры}{POST параметры} 
		
		if ($this->secret)
		{
			$url = 'http://api.vk.com' . $url . '&sig=' . md5($url.$urlPostPart.$this->secret);
		}else
		{
			$url = 'https://api.vk.com' . $url;
		}
		
		$res = $this->curl->getUrlContent($url,$postParams);
		
		return json_decode($res);
	}
	
	private function getCommonUrlPart()
	{
		return "user_id={$this->userId}&v=5.10&access_token={$this->accessToken}";
	}
	
	private function isError($res)
	{
		if (!is_object($res))
			return true;
			
		if (isset($res->error))
		{
			$this->lastError = $res->error_description;
			return true;
		}
		return false; 
	}
	
	private function getFullUserDirName()
	{
		return $this->cwd .'/'. $this->userDirName;
	}
	
	private function createDataDirStructure($dirname)
	{
		// check/create dir
		if (!file_exists($dirname))
		{
			mkdir($dirname, 0777, true);
		}
	
		// check create subdirs
		foreach(array('/messages','/chats','/photos','/users') as $subdir)
		{
			if (!file_exists($dirname . $subdir))
			{
				mkdir($dirname . $subdir, 0777, true);
			}
		}
		
		if ($this->sqlite)
		{
			$this->sqlite->commitTransaction();
			$this->sqlite->close();
		}
	
		// create sqlite structure
		$this->sqlite= new SQLite3($dirname . '/data.sqlite', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
		// messages
		$this->sqlite->exec('CREATE TABLE IF NOT EXISTS message(id UNSIGNED BIG INT PRIMARY_KEY UNIQUE, datetime UNSIGNED BIG INT, out TINYINT, user_id UNSIGNED BIG INT, from_id UNSIGNED BIG INT, read_state TINYINT, chat_id INTEGER, has_attachment TINYINT, deleted TINYINT)');
		
		$this->sqlite->exec('CREATE TABLE IF NOT EXISTS chat(id INTEGER PRIMARY_KEY UNIQUE,user_ids NCHAR(1024))');

		$this->sqlite->exec('CREATE TABLE IF NOT EXISTS album(id INTEGER PRIMARY_KEY UNIQUE, title NCHAR(1024), descr NCHAR(1024), created UNSIGNED BIG INT, updated UNSIGNED BIG INT, owner_id  UNSIGNED BIG INT)');

		$this->sqlite->exec('CREATE TABLE IF NOT EXISTS photo(id INTEGER PRIMARY_KEY UNIQUE, album_id INTEGER , owner_id INTEGER)');
	
		$this->sqlite->exec('CREATE TABLE IF NOT EXISTS user(id INTEGER PRIMARY_KEY UNIQUE, first_name NCHAR(1024), last_name NCHAR(1024))');

		$this->sqlite->exec('CREATE TABLE IF NOT EXISTS log(time INTEGER PRIMARY_KEY UNIQUE, text NCHAR(4096))');
	}
	
	public function saveMessageToBase($msg, $deleted = 0)
	{
		$id = 0;
		$datetime = 0;
		$out=-1;
		$userId = 0;
		$fromId = 0;
		$readState = 1;
		$chatId = 0;
		$hasAttachment = 0;
		
		if ($msg)
		{
			if (isset($msg->id))
			{
				$id = $msg->id;
			}
			if (isset($msg->date))
			{
				$datetime = $msg->date;
			}
			if (isset($msg->out))
			{
				$out = $msg->out;
			}
			if (isset($msg->user_id))
			{
				$userId = $msg->user_id;
			}
			if (isset($msg->from_id))
			{
				$fromId = $msg->from_id;
			}
			if (isset($msg->read_state))
			{
				$readState = $msg->read_state;
			}
			if (isset($msg->chat_id))
			{
				$readState = $msg->chat_id;
			}
			if (isset($msg->attachments))
			{
				$hasAttachment = 1;
			}
		}
		
		// (id UNSIGNED BIG INT PRIMARY_KEY UNIQUE, datetime UNSIGNED BIG INT, out TINYINT, user_id UNSIGNED BIG INT, from_id UNSIGNED BIG INT, read_state TINYINT, chat_id INTEGER, has_attachment TINYINT, deleted TINYINT)');		
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from message where id=$id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				$this->sqlite->exec("update message set datetime=$datetime,out=$out,user_id=$userId,from_id=$fromId,read_state=$readState,chat_id=$chatId,has_attachment=$hasAttachment,deleted=$deleted where id = $id");
			}else
			{
				$this->sqlite->exec("insert into message(id,datetime,out,user_id,from_id,read_state,chat_id,has_attachment,deleted) values ($id,$datetime,$out,$userId,$fromId,$readState,$chatId,$hasAttachment,$deleted)");
			}
		}
	}
	
	public function saveMessageToFile($msg)
	{
		if ($msg && isset($msg->id))
		{
			$id = $msg->id;
			file_put_contents($this->getFullUserDirName()."/messages/msg_{$id}",json_encode($msg));
		}	
	}
	
	public function getMessageFromFile($id)
	{
		return json_decode(file_get_contents($this->getFullUserDirName()."/messages/msg_{$id}"));
	}
	
	public function saveChatToBase($chat)
	{
		$id = 0;
		$userIds = 0;
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from chat where id = $id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				$this->sqlite->exec("update chat set user_ids='".implode(',',$userIds)."' where id = $id");
			}else
			{
				$this->sqlite->exec("insert into chat(id,user_ids) values ($id,'".implode(',',$userIds)."')");
			}
		}
	}
	
	public function getAuthToken($dir, $login, $password)
	{
		echo 'Getting auth token. Doing request...' . PHP_EOL;
	
		$client_id = CLIENT_ID;
		$client_secret = CLIENT_SECRET;
		
		$url = "https://oauth.vk.com/token?grant_type=password&scope=messages,friends,photos,audio,video,docs,groups,status&client_id={$client_id}&client_secret={$client_secret}&username={$login}&password={$password}";
		
		$res = $this->curl->getUrlContent($url);
		
		$this->createDataDirStructure($dir);
		
		file_put_contents($dir . '/auth.token' , $res);
		
		$res = json_decode($res);
		
		if ($this->isError($res))
		{
			return false;
		}
		
		return $res;
	}
	
	public function testRedirectUri($login, $password)
	{
		echo 'Getting auth token. Doing request...' . PHP_EOL;
	
		$client_id = CLIENT_ID;
		$client_secret = CLIENT_SECRET;
		
		$url = "https://oauth.vk.com/token?grant_type=password&scope=messages,friends,photos,audio,video,docs,groups,status&client_id={$client_id}&client_secret={$client_secret}&username={$login}&password={$password}&test_redirect_uri=1";
		
		$res = $this->curl->getUrlContent($url);
		
		$res = json_decode($res);
		
		if ($this->isError($res))
		{
			return false;
		}
		
		return $res;
	}
	
	
	public function tryLogout()
	{
		echo 'Logout...' . PHP_EOL;
	
		$client_id = CLIENT_ID;
		$client_secret = CLIENT_SECRET;
		
		$url = "https://oauth.vk.com/logout?access_token={$access_token}";
		
		$res = $this->curl->getUrlContent($url);
		
		$res = json_decode($res);
		
		if ($this->isError($res))
		{
			return false;
		}
		
		return $res;
	}
	
	
	//http://api.vk.com/oauth/logout?succe...&display=popup

	
	function login($dir)
	{
		$res = null;
		
		// check/create dir
		$this->createDataDirStructure($dir);
		
		if (file_exists($dir.'/auth.token'))
		{
			$res = json_decode(file_get_contents($dir . '/auth.token'));
			
			$this->userId = $res->user_id;
			$this->accessToken = $res->access_token;
			
			if (isset($res->secret))
			{
				$this->secret = $res->secret;
			}
			
			$this->userDirName = $dir;
		}
		
		if ($this->isError($res))
		{
			return false;
		}

		$this->sqlite= new SQLite3($dir . '/data.sqlite', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
		
		return $res;
	}
	
	public function getDialogs()
	{
		//http://vk.com/dev/messages.getDialogs
		$res = $this->doAPIRequest('messages.getDialogs',array('offset'=>0,'count'=>200,'preview_length'=>0));
		
		return $res;
	}
	
	public function getMessages($out,$offset,$count,$timeOffset = null,$lastMessageId = null)
	{
		$params = array();
		$params['out'] = $out;
		$params['offset'] = $offset;
		$params['count'] = $count;
		if ($timeOffset)
		{
			$params['time_offset'] = $timeOffset;
		}	
		
		$msgs = $this->doAPIRequest('messages.get',$params);
	
		return $msgs;
	}
	
	public function restoreMessage($msgId)
	{
		$res = $this->doAPIRequest('messages.restore',array('message_id'=>$msgId));
		if (isset($res->response) && $res->response == 1)
		{
			return true;
		}
		return false;
	}
	
	public function getAllUsersFromDialogBase()
	{
		$userIds = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select distinct user_id from message");
			
			while ($row = $res->fetchArray())
			{
				$userIds[] = intVal($row['id']);
			}
			
			$res = $this->sqlite->query("select distinct from_id from message");
			
			while ($row = $res->fetchArray())
			{
				$userIds[] = intVal($row['id']);
			}
		}
		
		return $userIds;
	}
	
	public function getMaxMessageDateTimeFromBase()
	{
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select max(datetime) as datetime from message");
		
			if ($row = $res->fetchArray())
			{
				return intVal($row['datetime']);
			}
		}
		
		return 0;
	}
	
	public function getEmptyMessageIdsFromBase($a,$b)
	{
		$ids = array();
		$ids2 = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from message where id>=$a and id<=$b");
		
			while ($row = $res->fetchArray())
			{
				$ids[intVal($row['id'])] = intVal($row['id']);
			}
			
			foreach(range($a, $b, 1) as $i)
			{
				if ( !array_key_exists($i, $ids))
				{
					$ids2[] = $i;
				}
			}
		}
		
		return $ids2;
	}
	
	public function getMessageIdsFromBaseForUser($userId)
	{
		$ids = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from message where user_id=$userId and chat_id=0 order by datetime desc");
		
			while ($row = $res->fetchArray())
			{
				$ids[] = intVal($row['id']);
			}
		}
		
		return $ids;
	}
	
	public function getMessageIdsFromBase()
	{
		$ids = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from message order by datetime desc");
		
			while ($row = $res->fetchArray())
			{
				$ids[] = intVal($row['id']);
			}
		}
		
		return $ids;
	}
	
	public function getMessageIdsFromBaseForChat($chatId)
	{
		$ids = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from message where chat_id=$chatId order by datetime desc");
		
			while ($row = $res->fetchArray())
			{
				$ids[] = intVal($row['id']);
			}
		}
		
		return $ids;
	}
	
	public function getAlbums()
	{
		$res = $this->doAPIRequest('photos.getAlbums',array('need_system'=>1,'photo_sizes'=>1));
		
		return $res;
	}
	
	public function saveAlbumToBase($albumData)
	{
		if ($this->sqlite)
		{
			$id = isset($albumData->id) ? $albumData->id : 0;
			$title = isset($albumData->title) ? $albumData->title : '';
			$descr = isset($albumData->description) ? $albumData->description : '';
			$created = isset($albumData->created) ? $albumData->created : 0;
			$updated = isset($albumData->updated) ? $albumData->updated : 0;
			$owner_id = isset($albumData->owner_id) ? $albumData->owner_id : 0;
			
			$title = $this->sqlite->escapeString($title);
			$descr = $this->sqlite->escapeString($descr);
			
			$res = $this->sqlite->query("select id from album where id=$id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				$this->sqlite->exec("update album set id=$id,title='$title',descr='$descr',created=$created,updated=$updated,owner_id=$owner_id where id = $id");
			}else
			{
				$this->sqlite->exec("insert into album(id,title,descr,created,updated,owner_id) values ($id,'$title','$descr',$created,$updated,$owner_id)");
			}
			
			return true;
		}
		
		return false;
	}
	
	public function saveAlbumToFile($a)
	{
		if ($a && isset($a->id))
		{
			$id = $a->id;
			file_put_contents($this->getFullUserDirName()."/photos/album_{$id}",json_encode($a));
		}
	}

	public function getPhotos($albumId)
	{
		$res = $this->doAPIRequest('photos.get',array('album_id'=>$albumId,'photo_sizes'=>1, 'rev'=>1));
		
		return $res;
	}
	
	public function getAlbumsFromBase()
	{
		$ids = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select distinct id from album order by updated desc");
		
			while ($row = $res->fetchArray())
			{
				$ids[] = intVal($row['id']);
			}
		}
		
		return $ids;
	}

	public function savePhotoToBase($photo)
	{
		if ($this->sqlite)
		{
			$id = isset($photo->id) ? $photo->id : 0;
			$album_id = isset($photo->album_id) ? $photo->album_id : '';
			$owner_id = isset($photo->owner_id) ? $photo->owner_id : '';
			
			$res = $this->sqlite->query("select id from photo where id=$id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				$this->sqlite->exec("update photo set album_id=$album_id, owner_id=$owner_id where id=$id");
			}else
			{
				$this->sqlite->exec("insert into photo(id,album_id,owner_id) values ($id, $album_id, $owner_id)");
			}
			
			return true;
		}
		
		return false;
	}
	
	public function baseHasPhoto($id)
	{
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id from photo where id=$id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				return true;
			}
		}
		
		return false;
	}
	
	
	public function downloadPhoto($p)
	{
		if ($p && isset($p->sizes))
		{
			$id = $p->id;
			$aid = $p->album_id;
			$maxWidth = 0;
			$maxWidthType = '';
			
			foreach($p->sizes as $s)
			{
				if (isset($s->src) && isset($s->type))
				{
					if (intVal($s->width) > $maxWidth)
					{
						$maxWidthType = $s->type;
						$maxWidth = intVal($s->width);
					}
				}
			}
			
			foreach($p->sizes as $s)
			{
				if (isset($s->src) && isset($s->type) && $s->type == $maxWidthType)
				{
					$this->downloadFile($s->src, $this->getFullUserDirName()."/photos/photo_{$aid}_{$id}_src_{$s->type}_{$s->width}");
				}
			}
		}
	}

	public function savePhotoToFile($p)
	{
		if ($p && isset($p->id))
		{
			$id = $p->id;
			file_put_contents($this->getFullUserDirName()."/photos/photo_{$id}",json_encode($p));
		}
	}

	public function getDialogUsersFromBase()
	{
		$ids = array();
		
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select distinct user_id from message where user_id>0 order by datetime desc");
		
			while ($row = $res->fetchArray())
			{
				$ids[] = intVal($row['user_id']);
			}
		}
		
		return $ids;
	}
	
	public function getUsers($ids)
	{
		$res = $this->doAPIRequest('users.get',array('user_ids'=>implode(',',$ids)));
		
		return $res;
	}

	public function saveUserToBase($user)
	{
		if ($this->sqlite)
		{
			$id = isset($user->id) ? $user->id : 0;
			$first_name = isset($user->first_name) ? $user->first_name : '';
			$last_name = isset($user->last_name) ? $user->last_name : '';
			
			$first_name = $this->sqlite->escapeString($first_name);
			$last_name = $this->sqlite->escapeString($last_name);
			
			$res = $this->sqlite->query("select id from user where id=$id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				$this->sqlite->exec("update user set first_name='$first_name',last_name='$last_name' where id=$id");
			}else
			{
				$this->sqlite->exec("insert into user(id,first_name,last_name) values ($id,'$first_name','$last_name')");
			}
			
			return true;
		}
		
		return false;
	}
	
	
	public function saveUserToFile($u)
	{
		if ($u && isset($u->id))
		{
			$id = $u->id;
			file_put_contents($this->getFullUserDirName()."/users/user_{$id}",json_encode($u));
		}
	}
	
	public function getUserNameFromBase($id, $defaultValue = null)
	{
		if ($this->sqlite)
		{
			$res = $this->sqlite->query("select id,first_name,last_name from user where id=$id");
			
			$row = $res->fetchArray();
			
			if ($row && isset($row['id']))
			{
				return $row['first_name'] . ' ' . $row['last_name'];
			}		
		}
		return $defaultValue;
	}

	
	private function downloadFile($url, $file)
	{
		$fp = fopen($file, 'w');
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		
		$data = curl_exec($ch);
		
		curl_close($ch);
		fclose($fp);
	}
	
	public function getUserInfo()
	{
		
	}
	
	public function getLongPollHistory()
	{
		$res = $this->doAPIRequest('messages.getLongPollHistory',array('ts'=>1,'pts'=>0, 'preview_length'=>5000, 'onlines' => 1, 'events_limit' => 1000, 'msgs_limit' => 201, 'max_msg_id'=>1));
		
		return $res;
	}
	
	public function getStatus($userId)
	{
		$res = $this->doAPIRequest('status.get',array('user_id'=>$userId));
		return $res;
	}

	public function log($string)
	{
		$time = time();
		$string = $this->sqlite->escapeString($string);
		$this->sqlite->exec("insert into log(time,text) values ($time,'$string')");
	}
}
?>

