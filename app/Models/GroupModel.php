<?php

namespace App\Models;

class GroupModel extends CommonModel
{
    public $table = 'group';
    public $primaryKey = 'group_id';


    /**
     * @param $nameList
     * @param $companyId
     * @return mixed
     */
    static public function getListByName($nameList,$companyId)
    {
        $models = self::whereIn('room_id', array_keys($nameList))->get()->keyBy('room_id');
        $list = [];

        foreach ($nameList as $roomId => $value){
            if(!isset($models[$roomId])){
                // 不存在则创建
                $type = 2;
                if (strpos($roomId, 'wr_') === 0) {
                    $type = 1;
                }
                $model = new self();
                $model->room_id = $roomId;
                $model->group_name = $value['group_name'];
                $model->list = $value['list'];
                $model->company_id = $companyId;
                $model->type = $type;
                $model->save();
                $list[$roomId] = $model->{$model->primaryKey};
            } else {
                // 存在则更新 list 字段，直接使用已查询的模型对象
                $model = $models[$roomId];
                $model->list = $value['list'];
                $model->save();
                $list[$roomId] = $model->{$model->primaryKey};
            }
        }
        return $list;
    }
}
