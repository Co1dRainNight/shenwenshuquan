<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  分页控制类
 */
namespace core\view;

use core\basic\Config;

class Paging
{

    // 每页数量
    public $pageSize;

    // 当前页码
    public $page;

    // 数字条数量
    public $num = 5;

    // 调整数量
    public $start = 1;

    // 总记录
    private $rowTotal = 0;

    // 页面数量
    private $pageCount;

    // 存储前置URL
    private $preUrl;

    // 分页实例
    private static $paging;

    private function __construct()
    {
        // 禁用类new实例化
    }

    // 获取单一实例
    public static function getInstance()
    {
        if (! self::$paging) {
            self::$paging = new self();
        }
        return self::$paging;
    }

    // 限制语句
    public function limit($total = null, $morePageStr = true)
    {
        // 起始数据调整
        if (! is_numeric($this->start) || $this->start < 1) {
            $this->start = 1;
        }
        if ($this->start > $total) {
            $this->start = $total + 1;
        }
        
        // 设置总数
        if ($total) {
            $this->rowTotal = $total - ($this->start - 1);
        }
        
        // 设置分页大小
        if (! isset($this->pageSize)) {
            $this->pageSize = get('pagesize') ?: Config::get('pagesize') ?: 15;
        }
        
        // 分页数字条数量
        $this->num = Config::get('pagenum') ?: 5;
        
        // 计算页数
        $this->pageCount = @ceil($this->rowTotal / $this->pageSize);
        
        // 获取当前页面
        $this->page = $this->page();
        
        // 注入分页模板变量
        $this->assign($morePageStr);
        
        // 返回限制语句
        return ($this->page - 1) * $this->pageSize + ($this->start - 1) . ",$this->pageSize";
    }

    // 快速分页字符代码
    public function quikLimit()
    {
        $page = get('page', 'int') ?: 1;
        if ($page < 1) {
            $page = 0;
        }
        $pagesize = config::get('pagesize') ?: 15;
        return ($page - 1) * $pagesize . ",$pagesize";
    }

    // 注入页面相关信息,用于模板调用，如：{$pagebar}调用分页条
    private function assign($morePageStr = true)
    {
        assign('pagebar', $this->pageBar());
        if ($morePageStr) {
            assign('pagecurrent', $this->page()); // 注入当前页
            assign('pagecount', $this->pageCount); // 注入总页数
            assign('pagerows', $this->rowTotal); // 注入总数据
            assign('pageindex', $this->pageIndex()); // 注入首页链接
            assign('pagepre', $this->pagePre()); // 注入前一页链接
            assign('pagenext', $this->pageNext()); // 注入后一页链接
            assign('pagelast', $this->pageLast()); // 注入最后一页链接
            assign('pagestatus', $this->pageStatus()); // 注入分页状态
            assign('pagenumbar', $this->pageNumBar()); // 注入数字
            assign('pageselectbar', $this->pageSelectBar()); // 注入选择栏
            
            assign('page', $this->page()); // 分页
            assign('pagesize', $this->pageSize); // 分页大小
        }
    }

    // 当前页码容错处理
    private function page()
    {
        $page = get('page', 'int') ?: $this->page;
        if (is_numeric($page) && $page > 1) {
            if ($page > $this->pageCount && $this->pageCount) {
                return $this->pageCount;
            } else {
                return $page;
            }
        } else {
            return 1;
        }
    }

    // 过滤pathinfo中分页参数
    private function getPreUrl()
    {
        if (! isset($this->preUrl) && URL) {
            $url = parse_url(URL);
            $path = preg_replace('/\/page\/[0-9]+/i', '', $url['path']);
            $this->preUrl = $path;
        }
        return $this->preUrl;
    }

