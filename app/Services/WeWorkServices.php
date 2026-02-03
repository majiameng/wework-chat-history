<?php
namespace App\Services;

use App\Jobs\MediaJob;
use App\Jobs\MessageAnalyzeJob;
use App\Models\CompanyModel;
use App\Models\MediaModel;
use App\Models\MessageOriginalModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use tinymeng\WeWorkFinanceSDK\WxFinanceSDK;

class WeWorkServices
{
    /**
     * 是否开启异步处理
     * 如果开启需要开启队列执行：php artisan queue:work
     * @var bool
     */
    public $async = false;

    public function index()
    {
        $where = [
            'status' => 1,
        ];
        $companyList = CompanyModel::where($where)->get();
        foreach ($companyList as $value){
            $this->execCompany($value);
        }
    }

    /**
     * @param $value
     * @return void
     */
    public function execCompany($value)
    {
        DB::beginTransaction();
        // 获取最新seq
        $seq = $value['seq']??0;
        // 企业配置
        $corpConfig = [
            'corpid'       => $value['corpid'],
            'secret'       => $value['secret'],
            'path'         => config('upload.upload_path'),
            'private_keys' => [
                1 => $value['prikey'],
            ],
        ];
        $wxFinanceSDK = WxFinanceSDK::init($corpConfig, ['default'   => 'php-ffi',]);
        // 获取会话记录数据(解密)
        $messages = $wxFinanceSDK->getDecryptChatData($seq,$value['limits']);
        // 下载媒体资源
        foreach ($messages as $key=>$message){
            if(!empty($message['msgtype']) && $wxFinanceSDK->isMedia($message['msgtype'])){
                $messages[$key]['media'] = $wxFinanceSDK->getDownloadMediaData($message[$message['msgtype']],$message['msgtype']);
            }
        }
        // 批量保存消息数据到数据库
        if (!empty($messages) && is_array($messages)) {
            $seq = $this->batchSaveMessages($messages,$value['company_id']);

            // 存储最后seq
            $value->seq = $seq;
            $value->save();
        }
        DB::commit();
    }

    /**
     * 批量保存消息到数据库
     * @param array $messages 消息数据数组
     * @return void
     */
    private function batchSaveMessages($messages,$companyId)
    {
        $seq = 0;
        $dataList = [];
        $mediaList = [];

        foreach ($messages as $message) {
            $media = $message['media'] ?? '';
            $seq = $message['seq'] ?? 0;
            // 准备保存的数据
            $data = [
                'company_id' => $companyId,
                'msgid' => $message['msgid'] ?? '',
                'publickey_ver' => 1, // 默认密钥版本
                'seq' => $message['seq'] ?? 0,
                'action' => $message['action'] ?? '',
                'msgfrom' => $message['from'] ?? '',
                'tolist' => !empty($message['tolist']) && is_array($message['tolist'])
                    ? implode(',', $message['tolist'])
                    : '',
                'msgtype' => $message['msgtype'] ?? '',
                'msgtime' => $message['msgtime'] ?? 0,
                'text' => '',
                'sdkfield' => '',
                'md5sum' => '',
                'msgdata' => json_encode($message, JSON_UNESCAPED_UNICODE), // 保存完整的消息数据为JSON
                'status' => 1, // 默认未加载媒体
                'media_code' => 0,
                'media_path' => '',
                'roomid' => $message['roomid'] ?? '',
            ];
            if(is_object($media)){
                $data['media_path'] = $media->getPathname();
            }

            // 处理文本消息
            if (isset($message['text']) && is_array($message['text']) && isset($message['text']['content'])) {
                $data['text'] = mb_substr($message['text']['content'], 0, 4000); // 限制长度
            } elseif (isset($message['text']) && is_string($message['text'])) {
                $data['text'] = mb_substr($message['text'], 0, 4000);
            }

            // 处理媒体消息（file, video, image, voice等）
            $mediaTypes = ['file', 'video', 'image', 'voice', 'emotion'];
            $mediaData = null;

            foreach ($mediaTypes as $mediaType) {
                if (isset($message[$mediaType]) && is_array($message[$mediaType])) {
                    $mediaData = $message[$mediaType];

                    // 提取 sdkfileid
                    if (isset($mediaData['sdkfileid'])) {
                        $data['sdkfield'] = mb_substr($mediaData['sdkfileid'], 0, 2000);
                    }
                    // 提取 md5sum
                    if (isset($mediaData['md5sum'])) {
                        $data['md5sum'] = $mediaData['md5sum'];
                    }

                    // 准备媒体表数据
                    $mediaItem = [
                        'company_id' => $companyId,
                        'md5sum' => $mediaData['md5sum'] ?? '',
                        'type' => $mediaType,
                        'filesize' => $mediaData['filesize'] ?? 0,
                        'media_path' => $data['media_path']??'',
                    ];
                    $mediaList[] = $mediaItem;
                    break; // 一个消息只有一种媒体类型
                }
            }

            $dataList[] = $data;
        }

        // 批量插入消息数据
        if (!empty($dataList)) {
            try {
                MessageOriginalModel::insert($dataList);
            } catch (\Exception $e) {
                // 记录错误日志
                Log::error('批量保存消息失败: ' . $e->getMessage(), [
                    'count' => count($dataList),
                    'error' => $e->getTraceAsString()
                ]);
            }
        }

        // 批量插入媒体数据
        if (!empty($mediaList)) {
            try {
                MediaModel::insert($mediaList);
                // 发布媒体处理任务
                $this->mediaProcess(array_column($mediaList,'md5sum'));
            } catch (\Exception $e) {
                // 记录错误日志
                Log::error('批量保存媒体失败: ' . $e->getMessage(), [
                    'count' => count($mediaList),
                    'error' => $e->getTraceAsString()
                ]);
            }
        }
        if (!empty($dataList)) {
            // 发布消息分析任务
            $this->messageAnalyze(array_column($dataList,'msgid'));
        }
        return $seq;
    }

    /**
     * @param $md5sumList
     * @return void
     */
    public function mediaProcess($md5sumList)
    {
        if(config('queue.status')){
            MediaJob::dispatch($md5sumList);
        }else{
            $mediaJob = new MediaJob($md5sumList);
            $mediaJob->handle();
        }
    }

    /**
     * @param $msgidList
     * @return void
     */
    public function messageAnalyze($msgidList)
    {
        if(config('queue.status')){
            MessageAnalyzeJob::dispatch($msgidList);
        }else{
            $mediaJob = new MessageAnalyzeJob($msgidList);
            $mediaJob->handle();
        }
    }

}
