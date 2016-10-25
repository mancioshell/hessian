<?php
/*
 * This file is part of the HessianPHP package.
 * (c) 2004-2010 Manuel Gómez
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * General utility functions for parsers and writers
 */
class HessianUtils{
	
	/**
	 * Changes the default time zone using the default if not specified
	 * NOTE: it turns error reporting off only during the transition, then restores the original level
	 * @param string timezone to use
	 */
	public static function setTimeZone($timezone = ''){
		if($timezone)
			date_default_timezone_set($timezone);
		else {
			$origError = error_reporting(0);
			$tz = date_default_timezone_get();
			error_reporting($origError);
			date_default_timezone_set($tz);
		}
		
	}

	/**
	 * Defines if the current platform is little-endian or big endian
	 * @var bool */
	public static $littleEndian = null;
	/**
	 * Constant equivalent to pow(2,32) used in many calculations
	 * @var int	 */
	const pow32 = 4294967296;

	/**
	 * Determines if an array is a 'list' by comparing the original keys and the
	 * keys from a surrogate array that is indeed a list (array_values)
	 * @param array $arr 
	 * @return bool
	 */
	public static function isListKeys($arr){
		$vals = array_values($arr);
		$keys = array_keys($arr);
		$numkeys = array_keys($vals);
		return $numkeys === $keys;
	}
	
	/**
	 * Determines if an array is a 'list' by comparing the keys with the ideal numeric keys
	 * from a 'list', return whenever the key does not correspond
	 * @param array $arr 
	 * @return bool
	 */
	public static function isListIterate($arr){
		$k = 0;
		foreach($arr as $key => $val) {
			if($key !== $k)
				return false;
			$k++;
		}
		return true;
	}
	
	/**
	 * Determines if an array is a 'list' by using a formula to calculate the total sum of the elements
	 * of a sequence, in this case the keys of the array
	 * @param array $arr 
	 * @return bool
	 */
	public static function isListFormula($arr){
		$n = count($arr);
		$sum = (0*$n) + ( ($n*($n-1)*1)/2 );
		$keys = array_keys($arr);
		$keysum = array_sum($keys);
		return $sum === $keysum;
	}
		
	/**
	 * Resolves if the machine architecture is big or little endian by comparing two binary packed
	 * values
	 */
	public static function isLittleEndian(){
		if(!is_null(self::$littleEndian))
			return self::$littleEndian;
		$machineLong = pack("L", 1);  // Machine dependent
		$indepLong  = pack("N", 1);  // Machine independent
		self::$littleEndian = $machineLong[0] != $indepLong[0];
		return self::$littleEndian;
	}
	
	/**
	 * Serializes a float into its 32-bit float representation considering endianess
	 * @param float $number
	 * @return string
	 */
	public static function floatBytes($number){
		$bytes = pack("s", $number);
		return self::$littleEndian ? strrev($bytes) : $bytes; 
	}
	
	/**
	 * Serializes a float into its 64-bit double representation considering endianess
	 * @param float $number
	 * @return string
	 */
	public static function doubleBytes($number){
		$bytes = pack("d", $number);
		return self::$littleEndian ? strrev($bytes) : $bytes; 
	}
	
	/**
	 * Extracts a number from a 8 bytes (64 bit) taking PHP's integer overflow into account
	 * EXPERIMENTAL
	 * @param string $bytes 8 bytes exactly
	 * @return mixed integer or float
	 */
	public static function longFromBytes64($bytes){
		$sec1 = $bytes[0].$bytes[1].$bytes[2].$bytes[3]; // primeros 4
		$sec2 = $bytes[4].$bytes[5].$bytes[6].$bytes[7]; // segundos 4
		$b1 = unpack('N', $sec1);
		$b2 = unpack('N', $sec2);
		$res = $b2[1];
		$num = ($b1[1] * self::pow32);
		if($res < 0){ // overflow
			$comp = PHP_INT_MAX + $res;
			$num += PHP_INT_MAX + $comp;
		} else {
			$num += $res; 
		}
		return $num;
	}
	
	/**
	 * Extracts the PHP UNIX timestamp from a 8 bytes (64 bit) sequence which includes milliseconds according with
	 * the spec. Takes PHP's integer overflow into account and it has a correction of 1 second (don't know why yet...)
	 * @param string $bytes 8 bytes exactly
	 * @return float
	 */
	public static function timestampFromBytes64($bytes){
		$sec1 = $bytes[0].$bytes[1].$bytes[2].$bytes[3]; // primeros 4
		$sec2 = $bytes[4].$bytes[5].$bytes[6].$bytes[7]; // segundos 4
		$b1 = unpack('N', $sec1);
		$b2 = unpack('N', $sec2);
		$res = $b2[1];
		$num = ($b1[1] * self::pow32);
		if($res < 0){ // overflow
			$comp = PHP_INT_MAX + $res;
			$num += PHP_INT_MAX + $comp + 1000;
		} else {
			$num += $res; 
		}
		$num = $num / 1000;
		return $num;
	}
	
