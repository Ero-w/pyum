<?php
/* *
* $Author ��PHPYUN�����Ŷ�
*
* ����: http://www.phpyun.com
*
* ��Ȩ���� 2009-2017 ��Ǩ�γ���Ϣ�������޹�˾������������Ȩ����
*
* ����������δ����Ȩǰ���£�����������ҵ��Ӫ�����ο����Լ��κ���ʽ���ٴη�����
*/
class sysnews_controller extends company
{
	function index_action(){

		$urlarr=array("c"=>"sysnews","page"=>"{{page}}");
		$pageurl=Url('member',$urlarr);
		$this->get_page("sysmsg","`fa_uid`='".$this->uid."' order by id desc",$pageurl,"15");
		$this->obj->DB_update_all("sysmsg","`remind_status`='1'","`fa_uid`='".$this->uid."' and `remind_status`='0'");
		$this->public_action();
		$this->yunset("js_def",7);
		$this->com_tpl('sysnews');
	}
	function del_action(){
		if ($_POST['del']||$_GET['id']){
			if(is_array($_POST['del'])){
				$ids=pylode(',',$_POST['del']);
				$layer_type='1';
			}else if($_GET['id']){
				$ids=(int)$_GET['id'];
				$layer_type='0';
			}
			$nid=$this->obj->DB_delete_all("sysmsg","`id` in(".$ids.") AND `fa_uid`='".$this->uid."'"," ");
 			if($nid)
 			{
 				$this->obj->member_log("ɾ��ϵͳ��Ϣ");
 				$this->layer_msg('ɾ���ɹ���',9,$layer_type,"index.php?c=sysnews");
 			}else{
 				$this->layer_msg('ɾ��ʧ�ܣ�',8,$layer_type,"index.php?c=sysnews");
 			}
		}
	}
}
?>