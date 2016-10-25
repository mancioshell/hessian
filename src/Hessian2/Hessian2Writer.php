<?php
/*
 * This file is part of the HessianPHP package.
 * (c) 2004-2010 Manuel Gómez
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Hessian2Writer{
	var $refmap;
	var $typemap;
	var $logMsg = array();
	var $options;
	var $filterContainer;
	
	function __construct($options = null){
		$this->refmap = new HessianReferenceMap();
		$this->typemap = new HessianTypeMap();
		$this->options = $options;
	}
		
	function logMsg($msg){
		$this->log[] = $msg;
	}
	
	function setTypeMap($typemap){
		$this->typemap = $typemap;
	}
	
	function setFilters($container){
		$this->filterContainer = $container;
	}
	
	function writeValue($value){
		if(is_object($value)) {
			switch(get_class($value)) {
				case 'HessianBinary' : return $value->writeBinary();
			}
		}
		$type = gettype($value);
		$dispatch = $this->resolveDispatch($type);
		if(is_object($value)){
			$filter = $this->filterContainer->getCallback($value);
			if($filter) {
				$value = $this->filterContainer->doCallback($filter, array($value, $this));
				if($value instanceof HessianStreamResult){
					return $value->stream;
				}
				$ntype = gettype($value);
				if($type != $ntype)
					$dispatch = $this->resolveDispatch($ntype);
			}
		}
		$data = $this->$dispatch($value);
		return $data;	
	}
	
	function resolveDispatch($type){
		$dispatch = '';
		// TODO usar algun type helper
		switch($type){
			case 'integer': $dispatch = 'writeInt' ;break;
			case 'boolean': $dispatch = 'writeBool' ;break;
			case 'string': $dispatch = 'writeString' ; break;
			case 'double': $dispatch = 'writeDouble' ; break;
			case 'array': $dispatch = 'writeArray' ; break;
			case 'object': $dispatch = 'writeObject' ;break;
			case 'NULL': $dispatch = 'writeNull';break;
			case 'resource': $dispatch = 'writeResource' ; break;
			default: 
				throw new Exception("Handler for type $type not implemented");
		}
		$this->logMsg("dispatch $dispatch");
		return $dispatch;
	}
	
	function writeNull(){
		return 'N';
	}
	
	function writeArray($array){
		if(empty($array))
			return 'N';
		
		$refindex = $this->refmap->getReference($array);
		if($refindex !== false){
			return $this->writeReference($refindex);
		}
				
		/* ::= x57 value* 'Z'        # variable-length untyped list
     	::= x58 int value*        # fixed-length untyped list
        ::= [x78-7f] value*       # fixed-length untyped list
     	*/
				
		$total = count($array);		
		if(HessianUtils::isListFormula($array)){
			$this->refmap->objectlist[] = &$array;
			$stream = '';
			if($total <= 7){
				$len = $total + 0x78;
				$stream = pack('c', $len);
			} else {
				$stream = pack('c', 0x58);
				$stream .= $this->writeInt($total);
			}
			foreach($array as $key => $value){
				$stream .= $this->writeValue($value); 
			}
			return $stream;
		} else{
			return $this->writeMap($array);
		}
	}
	
	function writeMap($map, $type = ''){
		if(empty($map))
			return 'N';
		
		/*
		::= 'M' type (value value)* 'Z'  # key, value map pairs
	   ::= 'H' (value value)* 'Z'       # untyped key, value 
		 */
		
		$refindex = $this->refmap->getReference($map);
		if($refindex !== false){
			return $this->writeReference($refindex);
		}
		
		$this->refmap->objectlist[] = &$map;
		
		if($type == '') {
			$stream = 'H';
		} else{
			$stream = 'M';
			$stream .= $this->writeType($type);
		}
		foreach($map as $key => $value){
			$stream .= $this->writeValue($key);
			$stream .= $this->writeValue($value);
		}
		$stream .= 'Z';
		return $stream;
	}
	
	function writeObjectData($value){
		$stream = '';
		$class = get_class($value);
		$index = $this->refmap->getClassIndex($class);
		
		if($index === false){
			$classdef = new HessianClassDef();
			$classdef->type = $class;
			if($class == 'stdClass'){
				$classdef->props = array_keys(get_object_vars($value));
			} else
				$classdef->props = array_keys(get_class_vars($class));
			$index = $this->refmap->addClassDef($classdef);
			$total = count($classdef->props);
			
			$type = $this->typemap->getRemoteType($class);
			$class = $type ? $type : $class;
			
			$stream .= 'C';
			$stream .= $this->writeString($class);
			$stream .= $this->writeInt($total);
			foreach($classdef->props as $name){
				$stream .= $this->writeString($name);
			}
		} 
				
		if($index < 16){
			$stream .= pack('c', $index + 0x60);
		} else{
			$stream .= 'O';
			$stream .= $this->writeInt($index);
		}
		
		$this->refmap->objectlist[] = $value;
		$classdef = $this->refmap->classlist[$index];
		foreach($classdef->props as $key){
			$val = $value->$key;
			$stream .= $this->writeValue($val);
		}	
		
		return $stream;
	}

	function writeObject($value){
		//if($this->dateAdapter->isDatetime($value))
		//	return $this->writeDate($value);
		
		$refindex = $this->refmap->getReference($value);
		if($refindex !== false){
			return $this->writeReference($refindex);
		}
		return $this->writeObjectData($value);
	}
	
	function writeType($type){
		$this->logMsg("writeType $type");
		$refindex = $this->refmap->getTypeIndex($type);
		if($refindex !== false){
			return $this->writeInt($refindex);
		}
		$this->references->typelist[] = $type;
		return $this->writeString($type);
	}
	
	function writeReference($value){
		$this->logMsg("writeReference $value");
		$stream = pack('c', 0x51);
		$stream .= $this->writeInt($value);
		return $stream;
	}
	
	function writeDate($value){
		//$ts = $this->dateAdapter->toTimestamp($value);
		$ts = $value;
		$this->logMsg("writeDate $ts");
		$stream = '';
		if($ts % 60 != 0){
			$stream = pack('c', 0x4a);
			$ts = $ts * 1000;
			$res = $ts / HessianUtils::pow32;
			$stream .= pack('N', $res);
			$stream .= pack('N', $ts);
		} else { // compact date, only minutes
			$ts = intval($ts / 60);
			$stream = pack('c', 0x4b);
			$stream .= pack('c', ($ts >> 24));
			$stream .= pack('c', ($ts >> 16));
			$stream .= pack('c', ($ts >> 8));
			$stream .= pack('c', $ts);
		}
		return $stream;
	}
		
	function writeBool($value){
		if($value) return 'T';
		else return 'F';
	}
	
	function between($value, $min, $max){
		return $min <= $value && $value <= $max;
	}
	
	function writeInt($value){
		if($this->between($value, -16, 47)){
			return pack('c', $value + 0x90);
		} else
		if($this->between($value, -2048, 2047)){
			$b0 = 0xc8 + ($value >> 8);
			$stream = pack('c', $b0);
			$stream .= pack('c', $value);
			return $stream;
		} else
		if($this->between($value, -262144, 262143)){
			$b0 = 0xd4 + ($value >> 16);
			$b1 = $value >> 8;
			$stream = pack('c', $b0);
			$stream .= pack('c', $b1);
			$stream .= pack('c', $value);
			return $stream;
		} else {
			$stream = 'I';
			$stream .= pack('c', ($value >> 24));
			$stream .= pack('c', ($value >> 16));
			$stream .= pack('c', ($value >> 8));
			$stream .= pack('c', $value);
			return $stream;
		}
	}

    /**
    * The function hessianString() translates an UTF-16 string to the
    * non-conformant UTF-8 representation required by Hessian using the same
    * logic implemented in the Java Hessian classes.
    * @author Massimo Squillace 2012-08-16
    * @param string UTF-16 encoded string
    * @return string Hessian encoded string
    */
	function hessianString($wrk){
        $length = strlen($wrk);
        $buffer = '';
        for($i = 0; $i < $length;) {
            list(, $ch) = unpack('n', $wrk[$i++].$wrk[$i++]);
            if($ch < 0x80)
                $buffer .= pack('C', $ch);
            else if($ch < 0x800) {
                $buffer .= pack('C', (0xc0 + (($ch >> 6) & 0x1f)));
                $buffer .= pack('C', (0x80 + ($ch & 0x3f)));
            } else {
                $buffer .= pack('C', (0xe0 + (($ch >> 12) & 0xf)));
                $buffer .= pack('C', (0x80 + (($ch >> 6) & 0x3f)));
                $buffer .= pack('C', (0x80 + ($ch & 0x3f)));
            }
        }
        return $buffer;
    }

    /**
    * Replaces original version, which was renamed writeString_original()
    * (see below). Checks if input is a well-formed UTF-8 string mapping
    * codepoints inside the standard Unicode range of 0-0x10FFFF. If OK,
    * encodes the input according to the Hessian serialization protocol,
    * version 2.0.
    * The Hessian serialization protocol (all versions) is built with Java
    * in mind, which internally manages strings in UTF-16.
    * UTF-16 uses a single 16-bit code unit to encode the most common 63K
    * characters, and a pair of 16-bit code units, called surrogates, to encode
    * the 1M less commonly used characters in Unicode (Note: The Unicode
    * Standard encodes characters in the range U+0000 to U+10FFFF, which amounts
    * to a 21-bit code space).
    * Now, Hessian serialization requires UTF-8, and the definition of UTF-8
    * requires that supplementary characters (those using surrogate pairs in
    * UTF-16) be encoded with a single four byte sequence.
    * However, there is a widespread practice of generating pairs of three byte
    * sequences in older software, especially software which pre-dates the
    * introduction of UTF-16 or that is interoperating with UTF-16 environments
    * under particular constraints.
    * Such an encoding is not conformant to UTF-8 as defined, nonetheless
    * Hessian serialization appears to follow this deprecated practice.
    * This creates a problem, since PHP UTF-8 support conforms to the standard
    * and no PHP function is available to convert to/from these three byte
    * sequences.
    * The function writeString() works around the hurdle by converting a PHP
    * UTF-8 string to UTF-16, thus generating surrogates if the original string
    * contained supplementary characters.
    * The UTF-16 string is then translated to the non-conformant UTF-8
    * representation required by Hessian using the same logic implemented in the
    * Java Hessian classes (see function hessianString()).
    * @author Massimo Squillace 2012-08-16
    * @param string UTF-8 encoded string
    * @return string Hessian encoded string
    */
    function writeString($value){
        if('' == $value) {
            return 'N';
        }
        if(HessianUtils::isValidUTF8($value)) {
            $value = mb_convert_encoding($value, 'UTF-16', 'UTF-8');
            $len = strlen($value) / 2; // "Hessian" length
            $stream = '';
            $strOffset = 0;
            while($len > 0x8000) {
                $sublen = 0x8000;
                // chunk can't end in high surrogate
                list(, $tail) = unpack('n', substr($value, $strOffset + ($sublen-1)*2, 2));
                if(0xd800 <= $tail && $tail <= 0xdbff) {
                    $sublen--;
                }
                $stream .= 'R'.pack('n', $sublen).$this->hessianString(substr($value, $strOffset, $sublen * 2));
                $len -= $sublen;
                $strOffset += $sublen * 2;
            }
            if($strOffset) {
                $value = substr($value, $strOffset);
            }
            if($len < 32) {
                return $stream.pack('C', $len).$this->hessianString($value);
            }
            if($len < 1024) {
                $b1 = intval($len / 256);
                $b0 = $len % 256;
                return $stream.pack('CC', $b1 + 0x30, $b0).$this->hessianString($value);
            }
            if($len > 1023) {
                return $stream.'S'.pack('n', $len).$this->hessianString($value);
            }
        } else {
            throw new Exception("Input is not well-formed UTF-8 or contains codepoints outside the Unicode range");
        }
    }

	function writeString_original($value){
		$len = HessianUtils::stringLength($value);
		if($len < 32){
			return pack('C', $len) 
				. $this->writeStringData($value);
		} else 
		if($len < 1024){
			$b0 = 0x30 + ($len >> 8);
			$stream = pack('C', $b0);
			$stream .= pack('C', $len);
			return $stream . $this->writeStringData($value);
		} else {
			// TODO :chunks
			$total = $len;
			$stream = '';
			$tag = 'S';
			$stream .= $tag . pack('n', $len);
			$stream .= $this->writeStringData($value);
			return $stream;
		}
	}
	
	function writeSmallString($value){
		$len = HessianUtils::stringLength($value);
		if($len < 32){
			return pack('C', $len) 
				. $this->writeStringData($value);
		} else 
		if($len < 1024){
			$b0 = 0x30 + ($len >> 8);
			$stream .= pack('C', $b0);
			$stream .= pack('C', $len);
			return $stream . $this->writeStringData($value);
		} 
	}
	
	function writeStringData($string){
		return HessianUtils::writeUTF8($string);
	}
	
	function writeDouble($value){
		$frac = abs($value) - floor(abs($value));
		if($value == 0.0){
			return pack('c', 0x5b);
		}
		if($value == 1.0){
			return pack('c', 0x5c);
		}
		
		// Issue 10, Fix thanks to nesnnaho...@googlemail.com, 
		if($frac == 0 && $this->between($value, -127, 128)){
			return pack('c', 0x5d) . pack('c', $value);
		}
		if($frac == 0 && $this->between($value, -32768, 32767)){
			$stream = pack('c', 0x5e);
			$stream .= HessianUtils::floatBytes($value);
			return $stream;
		}
		// TODO double 4 el del 0.001, revisar
		$mills = (int) ($value * 1000);
	    if (0.001 * $mills == $value) {
	    	$stream = pack('c', 0x5f);
	      	$stream .= pack('c', $mills >> 24);
	      	$stream .= pack('c', $mills >> 16);
	      	$stream .= pack('c', $mills >> 8);
	      	$stream .= pack('c', $mills);
			return $stream;
	    }
		// 64 bit double
		$stream = 'D';
		$stream .= HessianUtils::doubleBytes($value);
		return $stream;
	}
	
	function writeResource($handle){
		$type = get_resource_type($handle);
		$info = stream_get_meta_data($handle);
		$stream = '';
		if('plainfile' === $info['wrapper_type']) {
			if(0x4000000 < filesize($info['uri'])) {
				throw new Exception("Cannot handle resource bigger than 64MB");	
			} else {
				$bin = new HessianBinary(stream_get_contents($handle));
				$stream = $bin->writeBinary();
			}
		} else {
			throw new Exception("Cannot handle resource of type '$type'");	
		}
		return $stream;
	}
		
}