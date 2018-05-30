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

namespace cn\atd3\response;

use suda\core\{Session,Cookie,Request,Query};
use cn\atd3\table\TestTable;
/**
* visit url / as all method to run this class.
* you call use u('index',Array) to create path.
* @template: default:index.tpl.html
* @name: index
* @url: /
* @param: 
*/
class IndexResponse extends \suda\core\Response
{
    public function onRequest(Request $request)
    {
        /**
         * glad to see you in this file
         * 
         * i was very happy that you choose suda as your php framework
         * 
         */
        $page=$this->page('index');
        $page->set('title', 'Welcome to use Suda!');
        $page->set('helloworld', 'Hello,World!');
        // create database table instance
        $table=new TestTable;
        // insert into database table
        $table->insert([
            'name'=>'dxkite',
            'value'=>date('Y-m-d H:i:s'). ' get request  from '.$request->ip(),
        ]);
        // list database table
        $page->set('list',$table->list());
        return $page->render();
    }
}
