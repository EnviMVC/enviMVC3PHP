<?php
/**
 * @category   MVC
 * @package    Envi3
 * @subpackage EnviCodeCoverage
 * @author     Akito <akito-artisan@five-foxes.com>
 * @copyright  2011-2014 Artisan Project
 * @license    http://opensource.org/licenses/BSD-2-Clause The BSD 2-Clause License
 * @version    GIT: $Id$
 * @link       https://github.com/EnviMVC/EnviMVC3PHP
 * @see        http://www.enviphp.net/
 * @since      Class available since Release v3.3.3.5
 */

/**
 * @category   MVC
 * @package    Envi3
 * @subpackage EnviCodeCoverage
 * @author     Akito <akito-artisan@five-foxes.com>
 * @copyright  2011-2014 Artisan Project
 * @license    http://opensource.org/licenses/BSD-2-Clause The BSD 2-Clause License
 * @version    Release: @package_version@
 * @link       https://github.com/EnviMVC/EnviMVC3PHP
 * @see        http://www.enviphp.net/
 * @since      Class available since Release v3.3.3.5
 */
class EnviCodeCoverage
{
    /**
     * @var EnviCodeCoverageDriver
     */
    private $driver;

    /**
     * @var EnviCodeCoverageFilter
     */
    private $filter;

    /**
     * @var EnviCodeCoverageParser
     */
    private $parser;

    /**
     * Code coverage data.
     *
     * @var array
     */
    private $coverage_data = array();

    const UN_USE_FLAG = -2;
    const COVERD     = 0;
    const NOT_COVERD = 1;
    const TOTAL_COVER = 1;

    /**
     * +-- コンストラクタ
     *
     * @access      private
     * @return      void
     */
    private function __construct()
    {
    }
    /* ----------------------------------------- */

    /**
     * +-- オブジェクトの精製
     *
     * @access      public
     * @static
     * @return      void
     */
    public static function factory()
    {
        $obj = new EnviCodeCoverage;
        $obj->initialize();
        return $obj;
    }
    /* ----------------------------------------- */

