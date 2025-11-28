<?php

namespace App\Models;

class MessageModel extends CommonModel
{
    public $table = 'message';
    
    /**
     * 关联媒体表
     */
    public function media()
    {
        return $this->belongsTo(MediaModel::class, 'media_id', 'media_id');
    }
    
    /**
     * 关联用户表
     */
    public function fromUser()
    {
        return $this->belongsTo(UserModel::class, 'from_user_id', 'user_id');
    }
    
    /**
     * 关联群组表
     */
    public function group()
    {
        return $this->belongsTo(GroupModel::class, 'group_id', 'group_id');
    }
}
