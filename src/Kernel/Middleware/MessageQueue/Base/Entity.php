<?php
/**
 * @script   Abstract.php
 * @brief    this file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-06-18
 */

namespace PHPCreeper\Middleware\MessageQueue\Base;

abstract class Entity
{
    const FLAG_NOPARAM = 0;

    /** 
     * the name of entity
     * @var string
     */
    public $name = '';

    /** 
     * the type of entity
     * @var string
     */
    public $type = '';

    /** 
     * the flags of entity
     * @var int
     */
    public $flags = 0;

    /** 
     * the arguments of entity
     * @var array
     */
    public $arguments = array();

    /**
     * the headers of entity
     * @var array
     */
    public $headers = array();

    /**
     * the body of entity
     * @var string
     */
    public $body = '';

    /**
     * the properties of entity
     * @var array
     */
    public $properties = array();

    /**
     * constructor
     *
     * @param   string  $name
     * @return  null
     */
    public function __construct($name = '', $headers = array(), $body = array(), $properties = array())
    {
        $this->name = $name;
        $this->headers = $headers;
        $this->body = $body;
        $this->properties = $properties;
        $this->arguments = array();
        $this->flags = self::FLAG_NOPARAM;
    }

    /**
     * set the name of entity
     *
     * @param   string  $name
     * @return  object
     */ 
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * get the name of entity
     *
     * @param   string  $name
     * @return  string
     */ 
    public function getName()
    {
        return $this->name;
    }

    /**
     * set the type of entity
     *
     * @param   string  $type
     * @return  null
     */ 
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * get the type of entity
     *
     * @param   string  $type
     * @return  string
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * set the flags of entity
     *
     * @param   int     $flags
     * @return  null
     */ 
    public function setFlags(int $flags)
    {
        $this->flags = $flags;
    }

    /**
     * get the flags of entity
     *
     * @param   int     $flags
     * @return  int
     */ 
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * remove the flags of entity
     *
     * @return  null
     */ 
    public function removeFlags()
    {
        $this->flags = self::FLAG_NOPARAM;
    }

    /**
     * add one flag to entity
     *
     * @return  null
     */ 
    public function addFlag(int $flag)
    {
        $this->flags |= $flag; 
    }

    /**
     * set the argument of entity
     *
     * @return  null
     */ 
    public function setArgument(string $k, $v)
    {
        $this->arguments[$k] = $v;
    }

    /**
     * get the argument of entity
     *
     * @return  null
     */ 
    public function getArgument(string $k, $default = null)
    {
        return array_key_exists($k, $this->arguments) ? $this->arguments[$k] : $default;
    }

    /**
     * set the arguments of entity
     *
     * @return  null
     */ 
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * get the arguments of entity
     *
     * @return  array
     */ 
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * get the arguments of entity
     *
     * @return  array
     */ 
    public function setBody(string $body)
    {
        $this->body = $body;
    }

    /**
     * get the body of entity
     *
     * @return  string 
     */ 
    public function getBody()
    {
        return $this->body;
    }

    /**
     * set the properties of entity
     *
     * @return  null 
     */ 
    public function setProperties(array $properties)
    {
        $this->properties = $properties;
    }

    /**
     * get the properties of entity
     *
     * @return  array 
     */ 
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * set the property of entity
     *
     * @return  null
     */ 
    public function setProperty(string $name, $value)
    {
        if(null === $value) unset($this->properties[$name]);
        if(null !== $value) $this->properties[$name] = $value;
    }

    /**
     * get the property of entity
     *
     * @return  mixed
     */ 
    public function getProperty(string $name, $default = null)
    {
        return array_key_exists($name, $this->properties) ? $this->properties[$name] : $default;
    }

    /**
     * set the headers of entity
     *
     * @return  null
     */ 
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * get the headers of entity
     *
     * @return  array
     */ 
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * set the header of entity
     *
     * @return  array
     */ 
    public function setHeader(string $name, $value)
    {
        if(null === $value) unset($this->headers[$name]);
        if(null !== $value) $this->headers[$name] = $value;
    }

    /**
     * get the header of entity
     *
     * @return  mixed
     */ 
    public function getHeader(string $name, $default = null)
    {
        return array_key_exists($name, $this->headers) ? $this->headers[$name] : $default;
    }
}
