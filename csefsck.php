<?php
//File System Checker
$blocksize=4096;
$devid=20;
$eofflag=0;
$freeblock=25; 
$blockcount=31;

for($i=0;$i<$blockcount;$i++) 
{
	//check for presence of files
	if(!(stream_resolve_include_path("FS/fusedata.".$i)==true))
	{
		echo "\nError:FileSystem is corrupted! Block $i not found!";
		$eofflag=1;
		goto endoffile;
	}
}
//1.
$fusedata0_json = file_get_contents("FS/fusedata.0",FILE_USE_INCLUDE_PATH);
//go to end of file if fusedata0 does not exist
if($fusedata0_json==false)
{
	echo "\nError:FileSystem is corrupted! Super Block not found!";
	$eofflag=1;
	goto endoffile;
}
//decode the json
$fusedata0 = json_decode($fusedata0_json);

//check the value of file's devId
if($fusedata0->devId==$devid)
	echo "\n\n\n1) DevID is correct!\n";

//correct devID
else 
{
	$fusedata0->devId = $devid;
	$fusedata0_corrected = json_encode($fusedata0);
	file_put_contents('FS/fusedata.0', $fusedata0_corrected,FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");
	echo "\n1) DevID is incorrect! Corrections made to the superblock!\n";
	
}
//2.
$blockcount=31;
$i=0;
$flagtime=0;

//fetch current time
	$currtime=explode(" ",microtime());

for($i=0;$i<$blockcount;$i++) 
{
	//fetch contents of ith file
	$fusedata_json[$i] = file_get_contents("FS/fusedata.".$i,FILE_USE_INCLUDE_PATH);
	if((preg_match("%time%",$fusedata_json[$i])==true) or (preg_match("%Time%",$fusedata_json[$i])==true))
	{
		//decode the json
		$fusedata[$i] = json_decode($fusedata_json[$i],true);
		foreach ($fusedata[$i] as $key => &$value) {
			if((preg_match("%time%",$key)==true) or (preg_match("%Time%",$key)==true))
			{
				if($value > $currtime[1])
				{
					echo("\n\n\n2) Time Errors found in fusedata$i!");
					echo "\n";
					echo $key . ': ' . $value .' is not in past';
        			echo "\n";
        			$value = $currtime[1];
        			$fusedata_corrected[$i] = json_encode($fusedata[$i]);
        			file_put_contents("FS/fusedata.".$i,$fusedata_corrected[$i],FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");
        			$flagtime=1;
				}
			}	
		}
	}
}
if($flagtime==1)
{
	echo("\n Changes have been made to the blocks!\n");
}
else
	echo("\n2) No Time Errors found!\n");

//3.

$blockcount=31;
$i=0;
$flagfreeblock=0;

for($i=0;$i<10000;$i++)
{
	$arrfreeblock[$i]=1;
}

$fusedata_json = file_get_contents("FS/fusedata.0",FILE_USE_INCLUDE_PATH);

//go to end of file if fusedata0 does not exist
if($fusedata_json==false)
{
	echo "\n\nFatal Error:FileSystem is corrupted! Super Block not found!";
	$eofflag=1;
	goto endoffile;
}

//fetch the value of start and end free block
$fusedata0 = json_decode($fusedata0_json);

//check the value of file's devId
if($fusedata0->freeStart == 1 && $fusedata0->freeEnd == 25)
	echo "\n3)Free block start and end positions are correct!\n";
else
{
	$fusedata0->freeStart = 1;
	$fusedata0->freeEnd = 25;
	echo "\n3)Free block start or end positions are incorrect! Necessary changes have been made to the superblock!";
	$fusedata0_corrected = json_encode($fusedata0);
	file_put_contents('FS/fusedata.0', $fusedata0_corrected,FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");
}
for($i=0;$i<$blockcount;$i++) 
{
	//fetch contents of ith file
	$fusedata[$i] = file_get_contents("FS/fusedata.".$i,FILE_USE_INCLUDE_PATH);
		
	if($fusedata[$i]==false)
	{
		$arrfreeblock[$i]=1;
	}
	else if(trim($fusedata[$i])=="")
	{
		$arrfreeblock[$i]=1;
	}
	else $arrfreeblock[$i]=0;
}
$flagfree=0;

for($i=0;$i<10000;$i++)
{
	$flagfree=0;
	for($j=1;$j<=$freeblock;$j++)
	{
		if(file_get_contents("FS/fusedata.".$j,FILE_USE_INCLUDE_PATH)==true)
		{
			$fusedata[$j] = file_get_contents("FS/fusedata.".$j,FILE_USE_INCLUDE_PATH);
			$len = strlen($fusedata[$j]);
			$fusedata[$j] = substr($fusedata[$j], 1 ,$len-2);//removing []
		
			$tempp=explode(",",$fusedata[$j]);
		
			if(preg_match("%,".$i.",%",$fusedata[$j]) || $i==$tempp[0] || $i==trim($tempp[sizeof($tempp)-1]))
			{
				$flagfree=1;
			
				//remove blocks from free block list if they are occupied
				if($arrfreeblock[$i]==0)
				{
					echo("\nBlock $i is occupied but is in free block list! Entry removed from the free block list");
				
					if($i==$tempp[0])
					{
						$fusedata[$j]=substr($fusedata[$j],(strpos($fusedata[$j],",")+1));
					}
					else if($i==trim($tempp[sizeof($tempp)-1]))
					{
						$fusedata[$j]=substr($fusedata[$j],0,(strlen($fusedata[$j])-strlen($tempp[sizeof($tempp)-1])-1));
					}
					else if(preg_match("%,".$i.",%",$fusedata[$j]))
						$fusedata[$j]=str_replace(",".$i.",",",",$fusedata[$j]);

					$fusedata[$j] = "[".$fusedata[$j]."]"; //adding []
					file_put_contents("FS/fusedata.".$j,$fusedata[$j],FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");	
				}
				$j=$freeblock+1;
			}
		}
	}
	if($flagfree==0 && $arrfreeblock[$i]==1)
	{
		for($j=1;$j<=$freeblock;$j++)
		{
			$fusedata[$j] = file_get_contents("FS/fusedata.".$j,FILE_USE_INCLUDE_PATH);
			$len = strlen($fusedata[$j]);
			$fusedata[$j] = substr($fusedata[$j], 1 ,$len-2);

			$temparr=explode(",",$fusedata[$j]);
			
			if(sizeof($temparr)<400)
			{
				$fusedata[$j]=$fusedata[$j].",".$i;
				$fusedata[$j]= "[".$fusedata[$j]."]";
				file_put_contents("FS/fusedata.".$j,$fusedata[$j],FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");				
				echo("\nBlock $i is free but is missing from free block list! Entry appended in the free block list fusedata.$j");
				$j=$freeblock+1;
			}
		}
	}		
}

//4.

$blockcount=31;
$i=0;
$direrror=0;
$flagonedotpresent=0;
$flagtwodotpresent=0;

for($i=0;$i<$blockcount;$i++) 
{	
	//fetch contents of ith file
	$fusedata_json[$i] = file_get_contents("FS/fusedata.".$i,FILE_USE_INCLUDE_PATH);
	
	if(preg_match("%filename_to_inode_dict%",$fusedata_json[$i])==true)//check directory
	{
		$arrfusedata= json_decode($fusedata_json[$i]);
		foreach ($arrfusedata->filename_to_inode_dict as &$key)
		{
			if($key->type=="d" && $key->name=".")
			{
				$flagonedotpresent=1;
				if($key->location!=$i)//check block number (should be pwd)
				{
					echo("\n4)Directory Pointer Error Detected in fusedata.".$i);
					$key->location = $i; //correcting block location to current file
					$fusedata_corrected[$i] = json_encode($arrfusedata[$i]);
					file_put_contents("FS/fusedata.".$i,$fusedata_corrected[$i],FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");
					echo("\n4)Changes made to fusedata.".$i);
					$direrror=1;
				}
			}
			if ($key->type=="d" && $key->name="..") 
			{
				$flagtwodotpresent=1;
				$parentdir = $key->location; //location of parent dir
				//opening parent file to check for child reference
				$fusedata_parent = file_get_contents("FS/fusedata.".$$parentdir,FILE_USE_INCLUDE_PATH);
				$fusedata_parent_decode = json_decode($fusedata_parent);
				foreach ($fusedata_parent_decode->filename_to_inode_dict as &$ckey)
				{
					if(preg_match("%filename_to_inode_dict%",$ckey)==true) //check if parent is a dir
					{	
						if($ckey->type = "d" && $ckey->location = $i)
							echo " Child reference found";
						else
						{
							//point parent dir to itself
							$key->location = $i;
							$fusedata_corrected[$i] = json_encode($arrfusedata[$i]);
							file_put_contents("FS/fusedata.".$i,$fusedata_corrected[$i],FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");
							echo("\n4)Changes made to fusedata.".$i);
							$direrror=1;
						}
					}
					else
					{
						//point parent dir to itself
						$key->location = $i;
						$fusedata_corrected[$i] = json_encode($arrfusedata[$i]);
						file_put_contents("FS/fusedata.".$i,$fusedata_corrected[$i],FILE_USE_INCLUDE_PATH | LOCK_EX) or die("File suddenly lost!");
						echo("\n4)Changes made to fusedata.".$i);
						$direrror=1;
					}
				}
			}
		}		
	}	
}
$blockcount=10000;
if($flagonedotpresent=0 || $flagtwodotpresent=0)
	echo("\n4)Directory Error Detected! Missing . or ..");
if($direrror==0)
	echo("\n4)Directory Error NOT Detected!\n");
			
				
//5 and 6.
$blockcount=31;

for($i=0;$i<$blockcount;$i++) 
{
	$flagindirectinvalid=0;
	$reallocation=0;
	
	//fetch contents of ith file
	$fusedata= file_get_contents("FS/fusedata.".$i,FILE_USE_INCLUDE_PATH);
	
	//check if file contains indirect string
	if(preg_match("%indirect%",$fusedata)==true)
	{
		
		$fusedata = json_decode($fusedata);
		$indirectval = $fusedata->indirect;
		
		if($indirectval == 1)
		{
			$loc = $fusedata->location;
			$pointerloc = file_get_contents("FS/fusedata.".$loc,FILE_USE_INCLUDE_PATH);
			$pointerloc = substr($pointerloc, 1 ,2);
			$arraydata = file_get_contents("FS/fusedata.".$pointerloc,FILE_USE_INCLUDE_PATH);
			$isarray = is_array($arraydata);

			echo "5) Indirect = 1 and data in the block pointed by location pointer is an array\n";
			//array data 
			echo "The array data is : ".$arraydata."\n";

		}

		//6
		if($indirectval == 0)
		{
			if($fusedata->size < $blocksize && $fusedata->size > 0)
				echo "6) Size is valid for the number of block pointers in the location array\n";
			else
				echo"6) Size is invalid for the number of block pointers in the location array\n";
		}
		//case 2 and 3
		elseif($indirectval == 1) //  single level of indirection indirect = 1
		{
			//fetch data from correct location
			$loc = $fusedata->location;
			$pointerloc = file_get_contents("FS/fusedata.".$loc,FILE_USE_INCLUDE_PATH);
			$pointerloc = substr($pointerloc, 1 ,2);
			$arraydata = file_get_contents("FS/fusedata.".$pointerloc,FILE_USE_INCLUDE_PATH);
			//array data 
			$len = strlen($arraydata);
			$param1 = $blocksize * $len;
			$param2 = $blocksize * ($len-1);
			if($fusedata->size < $param1 && $fusedata->size < $param2) // case 2 or 3
				echo "6) Size is valid for the number of block pointers in the location array\n";
			else
				echo"6) Size is invalid for the number of block pointers in the location array\n";
		}
	}

}

endoffile:
if($eofflag==1)
	echo "\nKindly add the required files";
?>
