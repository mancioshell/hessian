<?php
/*
 * This file is an addition to the original HessianPHP package.
 * (c) 2012 Massimo Squillace
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class HessianBinary {
    protected $data;

    function __construct($data = null) {
        $this->data = $data;
    }

    public function writeBinary() {
        $len = strlen($this->data);
        $stream = '';
        $strOffset = 0;
        $sublen = 0xFFFF;
        while($len > $sublen) {
            $stream .= 'A'.pack('n', $sublen).substr($this->data, $strOffset, $sublen);
            $len -= $sublen;
            $strOffset += $sublen;
            }
        if($strOffset) {
            $this->data = substr($this->data, $strOffset);
        }
        if($len < 16) {
            return $stream.pack('C', $len + 0x20).$this->data;
        }
        if($len < 1024) {
            $b1 = intval($len / 256);
            $b0 = $len % 256;
            return $stream.pack('CC', $b1 + 0x34, $b0).$this->data;
        }
        if($len > 1023) {
            return $stream.'B'.pack('n', $len).$this->data;
        }
    }
}