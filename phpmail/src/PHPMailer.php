<?php
/**
 * PHPMailer - PHP email creation and transport class.
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
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.
 */

namespace PHPMailer\PHPMailer;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * PHPMailer - PHP email creation and transport class.
 *
 * @author Marcus Bointon <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski <jimjag@gmail.com>
 * @author Andy Prevost <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 */
class PHPMailer
{
    /**
     * Email priority.
     * Options: null (default), 1 = High, 3 = Normal, 5 = low.
     * When null, the header is not set at all.
     *
     * @var int|null
     */
    public $Priority;

    /**
     * The character set of the message.
     *
     * @var string
     */
    public $CharSet = 'iso-8859-1';

    /**
     * The Content-Type of the message.
     *
     * @var string
     */
    public $ContentType = 'text/plain';

    /**
     * The message encoding.
     * Options: '8bit', '7bit', 'binary', 'base64', 'quoted-printable'.
     *
     * @var string
     */
    public $Encoding = '8bit';

    /**
     * Holds the most recent mailer error message.
     *
     * @var string
     */
    public $ErrorInfo = '';

    /**
     * The From email address for the message.
     *
     * @var string
     */
    public $From = 'root@localhost';

    /**
     * The From name for the message.
     *
     * @var string
     */
    public $FromName = 'Root User';

    /**
     * The Sender email (Return-Path) of the message.
     * If not empty, will be sent via -f to sendmail or as 'MAIL FROM' in SMTP mode.
     *
     * @var string
     */
    public $Sender = '';

    /**
     * The Subject of the message.
     *
     * @var string
     */
    public $Subject = '';

    /**
     * The plain-text message body.
     *
     * @var string
     */
    public $Body = '';

    /**
     * The HTML message body.
     *
     * @var string
     */
    public $AltBody = '';

    /**
     * The word-wrapping breakpoint.
     *
     * @var int
     */
    public $WordWrap = 0;

    /**
     * The method to use for sending mail.
     * Options: 'mail', 'sendmail', 'smtp'.
     *
     * @var string
     */
    public $Mailer = 'mail';

    /**
     * The path to the sendmail program.
     *
     * @var string
     */
    public $Sendmail = '/usr/sbin/sendmail';

    /**
     * Whether to use VERP.
     *
     * @see http://en.wikipedia.org/wiki/Variable_envelope_return_path
     *
     * @var bool
     */
    public $do_verp = false;

    /**
     * Whether to validate email addresses.
     *
     * @var bool
     */
    public $ValidateAddress = true;

    /**
     * SMTP hostname.
     *
     * @var string
     */
    public $Host = 'localhost';

    /**
     * SMTP port number.
     *
     * @var int
     */
    public $Port = 25;

    /**
     * SMTP HELO/EHLO hostname.
     *
     * @var string
     */
    public $Helo = '';

    /**
     * SMTP authentication username.
     *
     * @var string
     */
    public $Username = '';

    /**
     * SMTP authentication password.
     *
     * @var string
     */
    public $Password = '';

    /**
     * SMTP authentication type.
     * Options: '', 'LOGIN', 'PLAIN', 'CRAM-MD5', 'DIGEST-MD5'.
     *
     * @var string
     */
    public $AuthType = '';

    /**
     * SMTP secure type.
     * Options: '', 'tls', 'ssl'.
     *
     * @var string
     */
    public $SMTPSecure = '';

    /**
     * Whether to enable SMTP debugging.
     *
     * @var bool
     */
    public $SMTPDebug = false;

    /**
     * Whether to keep SMTP connection open after each message.
     *
     * @var bool
     */
    public $SMTPKeepAlive = false;

    /**
     * Whether to use TLS.
     *
     * @var bool
     */
    public $SMTPAutoTLS = true;

    /**
     * The timeout for SMTP in seconds.
     *
     * @var int
     */
    public $Timeout = 10;

    /**
     * The SMTP options.
     *
     * @var array
     */
    public $SMTPOptions = [];

    /**
     * The DKIM selector.
     *
     * @var string
     */
    public $DKIM_selector = '';

    /**
     * The DKIM identity.
     *
     * @var string
     */
    public $DKIM_identity = '';

    /**
     * The DKIM passphrase.
     *
     * @var string
     */
    public $DKIM_passphrase = '';

