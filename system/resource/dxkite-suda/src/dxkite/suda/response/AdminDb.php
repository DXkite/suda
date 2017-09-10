<?php
/**
 * Suda FrameWork
 *
 * An open source application development framework for PHP 7.0.0 or newer
 * 
 * Copyright (c)  2017 DXkite
 *
 * @category   PHP FrameWork
 * @package    Suda
 * @copyright  Copyright (c) DXkite
 * @license    MIT
 * @link       https://github.com/DXkite/suda
 * @version    since 1.2.4
 */

namespace dxkite\suda\response;

use suda\core\Session;
use suda\core\Cookie;
use suda\core\Request;
use suda\core\Query;
use dxkite\suda\DBManager;

/**
* visit url /system/database as all method to run this class.
* you call use u('admin_database',Array) to create path.
* @template: default:admin_db.tpl.html
* @name: admin_database
* @url: /system/database
* @param:
*/
class AdminDb extends \dxkite\suda\ACResponse
{
    public function onAction(Request $request)
    {
        
        $page=$this->page('suda:admin_db')
        ->set('title', __('数据管理'))
        ->set('header_select', 'system_admin');
        $list=DBManager::readList();
        if (count($list)) {
            $backupname= $request->get()->current ?? DBManager::selectLaster();
            $read=DBManager::read($backupname);
            $page->set('time', $read['time']??0);
            $page->set('current_name', $backupname);
            $page->set('current', $read['module'] ?? []);
            $page->set('no_current', false);
            $page->set('current_size', $read['module_size'] ?? []);
        }else{
            $page->set('no_current', true);
        }
        
        $page->set('backup_list', $list);
        return $page->render();
    }
}
