<?php

$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "db";

$conn = new mysqli($servername, $username, $password, $dbname);

class MembershipCommission
{
	/*
	会员等级返现比率
	*/
	public $profit_perc = array("1"=>"0.02", "2"=>"0.05", "3"=>"0.1", "4"=>"0.15");
	
	/*
	会员晋级要求（下线人数、团队人数）
	*/
	public $promotion_standard = array(
		"to_level_1"=>array("downline_required"=>"1", "member_required"=>"1"),
		"to_level_2"=>array("downline_required"=>"3", "member_required"=>"5"),
		"to_level_3"=>array("downline_required"=>"10", "member_required"=>"20"),
		"to_level_4"=>array("downline_required"=>"35", "member_required"=>"60"),
	);
	
	/*
	设定会员等级返现比率
	@param $level 设定等级
	@param $perc  设定比率
	*/
	public function set_profit_perc($level, $perc)
	{
		if($level == "1"
		|| $level == "2"
		|| $level == "3"
		|| $level == "4")
		{
			$profit_perc[$level] = $perc;
		}
		$err_msg = "Invalid Parameter!";
		else return $err_msg;
	}
	
	/*
	设定会员晋升标准
	@param $level "to_level_x"
	@param $downline_required 下线人数 默认值则保留原数据
	@param $member_required 团队人数 默认值则保留原数据
	*/
	public function set_promotion_standard($level, $downline_required = -1, $member_required = -1)
	{
		if($level == "to_level_1"
		|| $level == "to_level_2"
		|| $level == "to_level_3"
		|| $level == "to_level_4")
		{
			$promotion_standard[$level]['downline_required'] = ($downline_required >= 0 ? $downline_required : $promotion_standard[$level]['downline_required']);
			$promotion_standard[$level]['member_required'] = ($member_required >= 0 ? $member_required : $promotion_standard[$level]['member_required']);
		}
		$err_msg = "Invalid Parameter!";
		else return $err_msg;
	}
	
	/*
	判断用户是否属于同一团队
	@param $uid_1 
	@param $uid_2
	retuen TURE/FALE/"UID Can Not Found!"
	database eb_user:用户表 
	字段   *uid *group_id
	*/
	public function check_same_group($uid_1, $uid_2)
	{
		$sql = "SELECT group_id FROM eb_user WHERE uid = ".$uid_1;
		$result_1 = $conn->query($sql);
		$sql = "SELECT group_id FROM eb_user WHERE uid = ".$uid_2;
		$result_2 = $conn->query($sql);
		
		$err_msg = "UID Can Not Found!"
		
		if((mysqli_num_rows($result_1) > 0) && (mysqli_num_rows($result_2) > 0))
		{
			$group_id_1 = mysqli_fetch_assoc($result_1);
			$group_id_2 = mysqli_fetch_assoc($result_2);
			if($group_id_1["group_id"] == $group_id_2["group_id"])
			{
				return true;
			}
			else return false;
		}
		else return $err_msg;
	}
	
	/*
	获得用户下线uid
	@param $uid
	return array $downline_id(数组中为存在的所有下线UID)/FALSE
	数据库 user_relation @@@原系统中无该表，使用时请新建，其中应包含"uid"及"downline_of"字段，"downline_of"在下线会员注册时更新为其上线会员的UID。@@@
	*/
	public function check_downline($uid)
	{
		$sql = "SELECT uid FROM user_relation WHERE downline_of = ".$uid;
		$result = $conn->query($sql);
		
		$err_msg = "User Can Not Found!";
		
		if($result->num_rows > 0)
		{
			$downline_id = [];
			while($row = mysqli_fetch_assoc($result))
			{
				$downline_id[] = $row['downline_of'];
			}
			return $downline_id;
		}
		else return FALSE;
	}
	
	/*
	获得用户上线uid
	@param $uid
	return $row['uid']/FALSE
	数据库 user_relation @@@原系统中无该表，使用时请新建，其中应包含"uid"及"downline_of"字段，"downline_of"在下线会员注册时更新为其上线会员的UID。@@@
	*/
	public function check_upline($uid)
	{
		$sql = "SELECT uid FROM user_relation WHERE downline_of = ".$uid;
		$result = $conn->query($sql);
		
		$err_msg = "User Can Not Found!";
		
		if($result->num_rows > 0)
		{
			$row = mysqli_fetch_assoc($result);
			return $row['uid'];
		}
		else return FALSE;
	}
	
