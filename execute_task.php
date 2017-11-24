<?php
/**
 * 号码抓取
 * 号码添加
 * 参数1     彩种Id
 * 		    默认 为0，全部
 * 
 */
require '/var/w600/phpcms/base.php';
ob_end_clean ();
pc_base::load_sys_class ( 'basecli', '', 0 );
class cleardata extends basecli {
	private $sTaskPrefix = 'caipiao:task_';
	private $sLockFilename = '';
	// TODO
	private $sPostUrl = 'http://www.w600.com/lottery/task_api/execute';
	public function __construct() {
		parent::__construct ();
	}
	
	/**
	 * 析构函数, 程序完整执行成功或执行错误后.
	 * 删除 locks 文件
	 */
	public function __destruct() {
		if ($this->bFinished == true) {
			@unlink ( $this->sLockFilename );
		}
	}
	
	/**
	 * 重写基类 _runCli() 方法, 程序主流程
	 */
	protected function _runCli() {
		$aTaskLable = array (
				'ffc_a',
				'ffc_b' 
		);
		// 彩种ID
		if (in_array ( $this->aArgv [1], $aTaskLable )) {
			$sTaskLable = $this->aArgv [1];
		} else {
			$sTaskLable = 'ffc_a';
		}
		
		$sTaskLableKey = $this->sTaskPrefix . $sTaskLable;
		
		// 检查是否已有相同CLI在运行中
		$this->sLockFilename = $sLocksFileName = 'lottery_' . $sTaskLable . '.locks';
		
		if (file_exists ( $sLocksFileName )) {
			// 超过10分钟，删除锁文件
			if (filectime ( $sLocksFileName ) < SYS_TIME - 60 * 10) {
				@unlink ( $sLocksFileName );
			} else {
				// 如果有运行的就终止本个CLI
				exit ( '[d] [' . date ( 'Y-m-d H:i:s' ) . '] The CLI is running' );
			}
		}
		//file_put_contents ( $sLocksFileName, 'running', LOCK_EX ); // CLI 独占锁
		echo '[d] [' . date ( 'Y-m-d H:i:s' ) . "] --------------START\n\n";
		
		$this->bFinished = true;
		
		pc_base::load_sys_class ( 'NewRedis','',0 );
		
		$oCurlHttp = pc_base::load_sys_class ( 'curl_http' );
		
		$oNewRedis = new NewRedis();
		$oNewRedis->setDatabase ('0');
		$iCount = $oNewRedis->lLen ( $sTaskLableKey );
		
		$iNum = 0;
		
		for($i = 0; $i < $iCount; $i ++) {
			
			$aData = $oNewRedis->RpopJson($sTaskLableKey);
			
			$aResult = curl_http::send ( $this->sPostUrl, $aData, 'post' );
			
			if ($aResult ['isSuccess']) {
				$iNum++;
				echo '--' . str_pad ( $aData ['_command'], 15, '-', STR_PAD_RIGHT ) . str_pad ( $aData ['game_name'], 12, '-', STR_PAD_RIGHT ) . "---$aData[number]---成功!!-0028\n";
			} else {
				echo '--' . str_pad ( $aData ['_command'], 15, '-', STR_PAD_RIGHT ) . str_pad ( $aData ['game_name'], 12, '-', STR_PAD_RIGHT ) . "---$aData[number]---失败!!-0030\n";
			}
		}
		
		echo "\n[d] [" . date('Y-m-d H:i:s') ."] --------------END\n";
		 
		echo "一共{$iCount}--成功处理---$iNum-----\n\n";
	}
}

$oCli = new cleardata ();


