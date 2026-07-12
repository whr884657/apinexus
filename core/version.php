<?php
/**
 * misc-api 系统当前版本号常量
 *
 * 说明：
 * - 在线更新、关于页、update.json 均读取此常量
 * - 发版时须同步 update.json、update-log.json
 */

if (!defined('VS_VERSION')) {
    define('VS_VERSION', '2.12.2');
}
