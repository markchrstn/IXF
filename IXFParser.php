<?php
/**
* Description: This class converts DB2 PC/IXF file into JSON file.
*
*@param1 int
*@param2 string
*@param3 boolean
*@return object with 
*/


class IXFParser {

	protected $mem;
	protected $var2;

	// Constructor boilerplate
	// and implementation of relevant interfaces
	public function __construct($var1, $var2)
	{
		$this->mem = '500M';
		$this->var2 = $var2;		
	}

	public function getHeader($feed, $start)	
	{
		// read offsets into array struct

		//print date("Y-m-d H:i:s")." Start reading header section for $feed ".PHP_EOL;

		
		$offsets = [
			 'IXFHRECL'=> 6,
			 'IXFHRECT' => 1,
			 'IXFHID' =>  3,  
			 'IXFHVERS' =>  4,  
			 'IXFHPROD' =>  12,  
			 'IXFHDATE' =>  8,  
			 'IXFHTIME' =>  6,  
			 'IXFHHCNT' =>  5,  
			 'IXFHSBCP' =>  5,  
			 'IXFHDBCP' =>  5,  
			 'IXFHFIL1' =>  2 			
		];

		

		$ret = $this->conv($feed, $start,  $offsets);
		
		return $ret;

	}//end func

	public function getTable($feed, $start)	
	{

		$offsets = [
			'IXFTRECL' =>      6,
			'IXFTRECT' =>       1,
			'IXFTNAML' =>       3,
			'IXFTNAME' =>       256,
			'IXFTQULL' =>       3,
			'IXFTQUAL' =>       256,
			'IXFTSRC' =>        12,
			'IXFTDATA' =>       1,
			'IXFTFORM' =>       1,
			'IXFTMFRM' =>       5,
			'IXFTLOC' =>        1,
			'IXFTCCNT' =>       5,
			'IXFTFIL1' =>       2,
			'IXFTDESC' =>       30,
			'IXFTPKNM' =>       257,
			'IXFTDSPC' =>       257,
			'IXFTISPC' =>       257,
			'IXFTLSPC' =>       257		
		];

		//print date("Y-m-d H:i:s")." Start reading table section for $feed ".PHP_EOL;	

		$ret = $this->conv($feed, $start, $offsets);
		
		return $ret;

	}//end func

	public function getColumnDescriptor($feed, $start)	
	{
		
		$offsets = [
				'IXFCRECL' =>       6,
				'IXFCRECT' =>       1,
				'IXFCNAML' =>       3,
				'IXFCNAME' =>       256,
				'IXFCNULL' =>       1,
				'IXFCDEF' =>        1,
				'IXFCSLCT' =>       1,
				'IXFCKPOS' =>       2,
				'IXFCCLAS' =>       1,
				'IXFCTYPE' =>       3,
				'IXFCSBCP' =>       5,
				'IXFCDBCP' =>       5,
				'IXFCLENG' =>       5,
				'IXFCDRID' =>       3,
				'IXFCPOSN' =>       6,
				'IXFCDESC' =>       30,
				'IXFCLOBL' =>       20,
				'IXFCUDTL' =>		3,
				'IXFCUDTN' =>       256,
				'IXFCDEFL' =>       3,
				'IXFCDEFV' =>       254,
				'IXFCREF' =>        1,
				'IXFCNDIM' =>       2,
				'IXFCDSIZ' =>       1

		];

		//print date("Y-m-d H:i:s")." Start reading column section for $feed ".PHP_EOL;		

		$ret = $this->conv($feed, $start, $offsets);
		
		return $ret;

	}//end func	

	public function getData($feed, $start)	
	{
		
		
		$offsets = [
			'IXFDRECL' =>     6,
			'IXFDRECT' =>       1,
			'IXFDRID'   =>      3,
			'IXFDFIL1'  =>      4,
			'IXFDCOLS'  =>    1		
		];

		$ret = $this->conv($feed, $start, $offsets);
		
		return $ret;

	}//end func

	
	public function getMeta($feed)	
	{
		$hstart = 0;
		$h = $this->getHeader($feed, $hstart); //			var_dump($h);

		$tstart = 6 + $h['IXFHRECL'];
		$t = $this->getTable($feed, $tstart); //			var_dump($t);	
		
		$numColumns = (int)$t["IXFTCCNT"];
		print "We have $numColumns columns.".PHP_EOL;
		
		$cstart = 6 + $tstart + $t['IXFTRECL'];

		for($i = 0, $s = $cstart ; $i < $numColumns; $i++)			
		{
			$c[$i] = $this->getColumnDescriptor($feed, $s);				
			$s += $c[$i]['IXFCRECL'] + 6;
		
		}	
		//var_dump($c);

		//print "The start \$hstart = $hstart and \$tstart = $tstart and \$cstart = $cstart".PHP_EOL;

		$ret['recLength'] = 0;

		foreach($c as $v)
		{
			$columnName = trim($v['IXFCNAME']);
			$recLength = (int)$v['IXFCRECL'];

			$columnLength = (int)$v['IXFCLENG']; // data
		//	print "column name is $columnName and it is $columnLength bytes long. ".PHP_EOL;

			$ret[$columnName] = $columnLength;	
			$ret['recLength'] += $columnLength;	
		}

		$ret['start'] = $s;

		//print "The start \$hstart = $hstart and \$tstart = $tstart and \$cstart = $cstart".PHP_EOL;

		return $ret;
		

	}//end func

	public function conv($feed, $start, $offsets)	
	{
		$fp = fopen($feed,'r'); 
		//move file pointer to the start position
		fseek($fp, $start, SEEK_SET);

		foreach($offsets as $k => $v)
				{				
					
					if($k == 'IXFCDSIZ') // last column of COLUMN DESCRIPTOR
					{						
						$lv = (int)$ret['IXFCRECL'] - 868;	
						$ret[$k] = fread($fp, $lv);
						$ret['pointer'] = ftell($fp);
					}
					elseif($k == 'IXFDCOLS') // last column of DATA COL
					{	//print "\$ret['IXFDRECL'] = ".$ret['IXFDRECL'];					
						//$lv2 = (int)$ret['IXFDRECL'] - 14;	
						$lv2 = (int)$ret['IXFDRECL'] - 8;	
						$ret[$k] = fread($fp, $lv2);
						$ret['pointer'] = ftell($fp);
					}
					else
					{
						$ret[$k] = fread($fp, $v); //read chunk						
					}
									
				}

		return $ret;

	}//end func

	public function process($f, $outfile)	
	{
		$m = $this->getMeta($f);	//var_dump($m);

		ini_set('memory_limit',$this->mem);

		$st = $m["start"];

		$out = [];
		$n = 0;
		do 
		{
			//print "$n".PHP_EOL; 
			$n++;
			$d = $this->getData($f, $st); //var_dump($d);
			
			$out[$n]["PRODUCT_KEY"] = trim(substr($d["IXFDCOLS"], 0, 11));
			$out[$n]["REVENUE_DIVISION"] = trim(substr($d["IXFDCOLS"], 12, 2));
			$out[$n]["KEY_PRODUCT_ID"] = trim(substr($d["IXFDCOLS"], 14, 4));
			$out[$n]["DESCRIPTION"] = substr($d["IXFDCOLS"], 18, 40);
			
			$st = $d['pointer'];
		}
		while($d["IXFDRECT"] == "D" );

		$json = json_encode($out,JSON_PRETTY_PRINT);		
		$w = file_put_contents($outfile, $json,FILE_APPEND);		
		
		return $n;

	}//end func

}//end class



?>