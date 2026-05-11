<?php
/**
 * PHPMailer - PHP email creation and transport class - SMTP class.
 * PHP Version 5.5.
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 *
 * @author    Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author    Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author    Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author    Brent R. Matzelle (original founder)
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer SMTP class.
 *
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski <jimjag@gmail.com>
 * @author Andy Prevost <codeworxtech@users.sourceforge.net>
 */
class SMTP
{
    /**
     * The SMTP port to use if one is not specified.
     *
     * @var int
     */
    const DEFAULT_PORT = 25;

    /**
     * The maximum line length.
     *
     * @var int
     */
    const MAX_LINE_LENGTH = 998;

    /**
     * Debug level.
     *
     * @var int
     */
    public $Debug = 0;

    /**
     * Debug output handler.
     *
     * @var callable
     */
    public $Debugoutput = 'echo';

    /**
     * SMTP host.
     *
     * @var string
     */
    public $Host = 'localhost';

    /**
     * SMTP port.
     *
     * @var int
     */
    public $Port = self::DEFAULT_PORT;

    /**
     * SMTP timeout.
     *
     * @var int
     */
    public $Timeout = 10;

    /**
     * SMTP secure.
     *
     * @var string
     */
    public $SMTPSecure = '';

    /**
     * SMTP auto TLS.
     *
     * @var bool
     */
    public $SMTPAutoTLS = true;

    /**
     * SMTP options.
     *
     * @var array
     */
    public $SMTPOptions = [];

    /**
     * SMTP username.
     *
     * @var string
     */
    public $Username = '';

    /**
     * SMTP password.
     *
     * @var string
     */
    public $Password = '';

    /**
     * SMTP auth type.
     *
     * @var string
     */
    public $AuthType = '';

    /**
     * SMTP VERP.
     *
     * @var bool
     */
    public $do_verp = false;

    /**
     * The socket resource.
     *
     * @var resource
     */
    protected $smtp_conn;

    /**
     * The last error message.
     *
     * @var string
     */
    protected $error;

    /**
     * The last reply code.
     *
     * @var int
     */
    protected $last_reply;

    /**
     * Connect to an SMTP server.
     *
     * @param string $host
     * @param int    $port
     * @param int    $timeout
     *
     * @return bool
     */
    public function connect($host, $port = null, $timeout = 30)
    {
        $this->Host = $host;
        $this->Port = $port ?: self::DEFAULT_PORT;
        $this->Timeout = $timeout;

        $this->smtp_conn = @fsockopen(
            $this->Host,
            $this->Port,
            $errno,
            $errstr,
            $this->Timeout
        );

        if (!$this->smtp_conn) {
            $this->error = "Failed to connect to server: {$errstr} ({$errno})";
            return false;
        }

        $this->setError('');
        $this->last_reply = $this->get_lines();

        if (substr($this->last_reply, 0, 3) !== '220') {
            $this->error = "Server not ready: {$this->last_reply}";
            return false;
        }

        return true;
    }

    /**
     * Send SMTP command.
     *
     * @param string $command
     * @param string $args
     *
     * @return bool
     */
    protected function sendCommand($command, $args = '')
    {
        if (!$this->smtp_conn) {
            return false;
        }

        $this->edebug("{$command} {$args}", 1);

        fputs($this->smtp_conn, $command . ' ' . $args . "\r\n");

        $this->last_reply = $this->get_lines();

        $this->edebug("REPLY: {$this->last_reply}", 2);

        if (substr($this->last_reply, 0, 3) !== '250') {
            $this->error = "Command failed: {$this->last_reply}";
            return false;
        }

        return true;
    }

    /**
     * Send HELO/EHLO command.
     *
     * @param string $host
     *
     * @return bool
     */
    public function hello($host = '')
    {
        $hello = ($this->SMTPSecure === 'tls' || $this->SMTPSecure === 'ssl') ? 'EHLO' : 'EHLO';
        
        return $this->sendCommand($hello, $host ?: $this->Host);
    }

    /**
     * Authenticate.
     *
     * @param string $username
     * @param string $password
     * @param string $authtype
     *
     * @return bool
     */
    public function authenticate($username, $password, $authtype = null)
    {
        if (!$this->sendCommand('AUTH', 'LOGIN')) {
            return false;
        }

        if (!$this->sendCommand(base64_encode($username), '')) {
            return false;
        }

        if (!$this->sendCommand(base64_encode($password), '')) {
            return false;
        }

        return true;
    }

    /**
     * Send MAIL FROM command.
     *
     * @param string $from
     *
     * @return bool
     */
    public function mail($from)
    {
        return $this->sendCommand('MAIL', "FROM:<{$from}>");
    }

    /**
     * Send RCPT TO command.
     *
     * @param string $to
     *
     * @return bool
     */
    public function recipient($to)
    {
        return $this->sendCommand('RCPT', "TO:<{$to}>");
    }

    /**
     * Send DATA command.
     *
     * @param string $data
     *
     * @return bool
     */
    public function data($data)
    {
        if (!$this->sendCommand('DATA', '')) {
            return false;
        }

        fputs($this->smtp_conn, $data . "\r\n.\r\n");

        $this->last_reply = $this->get_lines();

        if (substr($this->last_reply, 0, 3) !== '250') {
            $this->error = "DATA command failed: {$this->last_reply}";
            return false;
        }

        return true;
    }

    /**
     * Send QUIT command.
     *
     * @return bool
     */
    public function quit()
    {
        return $this->sendCommand('QUIT', '');
    }

    /**
     * Close connection.
     *
     * @return bool
     */
    public function close()
    {
        if ($this->smtp_conn) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            return true;
        }
        return false;
    }

    /**
     * Get lines from server.
     *
     * @return string
     */
    protected function get_lines()
    {
        $data = '';
        while ($str = fgets($this->smtp_conn, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        return $data;
    }

    /**
     * Set error.
     *
     * @param string $msg
     */
    protected function setError($msg)
    {
        $this->error = $msg;
    }

    /**
     * Get error.
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set debug output.
     *
     * @param int $level
     */
    public function setDebug($level)
    {
        $this->Debug = $level;
    }

    /**
     * Set debug output handler.
     *
     * @param callable $handler
     */
    public function setDebugoutput($handler)
    {
        $this->Debugoutput = $handler;
    }

    /**
     * Set SMTP secure.
     *
     * @param string $secure
     */
    public function setSMTPSecure($secure)
    {
        $this->SMTPSecure = $secure;
    }

    /**
     * Set SMTP auto TLS.
     *
     * @param bool $auto
     */
    public function setSMTPAutoTLS($auto)
    {
        $this->SMTPAutoTLS = $auto;
    }

    /**
     * Set SMTP options.
     *
     * @param array $options
     */
    public function setSMTPOptions($options)
    {
        $this->SMTPOptions = $options;
    }

    /**
     * Set VERP.
     *
     * @param bool $enabled
     */
    public function setVerp($enabled)
    {
        $this->do_verp = $enabled;
    }

    /**
     * Set timeout.
     *
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->Timeout = $timeout;
    }

    /**
     * Debug output.
     *
     * @param string $str
     * @param int    $level
     */
    protected function edebug($str, $level = 0)
    {
        if ($level <= $this->Debug) {
            if (is_callable($this->Debugoutput)) {
                call_user_func($this->Debugoutput, $str);
            } else {
                echo $str . "\n";
            }
        }
    }
}
