<?php
/**
 *  キャッシュのクリアタスク
 *
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
umask(0);
if (!isset($argv[2])) {
    eecho('引数が足りません。');
    die;
}

include_once ENVI_BASE_DIR.'spyc.php';
include_once ENVI_BASE_DIR.'vendor'.DIRECTORY_SEPARATOR.'EnviDB.php';

ob_start();
include $argv[2];
$buff      = ob_get_contents();
ob_end_clean();

$config = spyc_load($buff);

if (!is_dir($config['DIRECTORY']['model_dir'])) {
    mkdir($config['DIRECTORY']['model_dir']);
    echo $config['DIRECTORY']['model_dir']."\n";
}
$model_dir = $config['DIRECTORY']['model_dir'].DIRECTORY_SEPARATOR;
$om_dir    = $model_dir.'om'.DIRECTORY_SEPARATOR;
if (!is_dir($om_dir)) {
    mkdir($om_dir);
    echo $om_dir."\n";
}


ob_start();
include $config['SETTING']['database_yaml'];
$buff      = ob_get_contents();
ob_end_clean();

$database_yaml = spyc_load($buff);

$database_yaml = array_merge((array)$database_yaml['all'], (array)$database_yaml[$config['SETTING']['env']]);


$DBInstance = NULL;
function pascalize($string)
{
    $string = strtolower($string);
    $string = str_replace('_', ' ', $string);
    $string = ucwords($string);
    $string = str_replace(' ', '', $string);
    return $string;
}

$getter_setter_text = file_get_contents($task_plugin_dir.$module.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'getSet.class.php');

foreach ($config['SCHEMA'] as $table_name => &$schema) {
    $enable_magic = '';
    $default_array = array();
    $instance_name = isset($schema['instance_name']) ? $schema['instance_name'] : $config['SETTING']['default_instance_name'];
    $auto_schema   = isset($schema['auto_schema']) ? $schema['auto_schema'] : $config['SETTING']['default_auto_schema'];

    // DBに接続して、自動的にスキーマ情報を取得する
    if ($auto_schema) {
        $schema['schema'] = array();
        if (is_null($DBInstance)) {
            $DBInstance = new DBInstance($database_yaml);
        }
        $dbi = $DBInstance->getInstance($instance_name);
        $schema_arr = $dbi->getAll('desc '.$table_name);
        foreach ($schema_arr as $k => $arr) {
            $schema['schema'][$arr['Field']]['type']    = $arr['Type'];
            switch ($arr['Default']) {
            case 'CURRENT_TIMESTAMP':
                $arr['Default'] = NULL;
            break;
            default:
                break;
            }
            $schema['schema'][$arr['Field']]['default'] = $arr['Default'];
            if ($arr['Key'] === 'PRI') {
                $schema['schema'][$arr['Field']]['index'] = 'primary';
            }
            if ($arr['Null'] === 'No') {
                $schema['schema'][$arr['Field']]['not_null'] = true;
            }
        }
    }


    $magic_method = isset($schema['magic_method']) ? $schema['magic_method'] : $config['SETTING']['default_magic_method'];
    if (!$magic_method) {
        $enable_magic = file_get_contents($task_plugin_dir.$module.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'magicEnable.class.php');
    }
    $getter_setter = '';
    // var_dump($schema);
    $class_name = isset($schema['class_name']) ? $schema['class_name'] : pascalize($table_name);
    $sql = "SELECT * FROM {$table_name} ";
    $func_args = array();
    $pkeys = array();
    $comma = '';
    $and = 'WHERE ';



    foreach ($schema['schema'] as $column => $status) {
        if (isset($status['index']) && $status['index'] == 'primary') {
            $sql .= $and.$column.' = ? ';
            $and = 'AND ';
            $func_args[] = '$pkey'.count($func_args);
            $pkeys[] = "'{$column}'";
        }
        $getter_setter .= str_replace(
            array('%%method%%', '%%column%%'),
            array(pascalize($column), $column),
            $getter_setter_text
        );
        $default_array[$column] = isset($status['default']) ? $status['default'] : NULL;
    }

    $default_array = var_export($default_array, true);



    $text = file_get_contents($task_plugin_dir.$module.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'BasePeer.class.php');
    $text = str_replace(
        array('%%class_name%%', '%%instance_name%%', '%%sql%%', '%%args%%', '%%pkeys%%', '%%table_name%%', '%%getter_setter%%', '%%enable_magic%%', '%%default_array%%'),
        array($class_name, $instance_name, $sql, join(',', $func_args), join(',', $pkeys), $table_name, $getter_setter, $enable_magic, $default_array),
        $text
    );
    echo $om_dir.'Base'.$class_name.'Peer.class.php'."\n";
    file_put_contents($om_dir.'Base'.$class_name.'Peer.class.php', $text);


    $text = file_get_contents($task_plugin_dir.$module.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'Base.class.php');
    $text = str_replace(
        array('%%class_name%%', '%%instance_name%%', '%%sql%%', '%%args%%', '%%pkeys%%', '%%table_name%%', '%%getter_setter%%', '%%enable_magic%%', '%%default_array%%'),
        array($class_name, $instance_name, $sql, join(',', $func_args), join(',', $pkeys), $table_name, $getter_setter, $enable_magic, $default_array),
        $text
    );

    echo $om_dir.'Base'.$class_name.'.class.php'."\n";
    file_put_contents($om_dir.'Base'.$class_name.'.class.php', $text);

    $text = file_get_contents($task_plugin_dir.$module.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'OrmapPeer.class.php');
    $text = str_replace(
        array('%%class_name%%', '%%instance_name%%', '%%sql%%', '%%args%%', '%%pkeys%%', '%%table_name%%', '%%getter_setter%%', '%%enable_magic%%', '%%default_array%%'),
        array($class_name, $instance_name, $sql, join(',', $func_args), join(',', $pkeys), $table_name, $getter_setter, $enable_magic, $default_array),
        $text
    );
    if (!is_file($model_dir.$class_name.'Peer.class.php')) {
        echo $model_dir.$class_name.'.class.php'."\n";
        file_put_contents($model_dir.$class_name.'Peer.class.php', $text);
    }


    $text = file_get_contents($task_plugin_dir.$module.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'Ormap.class.php');
    $text = str_replace(
        array('%%class_name%%', '%%instance_name%%', '%%sql%%', '%%args%%', '%%pkeys%%', '%%table_name%%', '%%getter_setter%%', '%%enable_magic%%', '%%default_array%%'),
        array($class_name, $instance_name, $sql, join(',', $func_args), join(',', $pkeys), $table_name, $getter_setter, $enable_magic, $default_array),
        $text
    );
    if (!is_file($model_dir.$class_name.'.class.php')) {
        echo $model_dir.$class_name.'.class.php'."\n";
        file_put_contents($model_dir.$class_name.'.class.php', $text);
    }
}
unset($schema);
if ($config['SETTING']['database_yaml']) {
    // リバースする
    $file_name = $argv[2].'.reverse.yml';
    $yaml = Spyc::YAMLDump($config, 2, 60);
    file_put_contents($file_name, $yaml);
    echo $file_name."\n";
}