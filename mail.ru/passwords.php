<?
class Passwords
{
	private $sqlite;
	
	public function __construct($fname)
	{
		if (file_exists($fname))
		{
			$this->sqlite = new SQLite3($fname,SQLITE3_OPEN_READWRITE);
		}else
		{
			$this->sqlite = new SQLite3($fname,SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
			$this->sqlite->exec('create table passwords (password TEXT PRIMARY_KEY UNIQUE, length INTEGER, mark INTEGER)');
			$this->sqlite->exec('create index idx on passwords(password)');
		}
	}
	
	public function addPassword($password)
	{
		$this->sqlite->query("insert or ignore into passwords values ('$password', ".strlen($password)." ,0)");
	}
	
	public function markPassword($password,$mark)
	{
		$this->sqlite->query("update passwords set mark=$mark where password='$password'");
	}
	
	public function getNextPassword($mark = 0, $length = null)
	{
		$res = null;
		
		if ($length !== null)
		{
			$res = $this->sqlite->query("select password from passwords where mark=$mark and length=$length limit 1");
		}else
		{
			$res = $this->sqlite->query("select password from passwords where mark=$mark limit 1");
		}	
		
		if ($res)
		{
			$res = $res->fetchArray();
			return $res['password'];
		}
		
		return null;
	}
	
	public function getPasswordCount()
	{
		return 0;
	}
}




?>