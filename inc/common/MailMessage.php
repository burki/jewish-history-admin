<?php
/*
 * MailMessage.php
 *
 * lightweight wrapper around Swift-Mailer 4.x
 *
 * (c) 2007-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-03-11 dbu
 *
 * Changes:
 *
 */

require_once VENDOR_PATH . '/swiftmailer/swiftmailer/lib/swift_init.php';

class MailerFactory
{
    var $mailer = NULL;

    function __construct ($config) {
        $this->config = $config;

        if (PHP_OS == 'WINNT' || defined('SMTP_HOST')) {
            if (!defined('SMTP_HOST')) {
                throw new Exception('MailerFactory::__construct: You have to define SMTP_HOST on Windows Systems');;
            }
            $transport = Swift_SmtpTransport::newInstance(SMTP_HOST);
            if (defined('SMTP_USERNAME')) {
                $transport->setUsername(SMTP_USERNAME);
                if (defined('SMTP_PASSWORD')) {
                    $transport->setPassword(SMTP_PASSWORD);
                }
            }
            if (defined('SMTP_PORT')) {
                $transport->setPort(SMTP_PORT);
            }
            if (defined('SMTP_ENCRYPTION')) {
                $transport->setEncryption(SMTP_ENCRYPTION);
            }
        }
        else {
          $transport = Swift_MailTransport::newInstance();
        }

        //Create the Mailer using your created Transport
        $this->mailer = Swift_Mailer::newInstance($transport);
    }

    function getConfig () {
        return $this->config;
    }

    function getInstance () {
        return $this->mailer;
    }
}

class MailMessage
{
    private static $swift = NULL;
    public static $mailer_config = array();

    public $message;
    public $recipients;
    public $from;
    public $line_width = -1; // uses format=flowed

    private static function getSwift () {
        if (!isset(self::$swift)) {
            $mailer_factory = new MailerFactory(self::$mailer_config);
            self::$swift = $mailer_factory->getInstance();
        }
        return self::$swift;
    }

    public function __construct ($subject, $body_plain = '') {
        $this->message = Swift_Message::newInstance()
            ->setSubject($subject);

        if (!empty($body_plain)) {
            $this->message->setBody($body_plain, 'text/plain', 'utf-8');
            if ($this->line_width > 0) {
                $this->message->setMaxLineLength($this->line_width + 1); // CR counts as well
            }
        }
    }

    public function buildAddress ($email, $name) {
        return new Swift_Address($email, $name);
    }

    public function addTo ($address) {
        return $this->message->addTo($address);
    }

    public function addCc ($address) {
        return $this->message->addCc($address);
    }

    public function addBcc ($address) {
        return $this->message->addBcc($address);
    }

    public function removeTo ($address) {
        return $this->message->removeTo($address);
    }

    public function addToBlocked ($address) {
        if (!isset($this->blocked))
            $this->blocked = new Swift_RecipientList();
        return $this->blocked->addTo($address);
    }

    public function setFrom ($address) {
        $this->message->setFrom($address);
    }

    public function setReplyTo ($address) {
        $this->message->setReplyTo($address);
    }

    public function setHeader ($name, $value) {
        $headers = $this->message->getHeaders();
        if (isset($headers))
            return $headers->addTextHeader($name, $value);
    }

    public function attachPlain ($body_plain) {
        $this->message->addPart($body_plain, 'text/plain', 'utf-8')
            ->setMaxLineLength($this->line_width + 1);
    }

    public function attachHtml ($body_html) {
        $this->message->addPart($body_html, 'text/html', 'utf-8');
    }

    public function attach ($child, $id = NULL) {
        return $this->message->attach($child, $id);
    }

    public function embed ($child, $id = NULL) {
        return $this->message->embed($child, $id);
    }

    public function setMaxLineLength ($len) {
        return $this->message->setMaxLineLength($len);
    }

    public function printOnly ($recipient_list = NULL, $comment = NULL) {
        if ($recipient_list == NULL) {
            $recipient_list = $this->message->getTo();
        }

        $body = '';

        if ('multipart/alternative' == $this->message->getContentType()) {
            foreach ($this->message->getChildren() as $id => $child) {
                if ('text/plain' == $child->getContentType()) {
                    $body = $child->getBody();
                    break;
                }
            }
        }
        else {
            $body = $this->message->getBody();
        }

        $recipients = array_keys($recipient_list);
        if ($comment) {
            echo "$comment <br/>";
        }

        echo '<p>Sending <tt>'
           . htmlspecialchars($this->message->getSubject())
           . '</tt> to <tt>' . implode(', ', $recipients) . '</tt>'
           . '<tt><pre>' . htmlspecialchars($body) . '</pre></tt></p>';

        return count($recipients);
    }

    public function send () {
        global $MAIL_WHITELIST;
        if (!defined('MAIL_SEND') || !MAIL_SEND) {
            $count = $this->printOnly();
            return $count;
        }

        if (isset($MAIL_WHITELIST) && is_array($MAIL_WHITELIST)) {
            foreach (array_keys($this->message->getTo()) as $to) {
                $matched = 0;
                foreach ($MAIL_WHITELIST as $exp) {
                    if (preg_match($exp, $to) > 0) {
                        $matched = 1;
                        break;
                    }
                }
                if (0 == $matched) {
                    $this->removeTo($to);
                    $this->addToBlocked($to);
                }
            }
        }

        $sent = 0;
        if (isset($this->blocked) && count($this->blocked) > 0) {
            $sent = $this->printOnly($this->blocked, 'Blocked by white-list');
            $addresses = $this->message->getTo();
            if (empty($addresses)) {
                // everything on white-list
                return $sent;
            }
        }

        $swift_conn = self::getSwift();

        try {
            $sent += $swift_conn->send($this->message, $this->recipients, $this->message->getFrom());
        }
        catch (Swift_TransportException $e) {
            var_dump($e->getMessage());
        }

        return $sent;
    }

    static function mail ($to, $subject, $message, $from = NULL) {
        $mail = new MailMessage($subject, $message);
        $mail->addTo($to);
        if (isset($from)) {
            $mail->setFrom($from);
        }
        return $mail->send(); // number sent
    }

}
