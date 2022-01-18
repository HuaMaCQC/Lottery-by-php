<?php
include_once('./Lottery.php');
$pypk10 = new Lottery(array(
            // 'repeat' => false, //是否重複
            'lotteryNumber' =>[],//array(1 => 5 , 2 => 10) 指定號碼
            'maxNum' => 10,   //最大號碼
            'minNum' => 1 ,   //最小號碼
            'Quantity' => 10  //數量
        ),array(
            'type' => 101,    //財種
            'issueinterval' => '0001' //issue規則
        ),array(
            'startHour' => 0, //開始開獎時間(小時)
            'startMinute' => 0, //開始開獎時間(分鐘)
            'startSecond' => 0, //開始開獎時間(秒)
            'endHour' => 23,  //結束開獎時間(小時)
            'endMinute' =>59, //結束開獎時間(分鐘)
            'endSecond' => 0, //結束開獎的時間(秒)
            'interval' => 60, //間隔(秒)
        ));
 $pypk10 ->CreatIssue();
 $pypk10->SetLottery();