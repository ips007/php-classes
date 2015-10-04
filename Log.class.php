<?php
class Log {
	//日记级别
	private $DEBUG		= 1;
	private $INFO		= 2;
	private $WORNING	= 3;
	private $ERROR		= 4;

	//日记文件分类方式
	public static $SHIFT_BY_SIZE	= 1;
	public static $SHIFT_BY_DAY		= 2;

	//IP 地址
	private $strIp;

	//文件对象
	private $objFile;

	//保存文件名
	private $strToFile;

	//日记记录方式
	private $strLogMode; //screen or file or both

	//日记文件最大容量
	private $intShiftMax;

	//日记文件划分标准
	private $intShiftType; //size day

	//保留日记文件数目
	private $intFileNum;

	//记录日记级别
	private $intLogLevel;

	//本类的对象，实现单例模式
	private static $objLog;

	//当前日记文件个数
	private $intFileCount	= 5;
	private $logInfo		= array(1 => "Debug", "Info", "Worning", "Error");

	//构造函数
	//private function __construct($strToFile, $strLogMode = "file", $intLogLevel = 1, $intShiftType = 1, $intShiftMax = 5242880, $intFileNum = 2) {
	private function __construct($params = array()) {
		if(!isset($params['strToFile'])  ||  '' == $params['strToFile']){
			throw new LogException("strToFile is Wrong!");
		}

		$this->strToFile	= $params['strToFile'];
		isset($params['strLogMode']) ? $this->strLogMode = $params['strLogMode'] : $this->strLogMode = 'file';
		isset($params['intLogLevel']) ? $this->intLogLevel = $params['intLogLevel'] : $this->intLogLevel = 1;
		isset($params['intShiftType']) ? $this->intShiftType = $params['intShiftType'] : $this->intShiftType = 1;
		isset($params['intShiftMax']) ? $this->intShiftMax = $params['intShiftMax'] : $this->intShiftMax = 5242880;
		isset($params['intFileNum']) ? $this->intFileNum = $params['intFileNum'] : $this->intFileNum = 5;

		if (!empty ($_SERVER['REMOTE_ADDR'])) {
			$this->strIp	= $_SERVER['REMOTE_ADDR'];
		} else {
			$this->strIp	= 'NO IP';
		}
	}
	private function closelogfile() {
		if(isset($this->objfile)){
			fclose($this->objfile);
			unset($this->objfile);
		}
	}
	function __destruct() {
		$this->closelogfile();
	}
	private function fopenlogfile($strfile) {
		$objfile	= fopen($strfile, "a+");
		try {
			if ($objfile == false) {
				$strerror	= 'err:can not open ' . $strfile . ' with mode a+ !';
				$strerror	.= "\n";
				throw new logexception($strerror);
			}
		} catch (exception $objexpetion) {
			$objexpetion->makelogexeption();
		}
		return $objfile;
	}

	//统计当前日记文件数目
	public function logFilesCount(){
		$pathInfo	= explode('/',$this->strToFile);

		$counts		= count($pathInfo);
		$logPath	= '';
		$logFile	= '';
		if($counts > 1){
			for($i = 0; $i < $counts - 1; $i ++){
				$logPath	.= $pathInfo[$i].'/';
			}
			$logFile	= $pathInfo[$counts - 1];
		}else{
			$logPath	= '.';
			$logFile	= $this->strToFile;
		}

		$dirStr		= scandir($logPath);
		$fileCounts	= 0;
		foreach($dirStr as $key => $val){
			if(!is_file($val))
				continue;

			if(preg_match("/$logFile\d*/",$val)){
				$fileCounts	++;
			}
		}

		$this->intFileCount	= $fileCounts;
	}

	//添加或删除日记文件
	private function shift(){
		if($this->intFileNum > 1){
			fclose($this->objFile);
			$this->logFilesCount();
			for($i = $this->intFileCount - 1; $i >= 0; $i--){
				if(file_exists($this->strToFile.($i+1))){
					unlink($this->strToFile.($i+1));
				}
				if($i == $this->intFileNum - 1 && $i != 0){
					if(file_exists($this->strToFile.$i)){
						unlink($this->strToFile.$i);
					}
				}
				else if($i == 0){
					rename($this->strToFile, $this->strToFile.($i + 1));
				}
				else{
					rename($this->strToFile.$i, $this->strToFile.($i+1));
				}
			}
			$this->intFileCount++;
			if($this->intFileCount >= $this->intFileNum){
				$this->intFileCount		= $this->intFileNum;
			}
			$this->objFile	= $this->fopenLogFile($this->strToFile);
		}
		else{
			//设文件大小为 0
			ftruncate($this->objFile, 0);
		}
	}
	private function genLogFile(){
		if(!isset($this->objFile)){
			$this->objFile	= $this->fopenLogFile($this->strToFile);
		}
		else{
			$fileInfo	= fstat($this->objFile);
			switch ( $this->intShiftType ) {
			case self::$SHIFT_BY_SIZE:
				if($fileInfo["size"] >= $this->intShiftMax){
					//print_r($this);
					//print_r($fileInfo);
					$this->shift();
				}
				break;
			case self::$SHIFT_BY_DAY:
				if(time() - $fileInfo["atime"] >= (15*24*3600)){
					$this->shift();
				}
				break;
			default:
				throw new LogException("No Such ShiftType => ".$this->intShiftType);
				break;
			}
		}
	}

