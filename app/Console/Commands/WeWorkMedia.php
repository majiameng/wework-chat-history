<?php
namespace App\Console\Commands;

use App\Models\MediaModel;
use App\Services\WeWorkServices;

class WeWorkMedia extends CommonCommand
{

    public $limit = 100;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'WeWorkMedia';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'WeWorkMedia';


    public function main($params=[]){
        $list = MediaModel::where('file_url','')->limit($this->limit)->pluck('md5sum')->toArray();
        (new WeWorkServices())->mediaProcess($list);
    }
}