	/*更新团队业绩，根据订单号将订单金额更新至商家所属团队的业绩
	@param $oid 订单号
	retuen TRUE/"Order Can Not Found!"
	数据库 eb_store_order:订单表 eb_user:用户表 
	       group_performance:团队业绩表 @@@原系统中无该表，使用时请新建，其中应包含"group_id"及"performance"字段，"performance"记录团队总业绩。@@@
	字段   total_price oid mer_id group_id performance
	*/
	public function performance_update($oid)
	{
		$sql = "SELECT total_price FROM eb_store_order WHERE oid = ".$oid;
		$order_price_result = $conn->query($sql);
		$order_price = mysqli_fetch_assoc($order_price_result);
		$err_msg = "Order Can Not Found!"
		if($order_price_result->num_rows > 0)
		{
			$sql = "SELECT mer_id FROM eb_store_order WHERE oid = ".$oid;
			$uid_result = $conn->query($sql);
			$uid = mysqli_fetch_assoc($uid_result);
			$sql = "SELECT group_id FROM eb_user WHERE mer_id = ".$uid['uid'];
			$group_id_result = $conn->query($sql);
			$group_id = mysqli_fetch_assoc($group_id_result);
			mysqli_query($conn,"UPDATE group_performance SET performance=performance + ".$order_price['total_price']." WHERE group_id =".$group_id['group_id']);
			return TRUE;
		}
		else return $err_msg;
	}
	
	/*
	更新用户佣金
	@param $uid 
	@param $price
	return TRUE/"User Can Not Found!"
	数据库 eb_user:用户表 
	字段   uid brokerage_price（用户佣金数额）
	*/
	public function simple_brokerage_price_update($uid, $price)
	{
		$sql = "SELECT * FROM eb_user WHERE uid = ".$uid;
		$result = $conn->query($sql);
		
		$err_msg = "User Can Not Found!";
		if($result->num_rows > 0)
		{
			mysqli_query($conn,"UPDATE eb_user SET brokerage_price=brokerage_price + ".$price." WHERE uid =".$uid);
		}
		else return $err_msg;
	}
	
	/*
	获取用户会员等级
	@param $uid
	return $row['level']/FALSE
	数据库 eb_user
	字段   level_id uid
	*/
	public function check_member_level($uid)
	{
		$sql = "SELECT level_id FROM eb_user WHERE uid = ".$uid;
		$level_result = $conn->query($sql);
		$err_msg = "User Can Not Found!"
		if($level_result->num_rows > 0)
		{
			$row = mysqli_fetch_assoc($level_result);
			return $row['level'];
		}
		else return FALSE;
	}
	
	/*
	获取订单买家uid及订单价格
	@param $oid 
	return array $info ($indo["uid"], $info["order_price"])
	数据库 eb_store_order
	字段   total_price uid
	*/
	public function get_info_by_oid($oid)
	{
		$sql = "SELECT total_price FROM eb_store_order WHERE oid = ".$oid;
		$order_price_result = $conn->query($sql);
		$order_price = mysqli_fetch_assoc($order_price_result);
		$err_msg = "Order Can Not Found!"
		if($order_price_result->num_rows > 0)
		{
			$sql = "SELECT uid FROM eb_store_order WHERE oid = ".$oid;
			$uid_result = $conn->query($sql);
			$uid = mysqli_fetch_assoc($uid_result);
			$info = array("uid"=>$uid['uid'],"order_price"=>$order_price['order_price']);
			return $info;
		}
		else return $err_msg;
	}
	