	// string functions

	public static function isInternalUTF8(){
		$encoding = ini_get('mbstring.internal_encoding');
		if(!$encoding)
			return false;
		return $encoding == 'UTF-8';
	} 
	
	public static function stringLength($string){
		$len = strlen($string);
		if(function_exists('mb_strlen')){
			if(self::isInternalUTF8())
				$len = mb_strlen($string, 'UTF-8');
			else
				$len = mb_strlen($string);
		}
		return $len;
	}
	
	public static function writeUTF8($string){
		// TODO: detect utf-8 and use iconv		
		if(self::isInternalUTF8()){
			//$enc = utf8_encode($string);
			//$enc2 = utf8_encode($enc);
			return $string;
		}
		return utf8_encode($string);
	}
	
    /**
    * Tests a string as to whether it's valid UTF-8 and supported by the
    * Unicode standard
    * Note: this function has been modified to simple return true or false
    * Note: included in HessianUtils by Massimo Squillace 2012-08-16
    * @author <hsivonen@iki.fi>
    * @param string UTF-8 encoded string
    * @return boolean true if valid
    * @see http://hsivonen.iki.fi/php-utf8/
    * @see utf8_compliant
    * @package utf8
    * @subpackage validation
    */
//    function utf8_is_valid($str) { // Comply to HessianUtils method naming conventions
    public static function isValidUTF8($str) {
    
        $mState = 0;     // cached expected number of octets after the current octet
                         // until the beginning of the next UTF8 character sequence
        $mUcs4  = 0;     // cached Unicode character
        $mBytes = 1;     // cached expected number of octets in the current sequence
        
        $len = strlen($str);
        
        for($i = 0; $i < $len; $i++) {
            
            $in = ord($str{$i});
            
            if ( $mState == 0) {
                
                // When mState is zero we expect either a US-ASCII character or a
                // multi-octet sequence.
                if (0 == (0x80 & ($in))) {
                    // US-ASCII, pass straight through.
                    $mBytes = 1;
                    
                } else if (0xC0 == (0xE0 & ($in))) {
                    // First octet of 2 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                    
                } else if (0xE0 == (0xF0 & ($in))) {
                    // First octet of 3 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                    
                } else if (0xF0 == (0xF8 & ($in))) {
                    // First octet of 4 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                    
                } else if (0xF8 == (0xFC & ($in))) {
                    /* First octet of 5 octet sequence.
                    *
                    * This is illegal because the encoded codepoint must be either
                    * (a) not the shortest form or
                    * (b) outside the Unicode range of 0-0x10FFFF.
                    * Rather than trying to resynchronize, we will carry on until the end
                    * of the sequence and let the later error handling code catch it.
                    */
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                    
                } else if (0xFC == (0xFE & ($in))) {
                    // First octet of 6 octet sequence, see comments for 5 octet sequence.
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                    
                } else {
                    /* Current octet is neither in the US-ASCII range nor a legal first
                     * octet of a multi-octet sequence.
                     */
                    return FALSE;
                    
                }
            
            } else {
                
                // When mState is non-zero, we expect a continuation of the multi-octet
                // sequence
                if (0x80 == (0xC0 & ($in))) {
                    
                    // Legal continuation.
                    $shift = ($mState - 1) * 6;
                    $tmp = $in;
                    $tmp = ($tmp & 0x0000003F) << $shift;
                    $mUcs4 |= $tmp;
                
                    /**
                    * End of the multi-octet sequence. mUcs4 now contains the final
                    * Unicode codepoint to be output
                    */
                    if (0 == --$mState) {
                        
                        /*
                        * Check for illegal sequences and codepoints.
                        */
                        // From Unicode 3.1, non-shortest form is illegal
                        if (((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
                            ((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
                            ((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
                            (4 < $mBytes) ||
                            // From Unicode 3.2, surrogate characters are illegal
                            (($mUcs4 & 0xFFFFF800) == 0xD800) ||
                            // Codepoints outside the Unicode range are illegal
                            ($mUcs4 > 0x10FFFF)) {
                            
                            return FALSE;
                            
                        }
                        
                        //initialize UTF8 cache
                        $mState = 0;
                        $mUcs4  = 0;
                        $mBytes = 1;
                    }
                
                } else {
                    /**
                    *((0xC0 & (*in) != 0x80) && (mState != 0))
                    * Incomplete multi-octet sequence.
                    */
                    
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

}