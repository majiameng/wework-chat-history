<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use tinymeng\tools\File;

class CommonCommand extends Command {

    public $day;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'CommonCommand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CommonCommand';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->day = date("Ymd",time());//今天日期
        parent::__construct();
    }

    /**
     * @return void
     */
    public function handle()
    {
        try {
            $this->main();
        }catch (\Exception $exception){
            $this->errorToEmail($exception);
        }
    }

    /**
     * @param $params
     * @return void
     */
    public function main($params=[]){}

    /**
     * 记录日志文件
     * @param $message
     */
    public function writeln($message,$file_name="",$echo=true){
        if(empty($file_name)) $file_name = "command_".str_replace(":","_",$this->getName());
        File::writeLog($this->getName().' '.$message,$file_name, $echo);
    }

    /**
     * 捕捉异常并发送邮件
     * @param $exception
     */
    public function errorToEmail($exception){
        $this->writeln('errorToEmail:'.$exception->getFile().','. $exception->getLine().','. $exception->getMessage());
        $this->writeln('errorToEmail:'.$exception->getFile().','. $exception->getLine().','. $exception->getMessage(),'command_error');
    }

}
