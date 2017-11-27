<?php
/**
 * 号码抓取
 * 号码添加
 * 参数1    彩种Id
 * 		   默认 为0，全部
 * 
 */
require '/var/w600/phpcms/base.php';
ob_end_clean ();
pc_base::load_sys_class ( 'basecli', '', 0 );
class cleardata extends basecli {

	// 今天开始时间戳
	private $iToday000 = 0;
	private $sLockFilename = '';
	// TODO
	private $sPostUrl = 'http://www.w600.com/lottery/source_api/add';
	private $iMixKeepDays = 5;
	public function __construct() {
		$this->iToday000 = strtotime ( '00:00:00' );
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
		// 彩种ID
		$iLotteryId = intval ( $this->aArgv [1] );

		// 检查是否已有相同CLI在运行中
		$this->sLockFilename = $sLocksFileName = 'lottery_' . $iLotteryId . '.locks';

		if (file_exists ( $sLocksFileName )) {
			// 超过10分钟，删除锁文件
			if (filectime ( $sLocksFileName ) < SYS_TIME - 60 * 10) {
				@unlink ( $sLocksFileName );
			} else {
				// 如果有运行的就终止本个CLI
				exit ( '[d] [' . date ( 'Y-m-d H:i:s' ) . '] The CLI is running' );
			}
		}
		file_put_contents( $sLocksFileName ,'running', LOCK_EX ); // CLI 独占锁
		echo '[d] [' . date ( 'Y-m-d H:i:s' ) . "] --------------START\n";

		$this->bFinished = true;

		$oGameModel = pc_base::load_model ( 'lottery_game_model' );
		$oSource = pc_base::load_model('lottery_catch_source_model');
		$oGameCenter = pc_base::load_app_class('game_center','lottery');
		$oCurlHttp = pc_base::load_sys_class ( 'curl_http' );
		//开奖api
		include PC_PATH . 'modules/lottery/kj_api.php';
		//入库api
		include PC_PATH . 'modules/lottery/source_api.php';

		$aGames = $oGameModel->get_games ();
		$KJ_api = new kj_api ();

		foreach ( $aGames as $k => $v ) {
			if ($iLotteryId !=0 && $v ['id'] != $iLotteryId) {
				continue;
			} 

			$sMethod = 'kj_' . $v ['name_en'];
			if (is_callable ( array ($KJ_api,$sMethod ) )) {
				//开奖号码
				$sBall = $KJ_api->$sMethod ( true );

				//获取奖源信息
				$aWhere = array(
						'tablename' => $v['tablename'],
				);
				$aSource = $oSource->get_one($aWhere);

				if(empty($aSource)){
					continue;
				}

				$aData['sourceid'] = $aSource['id'];
				$aData['key']      = $aSource['key'];

				$oGame = $oGameCenter->get_game($v['id']);
				//当前期,奖期
				$sCurIssue = $oGame->get_current_number_time()['number'];
				$aCurIssueInfo = $oGame->get_game_numberinfo($sCurIssue);
				//上一期奖期。
				$aLastIssue = $oGame->get_last_info_by_numberid($aCurIssueInfo['id']);
				if(empty($aLastIssue)){
					continue;
				}
				$aData['number'] = $aLastIssue['number'];
				$aData['balls'] = $sBall;

				$aResult = curl_http::send ( $this->sPostUrl, $aData, 'post' );

					
				if ($aResult ['isSuccess']) {
					echo '--' . str_pad($v['name_en'], 15,'-',STR_PAD_RIGHT) . str_pad($aData['number'], 12,'-',STR_PAD_RIGHT) ."---$aResult[msg]---$sBall---成功录入!!-0028\n";
				} else {
					echo '--' . str_pad($v['name_en'], 15,'-',STR_PAD_RIGHT) . str_pad($aData['number'], 12,'-',STR_PAD_RIGHT) ."---$aResult[msg]-----添加号码发生错误!!-0030\n";
				}


			}
		}
	}
}

$oCli = new cleardata ();