    /**
     * The DKIM signing domain.
     *
     * @var string
     */
    public $DKIM_domain = '';

    /**
     * The DKIM private key file path.
     *
     * @var string
     */
    public $DKIM_private = '';

    /**
     * The DKIM extra headers.
     *
     * @var array
     */
    public $DKIM_extraHeaders = [];

    /**
     * The message action.
     *
     * @var string
     */
    public $action_function = '';

    /**
     * The XMailer header.
     *
     * @var string
     */
    public $XMailer = '';

    /**
     * The SMTP instance.
     *
     * @var SMTP
     */
    public $smtp;

    /**
     * The message recipients.
     *
     * @var array
     */
    protected $to = [];

    /**
     * The message CC recipients.
     *
     * @var array
     */
    protected $cc = [];

    /**
     * The message BCC recipients.
     *
     * @var array
     */
    protected $bcc = [];

    /**
     * The message Reply-To recipients.
     *
     * @var array
     */
    protected $ReplyTo = [];

    /**
     * The message attachments.
     *
     * @var array
     */
    protected $attachment = [];

    /**
     * The message custom headers.
     *
     * @var array
     */
    protected $CustomHeader = [];

    /**
     * The message MIME type.
     *
     * @var string
     */
    protected $message_type = '';

    /**
     * The message boundaries.
     *
     * @var array
     */
    protected $boundary = [];

    /**
     * The message languages.
     *
     * @var array
     */
    protected $language = [];

    /**
     * The message error count.
     *
     * @var int
     */
    protected $error_count = 0;

    /**
     * The message sign certificate file path.
     *
     * @var string
     */
    protected $sign_cert_file = '';

    /**
     * The message sign key file path.
     *
     * @var string
     */
    protected $sign_key_file = '';

    /**
     * The message sign key passphrase.
     *
     * @var string
     */
    protected $sign_key_pass = '';

    /**
     * The message exceptions.
     *
     * @var bool
     */
    protected $exceptions = false;

    /**
     * Unique ID used for caching.
     *
     * @var string
     */
    protected $uniqueid = '';

    /**
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
     */
    public function __construct($exceptions = false)
    {
        $this->exceptions = $exceptions;
    }

