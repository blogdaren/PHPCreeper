<?php
/**
 * @script   BloomFilterA.php
 * @brief    See: https://github.com/dsx724/php-bloom-filter
 * @author   blogdaren<blogdaren@163.com>
 * @version  1.0.0
 * @modify   2019-10-30
 */
namespace PHPCreeper\Kernel\Middleware\DropDuplicate;

use PHPCreeper\Kernel\Slot\BloomInterface;

class BloomFilterLocal implements DropDuplicateInterface
{
    /**
     * element size
     *
     * @var int
     */
    private $n = 0; 

    /**
     * vector bits
     *
     * @var int
     */
    private $m; 

    /**
     * hash count
     *
     * @var int
     */
    private $k;

    /**
     * hash algorithm
     *
     * @var string
     */
    private $hash;

    /**
     * mask
     *
     * @var int
     */
	private $mask;

    /**
     * chunk size
     *
     * @var int
     */
	private $chunk_size;

    /**
     * bit_array
     *
     * @var int
     */
	private $bit_array; 

    /**
     * @brief    __construct    
     *
     * @param    int     $m
     * @param    int     $k
     * @param    string  $h
     *
     * @return   void
     */
    public function __construct($m = 1048576, $k = 3, $h = 'md5')
    {
        if($m < 8) 
        {
            throw new \Exception('The bit array length must be at least 8 bits.');
        }

        if(($m & ($m - 1)) !== 0) 
        {
            throw new \Exception('The bit array length must be power of 2.');
        }

        if($m > 8589934592) 
        {
            throw new \Exception('The maximum data structure size is 1GB.');
        }

		$this->m = $m; 
		$this->k = $k;
		$this->hash = $h;
		$address_bits = (int)log($m, 2);
		$this->mask = (1 << $address_bits) - 8;
		$this->chunk_size = (int)ceil($address_bits / 8);
		$this->hash_times = ((int)ceil($this->chunk_size * $this->k / strlen(hash($this->hash, null, true)))) - 1;
		$this->bit_array = (binary)(str_repeat("\0", $this->getSize(true)));
	}

    /**
     * @brief    calculateProbability   
     *
     * @param    int  $n
     *
     * @return   number
     */
    public function calculateProbability($n = 0)
    {
		return pow(1 - pow(1 - 1 / $this->m, $this->k * ($n ?: $this->n)), $this->k);
	}

    /**
     * @brief    calculateCapacity  
     *
     * @param    int  $p
     *
     * @return   float
     */
    public function calculateCapacity($p)
    {
		return floor($this->m * log(2) / log($p, 1 - pow(1 - 1/$this->m, $this->m * log(2))));
	}

    /**
     * @brief    getElementCount    
     *
     * @return   int
     */
    public function getElementCount()
    {
        return $this->n;
	}

    /**
     * @brief    getSize    
     *
     * @param    boolean    $bytes
     *
     * @return   int
     */
    public function getSize($bytes = false)
    {
		return $this->m >> ($bytes ? 3 : 0);
	}

    /**
     * @brief    getHashCount   
     *
     * @return   int
     */
    public function getHashCount()
    {
        return $this->k;
	}

    /**
     * @brief    report     
     *
     * @param    int  $p
     *
     * @return   string
     */
    public function report($p = null)
    {
		$units = array('','K','M','G','T','P','E','Z','Y');
		$size = $this->getSize(true);
		$magnitude = intval(floor(log($size,1024)));
		$unit = $units[$magnitude];
		$size /= pow(1024,$magnitude);

		return  'Allocated '.$this->getSize().' bits ('.$size.' '.$unit.'Bytes)'.PHP_EOL.
			    'Using '.$this->getHashCount(). ' ('.($this->chunk_size << 3).'b) hashes'.PHP_EOL.
			    'Contains '.$this->getElementCount().' elements'.PHP_EOL.
			    (isset($p) ? 'Capacity of '.number_format($this->calculateCapacity($p)).' (p='.$p.')'.PHP_EOL : '');
	}

    /**
     * @brief    add element    
     *
     * @param    string  $key
     *
     * @return   void
     */
    public function add($key)
    {
		$hash = hash($this->hash, $key, true);

        for($i = 0; $i < $this->hash_times; $i++) 
        {
            $hash .= hash($this->hash, $hash, true);
        }

        for($index = 0; $index < $this->k; $index++)
        {
			$hash_sub = hexdec(unpack('H*',substr($hash,$index * $this->chunk_size, $this->chunk_size))[1]);
			$word = ($hash_sub & $this->mask) >> 3;
			$this->bit_array[$word] = $this->bit_array[$word] | chr(1 << ($hash_sub & 7));
		}

		$this->n++;
	}

    /**
     * @brief    check whether element exists in bit array or not  
     *
     * @param    string  $key
     *
     * @return   boolean
     */
    public function has($key)
    {
		$hash = hash($this->hash,$key,true);
        for($i = 0; $i < $this->hash_times; $i++) 
        {
            $hash .= hash($this->hash,$hash,true);
        }

        for($index = 0; $index < $this->k; $index++)
        {
			$hash_sub = hexdec(unpack('H*',substr($hash,$index*$this->chunk_size,$this->chunk_size))[1]);
			if((ord($this->bit_array[($hash_sub & $this->mask) >> 3]) & (1 << ($hash_sub & 7))) === 0) return false;
		}

		return true;
	}

}




