<?php
/* *
* $Author ：PHPYUN开发团队
*
* 官网: http://www.phpyun.com
*
* 版权所有 2009-2016 宿迁鑫潮信息技术有限公司，并保留所有权利。
*
* 软件声明：未经授权前提下，不得用于商业运营、二次开发以及任何形式的再次发布。
*/
class index_controller extends common{
	function reg_action(){
		if(!$_POST['username'] || !$_POST['password'] || !$_POST['moblie']){
			$data['error']=2;
			echo json_encode($data);die;
		}
		if(!$this->CheckMoblie($_POST['moblie'])){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$_POST['username']=$this->stringfilter($_POST['username']);
		$nid = $this->obj->DB_select_once("member","`username`='".$_POST['username']."' or `moblie`='".$_POST['moblie']."'");
		if(is_array($nid)){
			$data['error']=4;
			echo json_encode($data);die;
		}
		if($this->config['sy_uc_type']=="uc_center"){
			$this->uc_open();
			$uid=uc_user_register($_POST['username'],$_POST['password'],$_POST['email']);
			list($uid,$username,$password,$email,$salt)=uc_user_login($_POST['username'],$_POST['password']);
			$pass = md5(md5($_POST['password']).$salt);
			$ucsynlogin=uc_user_synlogin($uid);
		}elseif($this->config[sy_pw_type]=="pw_center"){
			include(APP_PATH."/api/pw_api/pw_client_class_phpapp.php");
			$username=$username;
			$password=$_POST['password'];
			$email=$_POST['email'];
			$pw=new PwClientAPI($username,$password,$email);
			$pwuid=$pw->register();
			$salt = substr(uniqid(rand()), -6);
			$pass = md5(md5($password).$salt);
		}else{
			$salt = substr(uniqid(rand()), -6);
			$pass = md5(md5($_POST['password']).$salt);
		}
		$ip = fun_ip_get();
		$sql['username']=$_POST['username'];
		$sql['password']=$pass;
		$sql['moblie']=$_POST['moblie'];
		$sql['email']=$_POST['email'];
		$sql['usertype']=1;
		$sql['status']=1;
		$sql['salt']=$salt;
		$sql['reg_date']=time();
		$sql['reg_ip']=$ip;
		$sql['source']=3;
		$userid=$this->obj->insert_into("member",$sql);
		if($userid){
			if($this->config[sy_pw_type]=="pw_center"){
				$this->obj->DB_update_all("member","`pwuid`='$pwuid'","`uid`='$userid'");
			}
			$sql1['uid']=$userid;
			$sql1['telphone']=$_POST['moblie'];
			$sql1['email']=$_POST['email'];
			$this->obj->DB_insert_once("member_statis","`uid`='".$userid."'");
			$this->obj->insert_into("resume",$sql1);
			$sql2['uid']=$userid;
			$sql2['nickname']=$_POST['username'];
			$sql2['usertype']=1;
			$this->get_integral_action($userid,"integral_login","会员登录");
			if($this->config['user_status']=="1"){
				$randstr=rand(10000000,99999999);
				$base=base64_encode($userid."|".$randstr."|".$this->config[coding]);
				$data_cert['uid']=$userid;
				$data_cert['type']="cert";
				$data_cert['email']=$_POST['email'];
				$data_cert['url']="<a href='".$this->config['sy_weburl']."/index.php?m=qqconnect&c=mcert&id=".$base."'>点击认证</a>";
				$data_cert['date']=date("Y-m-d");

				$this->send_msg_email($data_cert);
				$data['error']=8;
				echo json_encode($data);die;
			}else{
				$data['uid']=$userid;
				$data['username']=iconv("gbk","UTF-8",$_POST['username']);
				$data['usertype']=1;
				$data['error']=1;
				echo json_encode($data);die;
			}
		}else{
			$data['error']=7;
			echo json_encode($data);die;
		}

	}

	function send_action(){
		$username=iconv("UTF-8","gbk",trim($_POST['username']));
		$info = $this->obj->DB_select_once("member","`username`='".$username."' or `moblie`='".$username."'","`moblie`,`username`,`uid`,`usertype`,`did`"); 
        if(!$info['uid']){ 
			$sendcode = rand(100000,999999);
			if(!$this->config["sy_msguser"] || !$this->config["sy_msgpw"] || !$this->config["sy_msgkey"]||$this->config['sy_msg_isopen']!='1'){
				$data['error']=6;//还没有配置短信，请联系管理员！
				echo json_encode($data);die;  
            }else{
	            $moblieCode = $this->obj->DB_select_once('company_cert',"`check`='".$_POST['username']."'");
				if((time()-$moblieCode['ctime'])<90){
					$data['error']=5;//请不要频繁重复发送！
					echo json_encode($data);die;  
				}
	            $num=$this->obj->DB_select_num("moblie_msg","`moblie`='".$_POST['username']."' and `ctime`>'".strtotime(date("Y-m-d"))."'");
				if($num>=$this->config['moblie_msgnum']){
					$data['error']=4;//"同一手机号一天发送".$this->config['moblie_msgnum']."条！"
					echo json_encode($data);die;  
				}
				$ip=fun_ip_get();
				$ipnum=$this->obj->DB_select_num("moblie_msg","`ip`='".$ip."' and `ctime`>'".strtotime(date("Y-m-d"))."'");
				if($ipnum>=$this->config['ip_msgnum']){
					$data['error']=3;//"同一IP一天最多发送".$this->config['ip_msgnum']."条！"
					echo json_encode($data);die;  
				}

				$status=$this->send_msg_email(array("moblie"=>$_POST['username'],"code"=>$sendcode,"type"=>'regcode'));
	            
				if($status=='发送成功!'){
					$datas['did']=$this->config['did'];
					$datas['uid']='0';
					$datas['type']='2';
					$datas['status']='0';
					$datas['step']='1';
					$datas['check']=$_POST['moblie'];
					$datas['check2']=$randstr;
					$datas['ctime']=time();
					$datas['statusbody']=iconv('gbk','utf-8','手机注册验证码');
					if(is_array($moblieCode) && !empty($moblieCode)){
						$this->obj->update_once("company_cert",$datas,"`check`='".$_POST['moblie']."'");
					}
				}
            }  
			
			$data['sendcode']=$sendcode; 
			$data['error']=1; 
			
			echo json_encode($data);die;
        }else{
			$data['error']=2;//手机已存在
			echo json_encode($data);die;
        }
	}

	function getGg_action(){
		$order=$_POST['order'];
		$where="1";
		if($order){//排序
			$where.=" order by ".$order;
		}else{
			$where.=" order by id asc";
		}
		$rows=$this->obj->DB_select_all("admin_announcement",$where);
		if(is_array($rows)&&$rows){
			foreach($rows as $key=>$va){
				$list[$key]['id']		=$va['id'];
				$list[$key]['title']	=iconv("gbk","UTF-8",$va['title']);
				$list[$key]['keyword']	=iconv("gbk","UTF-8",$va['keyword']);
				$list[$key]['description']	=iconv("gbk","UTF-8",$va['description']);
				$list[$key]['content']	=iconv("gbk","UTF-8",html_entity_decode(str_replace("/data/upload/kindeditor/image/",$this->config['sy_weburl']."/data/upload/kindeditor/image/",$va['content']),ENT_QUOTES,"GB2312"));
				$list[$key]['datetime']	=iconv("gbk","UTF-8",$va['datetime']);
				$list[$key]['did']		=iconv("gbk","UTF-8",$va['did']);
			}
			foreach($list as $k=>$v){	
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}

	function GgShow_action(){
		$id = (int)$_POST['id'];
		if(!$id){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$row = $this->obj->DB_select_once("admin_announcement","`id`='".$id."'");
		if(is_array($row)){
			$list['id']			=$row['id'];
			$list['title']		=iconv("gbk","UTF-8",$row['title']);
			$list['keyword']	=iconv("gbk","UTF-8",$row['keyword']);
			$list['description']=iconv("gbk","UTF-8",$row['description']);
			$list['content']	=iconv("gbk","UTF-8",html_entity_decode(str_replace("/data/upload/kindeditor/image/",$this->config['sy_weburl']."/data/upload/kindeditor/image/",$row['content']),ENT_QUOTES,"GB2312"));
			$list['datetime']	=iconv("gbk","UTF-8",$row['datetime']);
			$list['did']		=iconv("gbk","UTF-8",$row['did']);
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function login_action(){
		if(!$_POST['username'] || !$_POST['password']){
			$data['error']=2;
			echo json_encode($data);die;
		}
		$_POST['username']=$this->stringfilter($_POST['username']);
		$row = $this->obj->DB_select_once("member","`username`='".$_POST['username']."'");
		if(!is_array($row)){
			$data['error']=3;
			echo json_encode($data);die;
		}else{
			if($row['usertype']==1){
				if($row['email_status']!='1' && $this->config['user_status']=="1"){
					$data['error']=5;//邮箱未激活
					echo json_encode($data);die;
				}elseif($row['status']==2){
					$data['error']=6;//用户名未审核
					echo json_encode($data);die;
				}
			}elseif($row['usertype']==2){
				if($row['status']==0){
					$data['error']=5;
					echo json_encode($data);die;
				}elseif($row['status']==2){
					$data['error']=6;
					echo json_encode($data);die;
				}
			}
		}
		$pass = md5(md5($_POST['password']).$row['salt']);
		if($pass!=$row[password]){
			$data['error']=4;
			echo json_encode($data);die;
		}else{
			$ip = fun_ip_get();
			$this->obj->DB_update_all("member","`login_ip`='".$ip."',`login_date`='".time()."',`login_hits`=`login_hits`+1","`uid`='".$row['uid']."'");
			$logtime=date("Ymd",$row['login_date']);
			$nowtime=date("Ymd",time());
			if($logtime!=$nowtime){
				$this->get_integral_action($row['uid'],"integral_login","会员登录");
			}
			$data[uid]=$row[uid];
			$data['username']=iconv("gbk","UTF-8",$row['username']);
			$data['usertype']=$row['usertype'];
			$data['error']=1;
			echo json_encode($data);die;
		}
	}
	function getuser_action(){
		if(!$_POST['uids'] && !$_POST['uid']){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$uid=(int)$_POST['uid'];
		if($_POST['uids']){
			$uid_array=explode(",",$_POST['uids']);
			$uid=pylode(",",$uid_array);
		}
		$row = $this->obj->DB_select_alls("member","member_statis","a.`uid`=b.`uid` and a.`uid` in (".$uid.")");
		/*此为ios新增，2014-11-11，start*/
		$resume_row = $this->obj->DB_select_all("resume","`uid` in (".$uid.")");
		/*此为ios新增，2014-11-11，end*/
		if(!is_array($row) && $_POST['uid']){
			$data['error']=2;
			echo json_encode($data);die;
		}
		foreach($row as $k){
			/*此为ios新增，2014-11-11，start*/
			foreach($resume_row as $v){
				if($k['uid']==$v['uid']){
					$data[$k['uid']]['resume_photo']=$v['resume_photo'];
					$data[$k['uid']]['small_photo']		=$v['photo'];
				}
			}

			//1.谁看过我总数、2.我的留言总数、3.我的求职咨询总数、4.我的私信总数
			$look_num=$this->obj->DB_select_num("look_resume","`uid`='".$k['uid']."' and `status`='0'");
			$message_num=$this->obj->DB_select_num("message","`uid`='".$k['uid']."'");
			$msg_num=$this->obj->DB_select_num("msg","`uid`='".$k['uid']."'");
			$fav_jobnum=$this->obj->DB_select_num("fav_job","`uid`='".$k['uid']."'");
			$sq_jobnum=$this->obj->DB_select_num("userid_job","`uid`='".$k['uid']."'");
			$resume_num=$this->obj->DB_select_num("resume_expect","`uid`='".$k['uid']."'");
			$userid_msg=$this->obj->DB_select_num("userid_msg","`uid`='".$k['uid']."'");
			$data[$k['uid']]['userid_msg']		=$userid_msg;
			$data[$k['uid']]['look_num']		=$look_num;
			$data[$k['uid']]['message_num']		=$message_num;
			$data[$k['uid']]['msg_num']			=$msg_num;
			$data[$k['uid']]['xin_num']			=$xin_num;
			/*此为ios新增，2014-11-11，end*/
			$data[$k['uid']]['uid']			=$k['uid'];
			$uids[]			=$k['uid'];
			$data[$k['uid']]['username']	=iconv("gbk","UTF-8",$k['username']);
			$data[$k['uid']]['email']		=$k['email'];
			$data[$k['uid']]['moblie']		=$k['moblie'];
			$data[$k['uid']]['reg_ip']		=$k['reg_ip'];
			$data[$k['uid']]['reg_date']	=$k['reg_date'];
			$data[$k['uid']]['login_ip']	=$k['login_ip'];
			$data[$k['uid']]['login_date']	=$k['login_date']?$k['login_date']:time();
			$data[$k['uid']]['login_hits']	=$k['login_hits'];
			$data[$k['uid']]['resume_num']	=$resume_num;
			$data[$k['uid']]['fav_jobnum']	=$fav_jobnum;
			$data[$k['uid']]['sq_jobnum']	=$sq_jobnum;
			$data[$k['uid']]['status']	=$k['status'];
		}
		$down_resume_rows=$this->obj->DB_select_all("down_resume","`uid` in(".pylode(',',$uids).") group by `uid`","count(id) as down_resume_num,`uid`");
		foreach($data as $k=>$v){
			$data[$k]['down_num']='0';
			foreach($down_resume_rows as $ke=>$va){
				if($v['uid']==$va['uid']){
					$data[$k]['down_num']=$va['down_resume_num'];
					break;
				}
			}
		}
		foreach($data as $k=>$v){
			if(is_array($v)){
				foreach($v as $key=>$val){
					$data[$k][$key]=isset($val)?$val:'';
				}
			}else{
				$data[$k]=isset($v)?$v:'';
			}
		}
		$data['error']=1;
		echo json_encode($data);die;
	}
	function getinfo_action(){
		if(!$_POST['uids'] && !$_POST['uid']){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$uid=(int)$_POST['uid'];
		if($_POST['uids']){
			$uid_array=explode(",",$_POST['uids']);
			$uid=pylode(",",$uid_array);
		}
		$row = $this->obj->DB_select_all("resume","`uid` in (".$uid.")");
		if((!is_array($row) || empty($row)) && $_POST['uid']){
			$data['error']=2;
			echo json_encode($data);die;
		}
		foreach($row as $k){
			/*此为ios新增，2014-11-11，start*/

			//1.谁看过我总数、2.我的留言总数、3.我的求职咨询总数、4.我的私信总数
			$look_num=$this->obj->DB_select_num("look_resume","`uid`='".$k['uid']."' and `status`='0'");
			$message_num=$this->obj->DB_select_num("message","`uid`='".$k['uid']."'");
			$msg_num=$this->obj->DB_select_num("msg","`uid`='".$k['uid']."'");
			$data[$k['uid']]['look_num']		=$look_num;
			$data[$k['uid']]['message_num']		=$message_num;
			$data[$k['uid']]['msg_num']			=$msg_num;
			$data[$k['uid']]['xin_num']			=$xin_num;
			/*此为ios新增，2014-11-11，end*/
			$data[$k['uid']]['uid']			=$k['uid'];
			$data[$k['uid']]['name']		=iconv("gbk","UTF-8",$k['name']);
			$data[$k['uid']]['sex']			=$k['sex'];
			$data[$k['uid']]['birthday']	=iconv("gbk","UTF-8",$k['birthday']);
			$data[$k['uid']]['marriage']	=iconv("gbk","UTF-8",$k['marriage']);
			$data[$k['uid']]['height']		=((float)$k['height']).'';
			$data[$k['uid']]['nationality']	=iconv("gbk","UTF-8",$k['nationality']);
			$data[$k['uid']]['weight']		=((float)$k['weight']).'';
			$data[$k['uid']]['living']	=iconv("gbk","UTF-8",$k['living']);
			$data[$k['uid']]['domicile']		=iconv("gbk","UTF-8",$k['domicile']);
			$data[$k['uid']]['idcard']		=$k['idcard'];
			$data[$k['uid']]['telphone']	=$k['telphone'];
			$data[$k['uid']]['telhome']		=$k['telhome'];
			$data[$k['uid']]['email']		=$k['email'];
			$data[$k['uid']]['edu']			=iconv("gbk","UTF-8",$k['edu']);
			$data[$k['uid']]['address']		=iconv("gbk","UTF-8",$k['address']);
			$data[$k['uid']]['homepage']	=iconv("gbk","UTF-8",$k['homepage']?$k['homepage']:'').'';
			$data[$k['uid']]['description']	=iconv("gbk","UTF-8",$k['description']);
			$data[$k['uid']]['resume_photo']=$k['resume_photo'];
			$data[$k['uid']]['photo']		=$k['photo'];
			$data[$k['uid']]['expect']		=$k['expect'];
			$data[$k['uid']]['def_job']		=$k['def_job'];
			$data[$k['uid']]['exp']			=iconv("gbk","UTF-8",$k['exp']);
			$data[$k['uid']]['status']		=iconv("gbk","UTF-8",$k['status']);
			$data[$k['uid']]['idcard_pic']	=$k['idcard_pic'];
			$data[$k['uid']]['idcard_status']=$k['idcard_status'];
			$data[$k['uid']]['statusbody']	=iconv("gbk","UTF-8",$k['statusbody']);
			$data[$k['uid']]['cert_time']	=$k['cert_time'];
			$data[$k['uid']]['r_status']	=iconv("gbk","UTF-8",$k['r_status']);
			$data[$k['uid']]['ant_num']		=iconv("gbk","UTF-8",$k['ant_num']);
			$data[$k['uid']]['email_dy']	=$k['email_dy'];
			$data[$k['uid']]['msg_dy']		=$k['msg_dy'];
		}
		foreach($data as $k=>$v){
			if(is_array($v)){
				foreach($v as $key=>$val){
					$data[$k][$key]=isset($val)?$val.'':'';
				}
			}else{
				$data[$k]=isset($v)?$v.'':'';
			}
		}
		$data['error']=1;
		echo json_encode($data);die;
	}
	function saveexpect_action(){
		if(!$_POST['name']||!$_POST['hy']||!$_POST['job_classid']||!$_POST['provinceid']||!$_POST['cityid']||!$_POST['minsalary']||!$_POST['type']||!$_POST['report']||!$_POST['jobstatus']||!$_POST['uid']){
			$data['error']=3;
			echo json_encode($data);die;
		}else if($_POST['maxsalary'] && (int)$_POST['minsalary']>(int)$_POST['maxsalary']){
			$data['error']=5;
			echo json_encode($data);die;
		}else {
			$_POST = $this->CheckAppUser();
			$_POST['name']=$this->stringfilter($_POST['name']);
			$_POST['lastupdate']=time();
			$_POST['height_status']="0";
			$_POST['uid']=(int)$_POST['uid'];
			$_POST['r_status']=$this->config['resume_status'];
			$_POST['did']=$this->userdid;
 			$eid=(int)$_POST['eid'];
			if($_POST['eid']!=""){
				$where['id']=$eid;
				$where['uid']=$_POST['uid'];
				$data['eid']=$_POST['eid'];
				unset($_POST['eid']);
				$nid=$this->obj->update_once("resume_expect",$_POST,$where);
				if($nid){
					$data['error']=1;
				}else{
					$data['error']=2;
				}
			}else{
				$statis=$this->obj->DB_select_once("member_statis","`uid`='".$_POST['uid']."'");
				$maxnum=$this->config['user_number']-$statis['resume_num'];
				$confignum=$this->config['user_number'];
				if($maxnum<=0 &&$confignum!=""){
					$data['error']=4;
				}else{
					$num=$this->obj->DB_select_num("resume_expect","`uid`='".$_POST['uid']."'");
					if($num=='0'){
						$_POST['defaults']=1;
					}
					$_POST['source']=3;
					
 					$eid=$this->obj->insert_into("resume_expect",$_POST);
					if($eid){
						if($num=='0'){
							$data['default']=1;
							$this->obj->DB_update_all("resume","`def_job`='".$eid."'","`uid`='".$_POST['uid']."'");
						}else{
							$data['default']=2;
						}
						$num=$num+1;
						$this->obj->DB_insert_once("user_resume","`uid`='".$_POST['uid']."',`expect`='1',`eid`='$eid'");
						$this->obj->DB_update_all("member_statis","`resume_num`='".$num."'","uid='".$_POST['uid']."'");
						$state_content = "发布了 <a href=\"".$this->config[sy_weburl]."/index.php?m=resume&id=$nid\" target=\"_blank\">新简历</a>。";
						$this->obj->DB_insert_once("friend_state","`uid`='".$_POST['uid']."',`content`='".$state_content."',`ctime`='".time()."'");
						$this->obj->member_log("发布了新简历");//会员日志
						$data['eid']=$eid;
						$data['error']=1;
					}else{
						$data['error']=2;
					}
				}
			}
			$row=$this->obj->DB_select_once("user_resume","`expect`='1'","`eid`='".$eid."'");
			if(!is_array($row)){
				$this->send_dingyue($eid,1);
			}
			$resume_row=$this->obj->DB_select_once("user_resume","`eid`='".$eid."'");
			$numresume=$this->complete($resume_row);
			$data[numresume]=$numresume;
			echo json_encode($data);die;
		}
	}
	function saveskill_action(){
		$this->resume("resume_skill","skill");
	}
	function savework_action(){
		$this->resume("resume_work","work");
	}
	function saveproject_action(){
		$this->resume("resume_project","project");
	}
	function saveedu_action(){
		$this->resume("resume_edu","edu");
	}
	function savetraining_action(){
		$this->resume("resume_training","training");
	}
	function savecert_action(){
		$this->resume("resume_cert","cert");
	}
	function saveother_action(){
		$this->resume("resume_other","other");
	}
	function resume($table,$url){
		$_POST = $this->CheckAppUser();

		$eid=(int)$_POST['eid'];
		$id=(int)$_POST['id'];
		$_POST['uid']=(int)$_POST['uid'];
		unset($_POST['id']);
		if($_POST['name'])$_POST['name'] = $this->stringfilter($_POST['name']);
		if($_POST['content'])$_POST['content'] =$this->stringfilter($_POST['content']);
		if($_POST['title'])$_POST['title'] =$this->stringfilter($_POST['title']);
		if($_POST['department'])$_POST['department'] = $this->stringfilter($_POST['department']);
		if($_POST['sys'])$_POST['sys'] = $this->stringfilter($_POST['sys']);
		if($_POST['specialty'])$_POST['specialty'] = $this->stringfilter($_POST['specialty']);
		if($_POST['sdate']){
			$_POST['sdate']=strtotime($_POST['sdate']);
		}
		if($_POST['edate']){
			$_POST['edate']=strtotime($_POST['edate']);
		}
		if($id!=""){
			$where['id']=$id;
			$nid=$this->obj->update_once($table,$_POST,$where);
			if($nid){
				$data['error']=1;
			}else{
				$data['error']=2;
			}
		}else{
			$nid=$this->obj->insert_into($table,$_POST);
			if($nid){
				$data['error']=1;
				$this->obj->DB_update_all("user_resume","`$url`=`$url`+1","`eid`='".$eid."' and `uid`='".$_POST['uid']."'");
			}else{
				$data['error']=2;
			}
		}
		$resume_row=$this->obj->DB_select_once("user_resume","`eid`='".$eid."'");
		$numresume=$this->complete($resume_row);
		$resume_row=$this->obj->DB_select_once("resume_expect","`id`='".$eid."'","`integrity`");
		$data[integrity]=$resume_row['integrity'];
		$data[numresume]=$numresume;
		echo json_encode($data);die;
	}
	function saveinfo_action(){
		if(!$_POST['name']||!$_POST['sex']||!$_POST['birthday']||!$_POST['edu']||!$_POST['exp']||!$_POST['telphone']||!$_POST['email']||!$_POST['uid']){
			$data['error']=3;
		}else{  
			$row=$this->obj->DB_select_once("resume","`uid`='".$_POST['uid']."'");
			$_POST = $this->CheckAppUser();
			$_POST['name']=$this->stringfilter($_POST['name']);
			$_POST['address']=$this->stringfilter($_POST['address']);
			$_POST['living']=$this->stringfilter($_POST['living']);
			$_POST['domicile']=$this->stringfilter($_POST['domicile']);
			$_POST['nationality']=$this->stringfilter($_POST['nationality']);
			if($row['email_status']!=1&&!empty($_POST['email'])){
				$email=$this->obj->DB_select_num("member","`uid`<>'".$_POST['uid']."' and `email`='".$_POST['email']."'","`uid`");
				if($email>0){
					$data['error']=4;
				}else{
					$mvalue['email']=$_POST['email'];
				}
			}else{
				$mvalue['email']=$_POST['email'];
			}
			if($row['moblie_status']!=1){
				$mobile=$this->obj->DB_select_once("member","`uid`<>'".$_POST['uid']."' and `moblie`='".$_POST['telphone']."'","`uid`");
				if($mobile>0){
					$data['error']=5;
				}else{
					$mvalue['moblie']=$_POST['telphone'];
				}
			}
 			$_POST['lastupdate']=time();
			delfiledir("../data/upload/tel/".$_POST['uid']);
			$where['uid']=$_POST['uid'];
			unset($_POST['uid']);
			$nid=$this->obj->update_once("resume",$_POST,$where);
			if($nid){
				if(!empty($mvalue)){
					$this->obj->update_once('member',$mvalue,$where);
				}
				//完善基本资料获得积分
				if($row['name']==""||$row['living']==""){ 
					$this->company_invtal($_POST['UID'],$this->config['integral_userinfo'],true,"首次填写基本资料",true,2,'integral',25);
				}else{
					$this->obj->update_once("resume_expect",array("edu"=>$_POST['edu'],"exp"=>$_POST['exp'],"uname"=>$_POST['name'],"sex"=>$_POST['sex'],"birthday"=>$_POST['birthday']),$where);
				}
				$this->obj->member_log("修改基本资料");//会员日志
				$data['error']=1;
			}else{
				$data['error']=2;
			}
		}
		echo json_encode($data);die;
	}
	function resumelist_action(){
		$_POST = $this->CheckAppUser();

		$uid=(int)$_POST['uid'];
		if(!$uid){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$rows=$this->obj->DB_select_all("resume_expect","`uid`='".$uid."'","id,uid,name,lastupdate,hits,open,integrity");
		$row = $this->obj->DB_select_once("resume","`uid`='".$uid."'","def_job,photo");
		if(is_array($rows) && !empty($rows)){
			foreach($rows as $k=>$v){
				$data2[$k]['id']=iconv("gbk","UTF-8",$v['id']);
				$data2[$k]['name']=iconv("gbk","UTF-8",$v['name']);
				$data2[$k]['lastupdate']=iconv("gbk","UTF-8",$v['lastupdate']);
				$data2[$k]['hits']=iconv("gbk","UTF-8",$v['hits']); 
				$data2[$k]['def_job']=$row['def_job']==$v['id']?1:0;
				$data2[$k]['open']=iconv("gbk","UTF-8",$v['open']);
				$data2[$k]['integrity']			=$v['integrity'];
				$data2[$k]['photo']			=$row['photo'];
			}
			$data['list']=$data2;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	
	
	function savepasswd_action(){
		if(!$_POST['oldpassword']||!$_POST['password']||!$_POST['uid']){
			$data['error']=3;
		}else{
			$info = $this->obj->DB_select_once("member","`uid`='".(int)$_POST['uid']."'");
			if(is_array($info))
			{
				$oldpass = md5(md5($_POST['oldpassword']).$info['salt']);
				if($info['password']!=$oldpass)
				{
					$data['error']=2;
				}else{
					if($this->config['sy_uc_type']=="uc_center" &&$info['name_repeat']!="1")
					{
						$this->uc_open();
						$ucresult= uc_user_edit($info['username'], $_POST['oldpassword'], $_POST['password'], "","1");
						if($ucresult == -1) {
							$data['error']=2;
						}
					}else{
						$salt = substr(uniqid(rand()), -6);
						$pass2 = md5(md5($_POST['password']).$salt);
						$value="`password`='$pass2',`salt`='$salt'";
						$this->obj->DB_update_all("member",$value,"`uid`='".(int)$_POST['uid']."'");
					}
					$this->obj->member_log("修改密码");//会员日志
					$data['error']=1;
				}
			}
		}
		echo json_encode($data);die;
	}
	
	function savequestion_action(){
		if(!$_POST['infotype']||!$_POST['linkman']||!$_POST['telphone']||!$_POST['content']){
			$data['error']=3;
		}else{
			$_POST['ctime']=time();
		    $value=array(
		    	'ctime'=>$_POST['ctime'],
				'username'=>$this->stringfilter($_POST['linkman']),
		        'infotype'=>$_POST['infotype'],
				'content'=>$this->stringfilter($_POST['content']),
				'mobile'=>$_POST['telphone'],
			);
			$nid = $this->obj->insert_into("advice_question",$value);
			if ($nid){
				$this->obj->member_log("反馈意见 ");//会员日志
				$data['error']=1;
			}else{
				$data['error']=2;
			}
		}
		echo json_encode($data);die;
	}
	function messagelist_action(){

		$_POST = $this->CheckAppUser();

		$where=1;
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$order=$_POST['order'];
		$limit=!$limit?10:$limit;
		if($_POST['uid']){
			//$where.=" and (`fa_uid`='".(int)$_POST['uid']."' or `uid`='".(int)$_POST['uid']."') and `keyid`='0' ";
			$where.=" and `uid`='".(int)$_POST['uid']."'";
		}

		if($order){
			$where.=" order by ".$order;
		}else{
			$where.=" order by id desc";
		}
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("message",$where);
		if(is_array($rows) && !empty($rows)){
			foreach($rows as $key=>$va){
				$list[$key]['id']			=$va['id'];
				$list[$key]['content']		=iconv("gbk","UTF-8",$va['content']);
				$list[$key]['username']	=iconv("gbk","UTF-8",$va['username']);
				$list[$key]['status']		=iconv("gbk","UTF-8",$va['status']);
				$list[$key]['ctime'] 		=iconv("gbk","UTF-8",$va['ctime']);
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function messageadd_action(){
		if(!$_POST['content']){
			$data['error']=3;
		}else{
			$_POST = $this->CheckAppUser();
			$content=$this->stringfilter($_POST['content']);
			$username=$this->stringfilter($_POST['username']);
			$value.="`content`='".$content."',";
			$value.="`uid`='".$_POST['uid']."',";
			$value.="`username`='".$username."',";
			$value.="`ctime`='".time()."'";
			$id=$this->obj->DB_insert_once("message",$value);
			if($id){
				$this->obj->member_log("发布留言反馈");//会员日志
				$data['error']=1;
			}else{
				$data['error']=2;
			}
		}
		echo json_encode($data);die;
	}
	function looklist_action(){

		$_POST = $this->CheckAppUser();

		$where=1;
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$order=$_POST['order'];
		$limit=!$limit?10:$limit;
		if($_POST['uid']){
			$where.=" and `uid`='".(int)$_POST['uid']."' and `status`='0' ";
		}

		if($order){
			$where.=" order by ".$order;
		}else{
			$where.=" order by id desc";
		}
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("look_resume",$where);
		if(is_array($rows) && !empty($rows)){
			foreach($rows as $k=>$v)
			{
				$com_uid[] = $v['com_id'];
				$res_uid[] = $v['resume_id'];
			}
			$resume=$this->obj->DB_select_all("resume_expect","`id` IN (".pylode(",",$res_uid).")","id,name");
			$com=$this->obj->DB_select_all("company","`uid` IN (".pylode(",",$com_uid).")","uid,name");
			foreach($rows as $k=>$v)
			{
			}
			foreach($rows as $key=>$va){
				foreach($resume as $k=>$v)
				{
					if($va['resume_id']==$v['id'])
					{
						$list[$key]['resume_name']=iconv("gbk","UTF-8",$v['name']);
					}
				}
				foreach($com as $value)
				{
					if($va['com_id']==$value['uid'])
					{
						$list[$key]['com_name']=iconv("gbk","UTF-8",$value['name']);
					}
				}
				$list[$key]['id']			=$va['id'];
				$list[$key]['com_id']		=$va['com_id'];
				$list[$key]['resume_id']	=$va['resume_id'];
				$list[$key]['status']		=$va['status'];
				$list[$key]['com_status']	=$va['com_status'];
				$list[$key]['datetime']		=$va['datetime'];
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function msglist_action(){
		$where=1;
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$order=$_POST['order'];
		$limit=!$limit?10:$limit;
		$_POST = $this->CheckAppUser();

		if($_POST['uid']){
			$where.=" and `uid`='".(int)$_POST['uid']."'";
		}
		if($order){
			$where.=" order by ".$order;
		}else{
			$where.=" order by id desc";
		}
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("msg",$where);
		if(is_array($rows) && !empty($rows)){
			foreach($rows as $key=>$va){
				$list[$key]['id']			=$va['id'];
				$list[$key]['uid']			=$va['uid'];
				$list[$key]['content']		=iconv("gbk","UTF-8",$va['content']);
				$list[$key]['username']	=iconv("gbk","UTF-8",$va['username']);
				$list[$key]['jobid']		=iconv("gbk","UTF-8",$va['jobid']);
				$list[$key]['job_name'] 	=iconv("gbk","UTF-8",$va['job_name']);
				$list[$key]['datetime'] 	=iconv("gbk","UTF-8",$va['datetime']);
				$list[$key]['reply'] 		=iconv("gbk","UTF-8",$va['reply']);
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	
	function sqjoblist_action()
	{
		$_POST = $this->CheckAppUser();
		$where=1;
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$order=$_POST['order'];
		$limit=!$limit?10:$limit;
		if($_POST['uid']){
			$where.=" and `uid`='".(int)$_POST['uid']."'";
		}
		if($order){
			$where.=" order by ".$order;
		}else{
			$where.=" order by id desc";
		}
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("userid_job",$where);
		if(is_array($rows) && !empty($rows)){
			$jobids=array();
			foreach($rows as $v){
				if(in_array($v['job_id'],$jobids)==false){
					$jobids[]=$v['job_id'];
				} 
			}
			$jobs=$this->obj->DB_select_all("company_job","`id` in(".pylode(',',$jobids).")","`id`,`provinceid`,`cityid`,`three_cityid`");
			$list=array();
			include(PLUS_PATH."city.cache.php");
			foreach($rows as $key=>$va){
				foreach($jobs as $v){ 
					if($v['id']==$va['job_id']){
						$list[$key]['provinceid']	=iconv("gbk","UTF-8",$city_name[$v['provinceid']]);
						$list[$key]['cityid']		=iconv("gbk","UTF-8",$city_name[$v['cityid']]);
						$list[$key]['three_cityid']	=iconv("gbk","UTF-8",$city_name[$v['three_cityid']]);	
					} 
				}
				
				$list[$key]['id']			=$va['id'];
				$list[$key]['job_id']		=$va['job_id'];
				$list[$key]['job_name']		=iconv("gbk","UTF-8",$va['job_name']);
				$list[$key]['com_id']		=iconv("gbk","UTF-8",$va['com_id']);
				$list[$key]['com_name']		=iconv("gbk","UTF-8",$va['com_name']);
				$list[$key]['datetime'] 	=iconv("gbk","UTF-8",$va['datetime']);
				$list[$key]['type'] 		=iconv("gbk","UTF-8",$va['type']);
				$list[$key]['is_browse'] 	=iconv("gbk","UTF-8",$va['is_browse']);
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function delsqjob_action()//删除申请的职位
	{
		if(!$_POST['ids'] || !$_POST['uid'])
		{
			$data['error']=3;//参数不正确
			echo json_encode($data);die;
		}
		$ids=@explode(",",$_POST['ids']);
		$delid=pylode(",",$ids);
		$uid=(int)$_POST['uid'];
		$nid=$this->obj->DB_delete_all("sq_job","`id` in (".$delid.") and `uid`='".$uid."'"," ");
	    if($nid)
	    {
	    	$this->obj->member_log("删除申请的职位");//会员日志
	    	$data['error']=1;//删除成功
	    }else{
	    	$data['error']=2;
	    }
		echo json_encode($data);die;
	}
	function favlist_action()
	{
		$_POST = $this->CheckAppUser();
		$where="`uid`='".(int)$_POST['uid']."'";
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$order=$_POST['order'];
		$limit=!$limit?10:$limit; 
		if($order){
			$where.=" order by ".$order;
		}else{
			$where.=" order by id desc";
		}
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("fav_job",$where);
		if(is_array($rows) && !empty($rows)){
			$jobids=$list=array();
			foreach($rows as $key=>$va){ 
				$jobids[]=$va['job_id'];
				$list[$key]['id']			=$va['id'];
				$list[$key]['job_id']		=$va['job_id'];
				$list[$key]['job_name']		=iconv("gbk","UTF-8",$va['job_name']);
				$list[$key]['com_id']		=iconv("gbk","UTF-8",$va['com_id']);
				$list[$key]['com_name']		=iconv("gbk","UTF-8",$va['com_name']);
				$list[$key]['datetime'] 	=iconv("gbk","UTF-8",$va['datetime']);
				$list[$key]['type'] 		=iconv("gbk","UTF-8",$va['type']);
			}
			$jobs=$this->obj->DB_select_all("company_job","`id` in(".pylode(',',$jobids).")","`id`,`minsalary`,`maxsalary`,`provinceid`,`cityid`,`three_cityid`,`state`");
			include(PLUS_PATH."city.cache.php");
			include(PLUS_PATH."com.cache.php");
			foreach($list as $k=>$v){
				foreach($jobs as $val){
					if($v['job_id']==$val['id']){
						$list[$k]['minsalary']		=iconv("gbk","UTF-8",$comclass_name[$val['minsalary']]);
						$list[$k]['maxsalary']		=iconv("gbk","UTF-8",$comclass_name[$val['maxsalary']]);
						$list[$k]['provinceid']		=iconv("gbk","UTF-8",$city_name[$val['provinceid']]);
						$list[$k]['cityid']			=iconv("gbk","UTF-8",$city_name[$val['cityid']]);
						$list[$k]['three_cityid']	=iconv("gbk","UTF-8",$city_name[$val['three_cityid']]);
						$list[$k]['state']			=iconv("gbk","UTF-8",$val['state']);
					} 
				}
				
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function delfavjob_action()//删除收藏的职位
	{
		if(!$_POST['ids'] || !$_POST['uid'])
		{
			$data['error']=3;//参数不正确
			echo json_encode($data);die;
		}
		$ids=@explode(",",$_POST['ids']);
		$delid=pylode(",",$ids);
		$uid=(int)$_POST['uid'];
		$nid=$this->obj->DB_delete_all("fav_job","`id` in (".$delid.") and `uid`='".$uid."'"," ");
	    if($nid)
	    {
	    	$this->obj->member_log("删除收藏的职位");//会员日志
	    	$data['error']=1;//删除成功
	    }else{
	    	$data['error']=2;
	    }
		echo json_encode($data);die;
	}
	function invitelist_action()
	{
		$_POST = $this->CheckAppUser();
		$where="type<>'1'";
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$order=$_POST['order'];
		$limit=!$limit?10:$limit;
		if($_POST['uid']){
			$where.=" and `uid`='".(int)$_POST['uid']."'";
		}
		if($order){
			$where.=" order by ".$order;
		}else{
			$where.=" order by id desc";
		}
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("userid_msg",$where);
		if(is_array($rows) && !empty($rows)){
			foreach($rows as $key=>$va){
				$list[$key]['id']			=$va['id'];
				$list[$key]['fid']			=$va['fid'];
				$list[$key]['title']		=iconv("gbk","UTF-8",$va['title']);
				$list[$key]['content']		=iconv("gbk","UTF-8",$va['content']);
				$list[$key]['fname']		=iconv("gbk","UTF-8",$va['fname']);
				$list[$key]['datetime'] 	=iconv("gbk","UTF-8",$va['datetime']);
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}

	function refresh_resume_action()
	{
		if(!$_POST['id'])
		{
			$data['error']=3;
		}else{
			$_POST = $this->CheckAppUser();

			$id=$this->obj->DB_update_all("resume_expect","`lastupdate`='".time()."'","`id`='".(int)$_POST['id']."'");
			if($id)
			{
				$this->obj->member_log("刷新简历");//会员日志
				$data['error']=1;
			}else{
				$data['error']=2;
			}
		}
		echo json_encode($data);die;
	}
	function default_resume_action(){
		if(!$_POST['id']||!$_POST['uid']){
			$data['error']=3;
		}else{
			$_POST = $this->CheckAppUser();
			$this->obj->DB_update_all("resume_expect","`defaults`='0'","`uid`='".(int)$_POST['uid']."'");
			$this->obj->DB_update_all("resume_expect","`defaults`='1'","`uid`='".(int)$_POST['uid']."' and `id`='".(int)$_POST['id']."'"); 
			$id=$this->obj->DB_update_all("resume","`def_job`='".(int)$_POST['id']."'","`uid`='".(int)$_POST['uid']."'");
			if($id){
				$this->obj->member_log("设置默认简历");//会员日志
				$data['error']=1;
			}else{
				$data['error']=2;
			}
		}
		echo json_encode($data);die;
	}
	function del_resume_action()
	{
		if(!$_POST['id']||!$_POST['table']||!$_POST['uid'])
		{
			$data['error']=3;
		}else{
			$_POST = $this->CheckAppUser();
			$_POST['id']=(int)$_POST['id'];
			$_POST['uid']=(int)$_POST['uid'];
			$table=$_POST['table'];
			$tables=array("expect","skill","work","project","edu","training","cert","other");
			if(!in_array($table,$tables)){
				$data['error']=3;
			}else{
				$row_item=$this->obj->DB_select_once("resume_".$table,"`id`='".$_POST['id']."'");
				$id=$this->obj->DB_delete_all("resume_".$table,"`id`='".$_POST['id']."'");
				$this->obj->DB_delete_all("look_resume","`resume_id`='".$_POST['id']."'");
				$eid=($table=='expect')?$row_item['id']:$row_item['eid'];
				if($id)
				{
					if($_POST['table']=="expect")
					{
						$del_array=array("resume_cert","resume_edu","resume_other","resume_project","resume_skill","resume_training","resume_work","resume_doc","user_resume");
						foreach($del_array as $v)
						{
							$this->obj->DB_delete_all($v,"`eid`='".$_POST['id']."'' and `uid`='".$_POST['uid']."'","");
						}
						$this->obj->DB_update_all("resume","`expect`=`expect`-1","`uid`='".$_POST['uid']."'");
						$def_id=$this->obj->DB_select_once("resume","`uid`='".$_POST['uid']."' and `def_job`='".$_POST['id']."'");
					    if(is_array($def_id))
					    {
							$row=$this->obj->DB_select_once("resume_expect","`uid`='".$_POST['uid']."'");
							$this->obj->DB_update_all("resume","`def_job`='".$row['id']."'","`uid`='".$_POST['uid']."'");
					    }
				    	$this->obj->DB_update_all("member_statis","`resume_num`=`resume_num`-1","uid='".$_POST['uid']."'");
 					}else{
						$nid=$this->obj->DB_delete_all("resume_".$table,"`id`='".$_POST['id']."' and `uid`='".$_POST['uid']."'");
						$this->obj->DB_update_all("user_resume","`$table`=`$table`-1","`eid`='".$eid."' and  `uid`='".$_POST['uid']."'");
						$resume=$this->obj->DB_select_once("user_resume","`eid`='".$eid."'");
					}
                    //设置默认简历
                    $resume_list=$this->obj->DB_select_all("resume_expect","`uid`='".$_POST['uid']."'");
                    foreach($resume_list as $k=>$v){
                        if($v['defaluts']=='1'){
                            $has_defaults=true;
                            break;
                        }
                    }
                    if($has_defaults!=true && count($resume_list)>0){
                        $this->obj->DB_update_all("resume","`def_job`='".$resume_list[0]['id']."'","`uid`='".$_POST['uid']."'");
                        $this->obj->DB_update_all("resume_expect","`defaults`='1'","`uid`='".$_POST['uid']."'");
                    }
					$resume_row=$this->obj->DB_select_once("user_resume","`eid`='".$eid."'");
					$numresume=$this->complete($resume_row);
					$resume_row=$this->obj->DB_select_once("resume_expect","`id`='".$eid."'","`integrity`");
					$data[integrity]=$resume_row['integrity'];
					$this->obj->member_log("删除简历");//会员日志
					$data['error']=1;
				}else{
					$data['error']=2;
				}
			}
		}
		echo json_encode($data);die;
	}
	function look_resume_action()//简历浏览记录
	{
		$_POST = $this->CheckAppUser();
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$limit=!$limit?10:$limit;
		if(!$_POST['uid']){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$where.="`uid`='".(int)$_POST['uid']."' and `status`='0' order by `datetime` desc";
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("look_resume",$where);
		if(is_array($rows) &&!empty($rows))
		{
			foreach($rows as $v)
			{
				$com_id[] = $v['com_id'];
				$resume_id[] = $v['resume_id'];
			}
			$resume=$this->obj->DB_select_all("resume_expect","`id` IN (".pylode(",",$resume_id).")","id,name");
			$com=$this->obj->DB_select_all("company","`uid` IN (".pylode(",",$com_id).")","uid,name");
			foreach($rows as $k=>$v)
			{
				foreach($resume as $key=>$val)
				{
					if($v['resume_id']==$val['id'])
					{
						$rows[$k]['resume_name']=$val['name'];
					}
				}
				foreach($com as $value)
				{
					if($v['com_id']==$value['uid'])
					{
						$rows[$k]['com_name']=$value['name'];
					}
				}
				foreach($lt as $value)
				{
					if($v['com_id']==$value['uid'])
					{
						$rows[$k]['com_name']=$value['com_name']?$value['com_name']:$value['realname'];
						$rows[$k]['realname']=$value['realname'];
					}
				}
			}
			foreach($rows as $key=>$va){
				$list[$key]['id']			=$va['id'];
				$list[$key]['com_id']		=$va['com_id'];
				$list[$key]['resume_name']	=iconv("gbk","UTF-8",$va['resume_name']);
				$list[$key]['com_name']		=iconv("gbk","UTF-8",$va['com_name']);
				$list[$key]['realname']		=iconv("gbk","UTF-8",$va['realname']);
				$list[$key]['datetime'] 	=iconv("gbk","UTF-8",$va['datetime']);
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function look_resume_del_action()//删除简历浏览记录
	{
		$_POST = $this->CheckAppUser();
		if(!$_POST['uid'] || !$_POST['ids']){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$id=$this->obj->DB_update_all("look_resume","`status`='1'","`uid`='".(int)$_POST['uid']."' and `id` in (".$_POST['ids'].")","");
		if($id)
		{
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function look_job_action()//职位浏览记录
	{
		$_POST = $this->CheckAppUser();
		$page=$_POST['page'];
		$limit=$_POST['limit'];
		$limit=!$limit?10:$limit;
		if(!$_POST['uid']){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$where.="`uid`='".(int)$_POST['uid']."' and `status`='0' order by `datetime` desc";
		if($page){
			$pagenav=($page-1)*$limit;
			$where.=" limit $pagenav,$limit";
		}else{
			$where.=" limit $limit";
		}
		$rows=$this->obj->DB_select_all("look_job",$where);
		if(is_array($rows) &&!empty($rows)){
			include(PLUS_PATH."city.cache.php");
			include(PLUS_PATH."com.cache.php");
			foreach($rows as $v)
			{
				$jobid[]=$v['jobid'];
			}
			$job=$this->obj->DB_select_all("company_job","`id` in (".pylode(",",$jobid).")","`id`,`name`,`com_name`,`minsalary`,`maxsalary`,`provinceid`,`cityid`,`three_cityid`,`state`");
			foreach($rows as $key=>$val){
				foreach($job as $va){
					if($val['jobid']==$va['id']){
						$rows[$key]['jobname']=$va['name'];
						$rows[$key]['com_name']=$va['com_name'];
						$rows[$key]['minsalary']=$va['minsalary'];
						$rows[$key]['maxsalary']=$va['maxsalary'];
						$rows[$key]['provinceid']=$va['provinceid'];
						$rows[$key]['cityid']=$va['cityid'];
						$rows[$key]['three_cityid']=$va['three_cityid'];
						$rows[$key]['state']=$va['state'];
					}
				}
			}
			foreach($rows as $key=>$va){
				$list[$key]['id']			=$va['id'];
				$list[$key]['jobid']		=$va['jobid'];
				$list[$key]['com_id']		=$va['com_id'];
				$list[$key]['com_name']		=iconv("gbk","UTF-8",$va['com_name']);
				$list[$key]['jobname']		=iconv("gbk","UTF-8",$va['jobname']);
				$list[$key]['datetime'] 	=iconv("gbk","UTF-8",$va['datetime']);
				$list[$key]['minsalary']	=iconv("gbk","UTF-8",$va['minsalary']);
				$list[$key]['maxsalary']	=iconv("gbk","UTF-8",$va['maxsalary']);
				$list[$key]['provinceid']	=iconv("gbk","UTF-8",$city_name[$va['provinceid']]);
				$list[$key]['cityid']		=iconv("gbk","UTF-8",$city_name[$va['cityid']]);
				$list[$key]['three_cityid']	=iconv("gbk","UTF-8",$city_name[$va['three_cityid']]);
				$list[$key]['state']		=iconv("gbk","UTF-8",$va['state']);
			}
			foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}
	function look_job_del_action()//删除职位浏览记录
	{
		$_POST = $this->CheckAppUser();
		if(!$_POST['uid'] || !$_POST['ids']){
			$data['error']=3;
			echo json_encode($data);die;
		}
		$id=$this->obj->DB_update_all("look_job","`status`='1'","`uid`='".(int)$_POST['uid']."' and `id` in (".$_POST['ids'].")","");
		if($id)
		{
			$data['error']=1;
		}else{
			$data['error']=2;
		}
		echo json_encode($data);die;
	}

	/*此为ios新增，2014-11-10，start*/
	//热门关键字
	function hotkeyword_action()
	{
		$where = "`check`='1'";
		//是否推荐
		if($_POST['recom'])
		{
			$tuijian = 1;
		}
		//类别
		if($_POST['type']){
			$type = $_POST['type'];
		}
		//查询条数
		if($_POST['limit'])
		{
			$limit=$_POST['limit'];
		}else{
			$limit=20;
		}
		include PLUS_PATH."/keyword.cache.php";

		$index =1;
		$list=array();
		$item=array();
		if(is_array($keyword))
		{
			$i=0;
			foreach($keyword as $k=>$v)
			{
				if($tuijian && $v['tuijian']!='1')
				{
					continue;
				}
				if($type && $v['type']!=$type)
				{
					continue;
				}
				$i++;
				$item['id']=$v['id'];
				$item['title']=iconv("gbk","UTF-8",$v['key_name']);
				$item['num']=$v['num'];
				$item['type']=$v['type'];
				$item['size']=$v['size'];
				$item['color']=strlen($v['color'])<7?'ffffff':substr($v['color'],1,7);
				$item['bold']=$v['bold'];
				$item['tuijian']=$v['tuijian'];
				$list[] = $item;
				if($i==$limit)
				{
					break;
				}
			}
		}
		foreach($list as $k=>$v){
				if(is_array($v)){
					foreach($v as $key=>$val){
						$list[$k][$key]=isset($val)?$val:'';
					}
				}else{
					$list[$k]=isset($v)?$v:'';
				}
			}
			$data['list']=count($list)?$list:array();
		echo json_encode($data);die;
	}
	/*此为ios新增，2014-11-10，end*/
}
?>