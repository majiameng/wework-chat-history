<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // 企业微信定时任务 - 已使用守护进程模式运行，此处注释掉
        // 如需使用调度器，需要配置 cron: * * * * * cd /path-to-project && php artisan schedule:run
        // $schedule->command(WeWork::class)
        //     ->everyMinute()
        //     ->withoutOverlapping() // 防止任务重叠执行
        //     ->runInBackground(); // 后台运行

        // 每月1号执行表轮转：将message_original表重命名为月份表，并创建新表
//        $schedule->command('MessageOriginalTableRotate')
//            ->monthlyOn(1, '00:00') // 每月1号00:00执行
//            ->withoutOverlapping() // 防止任务重叠执行
//            ->runInBackground(); // 后台运行
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