    // 构建链接地址
    private function buildPath($page)
    {
        if ($page) {
            
            if (! ! $pagelink = get_var('pagelink')) {
                $qs = defined('MAKEHTML') ? '' : query_string('p,s,page');
                $url_rule_type = Config::get('url_rule_type') ?: 3;
                $str = ($url_rule_type == 3 && $pagelink != SITE_INDEX_DIR . '/') ? "&" : "?"; // 兼容模式，非首页连接符号用&
                                                                                               
                // 让在静态时支持分页，走动态入口
                $index = '';
                if (($url_rule_type == 4)) {
                    if ($pagelink == SITE_INDEX_DIR . '/') {
                        $index = SITE_INDEX_DIR . '/index.php';
                    } else {
                        $index = SITE_INDEX_DIR . '/index.php?';
                        $str = '&';
                    }
                } else {
                    $index = '/';
                }
                
                if ($page == 1) {
                    if (! ! $qs) {
                        $path = $pagelink . $str . $qs;
                    } else {
                        $path = $pagelink;
                    }
                } else {
                    if (get_var('listpage')) {
                        if (! ! $qs) {
                            $path = rtrim($pagelink, '/') . '_' . $page . '/' . $str . $qs;
                        } else {
                            $path = rtrim($pagelink, '/') . '_' . $page . '/';
                        }
                    } else {
                        if (! ! $qs) {
                            $path = $index . ltrim($pagelink, '/') . $str . $qs . '&page=' . $page;
                        } else {
                            $path = $index . ltrim($pagelink, '/') . $str . 'page=' . $page;
                        }
                    }
                }
                return $path;
            } else {
                return $this->buildBasicPage($page);
            }
        } else {
            return 'javascript:;';
        }
    }

    // 构建基本分页
    private function buildBasicPage($page)
    {
        // 对于路径保留变量给予去除
        $qs = $_SERVER["QUERY_STRING"];
        //伪静态、普通模式路由单独处理
        $urlSite = [1,2];
        $urlRule = Config::get('url_rule_type');
        if (M == 'home' && in_array($urlRule,$urlSite)) {
            parse_str($qs,$qs_array);
            $path = '';
            switch ($urlRule){
                case 1:
                    $path = preg_replace('/[&\?]?page=([0-9]+)?/i', '', URL);
                    break;
                case 2:
                    $path = $path . $qs_array['s'];
                    break;
            }
            if ($page == 1) {
                return $path;
            }else{
                return '?page=' . $page;
            }
        }

        if (M != 'home' && Config::get('app_url_type') == 2) {
            $qs = preg_replace('/[&\?]?p=([\w\/\.]+)?&?/i', '', $qs);
            $qs = preg_replace('/[&\?]?s=([\w\/\.]+)?&?/i', '', $qs);
        }
        $qs = preg_replace('/[&\?]?page=([0-9]+)?/i', '', $qs);
        
        if ($page == 1) {
            if ($qs) {
                return '?' . $qs;
            } else {
                return;
            }
        } else {
            if ($qs) {
                return '?' . $qs . '&page=' . $page;
            } else {
                return '?page=' . $page;
            }
        }
    }

    // 分页条
    private function pageBar()
    {
        if (! $this->pageCount)
            return "<span class='page-none' style='color:#999'>未查询到任何数据!</span>";
        $string = "<span class='page-status'>{$this->pageStatus()}</span>";
        $string .= "<span class='page-index'><a href='" . $this->pageIndex() . "'>首页</a></span>";
        $string .= "<span class='page-pre'><a href='" . $this->pagePre() . "'>前一页</a></span>";
        $string .= "<span class='page-numbar'>{$this->pageNumBar()}</span>";
        $string .= "<span class='page-next'><a href='" . $this->pageNext() . "'>后一页</a></span>";
        $string .= "<span class='page-last'><a href='" . $this->pageLast() . "'>尾页</a></span>";
        // $string .= "<span class='page-select'>{$this->pageSelectBar()}</span>";
        return $string;
    }

