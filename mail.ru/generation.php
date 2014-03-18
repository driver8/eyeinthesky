<?php
function Cnm($alphabet, $m, $repeats = false)
{
	$result = array();
	// init
	foreach($alphabet as $a)
	{
		$tmp_array = array();
		$tmp_array[] = $a;
		$result[] = $tmp_array;
	}
	
	$keepGoing = true;
	
	while($keepGoing)
	{
		$result_tmp = $result;
		$result = array();
		$keepGoing = false;
		foreach($result_tmp as $r)
		{
			if (count($r) < $m)
			{
				$keepGoing = true;
				if ($repeats)
				{
					$tmp_array = $alphabet;
				}else
				{
					$tmp_array = array_diff($alphabet,$r);
				}
				
				foreach($tmp_array as $a2)
				{
					$r2 = $r;
					$r2[] = $a2;
					$result[] = $r2;
				}
			}else{
			
				$result[] = $r;
			}	
		}
		
	}
	
	return $result;
}

function CnmAll($alphabet, $m, $repeats = false)
{
	$result = array();
	
	for ($i = 1; $i<=$m; $i++)
	{
		$result = array_merge(Cnm($alphabet, $i, $repeats), $result);
	}

	return $result;
}

function CnmAllWithGlues($alphabet,$m,$repeats,$glues,$differentGlues = false)
{
	return false;
}


?>