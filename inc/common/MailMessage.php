<?php

/*
 * MailMessage.php
 *
 * lightweight wrapper around SymfonyMailer
 *
 * (c) 2007-2026 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2026-08-22 dbu
 *
 * Changes:
 *
 */

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class MailerFactory
{
    var $config;
    var $mailer = null;
    var $transport = null;

    function __construct($config)
    {
        $this->config = $config;

        $transport = null;

        if (PHP_OS == 'WINNT' || defined('SMTP_HOST')) {
            if (!defined('SMTP_HOST')) {
                throw new Exception('MailerFactory::__construct: You have to define SMTP_HOST on Windows Systems');
                ;
            }

            $dsn = 'smtp://';
            if (defined('SMTP_USERNAME')) {
                $dsn .= rawurlencode(SMTP_USERNAME);
                if (defined('SMTP_PASSWORD')) {
                    $dsn .= ':' . rawurlencode(SMTP_PASSWORD);
                }
                $dsn .= '@';
            }
            $dsn .= SMTP_HOST;
            if (defined('SMTP_PORT')) {
                $dsn .= ':' . SMTP_PORT;
            }
            if (defined('SMTP_ENCRYPTION')) {
                $dsn .= '?encryption=' . rawurlencode(SMTP_ENCRYPTION);
            }
            $transport = Transport::fromDsn($dsn);
        }

        if (is_null($transport)) {
            $transport = Transport::fromDsn('native://default');
        }

        // Create the Mailer using your created Transport
        $this->mailer = new Mailer($this->transport = $transport);
    }

    function getConfig()
    {
        return $this->config;
    }

    function getTransport()
    {
        return $this->transport;
    }

    function getInstance()
    {
        return $this->mailer;
    }
}

class MailMessage
{
    private static $mailer = null;
    public static $mailer_config = [];

    public $message;
    public $recipients;
    public $from;
    public $line_width = -1; // uses format=flowed
    public $blocked = [];  // addresses that are blocked by white-list

    private static function getMailer()
    {
        if (!isset(self::$mailer)) {
            $mailer_factory = new MailerFactory(self::$mailer_config);
            self::$mailer = $mailer_factory->getInstance();
        }

        return self::$mailer;
    }

    public static function instantiateAddress(string $address, string $name = '')
    {
        return new Address($address, $name);
    }

    public function __construct($subject, $body_plain = '')
    {
        $this->message = (new Email())
            ->subject($subject);

        if (!empty($body_plain)) {
            $this->message->text($body_plain);

            if ($this->line_width > 0) {
                $this->message->getHeaders()
                    ->setMaxLineLength($this->line_width + 1); // CR counts as well
            }
        }
    }

    public function buildAddress($email, $name)
    {
        return new Address($email, $name);
    }

    public function addTo($address)
    {
        return $this->message->addTo($address);
    }

    public function addCc($address)
    {
        return $this->message->addCc($address);
    }

    public function addBcc($address)
    {
        return $this->message->addBcc($address);
    }

    public function removeTo($address)
    {
        $remaining = array_filter(
            $this->message->getTo(),
            static fn(Address $recipient) => $recipient->getAddress() !== (string) $address
        );

        return $this->message->to(...array_values($remaining));
    }

    public function addToBlocked($address)
    {
        if (!isset($this->blocked)) {
            $this->blocked = [];
        }

        $this->blocked[] = $address instanceof Address ? $address : new Address($address);

        return $this->blocked[count($this->blocked) - 1];
    }

    public function setFrom($address)
    {
        if (is_array($address)) {
            $addresses = [];
            foreach ($address as $email => $name) {
                $addresses[] = is_int($email) ? $name : new Address($email, $name);
            }

            $this->message->from(...$addresses);

            return;
        }

        $this->message->from($address);
    }

    public function setReplyTo($address)
    {
        $this->message->replyTo($address);
    }

    public function setHeader($name, $value)
    {
        $headers = $this->message->getHeaders();
        if (isset($headers)) {
            return $headers->addTextHeader($name, $value);
        }
    }

    public function setPlain($body_plain)
    {
        $this->message->text($body_plain)
            // ->setMaxLineLength($this->line_width + 1)
        ;
    }

    public function setHtml($body_html)
    {
        $this->message->html($body_html);
    }

    public function attachPlain($body_plain)
    {
        $this->message->attach($body_plain, null, 'text/plain;charset=utf-8')
            // ->setMaxLineLength($this->line_width + 1)
        ;
    }

    public function attachHtml($body_html)
    {
        $this->message->attach($body_html, null, 'text/html;utf-8');
    }

    public function attach($body, ?string $contentType = null)
    {
        if ($body instanceof DataPart) {
            return $this->message->attachPart($body);
        }

        return $this->message->attach($body, null, $contentType);
    }

    public function attachFromPath(string $path, ?string $contentType = null)
    {
        return $this->message->attachFromPath($path, null, $contentType);
    }

    public function embed($child, $id = null)
    {
        return $this->message->addPart($child->asInline());
    }

    public function setMaxLineLength($len)
    {
        return $this->message->getHeaders()->setMaxLineLength($len);
    }

    public function printOnly($recipient_list = null, $comment = null)
    {
        if ($recipient_list == null) {
            $recipient_list = $this->message->getTo();
        }

        $body = $this->message->toString();

        $recipients = array_map(
            static fn($recipient) => $recipient instanceof Address ? $recipient->toString() : (string) $recipient,
            is_array($recipient_list) ? array_values($recipient_list) : []
        );

        if ($comment) {
            echo "$comment <br/>";
        }

        echo '<p>Sending <tt>'
           . htmlspecialchars($this->message->getSubject())
           . '</tt> to <tt>' . implode(', ', $recipients) . '</tt>'
           . '<tt><pre>' . htmlspecialchars($body) . '</pre></tt></p>';

        return count($recipients);
    }

    public function send()
    {
        if (!defined('MAIL_SEND') || !MAIL_SEND) {
            $count = $this->printOnly();

            return $count;
        }

        if (isset($MAIL_WHITELIST) && is_array($MAIL_WHITELIST)) {
            foreach ($this->message->getTo() as $recipient) {
                $to = $recipient->getAddress();
                $matched = 0;
                foreach ($GLOBALS['MAIL_WHITELIST'] as $exp) {
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

        $mailer = self::getMailer();

        try {
            $mailer->send($this->message, $this->recipients, $this->message->getFrom());
            ++$sent;
        }
        catch (\Exception $e) {
            var_dump($e->getMessage());
        }

        return $sent;
    }

    static function mail($to, $subject, $message, $from = null)
    {
        $mail = new MailMessage($subject, $message);
        $mail->addTo($to);
        if (isset($from)) {
            $mail->setFrom($from);
        }

        return $mail->send(); // number sent
    }
}
