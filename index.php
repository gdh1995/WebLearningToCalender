<?php
error_reporting(E_ERROR | E_PARSE);
if(!isset($_REQUEST['userid'])){
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
</head>
<body>
	<style type="text/css">
	*{margin:0; padding:0;}
	img{max-width: 100%; height: auto;}
	.test{height: 600px; max-width: 600px; font-size: 40px;}
	</style>
	<script type="text/javascript">
		function is_weixin() {
		    var ua = navigator.userAgent.toLowerCase();
		    if (ua.match(/MicroMessenger/i) == "micromessenger") {
		        return true;
		    } else {
		        return false;
		    }
		}
		var isWeixin = is_weixin();
		var winHeight = typeof window.innerHeight != 'undefined' ? window.innerHeight : document.documentElement.clientHeight;
		function loadHtml(){
			var div = document.createElement('div');
			div.id = 'weixin-tip';
			div.innerHTML = '<p><img src="live_weixin.png" alt="微信打开"/></p>';
			document.body.appendChild(div);
		}
		
		function loadStyleText(cssText) {
	        var style = document.createElement('style');
	        style.rel = 'stylesheet';
	        style.type = 'text/css';
	        try {
	            style.appendChild(document.createTextNode(cssText));
	        } catch (e) {
	            style.styleSheet.cssText = cssText; //ie9以下
	        }
            var head=document.getElementsByTagName("head")[0]; //head标签之间加上style样式
            head.appendChild(style); 
	    }
	    var cssText = "#weixin-tip{position: fixed; left:0; top:0; background: rgba(0,0,0,0.8); filter:alpha(opacity=80); width: 100%; height:100%; z-index: 100;} #weixin-tip p{text-align: center; margin-top: 10%; padding:0 5%;}";
		if(isWeixin){
			loadHtml();
			loadStyleText(cssText);
		}
	</script>
    <form id="form1" name="form1" method="get" action="index.php">
        <label for="userid">用户名</label>
        <input type="text" name="userid" id="userid" />
        <label for="userpass">密码</label>
        <input type="text" name="userpass" id="userpass" />
        <input type="submit" name="submit" id="submit" value="提交" />
    </form>
</body>
</html>
<?php
}else if(isset($_SERVER['HTTP_REFERER'])&&$_SERVER['HTTP_REFERER']!==''){
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>更新Deadline</title>
</head>
<body>
    <p>iPhone: 点击下方中间图标，选择“添加到主屏幕”。</p>
    <p>Android: 添加书签，然后长按拖动到主屏幕。</p>
</body>
</html>
<?php
}else{
    //现在应当看MyCourse.jsp?typepage=$page这一页
    $page=2;
    require_once "iCalcreator.php";

    function ExitCode($code,$disp=""){
        http_response_code($code);
        if($disp!=="")print($disp);
        exit;
    }
    function GetField($name){
        if(!isset($_REQUEST[$name]))ExitCode(400);
        return $_REQUEST[$name];
    }
    function HttpGet($url,&$cookie=null){
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

    $userid=GetField("userid");
    $userpass=GetField("userpass");

    $cookie="";
    //登录
    $ret=HttpGet("http://learn.tsinghua.edu.cn/MultiLanguage/lesson/teacher/loginteacher.jsp?userid=".urlencode($userid)."&userpass=".urlencode($userpass),$cookie);
    if(!preg_match('/loginteacher/',$ret))ExitCode(400,"登录失败");
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
}
?>