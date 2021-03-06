<?php
namespace app\manage\admin;

use app\admin\controller\Admin;
use think\Db;
use think\Request;
use think\Validate;
use app\common\builder\ZBuilder;
use app\user\model\User as AdminModel;

class Merchant extends Admin
{
	//商户列表
	public function index(){
		//获取筛选参数
        $filter=[
        	'admin_name'=>input('param.admin_name',''),
            'shop_name'=>input('param.shop_name',''),
            'sn'=>input('param.sn',''),
            'state'=>input('param.state',''),
        	'created_at_start'=>input('param.created_at_start',''),
        	'created_at_end'=>input('param.created_at_end',''),
        ];
        $filter=fortrim($filter);
        //整理筛选参数
        $map=[];
        if($filter['admin_name']!==''){
        	$map['b.username']=['like','%'.$filter['admin_name'].'%'];
        }
        if($filter['shop_name']!==''){
            $map['a.shop_name']=['like','%'.$filter['shop_name'].'%'];
        }
        if($filter['sn']!==''){
            $map['a.sn']=['like','%'.$filter['sn'].'%'];
        }
        if($filter['state']!==''){
            $map['a.state']=$filter['state'];
        }
        $filter_at['created_at_start']=$filter['created_at_start'].' 00:00:00';
        $filter_at['created_at_end']=$filter['created_at_end'].' 23:59:59';
        if($filter['created_at_start']!=='' && $filter['created_at_end']!==''){
        	$map['a.created_at']=['between time',[$filter_at['created_at_start'],$filter_at['created_at_end']]];
        }elseif($filter['created_at_start']!=='' && $filter['created_at_end']===''){
        	$map['a.created_at']=['>= time',$filter_at['created_at_start']];
        }elseif($filter['created_at_start']==='' && $filter['created_at_end']!==''){
        	$map['a.created_at']=['<= time',$filter_at['created_at_end']];
        }
        $order=input('param.order','a.created_at desc');
        $order=str_replace('+', ' ', $order);
        //查出数据
        $object=db('merchants')->alias('a')->field('a.id,b.username admin_name,a.company_name,a.shop_name,a.sn,a.created_at,a.state')->join('admin_user b','a.admin_id=b.id','LEFT')->where($map)->order($order)->paginate(15);
        // 获取分页显示
		$page = $object->render();
		$data_all = json_decode(json_encode($object),TRUE);
        $data=$data_all['data'];
        //缓存导出数据
        session('user_index_export_map',$map);
        session('user_index_export_order',$order);
        //处理数据
        foreach ($data as $key => $value) {
        	$data[$key]['created_at_str']=date('Y-m-d H:i',$value['created_at']);
        }
        //模板赋值
        $this->assign([
        	'filter'=>$filter,
        	'order'=>$order,
        	'data'=>$data,
        	'page'=>$page,
        ]);
        //渲染模板
        return $this->fetch();
	}
	//商户列表导出
	public function export(){
		$header = array(
          'ID'=>'string',//text
          '用户名'=>'string',//text
          '公司名称'=>'string',//text
          '店铺名称'=>'string',//text
          '商户号'=>'string',//text
          '创建时间'=>'string',//text
          '状态'=>'string',//text
        );
        $data = db('merchants')->alias('a')->field('a.id,b.username admin_name,a.company_name,a.shop_name,a.sn,a.created_at,a.state')->join('admin_user b','a.admin_id=b.id','LEFT')->where(session('user_index_export_map'))->order(session('user_index_export_order'))->select();
        //处理数据
        foreach ($data as $key => $value) {
        	$data[$key]['created_at']=date('Y-m-d H:i',$value['created_at']);
            $data[$key]['state']=$value['state']?'开通':'冻结';
        }
        $file_name = 'merchantList-'.date('YmdHis').'-'.mt_rand(1000,9999).'.xlsx';     //下载文件名    
        $file_dir = "excels/";        //下载文件存放目录  
        do_rmdir($file_dir,false);//先清空文件夹
        $rows = $data;
        import('PHP_XLSXWriter-master.xlsxwriter', EXTEND_PATH,'.class.php');
        $writer = new \XLSXWriter();
        $writer->writeSheetHeader('Sheet1', $header);
        foreach($rows as $row){
            $row=array_values($row);
            $writer->writeSheetRow('Sheet1', $row);
        }
        $writer->writeToFile($file_dir.$file_name);
        //检查文件是否存在    
        if (! file_exists ( $file_dir . $file_name )) {    
            return $this->error('文件未生成成功，请重试');
        } else {    
            record_log(request()->module(),request()->controller(),'导出商户列表');
            header('Location:'.config('finecar.host_url').'/'.$file_dir.$file_name);
            die;
        } 
	}
    //商户开立
    public function add(){
        $now=time();
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data=fortrim($data);
            //验证
            if($data['company_name']===''){
                return json_return('F','1001','公司名称必填');
            }
            if(!preg_match('/^[（）()\x{4e00}-\x{9fa5}]+$/u',$data['company_name'])) {
                return json_return('F','1001','公司名称只允许输入汉字或中英文()，不允许输入字母、数字、及其他特殊符号');
            }
            if($data['shop_name']===''){
                return json_return('F','1002','店铺名称必填');
            }
            if(!preg_match('/^[A-Za-z0-9\x{4e00}-\x{9fa5}]+$/u',$data['shop_name'])) {
                return json_return('F','1002','店铺名称允许输入汉字、字母、数字，不允许特殊字符输入');
            }
            if($data['contract_no']===''){
                return json_return('F','1003','合同编号必填');
            }
            if(!preg_match('/^[A-Za-z0-9-]{10,30}$/',$data['contract_no'])) {
                return json_return('F','1003','合同编号允许输入大写字母、数字、- 三种字符，字符长度：10-30个字符，例：HC-JM-20180415001');
            }
            if($data['contract_valid_start']===''){
                return json_return('F','1004','合同有效期开始日期必选');
            }
            if($data['contract_valid_end']===''){
                return json_return('F','1005','合同有效期结束日期必选');
            }
            if(strtotime($data['contract_valid_end']." 00:00:00")-strtotime($data['contract_valid_start']." 00:00:00")<=0){
                return json_return('F','1005','合同有效期结束日期必须大于开始日期');
            }
            if($data['licence_type']===''){
                return json_return('F','1006','证件类型必选');
            }
            if($data['licence_no']===''){
                return json_return('F','1007','证件号码必填');
            }
            if($data['licence_type']=='工商营业执照'){
                if(!preg_match('/^[0-9]{13}$/',$data['licence_no'])) {
                    return json_return('F','1007','证件号码，选择工商营业执照时对应输入框，只允许输入13位数字');
                }
            }
            if($data['licence_type']=='统一社会信用代码营业执照'){
                if(!preg_match('/^[0-9]{18}$/',$data['licence_no'])) {
                    return json_return('F','1007','证件号码，选择统一社会信用代码营业执照时只允许输入18位数字');
                }
            }
            if($data['licence_province_id']===''){
                return json_return('F','1008','营业执照所在省份必选');
            }
            if($data['licence_city_id']===''){
                return json_return('F','1009','营业执照所在城市必选');
            }
            if($data['licence_area_id']===''){
                return json_return('F','1010','营业执照所在区县必选');
            }
            if($data['licence_address']===''){
                return json_return('F','1011','营业执照详细地址必填');
            }
            if($data['licence_img']===''){
                return json_return('F','1012','营业执照必传');
            }
            if($data['licence_valid_start']===''){
                return json_return('F','1013','营业执照有效期开始日期必填');
            }
            if($data['licence_valid_end']===''){
                return json_return('F','1014','营业执照有效期结束日期必填');
            }
            if(strtotime($data['licence_valid_end']." 00:00:00")-strtotime($data['licence_valid_start']." 00:00:00")<=0){
                return json_return('F','1014','营业执照有效期结束日期必须大于开始日期');
            }
            if($data['work_address']===''){
                return json_return('F','1015','办公地址必填');
            }
            if($data['custom_tel']===''){
                return json_return('F','1016','客服电话必填');
            }
            if(!preg_match('/^[0-9]+$/',$data['custom_tel'])) {
                return json_return('F','1016','客服电话只允许输入数字');
            }
            if($data['corporation_name']===''){
                return json_return('F','1017','法人必填');
            }
            if(mb_strlen($data['corporation_name'],'utf8')<2 || mb_strlen($data['corporation_name'],'utf8')>8){
                return json_return('F','1027','法人字符限制2-8个字');
            }
            if($data['mobile']===''){
                return json_return('F','1018','联系电话必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['mobile'])) {
                return json_return('F','1018','联系电话只允许输入11位数字');
            }
            if($data['identcard_no']===''){
                return json_return('F','1019','身份证号必填');
            }
            if(!preg_match('/^([\d]{17}[xX\d]|[\d]{15})$/',$data['identcard_no'])) {
                return json_return('F','1019','身份证号格式不正确');
            }
            if($data['identcard_front_img']===''){
                return json_return('F','1020','身份证正面必传');
            }
            if($data['identcard_back_img']===''){
                return json_return('F','1021','身份证背面必传');
            }
            if($data['businesser_name']===''){
                return json_return('F','1022','业务负责人姓名必填');
            }
            if(mb_strlen($data['businesser_name'],'utf8')<2 || mb_strlen($data['businesser_name'],'utf8')>8){
                return json_return('F','1022','业务负责人姓名字符限制2-8个字');
            }
            if($data['businesser_tel_1']===''){
                return json_return('F','1023','业务负责人座机区号必填');
            }
            if(!preg_match('/^[0-9]{3,4}$/',$data['businesser_tel_1'])) {
                return json_return('F','1023','业务负责人座机区号允许3位和4位数字');
            }
            if($data['businesser_tel_2']===''){
                return json_return('F','1024','业务负责人电话号码必填');
            }
            if(strlen($data['businesser_tel_1'])==3){
                if(!preg_match('/^[0-9]{8}$/',$data['businesser_tel_2'])) {
                    return json_return('F','1024','业务负责人电话号码，当区号时3位数字时，号码是8位');
                }
            }
            if(strlen($data['businesser_tel_1'])==4){
                if(!preg_match('/^[0-9]{7}$/',$data['businesser_tel_2'])) {
                    return json_return('F','1024','业务负责人电话号码，当区号是4位数字时，号码是7位');
                }
            }
            if($data['businesser_mobile']===''){
                return json_return('F','1025','业务负责人手机号必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['businesser_mobile'])) {
                return json_return('F','1025','业务负责人手机号只允许输入11位数字');
            }
            if($data['businesser_email']===''){
                return json_return('F','1026','业务负责人邮箱必填');
            }
            if(!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i',$data['businesser_email'])) {
                return json_return('F','1026','业务负责人邮箱格式不正确');
            }
            if($data['financer_name']===''){
                return json_return('F','1027','财务负责人姓名必填');
            }
            if(mb_strlen($data['financer_name'],'utf8')<2 || mb_strlen($data['financer_name'],'utf8')>8){
                return json_return('F','1027','财务负责人姓名字符限制2-8个字');
            }
            if($data['financer_tel_1']===''){
                return json_return('F','1028','财务负责人座机区号必填');
            }
            if(!preg_match('/^[0-9]{3,4}$/',$data['financer_tel_1'])) {
                return json_return('F','1028','财务负责人座机区号允许3位和4位数字');
            }
            if($data['financer_tel_2']===''){
                return json_return('F','1029','财务负责人电话号码必填');
            }
            if(strlen($data['financer_tel_1'])==3){
                if(!preg_match('/^[0-9]{8}$/',$data['financer_tel_2'])) {
                    return json_return('F','1029','财务负责人电话号码，当区号时3位数字时，号码是8位');
                }
            }
            if(strlen($data['financer_tel_1'])==4){
                if(!preg_match('/^[0-9]{7}$/',$data['financer_tel_2'])) {
                    return json_return('F','1029','财务负责人电话号码，当区号是4位数字时，号码是7位');
                }
            }
            if($data['financer_mobile']===''){
                return json_return('F','1030','财务负责人手机号必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['financer_mobile'])) {
                return json_return('F','1030','财务负责人手机号只允许输入11位数字');
            }
            if($data['financer_email']===''){
                return json_return('F','1031','财务负责人邮箱必填');
            }
            if(!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i',$data['financer_email'])) {
                return json_return('F','1031','财务负责人邮箱格式不正确');
            }
            if($data['admin_name']===''){
                return json_return('F','1032','用户名必填');
            }
            if(!preg_match('/^[A-Za-z0-9@]{3,20}$/',$data['admin_name'])) {
                return json_return('F','1032','用户名允许数字、大小写字母及@符号（邮箱时）的3-20个字符');
            }
            if(db('admin_user')->where('username',$data['admin_name'])->find()){
                return json_return('F','1032','用户名已有');
            }
            if($data['admin_password']===''){
                return json_return('F','1033','登录密码必填');
            }
            if(!preg_match('/^[A-Za-z0-9!@#$%^&*-,.?;]{8,16}$/',$data['admin_password'])) {
                return json_return('F','1033','密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
            }
            $a=preg_match('/[0-9]/', $data['admin_password']);
            $b=preg_match('/[a-zA-Z]/', $data['admin_password']);
            $c=preg_match('/[!@#$%^&*-,.?;]/', $data['admin_password']);
            if(!($a && $b && $c)){
                return json_return('F','1033','密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
            }
            if(!isset($data['imgs']) || $data['imgs']==[]){
                return json_return('F','1034','车行照片必传');
            }
            $imgs_count=count($data['imgs']);
            if($imgs_count>4){
                return json_return('F','1034','车行照片最多上传4张');
            }
            //处理数据
            $insert['company_name']=$data['company_name'];
            $insert['shop_name']=$data['shop_name'];
            $insert['contract_no']=$data['contract_no'];
            $insert['contract_valid_start']=$data['contract_valid_start'];
            $insert['contract_valid_end']=$data['contract_valid_end'];
            $insert['licence_type']=$data['licence_type'];
            $insert['licence_no']=$data['licence_no'];
            $insert['licence_province_id']=$data['licence_province_id'];
            $insert['licence_city_id']=$data['licence_city_id'];
            $insert['licence_area_id']=$data['licence_area_id'];
            $insert['licence_address']=$data['licence_address'];
            $insert['licence_img']=$data['licence_img'];
            $insert['licence_valid_start']=$data['licence_valid_start'];
            $insert['licence_valid_end']=$data['licence_valid_end'];
            $insert['work_address']=$data['work_address'];
            $insert['custom_tel']=$data['custom_tel'];
            $insert['corporation_name']=$data['corporation_name'];
            $insert['mobile']=$data['mobile'];
            $insert['identcard_no']=$data['identcard_no'];
            $insert['identcard_front_img']=$data['identcard_front_img'];
            $insert['identcard_back_img']=$data['identcard_back_img'];
            $insert['businesser_name']=$data['businesser_name'];
            $insert['businesser_tel']=$data['businesser_tel_1'].'-'.$data['businesser_tel_2'];
            $insert['businesser_mobile']=$data['businesser_mobile'];
            $insert['businesser_email']=$data['businesser_email'];
            $insert['financer_name']=$data['financer_name'];
            $insert['financer_tel']=$data['financer_tel_1'].'-'.$data['financer_tel_2'];
            $insert['financer_mobile']=$data['financer_mobile'];
            $insert['financer_email']=$data['financer_email'];
            $insert['imgs']=implode(',', $data['imgs']);
            $insert['state']=1;
            $insert['created_at']=$now;
            $insert['runner_id']=UID;
            $insert_id=db('merchants')->insertGetId($insert);
            //入库
            if ($insert_id>0) {
                $admin_add=[
                    'username'=>$data['admin_name'],
                    'password'=>$data['admin_password'],
                    'role'=>'2',
                    'create_time'=>$now,
                    'update_time'=>$now,
                    'sort'=>'100',
                    'status'=>'1',
                ];
                $admin=AdminModel::create($admin_add);
                db('merchants')->where('id',$insert_id)->update(['sn'=>'H'.str_pad($insert_id,4,"0",STR_PAD_LEFT),'admin_id'=>$admin['id']]);
                record_log(request()->module(),request()->controller(),'商户开立');
                return json_return('T','','商户开立成功');
            } else {
                return json_return('F','500','商户开立失败');
            }
        }
        //获取中国省份
        $province=db('regions')->field('id,name')->where(['parent_id'=>'1','level'=>'1'])->order('sort asc')->select();
        //模板赋值
        $this->assign([
            'province'=>$province,
        ]);
        //渲染模板
        return $this->fetch();
    }
    //修改
    public function edit($id=''){
        $now=time();
        // 保存数据
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data=fortrim($data);
            $merchant=db('merchants')->find($data['id']);
            if(!$merchant){
                return json_return('F','500','请求错误');
            }
            //验证
            if($data['company_name']===''){
                return json_return('F','1001','公司名称必填');
            }
            if(!preg_match('/^[（）()\x{4e00}-\x{9fa5}]+$/u',$data['company_name'])) {
                return json_return('F','1001','公司名称只允许输入汉字或中英文()，不允许输入字母、数字、及其他特殊符号');
            }
            if($data['shop_name']===''){
                return json_return('F','1002','店铺名称必填');
            }
            if(!preg_match('/^[A-Za-z0-9\x{4e00}-\x{9fa5}]+$/u',$data['shop_name'])) {
                return json_return('F','1002','店铺名称允许输入汉字、字母、数字，不允许特殊字符输入');
            }
            if($data['contract_no']===''){
                return json_return('F','1003','合同编号必填');
            }
            if(!preg_match('/^[A-Za-z0-9-]{10,30}$/',$data['contract_no'])) {
                return json_return('F','1003','合同编号允许输入大写字母、数字、- 三种字符，字符长度：10-30个字符，例：HC-JM-20180415001');
            }
            if($data['contract_valid_start']===''){
                return json_return('F','1004','合同有效期开始日期必选');
            }
            if($data['contract_valid_end']===''){
                return json_return('F','1005','合同有效期结束日期必选');
            }
            if(strtotime($data['contract_valid_end']." 00:00:00")-strtotime($data['contract_valid_start']." 00:00:00")<=0){
                return json_return('F','1005','合同有效期结束日期必须大于开始日期');
            }
            if($data['licence_type']===''){
                return json_return('F','1006','证件类型必选');
            }
            if($data['licence_no']===''){
                return json_return('F','1007','证件号码必填');
            }
            if($data['licence_type']=='工商营业执照'){
                if(!preg_match('/^[0-9]{13}$/',$data['licence_no'])) {
                    return json_return('F','1007','证件号码，选择工商营业执照时对应输入框，只允许输入13位数字');
                }
            }
            if($data['licence_type']=='统一社会信用代码营业执照'){
                if(!preg_match('/^[0-9]{18}$/',$data['licence_no'])) {
                    return json_return('F','1007','证件号码，选择统一社会信用代码营业执照时只允许输入18位数字');
                }
            }
            if($data['licence_province_id']===''){
                return json_return('F','1008','营业执照所在省份必选');
            }
            if($data['licence_city_id']===''){
                return json_return('F','1009','营业执照所在城市必选');
            }
            if($data['licence_area_id']===''){
                return json_return('F','1010','营业执照所在区县必选');
            }
            if($data['licence_address']===''){
                return json_return('F','1011','营业执照详细地址必填');
            }
            if($data['licence_img']===''){
                return json_return('F','1012','营业执照必传');
            }
            if($data['licence_valid_start']===''){
                return json_return('F','1013','营业执照有效期开始日期必填');
            }
            if($data['licence_valid_end']===''){
                return json_return('F','1014','营业执照有效期结束日期必填');
            }
            if(strtotime($data['licence_valid_end']." 00:00:00")-strtotime($data['licence_valid_start']." 00:00:00")<=0){
                return json_return('F','1014','营业执照有效期结束日期必须大于开始日期');
            }
            if($data['work_address']===''){
                return json_return('F','1015','办公地址必填');
            }
            if($data['custom_tel']===''){
                return json_return('F','1016','客服电话必填');
            }
            if(!preg_match('/^[0-9]+$/',$data['custom_tel'])) {
                return json_return('F','1016','客服电话只允许输入数字');
            }
            if($data['corporation_name']===''){
                return json_return('F','1017','法人必填');
            }
            if(mb_strlen($data['corporation_name'],'utf8')<2 || mb_strlen($data['corporation_name'],'utf8')>8){
                return json_return('F','1027','法人字符限制2-8个字');
            }
            if($data['mobile']===''){
                return json_return('F','1018','联系电话必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['mobile'])) {
                return json_return('F','1018','联系电话只允许输入11位数字');
            }
            if($data['identcard_no']===''){
                return json_return('F','1019','身份证号必填');
            }
            if(!preg_match('/^([\d]{17}[xX\d]|[\d]{15})$/',$data['identcard_no'])) {
                return json_return('F','1019','身份证号格式不正确');
            }
            if($data['identcard_front_img']===''){
                return json_return('F','1020','身份证正面必传');
            }
            if($data['identcard_back_img']===''){
                return json_return('F','1021','身份证背面必传');
            }
            if($data['businesser_name']===''){
                return json_return('F','1022','业务负责人姓名必填');
            }
            if(mb_strlen($data['businesser_name'],'utf8')<2 || mb_strlen($data['businesser_name'],'utf8')>8){
                return json_return('F','1022','业务负责人姓名字符限制2-8个字');
            }
            if($data['businesser_tel_1']===''){
                return json_return('F','1023','业务负责人座机区号必填');
            }
            if(!preg_match('/^[0-9]{3,4}$/',$data['businesser_tel_1'])) {
                return json_return('F','1023','业务负责人座机区号允许3位和4位数字');
            }
            if($data['businesser_tel_2']===''){
                return json_return('F','1024','业务负责人电话号码必填');
            }
            if(strlen($data['businesser_tel_1'])==3){
                if(!preg_match('/^[0-9]{8}$/',$data['businesser_tel_2'])) {
                    return json_return('F','1024','业务负责人电话号码，当区号时3位数字时，号码是8位');
                }
            }
            if(strlen($data['businesser_tel_1'])==4){
                if(!preg_match('/^[0-9]{7}$/',$data['businesser_tel_2'])) {
                    return json_return('F','1024','业务负责人电话号码，当区号是4位数字时，号码是7位');
                }
            }
            if($data['businesser_mobile']===''){
                return json_return('F','1025','业务负责人手机号必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['businesser_mobile'])) {
                return json_return('F','1025','业务负责人手机号只允许输入11位数字');
            }
            if($data['businesser_email']===''){
                return json_return('F','1026','业务负责人邮箱必填');
            }
            if(!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i',$data['businesser_email'])) {
                return json_return('F','1026','业务负责人邮箱格式不正确');
            }
            if($data['financer_name']===''){
                return json_return('F','1027','财务负责人姓名必填');
            }
            if(mb_strlen($data['financer_name'],'utf8')<2 || mb_strlen($data['financer_name'],'utf8')>8){
                return json_return('F','1027','财务负责人姓名字符限制2-8个字');
            }
            if($data['financer_tel_1']===''){
                return json_return('F','1028','财务负责人座机区号必填');
            }
            if(!preg_match('/^[0-9]{3,4}$/',$data['financer_tel_1'])) {
                return json_return('F','1028','财务负责人座机区号允许3位和4位数字');
            }
            if($data['financer_tel_2']===''){
                return json_return('F','1029','财务负责人电话号码必填');
            }
            if(strlen($data['financer_tel_1'])==3){
                if(!preg_match('/^[0-9]{8}$/',$data['financer_tel_2'])) {
                    return json_return('F','1029','财务负责人电话号码，当区号时3位数字时，号码是8位');
                }
            }
            if(strlen($data['financer_tel_1'])==4){
                if(!preg_match('/^[0-9]{7}$/',$data['financer_tel_2'])) {
                    return json_return('F','1029','财务负责人电话号码，当区号是4位数字时，号码是7位');
                }
            }
            if($data['financer_mobile']===''){
                return json_return('F','1030','财务负责人手机号必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['financer_mobile'])) {
                return json_return('F','1030','财务负责人手机号只允许输入11位数字');
            }
            if($data['financer_email']===''){
                return json_return('F','1031','财务负责人邮箱必填');
            }
            if(!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i',$data['financer_email'])) {
                return json_return('F','1031','财务负责人邮箱格式不正确');
            }
            if($data['admin_name']===''){
                return json_return('F','1032','用户名必填');
            }
            if(!preg_match('/^[A-Za-z0-9@]{3,20}$/',$data['admin_name'])) {
                return json_return('F','1032','用户名允许数字、大小写字母及@符号（邮箱时）的3-20个字符');
            }
            if(db('admin_user')->where('username',$data['admin_name'])->where('id','neq',$merchant['admin_id'])->find()){
                return json_return('F','1032','用户名已有');
            }
            if($data['admin_password']!==''){
                if(!preg_match('/^[A-Za-z0-9!@#$%^&*-,.?;]{8,16}$/',$data['admin_password'])) {
                    return json_return('F','1033','密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
                }
                $a=preg_match('/[0-9]/', $data['admin_password']);
                $b=preg_match('/[a-zA-Z]/', $data['admin_password']);
                $c=preg_match('/[!@#$%^&*-,.?;]/', $data['admin_password']);
                if(!($a && $b && $c)){
                    return json_return('F','1033','密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
                }
            }
            if(!isset($data['imgs']) || $data['imgs']==[]){
                return json_return('F','1034','车行照片必传');
            }
            $imgs_count=count($data['imgs']);
            if($imgs_count>4){
                return json_return('F','1034','车行照片最多上传4张');
            }
            if(!isset($data['state']) || $data['state']===''){
                return json_return('F','1035','商户状态必选');
            }
            //处理数据
            $update['company_name']=$data['company_name'];
            $update['shop_name']=$data['shop_name'];
            $update['contract_no']=$data['contract_no'];
            $update['contract_valid_start']=$data['contract_valid_start'];
            $update['contract_valid_end']=$data['contract_valid_end'];
            $update['licence_type']=$data['licence_type'];
            $update['licence_no']=$data['licence_no'];
            $update['licence_province_id']=$data['licence_province_id'];
            $update['licence_city_id']=$data['licence_city_id'];
            $update['licence_area_id']=$data['licence_area_id'];
            $update['licence_address']=$data['licence_address'];
            $update['licence_img']=$data['licence_img'];
            $update['licence_valid_start']=$data['licence_valid_start'];
            $update['licence_valid_end']=$data['licence_valid_end'];
            $update['work_address']=$data['work_address'];
            $update['custom_tel']=$data['custom_tel'];
            $update['corporation_name']=$data['corporation_name'];
            $update['mobile']=$data['mobile'];
            $update['identcard_no']=$data['identcard_no'];
            $update['identcard_front_img']=$data['identcard_front_img'];
            $update['identcard_back_img']=$data['identcard_back_img'];
            $update['businesser_name']=$data['businesser_name'];
            $update['businesser_tel']=$data['businesser_tel_1'].'-'.$data['businesser_tel_2'];
            $update['businesser_mobile']=$data['businesser_mobile'];
            $update['businesser_email']=$data['businesser_email'];
            $update['financer_name']=$data['financer_name'];
            $update['financer_tel']=$data['financer_tel_1'].'-'.$data['financer_tel_2'];
            $update['financer_mobile']=$data['financer_mobile'];
            $update['financer_email']=$data['financer_email'];
            $update['imgs']=implode(',', $data['imgs']);
            $update['state']=$data['state'];
            $update['runner_id']=UID;
            //入库
            $rt=db('merchants')->where('id',$data['id'])->update($update);
            if ($rt!==false) {
                $merchant['admin_name']=(string)db('admin_user')->where('id',$merchant['admin_id'])->value('username');
                $up=['id'=>$merchant['admin_id'],'username'=>$data['admin_name'],'update_time'=>$now];
                if($data['admin_password']!==''){
                    $up['password']=$data['admin_password'];
                }
                AdminModel::update($up);
                if($rt>0 || $data['admin_password']!=='' || $data['admin_name']!==$merchant['admin_name']){
                    record_log(request()->module(),request()->controller(),'商户更新');
                }
                return json_return('T','','商户修改成功');
            } else {
                return json_return('F','500','商户修改失败');
            }
        }
        $merchant=db('merchants')->find($id);
        if(!$merchant){
            return $this->error('请求错误');
        }
        //处理数据
        $merchant['created_at_str']=date('Y-m-d H:i',$merchant['created_at']);
        $businesser_tel=explode('-',$merchant['businesser_tel']);
        $merchant['businesser_tel_1']=$businesser_tel[0];
        $merchant['businesser_tel_2']=$businesser_tel[1];
        $financer_tel=explode('-',$merchant['financer_tel']);
        $merchant['financer_tel_1']=$financer_tel[0];
        $merchant['financer_tel_2']=$financer_tel[1];
        $merchant['admin_name']=(string)db('admin_user')->where('id',$merchant['admin_id'])->value('username');
        if($merchant['imgs']===''){
            $merchant['imgs']=[];
        }else{
            $merchant['imgs']=explode(',', $merchant['imgs']);
        }
        //获取地区数据
        $province=db('regions')->field('id,name')->where(['parent_id'=>'1','level'=>'1'])->order('sort asc')->select();
        $city=db('regions')->field('id,name')->where(['parent_id'=>$merchant['licence_province_id'],'level'=>'2'])->order('sort asc')->select();
        $area=db('regions')->field('id,name')->where(['parent_id'=>$merchant['licence_city_id'],'level'=>'3'])->order('sort asc')->select();
        //模板赋值
        $this->assign([
            'province'=>$province,
            'city'=>$city,
            'area'=>$area,
            'merchant'=>$merchant,
        ]);
        //渲染模板
        return $this->fetch();
    }
    //详情
    public function look($id=''){
        $merchant=db('merchants')->where('id',$id)->find();
        if($merchant){
            $merchant['region_text']=region_text([$merchant['licence_province_id'],$merchant['licence_city_id'],$merchant['licence_area_id']]);
            $merchant['created_at_str']=date('Y-m-d H:i',$merchant['created_at']);
            $merchant['admin_name']=(string)db('admin_user')->where('id',$merchant['admin_id'])->value('username');
            if($merchant['imgs']===''){
                $merchant['imgs']=[];
            }else{
                $merchant['imgs']=explode(',', $merchant['imgs']);
            }
            //模板赋值
            $this->assign([
                'merchant'=>$merchant,
            ]);
            //渲染模板
            return $this->fetch();
        }else{
            return $this->error('请求错误');
        }
    }
    //开通
    public function enable($id=''){
        $merchant=db('merchants')->where('id',$id)->find();
        if($merchant){
            $rt=db('merchants')->where('id',$id)->update(['state'=>'1']);
            if($rt!==false){
                if($rt>0) record_log(request()->module(),request()->controller(),'商户开通');
                return json_return('T','200','开通成功');
            }else{
                return json_return('F','500','开通失败');
            }
        }else{
            return json_return('F','500','请求错误');
        }
    }
    //冻结
    public function disable($id=''){
        $merchant=db('merchants')->where('id',$id)->find();
        if($merchant){
            $rt=db('merchants')->where('id',$id)->update(['state'=>'0']);
            if($rt!==false){
                if($rt>0) record_log(request()->module(),request()->controller(),'商户冻结');
                return json_return('T','200','冻结成功');
            }else{
                return json_return('F','500','冻结失败');
            }
        }else{
            return json_return('F','500','请求错误');
        }
    }
    //权限设置
    public function setauth(){
        $now=time(); 
        if ($this->request->isPost()) {
            $data = $this->request->post();
            //检查有无越限
            $menu_auth=db('admin_role')->where('id','3')->value('menu_auth');
            $menu_auth=json_decode($menu_auth,true);
            $new_menu_auth=[];
            //检查是否丢失上级
            foreach ($data['menu_auth'] as $k => $v) {
                $menu=db('admin_menu')->where('id',$v)->find();
                if($menu){
                    $new_menu_auth[]=$v;
                    $pids=get_menu_pids($v);
                    $pids=explode(',',$pids);
                    if($pids){
                        foreach ($pids as $_k => $_v) {
                            if(is_numeric($_v)){
                                $new_menu_auth[]=$_v;
                            }
                        }
                    }
                }
            }
            $new_menu_auth=array_unique($new_menu_auth);
            $new_menu_auth=array_values($new_menu_auth);
            $new_menu_auth=json_encode($new_menu_auth);
            //入库
            $update=[];
            $update['menu_auth']=$new_menu_auth;
            $rt=db('admin_role')->where('id','2')->update($update);
            if($rt!==false){
                if($rt>0){
                    record_log(request()->module(),request()->controller(),'权限设置');
                }
                return json_return('T','200','权限设置成功');
            }else{
                return json_return('F','500','权限设置失败');
            }
        } 
        //查出所有权限
        $menu_auth=db('admin_role')->where('id','3')->value('menu_auth');
        $menu_auth=json_decode($menu_auth,true);
        $admin_menu=db('admin_menu')->field('id,title,pid,url_value')->where('id','in',$menu_auth)->order('sort asc')->select();
        $admin_menu=cateSort($admin_menu,0,0,'pid');
        //查出商户权限
        $admin_role=db('admin_role')->field('menu_auth')->where('id','2')->find();
        //处理数据
        $admin_role['menu_auth']=json_decode($admin_role['menu_auth'],true);
        //模板赋值
        $this->assign([
            'admin_menu'=>$admin_menu,
            'menu_auth'=>$menu_auth,
            'admin_role'=>$admin_role,
        ]);
        //渲染模板
        return $this->fetch();
    }
}