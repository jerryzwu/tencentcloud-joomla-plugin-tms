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

/**
 * Script file of Joomla CMS
 *
 * @since  1.6.4
 */
include_once 'DebugerLog.php';
include_once 'TencentcloudTmsAction.php';

class PlgContentTencentcloud_tmsInstallerScript
{
    /**
     * db
     * @var JDatabaseDriver|null
     */
    private $db;

    private $tms_object;

    public function __construct()
    {
        DebugLog::writeDebugLog('info', 'PlgContentTencentcloud_tmsInstallerScript __construct');
        $this->db = JFactory::getDbo();
        $this->tms_object = new TencentcloudTmsAction();
    }

    /**
     * 安装事件
     * @param string $action
     * @param object $installer
     * @return bool
     * @throws Exception
     */
    public function postflight($action, $installer)
    {
        try {
            DebugLog::writeDebugLog('info', 'tencentcloud_tms postflight');
            if (!$this->tms_object->isTableExist('#__tencentcloud_conf')) {
                $this->tms_object->createConfTable();
            }

            if (!$this->tms_object->isTableExist('#__tencentcloud_plugin_conf')) {
                $this->tms_object->createPluginConfTable();
            }

            //获取配置
            $conf = $this->tms_object->getSiteInfo();
            //如果没有腾讯配置，则认为是第一次安装,写入初始化配置
            if (!$conf) {
                $this->tms_object->setSiteInfo();
            }
            $this->tms_object->report('activate');
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }


    /**
     * 卸载事件
     * @param object $installer
     * @return bool
     */
    public function uninstall($installer)
    {
        try {
            DebugLog::writeDebugLog('debug', 'tencentcloud_tms uninstall');
            $this->tms_object->report('uninstall');
            $this->tms_object->dropPluginConfTable();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }

    }
}