<?php
/**
 * @script   ExtractorService.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-11-06
 */

namespace PHPCreeper\Kernel\Service\Wrapper;

require_once dirname(dirname(dirname(__FILE__))) . '/Library/phpQuery/phpQuery.php';

use PHPCreeper\Kernel\PHPCreeper;
use \phpQuery as phpQuery;

class ExtractorService
{
    /**
     * html document
     *
     * @var string
     */
    public $html = null;

    /**
     * document object
     *
     * @var object
     */
    public $document = null;

    /**
     * origin document object
     *
     * @var object
     */
    public $originDocument = null;

    /**
     * rule
     *
     * @var array
     */
    public $rule = [];

    /**
     * @brief    init   
     *
     * @return   object
     */
    static public function init()
    {
        return new self();
    }

    /**
     * @brief    __call     
     *
     * @param    string  $name
     * @param    mixed   $args
     *
     * @return   mixed
     */
    public function __call($name, $args)
    {
        if(empty($this->document) || !is_object($this->document) || !method_exists($this->document, $name)) 
        {
            return '';
        }

        $callback = array($this->document, $name);

        if(!is_callable($callback)) return '';

        return call_user_func_array($callback, $args);
    }

    /**
     * @brief    create document     
     *
     * @param    string  $input
     * @param    string  $option
     * @param    string  $type
     *
     * @return   object
     */
    public function createDocument($input = '', $option = '', $type = 'html')
    {
        $type = strtolower($type);

        switch($type)
        {
            case 'document':
                $this->document = phpQuery::newDocument($input, $option);
                break;
            case 'html':
                $this->document = phpQuery::newDocumentHTML($input, $option);
                break;
            case 'file':
                $this->document = phpQuery::newDocumentFile($input, $option);
                break;
            case 'xhtml':
                $this->document = phpQuery::newDocumentXHTML($input, $option);
                break;
            case 'xml':
                $this->document = phpQuery::newDocumentPHP($input, $option);
                break;
            case 'file+html':
                $this->document = phpQuery::newDocumentFILEHTML($input, $option);
                break;
            case 'file+xhtml':
                $this->document = phpQuery::newDocumentFILEXHTML($input, $option);
                break;
            case 'file+xml':
                $this->document = phpQuery::newDocumentFILEXML($input, $option);
                break;
            case 'file+php':
                $this->document = phpQuery::newDocumentFILEPHP($input, $option);
                break;
            default:
                $this->document = phpQuery::newDocumentHTML($this->getHtml() . $input, $option);
                break;
        }

        //save origin document
        $this->originDocument = $this->document;
        //save origin document

        return $this;
    }

    /**
     * @brief    get html    
     *
     * @return   string
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * @brief    set    
     *
     * @param    array  $option
     *
     * @return   object
     */
    public function set($option = [])
    {
        if(!isset($option['html']))
        {
            $this->setHtml(null, null);
        }
        else
        {
            !is_array($option['html']) && $option['html'] = [$option['html']];
            $html = $option['html'][0] ?? null;
            $charset = $option['html'][1] ?? 'UTF8';
            $this->setHtml($html, $charset);
        }

        isset($option['range']) && $this->setRange($option['range']);

        if(isset($option['rule']) && is_array($option['rule'])) 
        {
            $this->setRule($option['rule']);
        }

        return $this;
    }

    /**
     * @brief    set html    
     *
     * @param    string  $html
     * @param    string  $charset
     *
     * @return   object
     */
    public function setHtml($html = null, $charset = 'UTF8')
    {
        $this->html = $html;
        $this->createDocument($this->html, $charset, 'html');

        return $this;
    }

    /**
     * @brief    find   
     *
     * @param    string  $selector
     *
     * @return   object
     */
    public function find($selector)
    {
        return $this->document->find($selector);
    }

    /**
     * @brief    set rule    
     *
     * @param    string  $rule
     *
     * @return   object
     */
    public function setRule($rule = [])
    {
        $this->rule = $rule;

        return $this;
    }

    /**
     * @brief    set range   
     *
     * @param    string  $selector
     *
     * @return   object
     */
    public function setRange($selector)
    {
        if(!empty($selector))
        {
            $this->document = $this->find($selector);
        }
        else
        {
            $this->document = $this->originDocument;
        }

        return $this;
    }

    /**
     * @brief    extract fields  
     *
     * @return   array
     */
    public function extract()
    {
        $result = [];

        foreach($this->rule as $name => $rule)
        {
            $i = 0;
            $selector = $rule[0] ?? '';
            $action   = $rule[1] ?? 'text';
            $range    = $rule[2] ?? '';
            $callback = $rule[3] ?? '';
            in_array($action, ['preg', 'pregs']) && $selector = '';
            $nodes = $this->setRange($range)->find($selector);

            foreach($nodes as $node) 
            {
                $data = '';
                if('text' == $action) {
                    $data = pq($node, $this->document)->text();
                }elseif('html' == $action) {
                    $data = pq($node, $this->document)->html();
                }elseif('preg' == $action) {
                    $source = pq($node, $this->document)->html();
                    !empty($rule[0]) && preg_match($rule[0], $source, $data);
                }elseif('pregs' == $action) {
                    $source = pq($node, $this->document)->html();
                    !empty($rule[0]) && preg_match_all($rule[0], $source, $data);
                }else{
                    $data = pq($node, $this->document)->attr($action);
                }

                is_callable($callback) && $data = call_user_func($callback, $name, $data);
                $result[$i][$name] = is_string($data) ? trim($data) : $data;
                $i++;
            }
        }

        return $result;
    }

}



