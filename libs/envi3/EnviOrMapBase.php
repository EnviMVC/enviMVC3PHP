<?php
/**
 * PropelPDO風のオブジェクトを作成するベースクラス
 *
 * PHP versions 5
 *
 *
 * @category   MVC
 * @package    Envi3
 * @subpackage EnviMVCCore
 * @author     Akito <akito-artisan@five-foxes.com>
 * @copyright  2011-2012 Artisan Project
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    GIT: $Id$
 * @link       https://github.com/EnviMVC/EnviMVC3PHP
 * @see        https://github.com/EnviMVC/EnviMVC3PHP/wiki
 * @since      File available since Release 1.0.0
 */


/**
 * PropelPDO風のオブジェクトを作成するベースクラス
 *
 * @abstract
 * @category   MVC
 * @package    Envi3
 * @subpackage EnviMVCCore
 * @author     Akito <akito-artisan@five-foxes.com>
 * @copyright  2011-2012 Artisan Project
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    Release: @package_version@
 * @link       https://github.com/EnviMVC/EnviMVC3PHP
 * @see        https://github.com/EnviMVC/EnviMVC3PHP/wiki
 * @since      Class available since Release 1.0.0
 */
abstract class EnviOrMapBase
{
    protected $_from_hydrate, $to_save;
    protected $_is_modify = true;
    protected $table_name,$pkeys;

    protected $default_instance_name = 'default_master';

    /**
     * +-- DBからセレクトしたデータをオブジェクトにセットする
     *
     * このMethodを使用してセットすると、saveがInsertではなくUpdateになります。
     *
     * @access public
     * @param array $arr
     * @return void
     */
    public function hydrate(array $arr)
    {
        $this->_is_modify = false;
        $this->_from_hydrate = $arr;
        $this->to_save       = $arr;
    }
    /* ----------------------------------------- */

    /**
     * +-- データを配列にして返します
     *
     * @access public
     * @return array
     */
    public function toArray()
    {
        return $this->to_save;
    }
    /* ----------------------------------------- */

    /**
     * +-- DBに保存します
     *
     * @access public
     * @param EnviDBIBase $con OPTIONAL:NULL
     * @return void
     */
    public function save(EnviDBIBase $con = NULL)
    {
        $table_name = $this->table_name;
        $pkeys      = $this->pkeys;
        $dbi = $con ? $con : extension()->DBI()->getInstance($this->default_instance_name);

        if (!isset($this->_from_hydrate[$pkeys[0]])) {
            $dbi->autoExecute($table_name, $this->to_save, DB::AUTOQUERY_INSERT);
            if (!isset($this->to_save[$pkeys[0]])) {
                $this->to_save[$pkeys[0]] = $dbi->lastInsertId();
            }
            $this->_from_hydrate = $this->to_save;
            $this->_is_modify = false;
            return true;
        }
        if (!$this->_is_modify) {
            return;
        }

        $and = '';
        $sql = '';
        foreach ($pkeys as $v) {
            $sql .= " {$and} {$v}=".$dbi->quoteSmart($this->_from_hydrate[$v]);
            $and = ' AND ';
        }
        $dbi->autoExecute($table_name, $this->to_save, DB::AUTOQUERY_UPDATE, $sql);
        $this->_from_hydrate = $this->to_save;
        $this->_is_modify = false;
    }
    /* ----------------------------------------- */

    /**
     * +-- データを削除する
     *
     * @access public
     * @param EnviDBIBase $con OPTIONAL:NULL
     * @return void
     */
    public function delete(EnviDBIBase $con = NULL)
    {
        $arr = array();
        $sql = "DELETE FROM {$this->table_name} WHERE ";
        $and = '';
        foreach ($this->pkeys as $pkey) {
            $sql .= $and.$pkey.' = ?';
            $and = ' AND ';
            if (!isset($this->_from_hydrate[$pkey])) {
                return false;
            }
            $arr[] = $this->_from_hydrate[$pkey];
        }
        $dbi = $con ? $con : extension()->DBI()->getInstance($this->default_instance_name);
        $dbi->query($sql, $arr);
        $this->_is_modify = false;
    }
    /* ----------------------------------------- */


    /**
     * +-- マジックメソッド
     *
     * @access public
     * @param  $name
     * @param  $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->_is_modify = true;
        $this->to_save[$name] = $value;
    }
    /* ----------------------------------------- */

    /**
     * +-- マジックメソッド
     *
     * @access public
     * @param  $name
     * @return integer|string
     */
    public function __get($name)
    {
        return isset($this->to_save[$name]) ? $this->to_save[$name] : NULL;
    }
    /* ----------------------------------------- */

    /**
     * +-- マジックメソッド
     *
     * @access public
     * @param  $name
     * @param  $arguments
     * @return mixed
     */
    public function __call ($name , $arguments)
    {
        if (strPos($name, 'get') === 0) {
            $name = strtolower(substr(mb_ereg_replace('([A-Z])', '_\1', $name), 4));
            return isset($this->to_save[$name]) ? $this->to_save[$name] : NULL;
        } elseif (strPos($name, 'set') === 0) {
            $name = strtolower(substr(mb_ereg_replace('([A-Z])', '_\1', $name), 4));
            $this->to_save[$name] = $arguments[0];
            $this->_is_modify = true;
            return;
        }
        trigger_error('undefined method:'.$name);
    }
    /* ----------------------------------------- */

}