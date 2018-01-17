<?php
/*
* $Author ：PHPYUN开发团队
*
* 官网: http://www.phpyun.com
*
* 版权所有 2009-2016 宿迁鑫潮信息技术有限公司，并保留所有权利。
*
* 软件声明：未经授权前提下，不得用于商业运营、二次开发以及任何形式的再次发布。
 */
class config_controller extends common{
	function index_action(){
		if(strpos($this->config['sy_weburl'],'https')!==false){

			$this->config['mapurl'] = 'https://api.map.baidu.com/api?v=2.0&ak='.$this->config['map_key'].'&s=1';

		}else{
			$this->config['mapurl'] = 'http://api.map.baidu.com/api?v=2.0&ak='.$this->config['map_key'];
		}
		$this->yunset("config",$this->config);
		$this->yuntpl(array('admin/admin_web_config'));
	}
	function save_logo_action(){
		$upload=$this->upload_pic("../data/logo/");
		if (is_uploaded_file($_FILES['logo']['tmp_name'])) {
			$logo_path = $this->logo_upload($_FILES['logo'],$upload);
			$this->logo_reset("sy_logo",$logo_path);
		}
		if (is_uploaded_file($_FILES['member_logo']['tmp_name'])) {
			$mlogo_path = $this->logo_upload($_FILES['member_logo'],$upload);
			$this->logo_reset("sy_member_logo",$mlogo_path);
		}
		if (is_uploaded_file($_FILES['lt_logo']['tmp_name'])) {
			$llogo_path = $this->logo_upload($_FILES['lt_logo'],$upload);
			$this->logo_reset("sy_lt_logo",$llogo_path);
		}
		if (is_uploaded_file($_FILES['ltmember_logo']['tmp_name'])) {
			$llogo_path = $this->logo_upload($_FILES['ltmember_logo'],$upload);
			$this->logo_reset("sy_ltmember_logo",$llogo_path);
		}
		if (is_uploaded_file($_FILES['unit_logo']['tmp_name'])) {
			$ulogo_path = $this->logo_upload($_FILES['unit_logo'],$upload);
			$this->logo_reset("sy_unit_logo",$ulogo_path);
		}
		if (is_uploaded_file($_FILES['rz_logo']['tmp_name'])) {
			$rzlogo_path = $this->logo_upload($_FILES['rz_logo'],$upload);
			$this->logo_reset("sy_rz_logo",$rzlogo_path);
		}

		if (is_uploaded_file($_FILES['px_logo']['tmp_name'])) {
			$pxlogo_path = $this->logo_upload($_FILES['px_logo'],$upload);
			$this->logo_reset("sy_px_logo",$pxlogo_path);
		}
		if (is_uploaded_file($_FILES['wap_logo']['tmp_name'])) {
			$waplogo_path = $this->logo_upload($_FILES['wap_logo'],$upload);
			$this->logo_reset("sy_wap_logo",$waplogo_path);
		}
		if (is_uploaded_file($_FILES['sy_wap_qcode']['tmp_name'])) {
			$waplogo_path = $this->logo_upload($_FILES['sy_wap_qcode'],$upload);
			$this->logo_reset("sy_wap_qcode",$waplogo_path);
		}
		if (is_uploaded_file($_FILES['sy_wx_qcode']['tmp_name'])) {
			$waplogo_path = $this->logo_upload($_FILES['sy_wx_qcode'],$upload);
			$this->logo_reset("sy_wx_qcode",$waplogo_path);
		}
		
		$this->web_config();
		$this->ACT_layer_msg("网站LOGO配置设置成功！",9,$_SERVER['HTTP_REFERER'],2,1);
	}
	function logo_upload($picurl,$upload){
		$pictures=$upload->picture($picurl);
		$pic = str_replace("../data/logo","data/logo",$pictures);
		return $pic;
	}
	function logo_reset($name,$value){
		$logo = $this->obj->DB_select_once("admin_config","`name`='".$name."'");
		if(is_array($logo)){
			unlink_pic("../".$logo['config']);
			$this->obj->DB_update_all("admin_config","`config`='".$value."'","`name`='".$name."'");
		}else{
			$this->obj->DB_insert_once("admin_config","`config`='".$value."',`name`='".$name."'");
		}
	}
	function save_action(){
 		if($_POST['config']){
			unset($_POST['config']);
			if($_POST['map_key']){
				if(strpos($this->config['sy_weburl'],'https')!==false){

					$_POST['mapurl'] = 'https://api.map.baidu.com/api?v=2.0&ak='.$_POST['map_key'].'&s=1';

				}else{
					$_POST['mapurl'] = 'http://api.map.baidu.com/api?v=2.0&ak='.$_POST['map_key'];
				}
			}
			foreach($_POST as $key=>$v){
		    	$config=$this->obj->DB_select_num("admin_config","`name`='$key'");
			   if($config==false){
				$this->obj->DB_insert_once("admin_config","`name`='$key',`config`='".iconv("utf-8", "gbk", $v)."'");
			   }else{
				$this->obj->DB_update_all("admin_config","`config`='".iconv("utf-8", "gbk", $v)."'","`name`='$key'");
			   }
		 	}
			if($_POST['code_strlength']<5){
			    $this->web_config();
			    $this->layer_msg("网站配置设置成功！",9,1);
			}else{
				$this->layer_msg("验证码字符数不要大于4！",8,1,'');
			}
		 }
	}
    function settplcache_action()
    {
        include(CONFIG_PATH."db.data.php");
        include(PLUS_PATH."cache.config.php");
		$modelconfig = $arr_data['modelconfig'];
		
		foreach($modelconfig as $key=>$value){
			$newModel[$key]['value'] = $value;
			$newModel[$key]['cache'] = $cache_config['sy_'.$key.'_cache'];
		}
        $this->yunset('newModel',$newModel);
        $this->yunset('cache_config',$cache_config);
        
		$this->yuntpl(array('admin/admin_tplcache'));
    }
    function savetplcache_action()
    {
		if($_POST["config"]){
		    unset($_POST["config"]);
			include(CONFIG_PATH."db.data.php");
			$modelconfig  =  array_keys($arr_data['modelconfig']);
			$config_new = array();
		    foreach($_POST as $key=>$v){
		        $model = explode('_', $key);
		        if (in_array($model[1], $modelconfig) || $model[1]=='index') {
                    $config_new[$key] = $v;
                }
			}
			
			made_web(PLUS_PATH.'cache.config.php',ArrayToString($config_new),'cache_config');
			$this->ACT_layer_msg("模块缓存设置修改成功！",9,"index.php?m=config",2,1);
		}
    }
}
?>