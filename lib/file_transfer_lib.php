<?php

namespace FileTransfer;

class Factory
{

    public static function getConnection($type, $username, $password, $hostname, $port=null)
    {
        switch($type) {
            case 'ssh' :
                if ($port == null) {
                    $port = SftpConnection::getDefaultPort();
                }
                return new SftpConnection($username, $password, $hostname, $port);
                break;

            case 'ftp':
                if ($port == null) {
                    $port = FtpConnection::getDefaultPort();
                }
                return new FtpConnection($username, $password, $hostname, $port);
                break;
        }

        throw new \Exception('Please provide a connection type');
    }

}

abstract class AbstractConnection
{
    public $username;
    public $password;
    public $hostname;
    public $port;

    protected $session;

    function __construct($username, $password, $hostname, $port=null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->hostname = $hostname;
        $this->port = $port;
    }

    abstract public function getDefaultPort();

    abstract public function cd($path);
    abstract public function pwd();
    abstract public function download($remote_file);
    abstract public function upload($local_file);
    abstract public function exec($cmd);
    abstract public function close();
}

class SftpConnection extends AbstractConnection
{
    const DEFAULT_PORT = 22;

    protected $cwd;

    function __construct($username, $password, $hostname, $port=self::DEFAULT_PORT)
    {
        parent::__construct($username, $password, $hostname, $port);

        $this->session = ssh2_connect(
            $this->hostname,
            $this->port,
            null,
            [
                'disconnect' => function ($code, $message) {
                    throw new \Exception($message, $code);
                }
            ]
        );

        if (!ssh2_auth_password($this->session, $this->username, $this->password)) {
            throw new \Exception(sprintf(
                "Authentication failed for username %s, password %s",
                $this->username, $this->password
            ));
        }
    }

    public function getDefaultPort()
    {
        return self::DEFAULT_PORT;
    }

    public function cd($path)
    {
        $this->cwd = $path;
        return $this;
    }

    public function pwd()
    {
        return $this->cwd;
    }

    public function download($filename)
    {
        $local_path = getcwd().'/'.$filename;
        $remote_path = $this->pwd().'/'.$filename;
        if (!ssh2_scp_recv($this->session, $remote_path, $local_path)) {
            throw new \Exception(
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
        $local_path = getcwd().'/'.$filename;
        $remote_path = $this->pwd().'/'.$filename;
        if (!ssh_scp_send($this->session, $local_path, $remote_path)) {
            throw new \Exception(
                "Cannot upload file %s, local path %s, remote path %s",
                 $filename,
                 $local_path,
                 $remote_path
            );
        }
        return $this;
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

class FtpConnection extends AbstractConnection
{
    const DEFAULT_PORT = 21;

    function __construct($username, $password, $hostname, $port=self::DEFAULT_PORT)
    {
        parent::__construct($username, $password, $hostname, $port);

        $this->session = ftp_connect($this->hostname, $this->port);
        if ($this->session === False) {
            throw new \Exception(sprintf(
                "Cannot connect to server %s, port %s",
                $this->hostname,
                $this->port
            ));
        }

        if (!@ftp_login($this->session, $this->username, $this->password)) {
            throw new \Exception(sprintf(
                "Cannot login as user %s, password %s",
                $this->username,
                $this->password
            ));
        }

        ftp_pasv($this->session, True);
    }

    public function getDefaultPort()
    {
        return self::DEFAULT_PORT;
    }

    public function cd($path)
    {
        ftp_chdir($this->session, $path);
        return $this;
    }

    public function pwd()
    {
        return ftp_pwd($this->session);
    }

    public function download($filename)
    {
        $local_path = getcwd().'/'.$filename;
        $remote_path = $this->pwd().'/'.$filename;

        if (!ftp_get($this->session, $filename, $filename, FTP_BINARY)) {
            throw new \Exception(
                "Cannot download file %s, local path %s, remote path: %s",
                 $filename,
                 $local_path,
                 $remote_path
            );
        }

        return $this;
    }

    public function upload($local_path)
    {
        $filename = basename($local_path);
        $remote_path = $this->pwd().'/'.$filename;

        if (!ftp_put($this->session, $filename, $local_path, FTP_BINARY)) {
            throw new \Exception(
                "Cannot upload file %s, local path %s, remote path %s",
                 $filename,
                 $local_path,
                 $remote_path
            );
        }

        return $this;
    }

    public function exec($cmd)
    {
        $result = [];

        $remote_tmpfile = 'output.txt';
        if(!ftp_exec($this->session, "$cmd > $remote_tmpfile")) {
            throw new \Exception("Cannot execute command '$cmd'.".
                " Reason: SITE command not supported");
        }

        $local_tmpfile = tempnam(sys_get_temp_dir(), 'ft');
        if (!ftp_get($this->session, $local_tmpfile, $remote_tmpfile, FTP_BINARY)) {
            throw new \Exception(
                "Cannot execute command '$cmd'. Reason: temporary file");
        }
        ftp_delete($this->session, $remote_tmpfile);

        $fh = fopen($local_tmpfile, "r");
        while ($line = fgets($fh)) {
            $result[]= chop($line);
        }
        fclose($dh);
        unlink($local_tmpfile);

        return $result;
    }

    public function close()
    {
        ftp_close($this->session);
    }

}

?>
