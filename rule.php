<?php
// include_once('../config.inc');
interface RULE_FILTER{
    public function RUN(array $Input ,$type_id , $issue) : bool;
}

class LINK_DB{
    private $conn ='';
    public function __construct(){
        include('../config.inc');
        $this->conn = $db_config['w'];
    }
    public function Link(){
        $link = mysqli_connect($this->conn['host'],$this->conn['user'],$this->conn['pass'],"shishi");  //連線
        if(mysqli_connect_errno()) {
            echo '與資料庫連線失敗';exit;
        }
        mysqli_set_charset($link,"utf8");
        return $link;  
    }
}


class RULE_PEOPLE_WIN implements RULE_FILTER { //一定要有人贏
    private $linkDB = '';
    public function __construct(){
        $LINK_DB = NEW LINK_DB();
        $this->linkDB = $LINK_DB -> Link(); 
    }
    public function RUN(array $Input , $type_id , $issue) : bool{
        $link = $this->linkDB;
        $content = join(",",$Input);
        $res = true;
        $sql = 'select count(*) from Bet_invoice 
                where `type_id`="'.$type_id.'" and `issue`="'.$issue.'" and `content`="'.$content.'" '; //尋找有幾個人押
        $result = mysqli_query($link,$sql);
        $c = 0;
        while($data = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            $c = $data['count(*)'];  
        }
        if($c == 0){
            $res = false;
        }
        return $res;
    }
}

class RULE_LOSE_UNDER_100K implements RULE_FILTER{ //不能輸超過100k
    private $linkDB = '';
    public function __construct(){
        $LINK_DB = NEW LINK_DB();
        $this->linkDB = $LINK_DB -> Link();
    }
    public function RUN(array $Input , $type_id , $issue) : bool {
        $link = $this->linkDB;
        $content = join(",",$Input);
        $res = true;
        $sql = 'select sum(bet_money * odds) total from Bet_invoice 
                where `type_id`="'.$type_id.'" and `issue`= "'.$issue.'" and `content` = "'.$content.'"';
        $total = 0;
        $result = mysqli_query($link,$sql);        
        while($data = mysqli_fetch_array($result,MYSQLI_ASSOC)){
           if($data['total'] > 100000){
                //賠超過100k
                $res = false;
            }
        }
        return $res;
    }
}


class RULE_DONT_REPEAT implements RULE_FILTER{ //不能重複
    private $linkDB = '';
    public function __construct(){
        include('../config.inc');
        $conn = $db_config['w'];
        $link = mysqli_connect($conn['host'],$conn['user'],$conn['pass'],"py_lottery");  //連線
        if(mysqli_connect_errno()) {
            echo '與資料庫連線失敗';exit;
        }
        mysqli_set_charset($link,"utf8");
        $this->linkDB = $link;
    }
    public function RUN(array $Input, $type_id , $issue) : bool{
        $link = $this->linkDB;
        $res = true;
        $content = join(",",$Input);
        $sql = 'SELECT * FROM `lottery_data2` WHERE `result` != "" ORDER BY `created_at` DESC LIMIT 1';
        $result = mysqli_query($link,$sql);
        while($data = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            $data['result'] == $content ? $res = false : $res = true;
        }
        return $res;
    }
}


class rule_executor {
    /**
     * 取得亂數開獎號碼
    */
    private $issue = '';
    private $rule = [];
    public function __construct(RULE_FILTER ...$rule){
        $this->rule = $rule;
    }
    private function getRandomArray($lotteryNum){
        $res = [];
        for($i = $lotteryNum['minNum'] ; $i<= $lotteryNum['maxNum'] ; $i++){   
            array_push($res,$i);
        }
        for($i = count($res) - 1 ; $i >= 0 ; $i--){
            $j = random_int( 0 , count($res) - 1);
            $temp = $res[$i];
            $res[$i] = $res[$j];
            $res[$j] = $temp;
        }
        foreach($lotteryNum['lotteryNumber'] as $x => $x_value) {
            if($x <= $lotteryNum['Quantity'] && $x_value <= $lotteryNum['maxNum'] && $x_value >= $lotteryNum['minNum'] ){  
                for($i = count($res) - 1 ; $i >= 0 ; $i--){
                    if($res[$i] == $x_value){
                        $temp = $res[$x];
                        $res[$x] = $x_value;
                        $res[$i] = $temp;
                        break;
                    }
                }
            }
        }
        array_splice($res,$lotteryNum['Quantity']);
        return $res;
    }
    public function run($type_id,$issue, $lotteryNum){
        $res = [];
        $pass_num = 0;
        for($i = 0; $i < 10 ;$i++){
            $Lottery = $this->getRandomArray($lotteryNum); //取得樂透號碼
            $new_pass= 0;
            for($e = 0 ; $e<count($this->rule);$e++){
                if($this->rule[$e]->RUN($Lottery , $type_id, $this->issue)){
                    $new_pass++;   
                }  
            }
            if($new_pass == count($this->rule)){
                $res = $Lottery;
                break;
            }else if($pass_num <= $new_pass){ //求最佳解
                $pass_num = $new_pass;
                $res = $Lottery;
            }
        }
        return $res;
    }
}




