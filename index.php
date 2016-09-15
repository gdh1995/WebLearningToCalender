<?php
error_reporting(E_ERROR | E_PARSE);
if(!isset($_POST['userid'])
  && !(isset($_GET['userid']) && isset($_COOKIE['userid']) && isset($_COOKIE['userpass']))
) {
  require_once "index-0.html";
  exit;
}

function ReadFieldAndUpdate($name) {
    $val="";
    if(isset($_REQUEST[$name])) {
      $val = $_REQUEST[$name];
      setcookie($name, $val, time()+(3600*24*180), "/", "", FALSE, TRUE);
    } else {
      ExitCode(400);
    }
    return $val;
}
function GetField($name){
    $val="";
    if(isset($_REQUEST[$name])) {
      $val = $_REQUEST[$name];
      if (!isset($_COOKIE[$name]) || $val !== $_COOKIE[$name]) {
        setcookie($name, $val, time()+(3600*24*180), "/", "", FALSE, TRUE);
      }
    } else if (isset($_COOKIE[$name])) {
      $val = $_COOKIE[$name];
    } else {
      ExitCode(400);
    }
    return $val;
}
function HttpGet($url, &$cookie=null){
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_HEADER,1);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    //用fiddler时启用下面一行
    //curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
    if($cookie!==null&&$cookie!="")curl_setopt($ch,CURLOPT_COOKIE,$cookie);
    $content=curl_exec($ch);
    curl_close($ch);
    preg_match('/Set-Cookie: (.*);/iU',$content,$str);
    if($cookie!==null&&isset($str[1]))$cookie=$str[1];
    return $content;
}
function ExitCode($code, $disp=""){
    http_response_code($code);
    if($disp!=="")print($disp);
    exit;
}

if (isset($_POST['userid'])) {
  $userid = ReadFieldAndUpdate("userid");
  $userpass = ReadFieldAndUpdate("userpass");
} else {
  $userid = GetField("userid");
  $userpass = GetField("userpass");
}
$cookie="";
//登录
$ret=HttpGet("http://learn.tsinghua.edu.cn/MultiLanguage/lesson/teacher/loginteacher.jsp?userid=".urlencode($userid)."&userpass=".urlencode($userpass),$cookie);
if(!preg_match('/loginteacher/',$ret)) {
  ExitCode(400, '登录失败。 <a href="javascript:history.go(-1)">返回上一页。</a>');
}

if (isset($_POST['userid'])) {
  require_once "after-post.html";
  exit;
}

//现在应当看MyCourse.jsp?typepage=$page这一页
$page=2;
require_once "iCalcreator.php";

function &NewCalender(){
    $config    = array( "unique_id" => "WLCal", "TZID" => "Asia/Beijing" );
    $vcalendar = new vcalendar( $config );
    $vcalendar->setProperty( "method",        "PUBLISH" );
    $vcalendar->setProperty( "CALSCALE",  "GREGORIAN" );
    //$vcalendar->setProperty( "x-wr-calname",  "网络学堂" );
    //$vcalendar->setProperty( "X-WR-CALDESC",  "Deadline们" );
    //$uuid      = "f93a0cef-df07-4594-a6bd-ff709afefd33";
    //$vcalendar->setProperty( "X-WR-RELCALID", $uuid );
    $vcalendar->setProperty( "X-WR-TIMEZONE", "Asia/Beijing" );
    return $vcalendar;
}
function NewEvent(&$v,$date,$summary,$uid){
    $tz = "Asia/Beijing";                                      // define time zone
    $vevent = $v->newComponent( "vevent" );                    // create next event calendar component
$tmr=date('Ymd', strtotime($date.' +1 day'));
    $date = preg_replace('/(.?+)-(.?+)-(.?+)/','$1$2$3',$date);
    $vevent->setProperty( "dtstart", $date, array("VALUE" => "DATE"));// alt. date format,
$vevent->setProperty( "dtend", $tmr, array("VALUE" => "DATE"));
    //  now for an all-day event
    $vevent->setProperty( "summary", $summary );
    $vevent->setProperty( "uid",$uid);
    //$vevent->setProperty( "categories", "DEADLINE" );
    $xprops = array( "X-LIC-LOCATION" => $tz );                // required of some calendar software
    iCalUtilityFunctions::createTimezone( $v, $tz, $xprops);   // create timezone component(-s)
    // based on all start dates in events
    // (i.e. dtstart)
}

class SharedVariable extends Stackable {
  public function __construct($v) {
    $this->merge($v);
  }

  public function run(){}
}
class workerThread extends Thread {
    function __construct($id,$cookie,$shared){
        $this->id=$id;
        $this->cookie=$cookie;
        $this->shared=$shared;
    }

    function run(){
        //获取这门课的作业页
        $ret=HttpGet("http://learn.tsinghua.edu.cn/MultiLanguage/lesson/student/hom_wk_brw.jsp?course_id=".$this->id,$this->cookie);
        //划分每个tr行
        preg_match_all('/<tr class="tr\d">(.*?)<\/tr>/sm',$ret,$hws);
        foreach ($hws[1] as $hw)
        {
            //划分出这项作业的每个td列
            preg_match_all('/<td(.*?)>(.*?)<\/td>/sm',$hw,$tds);
            //获取作业名称、id、截止日期、提交状态
            preg_match('/<a(.*?)>(.*?)<\/a>/sm',$tds[2][0],$name);
            $name=html_entity_decode($name[2]);
            preg_match('/\?id=(\d*)/',$tds[2][0],$hwid);
            $deadline=$tds[2][2];
            $status=$tds[2][3];
            //下面这一行应该去掉注释，已经提交的作业不添加了吧
            //if(preg_match('/已经/',$status))continue;
            //新建日历项
            $this->lock();
    $v=$this->shared[0];
            NewEvent($v,$deadline,$name,$this->id.'-'.$hwid[1].'-v2');
    $this->shared[0]=$v;
            $this->unlock();
        }
    }
}

$v=NewCalender();
NewEvent($v,'','','');
$v=NewCalender();

$shared = new SharedVariable(array($v));
//获取选中学期的课程列表
$retc=HttpGet("http://learn.tsinghua.edu.cn/MultiLanguage/lesson/student/MyCourse.jsp?typepage=$page",$cookie);
//旧版学堂
preg_match_all('/course_id=(\d*)/',$retc,$courses);
$count=0;
$threads=array();
foreach ($courses[1] as $courseid){
    $thread=new workerThread($courseid,$cookie,$shared);
    array_push($threads,$thread);
    $thread->start();
    $count++;
//测试时不让它输出太多
    if($count==5)break;
}
foreach ($threads as $thread)
{
  $thread->join();
}
//新版学堂
//太慢了，先不写

//返回ics日历
$v=$shared[0];
$v->returnCalendar();
?>