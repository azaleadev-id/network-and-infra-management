<?php
/*****************************
 *
 * RouterOS PHP API class v1.6
 * Author: Denis Basta
 * Contributors:
 *    Nick Barnes
 *    Ben Menking (ben@infotechsc.com)
 *    Jeremy Jefferson (http://jeremyj.com)
 *    Ales Moor
 *    balu (http://www.kassiopeia.juls.savba.sk/~balu/)
 *    http://www.mikrotik.com
 *    http://www.steakhound.com
 *
 ******************************/
class RouterosAPI
{
    var $debug     = false; 
    var $connected = false;
    var $port      = 8728;
    var $timeout   = 3;
    var $attempts  = 5;
    var $delay     = 3;
    var $socket;
    var $error_no;
    var $error_str;

    public function debug($text) {
        if ($this->debug) {
            echo $text . "\n";
        }
    }

    public function connect($ip, $login, $password) {
        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; $ATTEMPT++) {
            $this->connected = false;
            $this->debug('Connection attempt #' . $ATTEMPT . ' to ' . $ip . ':' . $this->port . '...');
            $this->socket = @fsockopen($ip, $this->port, $this->error_no, $this->error_str, $this->timeout);
            if ($this->socket) {
                socket_set_timeout($this->socket, $this->timeout);
                $this->write('/login', false);
                $this->write('=name=' . $login, false);
                $this->write('=password=' . $password);
                $RESPONSE = $this->read(false);

                if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                    $this->connected = true;
                    break;
                }

                $this->write('/login');
                $RESPONSE = $this->read(false);
                if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done' && isset($RESPONSE[1])) {
                    $MATCH = preg_match('/[^=]+=(.*)/', $RESPONSE[1], $MATCHES);
                    if ($MATCH) {
                        $this->write('/login', false);
                        $this->write('=name=' . $login, false);
                        $this->write('=response=00' . md5(chr(0) . $password . pack('H*', $MATCHES[1])));
                        $RESPONSE = $this->read(false);
                        if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                            $this->connected = true;
                            break;
                        }
                    }
                }
                fclose($this->socket);
            }
            sleep($this->delay);
        }

        if ($this->connected) {
            $this->debug('Connected...');
        } else {
            $this->debug('Error...');
        }
        return $this->connected;
    }

    public function disconnect() {
        $this->connected = false;
        $this->debug('Disconnected...');
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    public function parseResponse($response) {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal','!re','!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCH = preg_match('/^=([^=]+)=(.*)/', $x, $MATCHES);
                    if ($MATCH) {
                        $CURRENT[$MATCHES[1]] = (isset($MATCHES[2]) ? $MATCHES[2] : '');
                    } else {
                        $CURRENT[] = $x;
                    }
                }
            }
            return $PARSED;
        } else {
            return array();
        }
    }

    public function array_change_key_case_ext(array $array, $case = CASE_LOWER) {
        if (!is_array($array)) {
            return false;
        }
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->array_change_key_case_ext($value, $case);
            }
        }
        return array_change_key_case($array, $case);
    }

    public function encodeLength($length) {
        if ($length < 0x80) {
            $length = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $length = chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $length = chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $length = chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        } elseif ($length >= 0x10000000) {
            $length = chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
        return $length;
    }

    public function write($command, $param2 = true) {
        if ($command) {
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('>>> [' . strlen($com) . '] ' . $com);
            }
            if ($param2) {
                fwrite($this->socket, chr(0));
                $this->debug('>>> [0] DONE');
            }
        }
    }

    public function read($parse = true) {
        $RESPONSE     = array();
        $receiveddone = false;
        while (true) {
            $_ = '';
            $BYTE   = ord(fread($this->socket, 1));
            $LENGTH = 0;
            if ($BYTE & 128) {
                if (($BYTE & 192) == 128) {
                    $LENGTH = (($BYTE & 63) << 8) + ord(fread($this->socket, 1));
                } else {
                    if (($BYTE & 224) == 192) {
                        $LENGTH = (($BYTE & 31) << 8) + ord(fread($this->socket, 1));
                        $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                    } else {
                        if (($BYTE & 240) == 224) {
                            $LENGTH = (($BYTE & 15) << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        } else {
                            $LENGTH = ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
            } else {
                $LENGTH = $BYTE;
            }

            if ($LENGTH > 0) {
                $_      = "";
                $retlen = 0;
                while ($retlen < $LENGTH) {
                    $toread = $LENGTH - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                $RESPONSE[] = $_;
                $this->debug('<<< [' . $retlen . '] ' . $_);
            }

            if ($_ == '!done') {
                $receiveddone = true;
            }

            $STATUS = socket_get_status($this->socket);
            if ($LENGTH > 0) {
                $dmo = $_;
            } else {
                $dmo = '';
            }

            if ($receiveddone == true) {
                break;
            }
        }
        if ($parse) {
            return $this->parseResponse($RESPONSE);
        } else {
            return $RESPONSE;
        }
    }

    public function comm($com, $arr = array()) {
        $count = count($arr);
        $this->write($com, !$count);
        $i = 0;
        foreach ($arr as $k => $v) {
            switch ($k[0]) {
                case '?':
                    $el = "$k=$v";
                    break;
                case '~':
                    $el = "$k~$v";
                    break;
                default:
                    $el = "=$k=$v";
                    break;
            }
            $last = ($i++ == $count - 1);
            $this->write($el, $last);
        }
        return $this->read();
    }
}
