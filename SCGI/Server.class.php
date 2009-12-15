<?php
namespace MFS\SCGI;

class Server
{
    private $socket = null;
    private $conn = null;

    private $headers = null;

    public function __construct()
    {
        $errno = 0;
        $errstr = "";        
    }

    public function __destruct()
    {
        fclose($this->socket);
        // $this->log("DeInitialized SCGI Application: ".get_class($this));
    }

    public function readRequest()
    {
        while (true) {
            $this->conn = stream_socket_accept($this->socket, -1);

            if (false === $this->conn)
                return false;

            $len = stream_get_line($this->conn, 20, ':');

            if (false === $len) {
                throw new LogicException('error reading data');
            }

            if ('' === $len) {
                // could be bug in PHP or Lighttpd. sometimes, app just gets empty request
                // retrying
                $this->doneWithRequest();
                continue;
            }

            if (!is_numeric($len)) {
                throw new BadProtocolException('invalid protocol (expected length, got '.var_export($len, true).')');
            }

            $_headers_str = stream_get_contents($this->conn, $len);

            $_headers = explode("\0", $_headers_str); // getting headers
            $divider = stream_get_contents($this->conn, 1); // ","

            $this->headers = array();
            $first = null;
            foreach ($_headers as $element) {
                if (null === $first) {
                    $first = $element;
                } else {
                    $this->headers[$first] = $element;
                    $first = null;
                }

            }
            unset($_headers, $first);

            if (!isset($this->headers['SCGI']) or $this->headers['SCGI'] != '1')
                throw new BadProtocolException("Request is not SCGI/1 Compliant (".var_dump($this->headers, true).")");

            if (!isset($this->headers['CONTENT_LENGTH']))
                throw new BadProtocolException("CONTENT_LENGTH header not present");

            // $this->body = ($this->headers['CONTENT_LENGTH'] > 0) ? stream_get_contents($this->conn, $this->headers['CONTENT_LENGTH']) : null;

            unset($this->headers['SCGI']);

            return true;
        }
    }

    public function doneWithRequest()
    {
        if (null !== $this->conn) {
            $this->headers = null;

            fclose($this->conn);
            $this->conn = null;
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getStdin()
    {
        return $this->conn;
    }

    public function write($data)
    {
        fwrite($this->conn, $data);
    }
}
