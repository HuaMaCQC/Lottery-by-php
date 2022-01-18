<?php
include_once('./rule.php');
// date_default_timezone_set("Asia/Taipei"); //設定時區
/**
 * class 建期數與開獎
 * 
 * @param Array $lotteryNum 開獎號碼選項
 * @param Array $lotteryType 開獎總類與期數規則
 * @param Array $lotteryrule 開獎規則
 */
class Lottery{
    private $startHour = 0;
    private $startMinute = 0;
    private $startSecond = 0; 
    private $endHour = 0;
    private $endMinute = 0;
    private $endSecond = 0;
    private $interval = 0;
    private $type = 0;
    private $issueinterval='001';
    private $newDate = 0; 
    private $rule_executor = [];
    private $conn ='';
    private $rule = [];
    //開獎
    private $lotteryNum = [];
    private $resultGenerator = [];
    private $LogData = [];
    private $Py_link = '';

    /**
     * 
     */
    public function __construct(
        $lotteryNum = array(
            'lotteryNumber' =>[],
            'maxNum' => 10,
            'minNum' => 0 ,
            'Quantity' => 10
        ),
        $lotteryType = array(
            'type' => 3,
            'issueinterval' => '001'
        ),
        $lotteryrule = array(
            'startHour' => 9,
            'startMinute' => 10,
            'startSecond' => 0,
            'endHour' => 23,
            'endMinute' => 0,
            'endSecond' => 0,
            'interval' => 10,
        )
    ){
        include('../config.inc');
        // new RULE_LOSE_UNDER_100K(),new RULE_PEOPLE_WIN()
        $this->resultGenerator = new rule_executor( new RULE_DONT_REPEAT());
        $this->startHour = $lotteryrule['startHour']; 
        $this->startMinute = $lotteryrule['startMinute'];
        $this->startSecond = $lotteryrule['startSecond'];
        $this->endHour =  $lotteryrule['endHour'];
        $this->endMinute = $lotteryrule['endMinute'];
        $this->endSecond = $lotteryrule['endSecond'];
        $this->interval = $lotteryrule['interval'];
        $this->type = $lotteryType['type'];
        $this->issueinterval=$lotteryType['issueinterval'];
        $this->conn = $db_config['r'];
        $this->lotteryNum = $lotteryNum;
        $this->LogData = array ('startHour' => $this->startHour,
                                'startMinute'=>$this->startMinute,
                                'startSecond' => $this->startSecond,
                                'endHour' =>$this->endHour,
                                'endMinute' => $this->endMinute,
                                'endSecond' => $this ->endSecond,
                                'interval'=>$this->interval,
                                'type' =>$this->type,
                                'issueinterval'=>$this->issueinterval,
                                'lotteryNum'=>$this->lotteryNum);
        $this ->Py_link = $this->linkDB();
        $this ->shishi_link = $this->linkDB('shishi');
        
    }
    /**
     * 連接DB
    */
    private function linkDB($database = 'py_lottery'){
        //$link = mysqli_connect("127.0.0.1","root","Bgg$789*","py_lottery");  //連線
        $link = mysqli_connect($this->conn['host'],$this->conn['user'],$this->conn['pass'],$database);  //連線
        if(mysqli_connect_errno()) {
            echo '與資料庫連線失敗';exit;
        }
        mysqli_set_charset($link,"utf8");
        return $link;
    }
    /**
     * 取得資料庫今天的總筆數
    */
    private function getDataLong(){
        // $link = $this->linkDB();
        $this->newDate = new DateTime();
        $sql = 'SELECT * FROM `lottery_data2` 
                WHERE created_at >= CURDATE() AND type = '.$this->type.' ORDER BY `lottery_data2`.`created_at` DESC'; //查表格
        $result = mysqli_query($this ->Py_link,$sql);
        $res = mysqli_num_rows($result);
        $this->LogData['getDataLong()'] = $res;
        return $res; 
    }
    /**
     * issue編碼
     */
    private function getissue($issue){
        $Num = $this->issueinterval;
        $newTime = date('Ymd');
        if($issue == 0){
            return $newTime.$Num;
        }else{
            return $issue + 1;
        }
    }
    /**
     * 計算今天要產生幾筆資料
     */
    private function getIssueNum(){
        $start = new DateTime($this->startHour . ':' . $this->startMinute.':'.$this->startSecond);
        $end = new DateTime($this->endHour.':'.$this->endMinute.':'.$this->endSecond);
        $interval = $start -> diff($end);
        $diffSecond = $interval ->h * 60 * 60;
        $diffSecond += $interval ->i * 60;
        $diffSecond += $interval ->s;
        $this->LogData['getIssueNum()']['diffSecon'] = $diffSecond; 
        $Num = floor($diffSecond / $this->interval) + 1; //產生$Num筆資料
        $this->LogData['getIssueNum()']['Num'] = $Num;
        return $Num;
    }
    /**
     * 產生一整天的Issue的SQL
     */
    private function getIssueSql(){
        //計算開始到結束相差多少分鐘
        $newTime =new DateTime($this->newDate->format('Y-m-d') .' '.$this->startHour . ':' . $this->startMinute.':'.$this->startSecond);
        $Num = $this->getIssueNum();
        $res = '';
        $regainSql = [];
        $issue = 0;
        $issue_data = []; 
        for($i = 1 ;$i <= $Num  ;$i++){
            $issue =  $this->getissue($issue); //取得期號
            array_push($regainSql,'("'.$newTime->format('Y-m-d H:i:s').'","'.$this->type.'","'.$issue.'","'.$i.'")'); //將結果push進陣列 
            $newTime->add(new DateInterval('PT'.$this->interval.'S') );
        }
        if(count($regainSql)> 0 ){
            $res = 'INSERT INTO lottery_data2 ( `created_at` , `type`, `issue`,`No.`) VALUES ' . join(',',$regainSql);
        }
        return $res;
    }
    /**
     * 取得開獎的sql
     */
    private function getLotterySql(){
        // $link = $this->linkDB();
        $sql = 'SELECT * FROM lottery_data2 
                WHERE result = "" 
                AND type = '.$this->type.'
                AND created_at <= SYSDATE()';
        $result = mysqli_query($this ->Py_link,$sql);
        $res = [];
        while($data = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            $issue = $data['issue'];
            $type_id = $data['type'];
            $d = join(',',$this->resultGenerator->run($type_id,$issue,$this->lotteryNum));
            $s = 'UPDATE lottery_data2 SET result = "'.$d.'" WHERE id = '.$data['id'];
            array_push($res,$s);
        }
        $this->LogData['getLotterySql()_count'] = count($res);
        return $res;
    }

