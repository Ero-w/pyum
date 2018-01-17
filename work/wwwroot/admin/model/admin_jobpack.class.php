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
class admin_jobpack_controller extends common
{
    function index_action()
	{
		$urlarr=array("c"=>"index","page"=>"{{page}}");
		$pageurl=Url('admin_jobpack',$urlarr);
		
		$where="`sharepack`='1' ";
			
		
		$job_rows=$this->get_page("company_job", $where, $pageurl, '10');
		if (is_array($job_rows)) {
    		foreach ($job_rows as $val) {
    		    $rows[$val['id']] = $val;
                $id_arr[] = $val['id'];
            }
            
            $id_str = @implode(',', $id_arr);
    		$where = "`jobid` in (" . $id_str . ") ";
    		$job_share_rows=$this->obj->DB_select_all("company_job_share", $where);
			$shareNum_rows = $this->obj->DB_select_all("company_job_sharelog", $where . "group by jobid","count(jobid) as num,jobid");
			foreach ($shareNum_rows as $val) {
                $shareNum[$val['jobid']] = $val;
            }
    		foreach ($job_share_rows as $val) {
                $rows[$val['jobid']] = array_merge($val, $rows[$val['jobid']]);
                $rows[$val['jobid']]['nowprice'] = sprintf("%.2f", $rows[$val['jobid']]['packnum']*$rows[$val['jobid']]['packmoney']);
                $rows[$val['jobid']]['sharenum'] = intval($shareNum[$val['jobid']]['num']);
            }
		}
		$this->yunset("rows",$rows);
		$this->yuntpl(array('admin/admin_jobpack'));
	}

	function reward_action(){
		

		$urlarr=array("c"=>"reward","page"=>"{{page}}");
		
		
		$pageurl=Url('member',$urlarr);

		$rows=$this->get_page("company_job_reward",'1',$pageurl,'10');
		
		if(is_array($rows) && !empty($rows)){
			$jobids=array();
			foreach($rows as $v){
				$jobids[]=$v['jobid'];
			}
			$joblist = $this->obj->DB_select_all("company_job","`id` IN (".@implode(',',$jobids).")");

			$sqNum = $this->obj->DB_select_all("company_job_rewardlist","`jobid` IN (".@implode(',',$jobids).") group by jobid","count(*) as num,jobid");
			
			foreach($rows as $k=>$v){
				
				foreach($joblist as $val){
					if($v['jobid']==$val['id']){
						$rows[$k]['name']=$val['name'];
						$rows[$k]['com_name']=$val['com_name'];
						$rows[$k]['status']=$val['status'];
						$rows[$k]['lastupdate']=$val['lastupdate'];
					}
				}
				foreach($sqNum as $val){
					if($v['jobid']==$val['jobid']){
						$rows[$k]['sqnum']=$val['num'];
						
					}
				}
			}
		}
		
		$this->yunset("rows",$rows);
		$this->yuntpl(array('admin/admin_jobrewardpack'));
	}
	function rewardlog_action()
	{
		$urlarr=array("c"=>"rewardlog","page"=>"{{page}}");
		$where="`jobid`='" . $_GET['jobid'] . "' ";
		if($_GET['jobid']){
			$where.=" AND `jobid`='".(int)$_GET['jobid']."'";
			$urlarr['jobid']=$_GET['jobid'];
		}
		$pageurl=Url('member',$urlarr);
		$rows=$this->get_page("company_job_rewardlist",$where." order by datetime DESC",$pageurl,'10');
		if(is_array($rows) && !empty($rows)){
			$jobids=array();
			foreach($rows as $v){
				$jobids[]=$v['jobid'];
				$eid[]=$v['eid'];
				$rewardid[] = $v['id'];
			}
			
			$joblist = $this->obj->DB_select_all("company_job","`id` IN (".@implode(',',$jobids).")");
			
			include PLUS_PATH."/user.cache.php";
			include PLUS_PATH."/job.cache.php";
			$ulist = $this->obj->DB_select_all("resume_expect","`id` IN (".@implode(',',$eid).")");
			$M			=	$this->MODEL('pack');
			$log = $this->obj->DB_select_all("company_job_rewardlog","`rewardid` IN (".@implode(',',$rewardid).") ORDER BY id ASC");
			if(is_array($log)){
				foreach($log as $value){
					$logList[$value['rewardid']][] = $value;
					
				}
			}
			foreach($rows as $k=>$v){
					$rows[$k]['log'] = $M->getStatusInfo($v['id'],2,$v['status'],$logList[$v['id']]);
				foreach($joblist as $val){
					if($v['jobid']==$val['id']){
						$rows[$k]['name']=$val['name'];
					}
				}
				foreach($ulist as $val){
					if($v['eid']==$val['id']){
					    $rows[$k]['reid']=$val['id'];
						$rows[$k]['uname']=$val['uname'];
						$rows[$k]['edu']=$userclass_name[$val['edu']];
						$rows[$k]['exp']=$userclass_name[$val['exp']];
						if($val['job_classid']){
							$class = @explode(',',$val['job_classid']);
							foreach($class as $v){
								$classname[] = $job_name[$v];
							}
							$rows[$k]['jobclass']=@implode(',',$classname);
							unset($classname);
						}
					}
				}
				
			}
		}
		$this->yunset("rows",$rows);
		$this->yuntpl(array('admin/admin_jobrewardlog'));
	}

	function getreward_action(){
		if($_POST){

			$M	=	$this->MODEL('pack');

			$Info = $M->getRewardAll($_POST['rewardid'],26);

			echo json_encode(yun_iconv('gbk','utf-8',$Info));
		}
	}

	function getarb_action(){
		if($_POST){

			$M	=	$this->MODEL('pack');

			$return	=  $M->logStatus((int)$_POST['rewardid'],(int)$_POST['status'],$_SESSION['auid'],'admin',array('content'=>iconv('utf-8','gbk',$_POST['content'])));
				
			 if($return['error']==''){
				 echo json_encode(array('error'=>'ok'));
					
			 }else{
				 
				 echo json_encode(array('error'=>iconv('gbk','utf-8',$return['error'])));
			 }

			
		}
		
	
	}
}
