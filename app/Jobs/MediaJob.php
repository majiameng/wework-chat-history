<?php

namespace App\Jobs;

use App\Models\MediaModel;
use Illuminate\Support\Facades\Log;
use tinymeng\uploads\Upload;

class MediaJob extends CommonJob
{
    public $md5sumList;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($md5sumList)
    {
//        Log::info('MediaJob: ', [
//            'md5sumList' => json_encode($md5sumList),
//        ]);
        $this->md5sumList = $md5sumList;
    }

    /**
     * 企业微信WebHook
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(empty($this->md5sumList)){
            return;
        }
        // 获取媒体文件列表
        $list = MediaModel::where('file_url','')
            ->whereIn('md5sum', $this->md5sumList)
            ->get();
        if(empty($list)){
            return;
        }

        // 上传文件到OSS
        $uploadConfig = config('upload');
        $drive = Upload::oss($uploadConfig);
        foreach ($list as $item) {
            try {
                $mediaPath = $item['media_path'];
                if(!file_exists($mediaPath)){
                    $item->status = 0;// 标记记录
                    $item->save();
                    Log::warning('MediaJob: 文件不存在', ['md5sum' => $item->md5sum, 'path' => $mediaPath]);
                    continue;
                }

                // 通过正则表达式从路径中提取文件后缀名
                $fileExtension = '';
                if (preg_match('/\.([a-zA-Z0-9]+)$/', $mediaPath, $matches)) {
                    $fileExtension = $matches[1];
                }
                // 使用 md5sum + 文件后缀作为文件名
                $fileName = $item->md5sum . (!empty($fileExtension) ? '.' . $fileExtension : '');
                $path = 'wework/media/' . date('Ymd') . '/' . $fileName;
                $result = $drive->uploadFile($path, $mediaPath);
                if($result){
                    // 更新数据库
                    $item->suffix = $fileExtension;
                    $item->file_url = $uploadConfig['urlPrefix'].$path;
                    $item->save();
                    // 删除文件
                    unlink($mediaPath);
                } else {
                    Log::warning('MediaJob: 文件上传返回失败', ['md5sum' => $item->md5sum, 'path' => $path]);
                }
            }catch (\Exception $e){
                Log::error('MediaJob: 媒体文件上传失败', [
                    'md5sum' => $item->md5sum ?? '',
                    'path' => $mediaPath ?? '',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('MediaJob 执行完成');
        return;
    }

}