    /**
     * +-- 初期化
     *
     * @access      private
     * @return      void
     */
    private function initialize()
    {
        if (!extension_loaded('xdebug')) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $res = dl('xdebug.dll');
            } else {
                $res = dl('xdebug.so');
            }
            if ($res) {
                throw new Exception('please install xdebug.http://pecl.php.net/package-changelog.php?package=xdebug');
            }
        }

        $this->driver = $this->subClassFactory('Driver');
        $this->filter = $this->subClassFactory('Filter');
        $this->parser = $this->subClassFactory('Parser');
        $this->filter->addBlackListByDirectory(dirname(__FILE__));
    }
    /* ----------------------------------------- */


    /**
     * +-- ドライバオブジェクトを返す
     *
     * @access      public
     * @return      EnviCodeCoverageDriver
     */
    public function &driver()
    {
        return $this->driver;
    }
    /* ----------------------------------------- */

    /**
     * +-- フィルタオブジェクトを返す
     *
     * @access      public
     * @return      EnviCodeCoverageFilter
     */
    public function &filter()
    {
        return $this->filter;
    }
    /* ----------------------------------------- */

    /**
     * +-- パーサーオブジェクトを返す
     *
     * @access      public
     * @return      EnviCodeCoverageParser
     */
    public function &parser()
    {
        return $this->parser;
    }
    /* ----------------------------------------- */


    /**
     * +-- トレース開始
     *
     * @access      public
     * @return      void
     */
    public function start()
    {
        $this->driver->start();
    }
    /* ----------------------------------------- */

    /**
     * +-- トレース終了
     *
     * @access      public
     * @return      void
     */
    public function finish()
    {
        $data = $this->driver->finish();
        foreach ($data as $file_name => $coverage_data) {
            if (!isset($this->coverage_data[$file_name])) {
                $this->coverage_data[$file_name] = $coverage_data;
                continue;
            }
            foreach ($coverage_data as $line => $flag) {
                if ($flag <= 0) {
                    continue;
                }
                if ($this->coverage_data[$file_name][$line] <= 0) {
                    $this->coverage_data[$file_name][$line] = $flag;
                } elseif ($this->coverage_data[$file_name][$line] > 0) {
                    $this->coverage_data[$file_name][$line] += $flag;
                }
            }
        }
    }
    /* ----------------------------------------- */

    /**
     * +-- 空にする
     *
     * @access      public
     * @return      void
     */
    public function free()
    {
        $this->coverage_data = array();
    }
    /* ----------------------------------------- */


    /**
     * +-- CodeCoverage詳細情報の取得
     *
     * @access      public
     * @param       var_text $add_white_list_file OPTIONAL:true
     * @return      array
     */
    public function getCodeCoverage($add_white_list_file = true)
    {
        if ($add_white_list_file) {
            $this->addWhiteListFiles();
        }

        $clone_coverage_data = $this->coverage_data;
        $cover_count = array(0, 0);
        $class_coverage_data = array();
        foreach ($clone_coverage_data as $file_name => &$coverage_data) {
            $epr = $this->parser->parseFile($file_name);
            $file_code_root_coverage = $epr->getCodeRouteCoverage();
            foreach ($coverage_data as $line => &$val) {
                if ($val < 0) {
                    $val = 0;
                }
                $is_cover = $val >= $file_code_root_coverage[$line];
                if ($is_cover) {
                    $cover_count[self::COVERD] += $file_code_root_coverage[$line];
                    $cover_count[self::TOTAL_COVER] += $file_code_root_coverage[$line];
                } else {
                    $cover_count[self::TOTAL_COVER]  += (int)$val;
                    $cover_count[self::TOTAL_COVER]  += $file_code_root_coverage[$line];
                }
                $coverage_data[$line] = array($val, $file_code_root_coverage[$line], $is_cover);
            }

            $class_list = $epr->getClassList();
            foreach ($class_list as $class_name => $class_item) {
                $line     = $class_item['token']->getLine();
                $end_line = $class_item['token']->getEndLine();
                $class_cover = array(
                    'class' => array(
                        'detail' => array(),
                        'cover_count' => array(0, 0)
                        ),
                    'method' => array(),
                    'file_name' => $file_name,
                );
                while ($line <= $end_line) {
                    if (isset($coverage_data[$line])) {
                        $class_cover['class']['detail'][$line] = $coverage_data[$line];
                        $class_cover['class']['cover_count'][self::TOTAL_COVER]++;
                        if ($coverage_data[$line][2]) {
                            $class_cover['class']['cover_count'][self::COVERD]++;
                        }
                    }
                    $line++;
                }
                // チェック行が0ならカバレッジ計測に使用しない。
                if ($class_cover['class']['cover_count'][self::TOTAL_COVER] === 0) {
                    continue;
                }
                $class_cover['class']['cover_rate'] = $class_cover['class']['cover_count'][self::TOTAL_COVER] === 0 ? 0 : ($class_cover['class']['cover_count'][self::COVERD]/$class_cover['class']['cover_count'][self::TOTAL_COVER]*100);
                $class_coverage_data[$class_name] = $class_cover;
                // メソッド
                foreach ($class_item['methods'] as $token) {
                    $line     = $token->getLine();
                    $end_line = $token->getEndLine();
                    $method = array(
                        'detail' => array(),
                        'cover_count' => array(0, 0)
                    );
                    while ($line <= $end_line) {
                        if (isset($coverage_data[$line])) {
                            $method['detail'][$line] = $coverage_data[$line];
                            $method['cover_count'][self::TOTAL_COVER]++;
                            if ($coverage_data[$line][2]) {
                                $method['cover_count'][self::COVERD]++;
                            }
                        }
                        $line++;
                    }
                    $method['cover_rate'] = $method['cover_count'][self::TOTAL_COVER] === 0 ? 0 : ($method['cover_count'][self::COVERD]/$method['cover_count'][self::TOTAL_COVER]*100);
                    $class_coverage_data[$class_name]['methods'][$token->getName()] = $method;

                }
            }
            unset($val);
        }
        unset($coverage_data);
        return array(
            'coverage_data_all'   => $clone_coverage_data,
            'class_coverage_data' => $class_coverage_data,
            'cover_count'         => $cover_count,
            'cover_rate'          => $cover_count[self::TOTAL_COVER] === 0 ? 0 : ($cover_count[self::COVERD]/$cover_count[self::TOTAL_COVER]*100),
        );
    }
    /* ----------------------------------------- */


    private function addWhiteListFiles()
    {
        $white_list_files = $this->filter->getWhiteList();
        $this->start();
        foreach ($white_list_files as $file_name) {
            if (!isset($this->coverage_data[$file_name])) {
                include_once $file_name;
            }
        }
        $this->finish();
    }



    private function subClassFactory($class_name)
    {
        $class_name = __CLASS__.$class_name;
        if (!class_exists($class_name, false)) {
            include dirname(__FILE__).DIRECTORY_SEPARATOR.'CodeCoverage'.DIRECTORY_SEPARATOR.$class_name.'.php';
        }
        return $class_name::factory($this);
    }



}

