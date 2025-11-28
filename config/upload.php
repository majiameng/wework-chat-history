<?php

return [
    'accessKeyId'		=> '',
    'accessKeySecret' 	=> '',
//        'endpoint'			=> 'https://oss-accelerate.aliyuncs.com',//内网
    'endpoint'			=> 'https://oss-cn-beijing.aliyuncs.com',//外网
    'bucket'            => 'oss',
    'isCName'			=> false,
    'timeout'           => '5184000',
    'connectTimeout'    => '10',
    'transport'     	=> 'http',//如果支持https，请填写https，如果不支持请填写http
    'max_keys'          => 1000,//max-keys用于限定此次返回object的最大数，如果不设定，默认为100，max-keys取值不能大于1000
    'securityToken'		=> null,
    'urlPrefix'         => 'https://oss.oss.com/',
    // 上传文件保存路径
    'upload_path'       => public_path('uploads/'),
];
