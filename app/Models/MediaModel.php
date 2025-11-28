<?php

namespace App\Models;

class MediaModel extends CommonModel
{
    public $table = 'media';
    public $primaryKey = 'media_id';

    /**
     * @param $md5sunArray
     * @return mixed
     */
    static public function getListByMd5sum($md5sunArray)
    {
        return self::where(['status' => 1])->whereIn('md5sum', $md5sunArray)->orderBy('media_id', 'asc')->pluck('media_id', 'md5sum')->toArray();
    }
}
