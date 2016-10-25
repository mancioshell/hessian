<?php
/*
 * This file is part of the HessianPHP package.
 * (c) 2004-2010 Manuel Gómez
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Represents a stream of bytes used for reading
 * It doesn't use any of the string length functions typically used
 * for files because if can cause problems with encodings different than latin1
 * @author vsayajin
 */
class HessianStream_vsayajin{
	public $pos = 0;
	public $len;
	public $bytes = array();
	
	function __construct($data = null, $length = null){
		if($data)
			$this->setStream($data, $length);
	}
	
	function setStream($data, $length = null){
		$this->bytes = str_split($data);
		$this->len = count($this->bytes);
		$this->pos = 0;
	}
	
	public function peek($count = 1, $pos = null){
		if($pos == null)
			$pos = $this->pos;
		
		$portion = array_slice($this->bytes, $pos, $count);
		return implode($portion);
	}

	public function read($count=1){
		if($count == 0)
			return;
		$portion = array_slice($this->bytes, $this->pos, $count);
		$read = count($portion);
		$this->pos += $read;
		if($read < $count) {
			if($this->pos == 0)
				throw new Exception('Empty stream received!');
			else
				throw new Exception('read past end of stream: '.$this->pos);
		}
		return implode($portion);
	}
	
	public function readAll(){
		$this->pos = $this->len;
		return implode($this->bytes);		
	}

	public function write($data){
		$bytes = str_split($data);
		$this->len += count($bytes);
		$this->bytes = array_merge($this->bytes, $bytes);
	}
 
	public function flush(){}
	
	public function getData(){
		return implode($this->bytes);
	}
	
	public function close(){		
	}
}

/**
 * The original class, now renamed HessianStream_vsayajin() was intolerably slow
 * when dealing with large streams. Can't understand why vsayajin had problems
 * with encodings different from Latin1, since this class doesn't care or check
 * encodings, so I rewrote it with substr/strlen, getting rid of the cumbersome
 * array functions. (Massimo Squillace 2012-09-07)
 */
class HessianStream{
	public $pos = 0;
	public $len;
	public $bytes = array();
	
	function __construct($data = null, $length = null){
		if($data)
			$this->setStream($data, $length);
	}
	
	function setStream($data, $length = null){
		$this->bytes = $data;
		$this->len = strlen($this->bytes);
		$this->pos = 0;
	}
	
	public function peek($count = 1, $pos = null){
		if($pos == null)
			$pos = $this->pos;
		
		return substr($this->bytes, $pos, $count);
	}

	public function read($count=1){
		if($count == 0)
			return;
		$portion = substr($this->bytes, $this->pos, $count);
		$read = strlen($portion);
		$this->pos += $read;
		if($read < $count) {
			if($this->pos == 0)
				throw new Exception('Empty stream received!');
			else
				throw new Exception('read past end of stream: '.$this->pos);
		}
		return $portion;
	}
	
	public function readAll(){
		$this->pos = $this->len;
		return $this->bytes;		
	}

	public function write($data){
		$this->len += strlen($data);
		$this->bytes .= $data;
	}
 
	public function flush(){}
	
	public function getData(){
		return $this->bytes;
	}
	
	public function close(){		
	}
}

