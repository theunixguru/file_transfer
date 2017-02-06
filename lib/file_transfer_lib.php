<?php

namespace FileTransfer;

class Factory
{

    public static function getConnection($type, $username, $password, $hostname, $port)
    {
        switch($type) {
            case 'ssh' :
                return new SftpConnection($username, $password, $hostname, $port);
                break;
        }

        throw new Exception('Please provide a connection type');
    }

}

abstract class AbstractConnection
{
    public $username;
    public $password;
    public $hostname;
    public $port;

    protected $session;

    public __construct($username, $password, $hostname, $port=null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->hostname = $hostname;
        $this->port = $port;
    }

    public function cd($path)
    {
        $this->exec("cd $path");
        return $this;
    }

    public function pwd()
    {
        return $this->exec("/bin/pwd");
    }

    abstract public function download($remote_file);
    abstract public function upload($local_file);
    abstract public function exec($cmd);
    abstract public function close();
}

class SftpConnection extends AbstractConnection
{
    const DEFAULT_PORT = 22;

    public __construct($username, $password, $hostname, $port = DEFAULT_PORT)
    {
        parent::__construct($username, $password, $hostname, $port);

        $this->session = ssh2_connect(
            $this->hostname,
            $this->port,
            null,
            [
                'disconnect' => function ($code, $message) {
                    throw new Exception($message, $code);
                }
            ]
        );

        if (!ssh2_auth_password($this->session, $this->username, $this->password)) {
            throw new Exception(sprintf(
                "Authentication failed for username %s, password %s",
                $this->username, $this->password
            ));
        }
    }

    public function download($filename)
    {
        $local_path = getcwd().'/'.$filename;
        $remote_path = $this->pwd().'/'.$filename;
        if (!ssh2_scp_recv($this->session, $remote_path, $local_path)) {
            throw new Exception(
                "Cannot download file %s, local path %s, remote path: %s",
                 $filename,
                 $local_path,
                 $remote_path
            );
        }
        return $this;
    }

    public function upload($filename)
    {
        // TODO
    }

    public function exec($cmd)
    {
        $stream_ssh = ssh2_exec($this->session, $cmd);
        stream_set_blocking($stream_ssh, true);
        $stream_io = ssh2_fetch_stream($stream_ssh, SSH2_STREAM_STDIO);
        return stream_get_contents($stream_io);
    }

    public function close()
    {
        $this->session = null;
    }

}

?>