    // 当前页面情况
    private function pageStatus()
    {
        if (! $this->pageCount)
            return;
        return "共" . $this->rowTotal . "条 当前" . $this->page . "/" . $this->pageCount . "页";
    }

    // 首页链接
    private function pageIndex()
    {
        if (! $this->pageCount)
            return $this->buildPath('');
        return $this->buildPath(1);
    }

    // 上一页链接
    private function pagePre()
    {
        if (! $this->pageCount)
            return $this->buildPath('');
        if ($this->page > 1) {
            $pre_page = $this->buildPath($this->page - 1);
        } else {
            $pre_page = $this->buildPath('');
        }
        return $pre_page;
    }

    // 下一页链接
    private function pageNext()
    {
        if (! $this->pageCount)
            return $this->buildPath('');
        if ($this->page < $this->pageCount) {
            $next_page = $this->buildPath($this->page + 1);
        } else {
            $next_page = $this->buildPath('');
        }
        return $next_page;
    }

    // 尾页
    private function pageLast()
    {
        if (! $this->pageCount)
            return $this->buildPath('');
        return $this->buildPath($this->pageCount);
    }

    // 数字分页,要修改数字显示的条数，请修改类头部num属性值
    private function pageNumBar()
    {
        if (! $this->pageCount)
            return;
        
        if (M == 'admin') {
            $total = 5;
        } else {
            $total = $this->num;
        }
        
        $halfl = intval($total / 2);
        $halfu = ceil($total / 2);
        
        $num_html = '';
        if ($this->page > $halfu) {
            $num_html .= '<span class="page-num">···</span>';
        }
        
        if ($this->page <= $halfl || $this->pageCount < $total) { // 当前页小于一半或页数小于总数
            for ($i = 1; $i <= $total; $i ++) {
                if ($i > $this->pageCount)
                    break;
                if ($this->page == $i) {
                    $num_html .= '<a href="' . $this->buildPath($i) . '" class="page-num page-num-current">' . $i . '</a>';
                } else {
                    $num_html .= '<a href="' . $this->buildPath($i) . '" class="page-num">' . $i . '</a>';
                }
            }
        } elseif ($this->page + $halfl >= $this->pageCount) { // 当前页为倒数页以内
            for ($i = $this->pageCount - $total + 1; $i <= $this->pageCount; $i ++) {
                if ($this->page == $i) {
                    $num_html .= '<a href="' . $this->buildPath($i) . '" class="page-num page-num-current">' . $i . '</a>';
                } else {
                    $num_html .= '<a href="' . $this->buildPath($i) . '" class="page-num">' . $i . '</a>';
                }
            }
        } else { // 正常的前后各5页
            for ($i = $this->page - $halfl; $i <= $this->page + $halfl; $i ++) {
                if ($this->page == $i) {
                    $num_html .= '<a href="' . $this->buildPath($i) . '" class="page-num page-num-current">' . $i . '</a>';
                } else {
                    $num_html .= '<a href="' . $this->buildPath($i) . '" class="page-num">' . $i . '</a>';
                }
            }
        }
        
        if ($this->pageCount > $total && $this->page < $this->pageCount - $halfl) {
            $num_html .= '<span class="page-num">···</span>';
        }
        
        return $num_html;
    }

    // 跳转分页
    private function pageSelectBar()
    {
        if (! $this->pageCount)
            return;
        $select_html = '<select onchange="changepage(this)" lay-ignore>';
        for ($i = 1; $i <= $this->pageCount; $i ++) {
            if ($i == $this->page) {
                $select_html .= '<option value="' . $i . '" selected="selected">跳到' . $i . '页</option>';
            } else {
                $select_html .= '<option value="' . $i . '">跳到' . $i . '页</option>';
            }
        }
        $select_html .= '</select><script>function changepage(tag){window.location.href="' . $this->buildPath('"+tag.value+"') . '";}</script>';
        return $select_html;
    }
}