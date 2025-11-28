<?php

namespace App\Models;

class UserModel extends CommonModel
{
    public $table = 'user';
    public $primaryKey = 'user_id';


    /**
     * @param $userName
     * @param $companyId
     * @return mixed
     */
    static public function getListByName($userName,$companyId)
    {
        $list = self::whereIn('username', $userName)->pluck('user_id', 'username')->toArray();

        foreach ($userName as $v){
            if(!isset($list[$v])){
                $type = 1;
                if (strpos($v, 'wb_') === 0) {
                    $type = 3;
                } elseif (strpos($v, 'wo_') === 0 || strpos($v, 'wm_') === 0) {
                    $type = 2;
                }
                $model = new self();
                $model->username = $v;
                $model->company_id = $companyId;
                $model->type = $type;
                $model->save();
                $list[$v] = $model->{$model->primaryKey};
            }
        }
        return $list;
    }
}