	/*
	根据订单号计算相关会员佣金(平级、级差等)
	@param $oid
	return $upline_end 最后一名产生佣金的用户UID
	*/
	public function commission_update($oid)
	{
		$direct_profit = 0;
		$uid = $this->get_info_by_oid($oid)["uid"];
		$order_price = $this->get_info_by_oid($oid)["order_price"];
		//direct profit
		////self profit
		$target_level = $this->check_member_level($uid);
		$self_profit = $this->$profit_perc[$target_level] * $order_price;
		$this->simple_brokerage_price_update($uid, $self_profit);
		$upline_end = $uid;
		if($this->check_upline($uid))
		{
			$upline_uid = $this->check_upline($uid);
			$upline_level = $this->check_member_level($upline_uid);
			$upline_end = $upline_uid;
			if($upline_uid && ($upline_level > $target_level)) //direct with level difference
			{
				$direct_profit = $order_price * $this->$profit_perc[$upline_level];
				$this->simple_brokerage_price_update($upline_uid, $direct_profit);
				$loop_upline_uid = $this->check_upline($upline_uid);
				$current_level = $upline_level;
				$same_level_profit = $direct_profit;
				$same_level_generation = 0;
				while($loop_upline_uid)
				{
					$loop_upline_level = $this->check_member_level($loop_upline_uid);
					if($loop_upline_level > $current_level)
					{
						$loop_upline_profit = $order_price * ($this->$profit_perc[$loop_upline_level] - $this->$profit_perc[$current_level]);
						$this->simple_brokerage_price_update($loop_upline_uid, $loop_upline_profit);
						$same_level_profit = $loop_upline_profit;
						$same_level_generation = 0;
					}
					else if($loop_upline_level == $current_level)
					{
						$same_level_generation ++;
						if($same_level_generation > 2)
						{
							$loop_upline_profit = 0;
						}
						else
						{
							$loop_upline_profit = $same_level_profit * 0.1;
						}
						$this->simple_brokerage_price_update($loop_upline_uid, $loop_upline_profit);
						$same_level_profit = $loop_upline_profit;
					}
					else
					{
						break;
					}
					$upline_end = $loop_upline_uid;
					$loop_upline_uid = $this->check_upline($loop_upline_uid);
					$current_level = $loop_upline_level;
				}
				return $upline_end;
			}
			else if($upline_uid && ($upline_level == $target_level) && $target_level == 1) //direct without level difference & level 1
			{
				$direct_profit = $order_price * $this->$profit_perc[$target_level] * 0.1;
				$this->simple_brokerage_price_update($upline_uid, $direct_profit);
				$loop_upline_uid = $this->check_upline($upline_uid);
				$current_level = $upline_level;
				$same_level_profit = $direct_profit;
				$same_level_generation = 1;
				while($loop_upline_uid)
				{
					$loop_upline_level = $this->check_member_level($loop_upline_uid);
					if($loop_upline_level > $current_level)
					{
						$loop_upline_profit = $order_price * ($this->$profit_perc[$loop_upline_level] - $this->$profit_perc[$current_level]);
						$this->simple_brokerage_price_update($loop_upline_uid, $loop_upline_profit);
						$same_level_profit = $loop_upline_profit;
						$same_level_generation = 0;
					}
					else if($loop_upline_level == $current_level)
					{
						$same_level_generation ++;
						if($same_level_generation == 1 && $loop_upline_level == 1)
						{
							$loop_upline_profit = 0;
						}
						else if($same_level_generation > 2)
						{
							$loop_upline_profit = 0;
						}
						else
						{
							$loop_upline_profit = $same_level_profit * 0.1;
						}
						$this->simple_brokerage_price_update($loop_upline_uid, $loop_upline_profit);
						$same_level_profit = $loop_upline_profit;
					}
					else
					{
						break;
					}
					$upline_end = $loop_upline_uid;
					$loop_upline_uid = $this->check_upline($loop_upline_uid);
					$current_level = $loop_upline_level;
				}
				return $upline_end;
			}
			else if($upline_uid && ($upline_level == $target_level) && $target_level != 1)//direct without level difference & level 2-4
			{
				$direct_profit = $order_price * $this->$profit_perc[$upline_level];
				$this->simple_brokerage_price_update($upline_uid, $direct_profit);
				$loop_upline_uid = $this->check_upline($upline_uid);
				$current_level = $upline_level;
				$same_level_profit = $direct_profit;
				$same_level_generation = 1;
				while($loop_upline_uid)
				{
					$loop_upline_level = $this->check_member_level($loop_upline_uid);
					if($loop_upline_level > $current_level)
					{
						$loop_upline_profit = $order_price * ($this->$profit_perc[$loop_upline_level] - $this->$profit_perc[$current_level]);
						$this->simple_brokerage_price_update($loop_upline_uid, $loop_upline_profit);
						$same_level_profit = $loop_upline_profit;
						$same_level_generation = 0;
					}
					else if($loop_upline_level == $current_level)
					{
						$same_level_generation ++;
						if($same_level_generation > 2)
						{
							$loop_upline_profit = 0;
						}
						else
						{
							$loop_upline_profit = $same_level_profit * 0.1;
						}
						$this->simple_brokerage_price_update($loop_upline_uid, $loop_upline_profit);
						$same_level_profit = $loop_upline_profit;
					}
					else
					{
						break;
					}
					$upline_end = $loop_upline_uid;
					$loop_upline_uid = $this->check_upline($loop_upline_uid);
					$current_level = $loop_upline_level;
				}
				return $upline_end;
			}
		}
		else return $upline_end;
	}
}