	//输出屏幕
	private function logToScreen($strToLog){
		echo rtrim($strToLog, "\n")."";
	}

	//保存到文件
	private function logToFile($strToLog){
		$this->genLogFile();
		fwrite($this->objFile, $strToLog);
	}

	//
	private function logThisInFile($intLogLevel, $strToLog) {
		$strToLog	= '['.date('Y-m-d') . " " . date('H:i:s') . "]::[" . $this->strIp . "]::[" . $this->logInfo[$intLogLevel] . "]::".$strToLog."\n";
		switch ( $this->strLogMode ) {
		case "screen":
			$this->logToScreen($strToLog);
			break;
		case "file":
			$this->logToFile($strToLog);
			break;
		case "both":
			$this->logToScreen($strToLog);
			$this->logToFile($strToLog);
			break;
		default:
			break;
		}
	}

	public function logError($strToLog){
		$this->logThis($this->ERROR,$strToLog);
	}
	
	public function logWorning($strToLog){
		$this->logThis($this->WORNING,$strToLog);
	}
	
	public function logInfo($strToLog){
		$this->logThis($this->INFO,$strToLog);
	}
	
	public function logDebug($strToLog){
		$this->logThis($this->DEBUG,$strToLog);
	}
	
	public function logThis($intLogLevel, $strToLog) {
		$strToLog	= str_replace(array (
			"\n",
			"\r"
		), array (
			' ',
			' '
		), $strToLog);
		if($intLogLevel >= $this->intLogLevel){
			$this->logThisInFile($intLogLevel, $strToLog);
		}
	}

	public static function getInstance($p = array()) {
		$params	= array(
			'strToFile'		=> $p['file'],
			'strLogMode'	=> $p['mode'],
			'intLogLevel'	=> $p['level'],
			'intShiftType'	=> $p['type'],
			'intShiftMax'	=> $p['max'],
			'intFileNum'	=> $p['num'],
		);
		if(!isset(self::$objLog)){
			try{
			self::$objLog = new Log($params);
			}catch(Exception $e){
				//print_r($e);
				exit(0);
			}
		}
		return self::$objLog;
	}

	public function _toString() {
		return "—————————Conf Info: ———————————————–\n".
			"LogFile =>". $this->strToFile.",\nLogMode => ".$this->strLogMode.", \nLogLevel =>". $this->intLogLevel."\n".
			"LogShiftType => ".$this->intShiftType.",\nLogShiftMax => ".$this->intShiftMax.",\nLogFileNum => ".$this->intFileNum."\n".
			"————————————————————————————\n";
	}
}
class LogException extends Exception {
	public function __construct($strErrorMsg) {
		parent :: __construct($strErrorMsg);
	}
	public function makeLogExeption() {
		echo ' ';
		echo print_r('Err File! -> ' . $this->getFile() . ' line ' . $this->getLine() . "\n" . 'Description : ' . $this->getMessage() . "\n", true);
		echo print_r("\n" . $this->getTraceAsString(), true);
		echo '';
		exit;
	}
	public function __toString() {
		return '' . print_r($this, true) . '';
	}
}


//使用示例
$p['file']		= './logs/test.log';	//日记文件
$p['mode']		= 'file';				//日记类型：file,screen,both
$p['level']		= 1;					//记录日记级别：1，2，3，4
$p['type']		= 1;					//文件划分类别：1-文件大小，2-文件日期
$p['max']		= 500;					//文件最大容量，type 为 1 时有效
$p['num']		= 5;					//保留文件个数

$logger		= Log::getInstance($p);
$logger->logError("error message");
$logger->logWorning("worning message");
$logger->logInfo("innfo message");
$logger->logDebug("debug message");
