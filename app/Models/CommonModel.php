<?php

/**
 * Created by tinymeng Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @Author : TinyMeng <666@majiameng.com>
 * @Date: 2022-11-24
 */
class CommonModel extends Model
{
    protected $perPage = 10;

    /**
     * 是否主动维护时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * 限制不能修改id字段
     * The attributes that aren't mass assignable.
     * @author: JiaMeng <666@majiameng.com>
     * @var array
     */
    protected $guarded = ['id'];

}
