<?php
	/** 
	 * Created by JetBrains PhpStorm.
	 * User: bor
	 * Date: 7/26/12
	 * Time: 1:47 PM
	 * To change this template use File | Settings | File Templates.
	 */
	class myCurl
	{
		protected $sUserAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11';

		protected $iCurl_timeout = 30;
		protected $oCurl='';
		protected $sReferer='';
		protected $bAutoUpdateReferer = false;
		protected $sCookie='';
		protected $sNew_cookie='';
		protected $bSecure = false;
		protected $useProxy=false;
		protected $proxyRetryCount = 5;
		protected $proxySOCKS5 = '80.76.190.188:1080';
		protected $bPOST = false;
		protected $bDebug = false;
		protected $sEncoding = '';
		// для прокси с авторизацией
		protected $proxyUser = "";
		protected $proxyPassword="";
		protected $proxyType = CURLPROXY_HTTP; // CURLPROXY_SOCKS5  или CURLPROXY_HTTP

		
		
		
        protected $bCacheSaveEnabled = false;
        protected $bCacheReplayFromCache = false;
        protected static $cache=array();

		function setEncoding($sEncoding='cp1251')
		{
			$this->sEncoding = $sEncoding;
		}

		function setDebug($bDebug = true)
		{
			$this->bDebug = $bDebug;
		}

		function secure($bool=true)
		{
			$this->bSecure = $bool;
		}

		function setUserAgent($string)
		{
			$this->sUserAgent = $string;
		}

		function cookie()
		{
			return $this->sNew_cookie;
		}

		function setCookie($string)
		{
			if ($this->bDebug) {  echo "setCookie: ".$string.'<br/>'; }
			$this->sCookie = $string;
		}

		public static function addCookie($c1, $c2)
		{

		}

		function setProxy($useProxy=true, $proxyRetryCount = 5, $proxySOCKS5 = null)
		{
			$this->useProxy = $useProxy;
			$this->proxyRetryCount = $proxyRetryCount;
			if ($proxySOCKS5 !==null) { $this->proxySOCKS5 = $proxySOCKS5; }
		}

		function setTimeout($sec=120)
		{
			$this->iCurl_timeout = $sec;
		}

		function referer($string)
		{
			return $this->sReferer;
		}

		function setReferer($string)
		{
			$this->sReferer=$string;
		}

		function setRefererAutoUpdate($bool=true)
		{
			$this->bAutoUpdateReferer = $bool;
		}
		
		// имя пользователя для прокси с авторизацией
		function setProxyUser($userName = "")
		{
			$this->proxyUser = $userName;
		}
		
		// пароль для прокси с авторизацией
		function setProxyPassword($password = "")
		{
			$this->proxyPassword = $password;
		}
		
		// тип прокси  CURLPROXY_HTTP   или CURLPROXY_SOCKS5
		function setProxyType($type=CURLPROXY_HTTP )
		{
			$this->proxyType = $type;
		}

	
		function getUrlContent($URL, $POSTFIELDS=null, $hintURL=null, $hintPOSTFIELDS=null)
		{

			if (! $this->oCurl ) {
				// 			echo "getUrlContent. Create new curl...";
				if ($this->sReferer) {
					$this->oCurl = curl_init ($this->sReferer);
				} else {
					$this->oCurl = curl_init ();
				}
				// 			echo "Ok.".PHP_EOL;
			}
			curl_setopt($this->oCurl, CURLOPT_URL, $URL);
			if ($this->sReferer) {
				curl_setopt($this->oCurl, CURLOPT_REFERER, $this->sReferer);
			}
			curl_setopt($this->oCurl, CURLOPT_HEADER, true);
			curl_setopt($this->oCurl, CURLOPT_RETURNTRANSFER, true);
			if ($this->sCookie) {
				curl_setopt($this->oCurl, CURLOPT_COOKIE, $this->sCookie);
			}
			curl_setopt($this->oCurl, CURLOPT_CONNECTTIMEOUT, $this->iCurl_timeout);
			$version = curl_version();
			if ($version['features'] && constant('CURLOPT_IPRESOLVE')) { // if this feature exists (since curl 7.10.8)
				curl_setopt($this->oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
			}			
			curl_setopt($this->oCurl, CURLOPT_USERAGENT, $this->sUserAgent);
			if ($this->bSecure) {
				curl_setopt($this->oCurl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($this->oCurl, CURLOPT_SSL_VERIFYPEER, 0);
			}

			// with proxy helper
			if ( $this->useProxy ) {
				echo 'will use proxy!!!<br/>';
				/*
				Для тех кто использует CURL

					Простое задание курл-опции для работы соединения через прокси:
					curl_setopt($ch, CURLOPT_PROXY, ‘XXX.XXX.XXX.XXX’);
					Задание курл-опций для работы через SOCKS4:
					curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
					curl_setopt($ch, CURLOPT_PROXY, ‘XXX.XXX.XXX.XXX’);
					Задание курл-опций для работы через SOCKS5:
					curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
					curl_setopt($ch, CURLOPT_PROXY, ‘XXX.XXX.XXX.XXX’);
					Задание курл-опции для работы через прокси с авторизацией, 1 вариант:
					curl_setopt($ch, CURLOPT_PROXY, ‘loginassword@XXX.XXX.XXX.XXX’);
					Задание курл-опций для работы через прокси с авторизацией, 2 вариант:
					curl_setopt ($ch, CURLOPT_PROXYUSERPWD, ‘loginassword’);
					curl_setopt($ch, CURLOPT_PROXY, ‘XXX.XXX.XXX.XXX’);
curl->userProxy
curl->setProxyUser
curl->setProxyPassword
 чтобы короче работало и с паролем и без
				*/
				//curl_setopt($this->oCurl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
				
				
				
				if($this->proxyType === CURLPROXY_SOCKS5)
				{
					curl_setopt($this->oCurl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);	
					
				}
				elseif ($this->proxyType === CURLPROXY_HTTP)
				{
					curl_setopt($this->oCurl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);			
				}
				if ($this->proxyUser !== "")
				{
					$login_pass= $this->proxyUser . ":" . $this->proxyPassword;
					curl_setopt($this->oCurl, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
					curl_setopt($this->oCurl, CURLOPT_PROXYUSERPWD, $login_pass);
				}
/*				
    curl_setopt ($ch, CURLOPT_FAILONERROR, true); 
*/
		
				curl_setopt($this->oCurl, CURLOPT_FOLLOWLOCATION, true);
				
				curl_setopt($this->oCurl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($this->oCurl, CURLOPT_SSLVERSION	,3	);
				curl_setopt($this->oCurl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($this->oCurl, CURLOPT_SSL_VERIFYHOST, false);
				// поставили адрес прокси и порт
				curl_setopt($this->oCurl, CURLOPT_PROXY, $this->proxySOCKS5);
				
				
			}

			switch ($POSTFIELDS!==null)
			{
				case true:
					curl_setopt($this->oCurl, CURLOPT_POSTFIELDS, $POSTFIELDS);
					$this->bPOST = true;
					break;

				case false:
					$this->bPOST = false;
					break;
			}

			if ($this->bDebug) { echo (($this->bPOST===true)?'POST':'GET') . " getUrlContent('".$URL."'" . (($this->bPOST===true)?", '".$POSTFIELDS."'":'') . "). Get data..."; }
			$data = curl_exec($this->oCurl);
			if ($this->bDebug) {  echo " error: ".curl_error($this->oCurl).' Ok.<br/>'; }

            if ($this->bCacheSaveEnabled) $this->putToCache($URL, $POSTFIELDS, $hintURL, $hintPOSTFIELDS, $data);

			//$header=substr($data,0,curl_getinfo($this->oCurl,CURLINFO_HEADER_SIZE));
			//$body=substr($data,curl_getinfo($this->oCurl,CURLINFO_HEADER_SIZE));

			$header=mb_substr($data,0,curl_getinfo($this->oCurl,CURLINFO_HEADER_SIZE));
			$body=substr($data,curl_getinfo($this->oCurl,CURLINFO_HEADER_SIZE));


			preg_match_all("/Set-Cookie: (.*?)=(.*?);/i",$header,$res);
			$this->sNew_cookie='';
			foreach ($res[1] as $key => $value) {
				$this->sNew_cookie .= $value.'='.$res[2][$key].';';
			};

			if ($this->bDebug) {  echo "cookies received: ". ($this->sNew_cookie) .'<br/>'; }

			if ( $this->bAutoUpdateReferer ) {
				this::setReferer($URL);
			}

			if ( $this->sEncoding ) return mb_convert_encoding($body, 'utf-8', $this->sEncoding);
			return $body;
		}

		function closeCurl()
		{
			if ( is_object($this->oCurl))
			{
				curl_close($this->oCurl);
			}
		}

		function __destruct()
		{
			echo 'Curl __destruct <br/>';
			$this->closeCurl();
		}

        function putToCache($URL, $POSTFIELDS, $hintURL, $hintPOSTFIELDS, $content)
        {
            $maskedURL = str_ireplace($hintURL, '%$#@!URLHINT!@#$%', $URL);
            $maskedPOSTFIELDS = str_ireplace($hintPOSTFIELDS, '%$#@!POSTFIELDSHINT!@#$%', $POSTFIELDS);
            $keyHash = md5($maskedURL.$maskedPOSTFIELDS);
            self::$cache["$keyHash"] = $content;
        }

        function getFromCache($URL, $POSTFIELDS, $hintURL, $hintPOSTFIELDS)
        {
            $maskedURL = str_ireplace($hintURL, '%$#@!URLHINT!@#$%', $URL);
            $maskedPOSTFIELDS = str_ireplace($hintPOSTFIELDS, '%$#@!POSTFIELDSHINT!@#$%', $POSTFIELDS);
            $keyHash = md5($maskedURL.$maskedPOSTFIELDS);
            return self::$cache["$keyHash"];
        }

	}