    /**
     * Send the message.
     *
     * @return bool
     */
    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $e) {
            $this->mailHeader = '';
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Prepare message before sending.
     *
     * @return bool
     */
    public function preSend()
    {
        try {
            if ($this->Mailer === 'smtp') {
                $this->smtp = new SMTP();
                $this->smtp->setTimeout($this->Timeout);
                $this->smtp->setDebug($this->SMTPDebug);
                $this->smtp->setDebugoutput($this->Debugoutput);
                $this->smtp->setSMTPSecure($this->SMTPSecure);
                $this->smtp->setSMTPAutoTLS($this->SMTPAutoTLS);
                $this->smtp->setSMTPOptions($this->SMTPOptions);
                $this->smtp->setVerp($this->do_verp);
            }

            if ((count($this->to) + count($this->cc) + count($this->bcc)) < 1) {
                throw new Exception($this->lang('provide_address'));
            }

            if (!empty($this->AltBody) && !empty($this->Body)) {
                $this->ContentType = 'multipart/alternative';
            }

            $this->message_type = $this->message_type();
            $this->boundary = $this->getBoundary($this->message_type, $this->CharSet);

            if (!$this->createHeader()) {
                return false;
            }

            if (!$this->createBody()) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Actually send the message.
     *
     * @return bool
     */
    protected function postSend()
    {
        try {
            switch ($this->Mailer) {
                case 'sendmail':
                    return $this->sendmailSend($this->Header, $this->Body);
                case 'smtp':
                    return $this->smtpSend($this->Header, $this->Body);
                case 'mail':
                    return $this->mailSend($this->Header, $this->Body);
                default:
                    return $this->mailSend($this->Header, $this->Body);
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * Set the From and FromName properties.
     *
     * @param string $address
     * @param string $name
     * @param bool   $auto   Whether to also set the Sender
     *
     * @return bool
     */
    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            $this->setError($this->lang('invalid_address') . ' (From)');
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto) {
            if (empty($this->Sender)) {
                $this->Sender = $address;
            }
        }
        return true;
    }

    /**
     * Add a "To" address.
     *
     * @param string $address
     * @param string $name
     *
     * @return bool
     */
    public function addAddress($address, $name = '')
    {
        return $this->addAnAddress('to', $address, $name);
    }

    /**
     * Add a "CC" address.
     *
     * @param string $address
     * @param string $name
     *
     * @return bool
     */
    public function addCC($address, $name = '')
    {
        return $this->addAnAddress('cc', $address, $name);
    }

    /**
     * Add a "BCC" address.
     *
     * @param string $address
     * @param string $name
     *
     * @return bool
     */
    public function addBCC($address, $name = '')
    {
        return $this->addAnAddress('bcc', $address, $name);
    }

    /**
     * Add a "Reply-To" address.
     *
     * @param string $address
     * @param string $name
     *
     * @return bool
     */
    public function addReplyTo($address, $name = '')
    {
        return $this->addAnAddress('Reply-To', $address, $name);
    }

    /**
     * Add an address to one of the recipient arrays.
     *
     * @param string $kind    One of 'to', 'cc', 'bcc', 'Reply-To'
     * @param string $address
     * @param string $name
     *
     * @return bool
     */
    protected function addAnAddress($kind, $address, $name = '')
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            $this->setError($this->lang('invalid_address') . " ($kind)");
            return false;
        }
        if ($kind !== 'Reply-To') {
            if (isset($this->$kind)) {
                $this->$kind[] = [$address, $name];
            }
        } else {
            $this->ReplyTo[$address] = [$address, $name];
        }
        return true;
    }

    /**
     * Set email format to HTML.
     *
     * @param bool $isHtml
     */
    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    /**
     * Set email format to plain text.
     */
    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    /**
     * Validate an email address.
     *
     * @param string $address
     * @param string $patternselect
     *
     * @return bool
     */
    public static function validateAddress($address, $patternselect = 'auto')
    {
        if (empty($address)) {
            return false;
        }
        if (strpos($address, "'") !== false || strpos($address, '"') !== false || strpos($address, '\\') !== false || strpos($address, '/') !== false) {
            return false;
        }
        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Set language.
     *
     * @param string $langcode
     * @param string $path
     *
     * @return bool
     */
    public function setLanguage($langcode = 'en', $path = '')
    {
        $this->language = [];
        return true;
    }

    /**
     * Get a language string.
     *
     * @param string $key
     *
     * @return string
     */
    protected function lang($key)
    {
        if (isset($this->language[$key])) {
            return $this->language[$key];
        }
        return $key;
    }

    /**
     * Set error message.
     *
     * @param string $msg
     */
    protected function setError($msg)
    {
        $this->ErrorInfo = $msg;
        $this->error_count++;
    }

    /**
     * Get message type.
     *
     * @return string
     */
    protected function message_type()
    {
        if (empty($this->AltBody)) {
            return 'plain';
        }
        return 'multipart/alternative';
    }

    /**
     * Get boundary.
     *
     * @param string $message_type
     * @param string $CharSet
     *
     * @return string
     */
    protected function getBoundary($message_type, $CharSet)
    {
        return 'b1_' . md5(uniqid(time()));
    }

    /**
     * Create message headers.
     *
     * @return bool
     */
    protected function createHeader()
    {
        $this->mailHeader = '';
        $this->Header = '';
        
        $this->Header .= 'Date: ' . $this->rfcDate() . $this->LE;
        $this->Header .= 'From: ' . $this->createHeaderLine('From', $this->From, $this->FromName) . $this->LE;
        
        if (!empty($this->Sender)) {
            $this->Header .= 'Sender: ' . $this->encodeHeader($this->Sender) . $this->LE;
        }
        
        if (count($this->to) > 0) {
            $this->Header .= 'To: ' . $this->addrAppend($this->to) . $this->LE;
        }
        
        if (count($this->cc) > 0) {
            $this->Header .= 'Cc: ' . $this->addrAppend($this->cc) . $this->LE;
        }
        
        if (count($this->ReplyTo) > 0) {
            $this->Header .= 'Reply-To: ' . $this->addrAppend($this->ReplyTo) . $this->LE;
        }
        
        if (!empty($this->Subject)) {
            $this->Header .= 'Subject: ' . $this->encodeHeader($this->Subject) . $this->LE;
        }
        
        $this->Header .= 'Message-ID: <' . $this->generateId() . '@' . $this->serverHostname() . '>' . $this->LE;
        $this->Header .= 'X-Mailer: PHPMailer ' . $this->Version . ' (https://github.com/PHPMailer/PHPMailer)' . $this->LE;
        
        if (!empty($this->ContentType)) {
            $this->Header .= 'MIME-Version: 1.0' . $this->LE;
            $this->Header .= 'Content-Type: ' . $this->ContentType . '; charset="' . $this->CharSet . '"' . $this->LE;
        }
        
        return true;
    }

    /**
     * Create message body.
     *
     * @return bool
     */
    protected function createBody()
    {
        $body = $this->Body;
        
        if ($this->ContentType === 'text/html' && empty($this->AltBody)) {
            $this->AltBody = $this->html2text($body);
        }
        
        $this->Body = $body;
        
        return true;
    }

    /**
     * Send via mail().
     *
     * @param string $header
     * @param string $body
     *
     * @return bool
     */
    protected function mailSend($header, $body)
    {
        $to = implode(',', array_column($this->to, 0));
        $params = null;
        
        if (!empty($this->Sender)) {
            $params = '-f' . $this->Sender;
        }
        
        $result = @mail($to, $this->Subject, $body, $header, $params);
        
        if (!$result) {
            throw new Exception($this->lang('instantiate'));
        }
        
        return true;
    }

    /**
     * Send via SMTP.
     *
     * @param string $header
     * @param string $body
     *
     * @return bool
     */
    protected function smtpSend($header, $body)
    {
        $this->smtp->connect($this->Host, $this->Port, $this->Timeout);
        
        if ($this->SMTPAuth) {
            $this->smtp->hello($this->Helo);
            $this->smtp->authenticate($this->Username, $this->Password, $this->AuthType);
        }
        
        $this->smtp->mail($this->Sender);
        
        foreach ($this->to as $to) {
            $this->smtp->recipient($to[0]);
        }
        
        foreach ($this->cc as $cc) {
            $this->smtp->recipient($cc[0]);
        }
        
        foreach ($this->bcc as $bcc) {
            $this->smtp->recipient($bcc[0]);
        }
        
        $this->smtp->data($header . $body);
        $this->smtp->quit();
        $this->smtp->close();
        
        return true;
    }

    /**
     * Send via sendmail.
     *
     * @param string $header
     * @param string $body
     *
     * @return bool
     */
    protected function sendmailSend($header, $body)
    {
        if (!@$this->mailSend($header, $body)) {
            throw new Exception($this->lang('instantiate'));
        }
        return true;
    }

    /**
     * Append addresses to string.
     *
     * @param array $addr
     *
     * @return string
     */
    protected function addrAppend($addr)
    {
        $addresses = [];
        foreach ($addr as $a) {
            $addresses[] = $this->addrFormat($a);
        }
        return implode(', ', $addresses);
    }

    /**
     * Format an address.
     *
     * @param array $addr
     *
     * @return string
     */
    protected function addrFormat($addr)
    {
        if (empty($addr[1])) {
            return $this->encodeHeader($addr[0]);
        }
        return $this->encodeHeader($addr[1]) . ' <' . $this->encodeHeader($addr[0]) . '>';
    }

    /**
     * Encode header.
     *
     * @param string $str
     *
     * @return string
     */
    protected function encodeHeader($str)
    {
        return $str;
    }

    /**
     * Create header line.
     *
     * @param string $name
     * @param string $value
     * @param string $charset
     *
     * @return string
     */
    protected function createHeaderLine($name, $value, $charset = null)
    {
        return $name . ': ' . $value;
    }

    /**
     * Get RFC date.
     *
     * @return string
     */
    protected function rfcDate()
    {
        return date('r');
    }

    /**
     * Generate unique ID.
     *
     * @return string
     */
    protected function generateId()
    {
        return md5(uniqid(time()));
    }

    /**
     * Get server hostname.
     *
     * @return string
     */
    protected function serverHostname()
    {
        return 'localhost';
    }

    /**
     * Convert HTML to plain text.
     *
     * @param string $html
     *
     * @return string
     */
    protected function html2text($html)
    {
        return strip_tags($html);
    }

    /**
     * PHPMailer version.
     *
     * @var string
     */
    const VERSION = '6.5.0';

    /**
     * Line ending.
     *
     * @var string
     */
    protected $LE = "\r\n";

    /**
     * Debug output.
     *
     * @var string
     */
    protected $Debugoutput = 'echo';
}
