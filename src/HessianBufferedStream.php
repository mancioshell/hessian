<?php
/*
 * This file is part of the HessianPHP package.
 * (c) 2004-2010 Manuel Gómez
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class HessianBufferedStream{
	public $fp;
	public $pos = 0;
	public $len = 0;
	public $bufferSize = 1024;
	public $bytes = array();
	
	function __construct($fp, $bufferSize = 1024){
		if(!is_resource($fp))
			throw new Exception('Parameter fp must be a valid resource handle');
		$this->fp = $fp;
		$this->bufferSize = $bufferSize;
	}
	
	function setStream($fp){
		$this->fp = $fp;
		$this->len = 0;
	}
	
	public function peek($count = 1, $pos = null){
		if($pos == null)
			$pos = $this->pos;
		
		$newpos = $pos + $count;
		$this->checkRead($newpos);
		
		$portion = array_slice($this->bytes, $pos, $count);
		return implode($portion);
	}
	
	public function read($count=1){
		if($count == 0)
			return;
		$newpos = $this->pos + $count;
		$this->checkRead($newpos);
		
		$portion = array_slice($this->bytes, $this->pos, $count);
		$read = count($portion);
		$this->pos += $read;
		if($read < $count)
			throw new Exception('read past end of stream: '.$this->pos);
		return implode($portion);
	}
	
	public function checkRead($newpos){
		//return;
		if(feof($this->fp) && $newpos > $this->len)
			throw new Exception('read past end of stream: '.$newpos);
		if($newpos > $this->len){
			while($this->len < $newpos){
				$data = fread($this->fp, $this->bufferSize);
				$bytes = str_split($data);
				$this->bytes = array_merge($this->bytes, $bytes);
				$this->len += count($bytes);
			}
		}
	}
	
	public function EOF(){
		return feof($this->fp);
	}
	
	public function write($data){
		$bytes = str_split($data);
		$this->bytes = array_merge($this->bytes, $bytes);
		$len = fwrite($this->fp, $data);
		$this->len += $len;
	}
	
	public function readAll(){
		$data = stream_get_contents($this->fp);
		$this->bytes = str_split($data);
		$this->len = count($this->bytes);
		return $data;		
	}
	
	public function flush(){
		fpassthru($this->fp);
		fflush($this->fp);
	}
	
	public function getData(){
		return implode($this->bytes);
	}
	
	public function close(){
		@fclose($this->fp);
	}
}

/**
 * The original class, now renamed HessianBufferedStream_vsayajin() was slow
 * when dealing with large streams. Can't understand why vsayajin had problems
 * with encodings different from Latin1, since this class doesn't care or check
 * encodings, so I rewrote it with substr/strlen, getting rid of the cumbersome
 * array functions. (Massimo Squillace 2012-09-07)
 */
class HessianBufferedStream_vsayajin{
	public $fp;
	public $pos = 0;
	public $len = 0;
	public $bufferSize = 1024;
	public $bytes = '';
	
	function __construct($fp, $bufferSize = 1024){
		if(!is_resource($fp))
			throw new Exception('Parameter fp must be a valid resource handle');
		$this->fp = $fp;
		$this->bufferSize = $bufferSize;
	}
	
	function setStream($fp){
		$this->fp = $fp;
		$this->len = 0;
	}
	
	public function peek($count = 1, $pos = null){
		if($pos == null)
			$pos = $this->pos;
		
		$newpos = $pos + $count;
		$this->checkRead($newpos);
		
		return substr($this->bytes, $pos, $count);
	}
	
	public function read($count=1){
		if($count == 0)
			return;
		$newpos = $this->pos + $count;
		$this->checkRead($newpos);
		
		$portion = substr($this->bytes, $this->pos, $count);
		$read = strlen($portion);
		$this->pos += $read;
		if($read < $count)
			throw new Exception('read past end of stream: '.$this->pos);
		return $portion;
	}
	
	public function checkRead($newpos){
		if(feof($this->fp) && $newpos > $this->len)
			throw new Exception('read past end of stream: '.$newpos);
		if($newpos > $this->len){
			while($this->len < $newpos){
				$data = fread($this->fp, $this->bufferSize);
				$this->bytes .= $data;
				$this->len += strlen($data);
			}
		}
	}
	
	public function EOF(){
		return feof($this->fp);
	}
	
	public function write($data){
		$this->bytes .= $data;
		$len = fwrite($this->fp, $data);
		$this->len += $len;
	}
	
	public function readAll(){
		$data = stream_get_contents($this->fp);
		$this->bytes = $data;
		$this->len = strlen($data);
		return $data;		
	}
	
	public function flush(){
		fpassthru($this->fp);
		fflush($this->fp);
	}
	
	public function getData(){
		return $this->bytes;
	}
	
	public function close(){
		@fclose($this->fp);
	}
}
