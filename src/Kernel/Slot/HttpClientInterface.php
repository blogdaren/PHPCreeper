<?php
/**
 * @script   HttpClientInterface.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-04
 */

namespace PHPCreeper\Kernel\Slot;

interface HttpClientInterface
{
    /**
     * create and send an HTTP request
     *
     * @param string              $method   http method
     * @param string|UriInterface $uri      URI object or string
     * @param array               $options  request options
     *
     * @return ResponseInterface
     */
    public function request($method, $uri, array $options = []);

    /**
     * get response status code
     *
     * @return  int
     */
    public function getResponseStatusCode();

    /**
     * get response status message
     *
     * @return  string
     */
    public function getResponseStatusMessage();

    /**
     * get response body
     *
     * @return  string
     */
    public function getResponseBody();
}