    /**
     * 取得回復的sql
     */
    private function recoveryIssueSQL(){
        $n =  $this->getIssueNum();
        // $link = $this->linkDB();
        $sql = 'SELECT * FROM `lottery_data2` 
        WHERE created_at >= CURDATE()  AND type = '.$this->type.' ORDER BY `lottery_data2`.`No.` ASC'; //查表格
        $result = mysqli_query($this ->Py_link,$sql);
        $Lost = [];
        $issue = '';
        $newTime =new DateTime($this->newDate->format('Y-m-d') .' '.$this->startHour . ':' . $this->startMinute.':'.$this->startSecond);
        for($i = 1 ;$i <= $n  ;$i++ ){
            $issue =  $this->getissue($issue);
            array_push($Lost,[$i ,$issue,$newTime->format('Y-m-d H:i:s')]);
            $newTime->add(new DateInterval('PT'.$this->interval.'S') );
        }
        while($data = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            for($i = 0 ; $i < count($Lost) ; $i++){
                if($Lost[$i][0] == $data['No.']){
                    array_splice($Lost,$i,1);
                    break;
                }
            }
        }
        $addSql = [];
        for($i = 0 ; $i < count($Lost) ; $i++){
           array_push($addSql,'("'.$Lost[$i][2].'","'.$this->type.'","'.$Lost[$i][1].'","'.$Lost[$i][0].'")'); 
        }
        if(count($addSql) > 0 ){
            $res = 'INSERT INTO lottery_data2 ( `created_at` , `type`, `issue`,`No.`) VALUES ' . join(',',$addSql);
        }
        $this->LogData['recoveryIssueSQL()_addSql'] = count($addSql);
        return $res;
        
    }
    /**
     * 紀錄Log
     */
    private function Log($cataLog){
        // $link = $this->linkDB();
        $log = json_encode($this->LogData);
        $sql = "INSERT INTO debug_log ( `catalog` , `log`) VALUES ('".$cataLog."' , '".$log."')";
        $this->LogData = [];
        mysqli_query($this ->Py_link,$sql);
    }
    /**
     * 建期數
     */
    public function CreatIssue(){
        $n = $this->getDataLong();
        if( $n == 0){
            // $link = $this->linkDB();
            $sql = $this->getIssueSql();
            mysqli_query($this ->Py_link,$sql);
        }else if($n < $this->getIssueNum()){ //缺少
            // $link = $this->linkDB();
            $sql = $this->recoveryIssueSQL();
            mysqli_query($this ->Py_link,$sql);
        }
        $this->Log('CreatIssue');
    }
    private function set_Lottery_result(){
        // $link = $this -> linkDB('shishi');        
        $start = new DateTime($this->startHour . ':' . $this->startMinute.':'.$this->startSecond);
        $start = new DateTime('-1 day');
        $sql =  "SELECT `issue` FROM `Lottery_result` WHERE 
                open_at >= '".$start->getTimestamp()."' 
                AND open_at <= UNIX_TIMESTAMP() 
                AND `type` = '".$this->type."' AND `numberobj` is null";
        $result = mysqli_query($this ->shishi_link,$sql);
        $sql_updata = [];
        $log = [];
        while($data = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            $lottery_link = $this -> linkDB();
            $sql = 'SELECT `result` FROM `lottery_data2` WHERE `issue` = "'.$data['issue'].'" AND type = "'.$this->type.'"';
            $res_lottery = mysqli_query($lottery_link,$sql);
            while($_res = mysqli_fetch_array($res_lottery,MYSQLI_ASSOC)){
                $res_json =  '{\"number\":['.$_res['result'].']}';
                array_push($log,array("res_json"=>$res_json , "issue" =>$data['issue']));
                array_push($sql_updata,"UPDATE `Lottery_result` SET `number` = '".$res_json."' WHERE `issue` = '".$data['issue']."' AND type = '".$this->type."'");
            }
        }
        $this->LogData['set_Lottery_result']['start'] = $start->getTimestamp();
        $this->LogData['set_Lottery_result']['sql_updata_count'] = count($sql_updata);
        for($i = 0 ; $i<count($sql_updata) ; $i++){
            $this->LogData['set_Lottery_result']['result'] = $log[$i];
            $this->Log('LotteryRes');
            mysqli_query($this ->shishi_link,$sql_updata[$i]);
        }
    }
    /**
     * 開獎
     */
    public function SetLottery(){
        // $link = $this->linkDB();
        $sql = $this->getLotterySql();
        for($i = 0 ; $i < count($sql) ; $i++){  
            mysqli_query($this ->Py_link,$sql[$i]);
        }
        //lottery Quantity = 樂透開獎的數量
         
        // $Log = 'lottery Quantity'.count($sql) . $log_data;
        $this->Log('SetLottery');
        $log_data  = $this->set_Lottery_result();
    }
}
?>