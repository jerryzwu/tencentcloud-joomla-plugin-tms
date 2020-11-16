<?php

/*
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

defined('_JEXEC') or die;

require_once 'DebugerLog.php';
require_once 'vendor/autoload.php';

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Cms\V20190321\CmsClient;
use TencentCloud\Cms\V20190321\Models\TextModerationRequest;
use TencentCloud\Cms\V20190321\Models\TextModerationResponse;


class TencentcloudTmsAction
{
    /**
     * db
     * @var JDatabaseDriver|null
     */
    private $db;

    /**
     * 插件商
     * @var string
     */
    private $name = 'tencentcloud';

    /**
     * 上报url
     * @var string
     */
    private $log_server_url = 'https://appdata.qq.com/upload';

    /**
     * 应用名称
     * @var string
     */
    private $site_app = 'Joomla';

    /**
     * 插件类型
     * @var string
     */
    private $plugin_type = 'tms';
    private $secret_id;
    private $secret_key;


    /**
     * tencent_cos constructor.
     * @param $cos_options
     */
    public function __construct()
    {
        $this->db = JFactory::getDbo();
        $this->init();
    }

    /**
     * 初始化配置
     */
    private function init()
    {
        $tms_options = $this->getOptions();
        $tms_options = !empty($tms_options) ? $tms_options : array();
        $this->secret_id = isset($tms_options['secret_id']) ? $tms_options['secret_id'] : '';
        $this->secret_key = isset($tms_options['secret_key']) ? $tms_options['secret_key'] : '';
        return true;
    }

    /**
     * 获取腾讯云对象存储插件的用户密钥
     * @return array|bool   用户密钥
     */
    public function getOptions()
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('params', 'type')))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('name') . " = 'plg_content_tencentcloud_tms'");
        $db->setQuery($query);

        $params = $db->loadAssoc();
        if (empty($params) || !isset($params, $params['params'])) {
            return false;
        }
        return json_decode($params['params'], true);
    }

    /**
     * 返回cos对象
     * @param array $options 用户自定义插件参数
     * @return \Qcloud\Cos\Client
     */
    private function getClient()
    {
        if (empty($this->secret_id) || empty($this->secret_key)) {
            DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_CONF_EMPTY'));
            return false;
        }
        $cred = new Credential($this->secret_id, $this->secret_key);
        $clientProfile = new ClientProfile();
        return new CmsClient($cred, "ap-shanghai", $clientProfile);
    }

    /**
     * 文本检测
     * @param $text
     * @param int $type
     * @return bool
     * @throws Exception
     */
    public function examineContent($article)
    {
        self::init();
        $text = '';
        // 检查文章名称、别名、文章内容
        isset($article->title) && $text .= $article->title;
        isset($article->alias) && $text .= "," . $article->alias;
        isset($article->introtext) && $text .= "," . $article->introtext;
        isset($article->fulltext) && $text .= "," . $article->fulltext;
        if ($text !== '' && !$this->textModeration($article, $text)) {
            DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_CONTENT_NOT_PASS'));
            $article->setError(JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_CONTENT_NOT_PASS'));
            return false;
        }

        $text = '';
        // 检查文章中图片替代文章
        isset($article->images) && $images = json_decode($article->images, true);
        if (!empty($images) && is_array($images)) {
            isset($images['image_intro_alt']) && $text .= $images['image_intro_alt'];
            isset($images['image_intro_caption']) && $text .= "," . $images['image_intro_caption'];
            isset($images['image_fulltext_alt']) && $text .= "," . $images['image_fulltext_alt'];
            isset($images['image_fulltext_caption']) && $text .= "," . $images['image_fulltext_caption'];
        }
        // 检查文章中链接及链接文字
        isset($article->urls) && $urls = json_decode($article->urls, true);
        if (!empty($urls) && is_array($urls)) {
            isset($urls['urla']) && $text .= $urls['urla'];
            isset($urls['urlatext']) && $text .= "," . $urls['urlatext'];
            isset($urls['targeta']) && $text .= "," . $urls['targeta'];
            isset($urls['urlb']) && $text .= "," . $urls['urlb'];
            isset($urls['urlbtext']) && $text .= "," . $urls['urlbtext'];
            isset($urls['targetb']) && $text .= "," . $urls['targetb'];
            isset($urls['urlc']) && $text .= "," . $urls['urlc'];
            isset($urls['urlctext']) && $text .= "," . $urls['urlctext'];
            isset($urls['targetc']) && $text .= "," . $urls['targetc'];
        }
        if ($text !== '' && !$this->textModeration($article, $text)) {
            DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_PICTURE_LINK_NOT_PASS'));
            $article->setError(JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_PICTURE_LINK_NOT_PASS'));
            return false;
        }

        $text = '';
        // 检查文章发布选项中元描述、元关键字
        isset($article->metakey) && $text .= $article->metakey;
        isset($article->metadesc) && $text .= "," . $article->metadesc;
        if ($text !== '' && !$this->textModeration($article, $text)) {
            DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_PUBLISH_OPTIONS_NOT_PASS'));
            $article->setError(JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_PUBLISH_OPTIONS_NOT_PASS'));
            return false;
        }
    }

    /**
     * 调用腾讯云文本检测接口
     * @param TMSOptions $TMSOptions
     * @param $text
     * @return Exception|TextModerationResponse|TencentCloudSDKException
     * @throws Exception
     */
    private function textModeration($article, $text)
    {
        try {
            $client = $this->getClient();
            if (!($client instanceof CmsClient)) {
                DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_CMSCLIENT_ERROR'));
                $article->setError(JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_CMSCLIENT_ERROR'));
                return false;
            }
            $req = new TextModerationRequest();
            $params['Content'] = base64_encode($text);
            $req->fromJsonString(json_encode($params, JSON_UNESCAPED_UNICODE));
            $response = $client->TextModeration($req);

            //检测接口异常不影响用户发帖回帖
            if (!($response instanceof TextModerationResponse)) {
                DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_OBJECT_ERROR'));
                $article->setError(JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_OBJECT_ERROR'));
                return false;
            }

            //检测通过
            if ($response->getData()->getEvilLabel() === 'Normal' && $response->getData()->getEvilFlag() === 0) {
                DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_PASS'));
                return true;
            }
            return false;
        } catch (TencentCloudSDKException $e) {
            DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_FUNCTIONT_EXECEPTION'));
            return false;
        }
    }

    /**
     * 获取腾讯云配置
     */
    public function getSiteInfo()
    {
        $db = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['site_id', 'site_url', 'uin']))
            ->from($db->quoteName('#__tencentcloud_conf'))
            ->where('1=1 limit 1');
        $db->setQuery($query);

        try {
            $row = $db->loadAssoc();
        } catch (RuntimeException $e) {
            return false;
        }

        return $row;
    }

    /**
     * 写入腾讯云配置
     */
    public function setSiteInfo()
    {
        $name = $this->name;
        $siteId = uniqid('joomla_');
        $siteUrl = $_SERVER['HTTP_HOST'];
        if (isset($_SERVER["REQUEST_SCHEME"])) {
            $siteUrl = $_SERVER["REQUEST_SCHEME"] . '://' . $siteUrl;
        }

        $db = $this->db;
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__tencentcloud_conf'))
            ->columns(array($db->quoteName('name'), $db->quoteName('site_id'), $db->quoteName('site_url')))
            ->values($db->quote($name) . ', ' . $db->quote($siteId) . ', ' . $db->quote($siteUrl));
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * 判断表是否存在
     * @param string $table_name
     * @return bool
     */
    public function isTableExist($table_name)
    {
        $db = $this->db;
        $table = $db->replacePrefix($db->quoteName($table_name));
        $table = trim($table, "`");
        $tables = $db->getTableList();
        if (in_array($table, $tables)) {
            return true;
        }
        return false;
    }

    /**
     * 创建腾讯云全局配置表
     * @return bool|void
     */
    public function createConfTable()
    {
        $db = $this->db;
        $serverType = $db->getServerType();
        if ($serverType != 'mysql') {
            return;
        }
        $creaTabSql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__tencentcloud_conf')
            . ' (' . $db->quoteName('name') . " varchar(100) NOT NULL DEFAULT '', "
            . $db->quoteName('site_id') . " varchar(100) NOT NULL DEFAULT '', "
            . $db->quoteName('site_url') . " varchar(255) NOT NULL DEFAULT '', "
            . $db->quoteName('uin') . " varchar(100) NOT NULL DEFAULT '' "
            . ') ENGINE=InnoDB';

        if ($db->hasUTF8mb4Support()) {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;';
        } else {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8 DEFAULT COLLATE=utf8_unicode_ci;';
        }
        $db->setQuery($creaTabSql)->execute();
        return true;
    }


    /**
     * 创建腾讯云插件配置表
     * @return bool|void
     */
    public function createPluginConfTable()
    {
        $db = $this->db;
        $serverType = $db->getServerType();
        if ($serverType != 'mysql') {
            return;
        }
        $creaTabSql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__tencentcloud_plugin_conf')
            . ' (' . $db->quoteName('type') . " varchar(20) NOT NULL DEFAULT '', "
            . $db->quoteName('uin') . " varchar(20) NOT NULL DEFAULT '',"
            . $db->quoteName('use_time') . " int(11) NOT NULL DEFAULT 0"
            . ') ENGINE=InnoDB';

        if ($db->hasUTF8mb4Support()) {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;';
        } else {
            $creaTabSql = $creaTabSql
                . ' DEFAULT CHARSET=utf8 DEFAULT COLLATE=utf8_unicode_ci;';
        }
        $db->setQuery($creaTabSql)->execute();
        return true;
    }


    public function dropPluginConfTable()
    {
        $db = $this->db;
        $serverType = $db->getServerType();
        if ($serverType != 'mysql') {
            return;
        }
        $creaTabSql = 'DROP TABLE IF EXISTS ' . $db->quoteName('#__tencentcloud_plugin_conf');

        $db->setQuery($creaTabSql)->execute();
        return true;
    }

    /**
     * 获取腾讯云插件配置
     * @return bool|mixed|null
     */
    private function getPluginConf()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['type', 'uin', 'use_time']))
            ->from($db->quoteName('#__tencentcloud_plugin_conf'))
            ->where($db->quoteName('type') . " = '" . $this->plugin_type . "' limit 1");
        $db->setQuery($query);

        try {
            $row = $db->loadAssoc();
        } catch (RuntimeException $e) {
            return false;
        }

        return $row;
    }


    /**
     * 发送post请求
     * @param string  地址
     * @param mixed   参数
     */
    private static function sendPostRequest($url, $data)
    {
        DebugLog::writeDebugLog('debug', JText::_('PLG_CONTENT_TENCENTCLOUD_TMS_SEND_DATA') . json_encode($data));
        if (function_exists('curl_init')) {
            ob_start();
            $json_data = json_encode($data);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);   //设置一秒超时
            curl_exec($curl);
            curl_exec($curl);
            curl_close($curl);
            ob_end_clean();
        }
    }


    /**
     * 发送用户信息（非敏感）
     * @param $data
     * @return bool|void
     */
    private function sendUserExperienceInfo($data)
    {
        if (empty($data) || !is_array($data) || !isset($data['action'])) {
            return;
        }
        $url = $this->log_server_url;
        $this->sendPostRequest($url, $data);
        return true;
    }

    /**
     * @param string $action 上报方法
     */
    public function report($action)
    {
        //数据上报
        $conf = $this->getSiteInfo();
        $pluginConf = $this->getPluginConf();
        if (isset($pluginConf, $pluginConf['uin'])) {
            $uin = $pluginConf['uin'];
        }
        $data = array(
            'action' => $action,
            'plugin_type' => $this->plugin_type,
            'data' => array(
                'site_id' => $conf['site_id'],
                'site_url' => $conf['site_url'],
                'site_app' => $this->site_app,
                'uin' => isset($uin) ? $uin : '',
                'others' => json_encode(array())
            )
        );
        $this->sendUserExperienceInfo($data);
    }
}
