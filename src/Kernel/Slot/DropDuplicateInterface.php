<?php
/**
 * @script   DropDuplicateInterface.php
 * @brief    This file is part of PHPCreeper
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-09-04
 */

namespace PHPCreeper\Kernel\Slot;

use PHPCreeper\Kernel\PHPCreeper;

Interface DropDuplicateInterface
{
    /**
     * @brief    add element to bit array, called collections.
     *
     * @param    string  $element
     *
     * @return   void
     */
    public function add($element);

    /**
     * @brief    check whether element exists bit array, called collections.   
     *
     * @param    string  $element
     *
     * @return   boolean
     */
    public function has($element);
}
