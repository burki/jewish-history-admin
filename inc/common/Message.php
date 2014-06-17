<?php

/*
 * Message.php
 *
 * Build multilingual (mail) messages with placeholders
 *
 * (c) 2008-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-06-06 dbu
 *
 * Changes:
 *
 */

class Message
{
    static $SUBJECTS = array(
        'pwd_recover' => 'Set new password',
        'register_confirm' => 'Your registration',
    );

    var $page;
    var $type;
    var $lang = 'en_US';
    var $id_to;
    var $options = array();

    function __construct ($page, $type, $id_to = NULL, $options = array(), $lang = NULL) {
        $this->page = $page;
        $this->type = $type;
        $this->id_to = $id_to;
        $this->lang = isset($lang) ? $lang : $page->lang();
        $this->options = $options;
    }

    function buildSubject ($subject = FALSE) {
        if (!$subject) {
            if (array_key_exists($this->type, self::$SUBJECTS)) {
                $subject = self::$SUBJECTS[$this->type];
            }
            else {
                $subject = 'A message from ' . $this->page->site_description['title'];
            }
        }

        return Page::gettext($subject, $this->lang);
    }

    function buildBody () {
        $body = FALSE;
        $subject = FALSE;

        $languages = array($this->lang);
        if ('en_US' != $this->lang) {
            $languages[] = 'en_US'; // add fallback language
        }

        foreach ($languages as $lang) {
            $fname_template = INC_PATH . '/messages/' . $this->type . '.' . $lang . '.txt';
            $lines = @file($fname_template);
            if (FALSE !== $lines) {
                break;
            }
        }

        if (FALSE === $lines) {
            return FALSE;
        }

        // The first line may contain the Subject
        if (preg_match('/^Subject:\s*(.*)/', $lines[0], $matches)) {
            $subject = $matches[1];
            array_shift($lines);
        }
        // $template = implode(PHP_EOL, $lines);
        $template = implode('', $lines);


        // fill in template
        $body = preg_replace_callback('|\%([a-z_0-9]+)\%|',
                                    array($this->messagePlaceholder, 'replace'),
                                    $template);

        return array($subject, $body);
    }

    function build () {
        $this->messagePlaceholder = MessagePlaceholder::getInstance($this);

        list($subject, $body) = $this->buildBody();
        if (isset($this->options['subject'])) {
            // put this after buildBody, so specific subject can override generic one
            $subject = $this->options['subject'];
        }
        $subject = $this->buildSubject($subject);

        return array($subject, $body);
    }

    function buildFrom () {
        if (isset($this->options['from'])) {
            return $this->options['from'];
        }

        global $MAIL_SETTINGS;
        $from = $MAIL_SETTINGS['from'];
        if (isset($MAIL_SETTINGS['from_name'])) {
            $from = array($from => $MAIL_SETTINGS['from_name']);
        }

        return $from;
    }

    function fetchUser ($id) {
        static $_users = array();

        if (!isset($_users[$id])) {
            $dbconn = new DB();

            $querystr = sprintf("SELECT id, email, firstname AS fname, lastname AS name, sex AS sex FROM User WHERE id=%d AND status >= 0", $id);

            $dbconn->query($querystr);

            if ($dbconn->next_record()) {
                $_users[$id] = $dbconn->Record;
            }
        }

        return isset($_users[$id]) ? $_users[$id] : NULL;
    }

    function buildRecipients () {
        $recipients = array();

        if (isset($this->options['recipients'])) {
            $recipients = $this->options['recipients'];
        }

        if (!isset($recipients['to']) && isset($this->id_to)) {
            $user = $this->fetchUser($this->id_to);
            if (isset($user)) {
                $recipients['to'] = $user['email'];
            }
        }

        return $recipients;
    }

    function buildMultipartMessage ($subject, $body) {
        // generate html and plain-text version
        $display = new PageDisplayBase($this->page);
        $display->charset = 'utf-8';
        $body_plain = $display->convertToPlain($body);
        $body_html = $display->formatParagraphs($body);
        $body_html = <<<EOT
<html>
<head><meta http-equiv="content-type" content="text/html; charset={$display->charset}" /></head>
<body>$body_html
</body>
</html>
EOT;

        $mail = new MailMessage($subject);
        $mail->attachPlain($body_plain);
        $mail->attachHtml($body_html);

        return $mail;
    }

    function send () {
        require_once INC_PATH . 'common/MailMessage.php';

        list($subject, $body) = $this->build();
        if (FALSE === $body) {
            return FALSE;
        }

        $recipients = $this->buildRecipients();

        try {
            if (!isset($this->options['html']) || $this->options['html']) {
                // generate html and plain-text version
                $mail = $this->buildMultipartMessage($subject, $body, $recipients);
            }
            else {
                // plain text only
                $mail = new MailMessage($subject, $body);
            }

            $mail->setFrom($this->buildFrom());
            $mail->addTo($recipients['to']);
            if (isset($recipients['cc']))
                $mail->addCc($recipients['cc']);
            if (isset($recipients['bcc']))
                $mail->addBcc($recipients['bcc']);
            if (isset($this->options['reply-to']))
                $mail->setReplyTo($this->options['reply-to']);

            if (isset($this->options['attachements']) && is_array($this->options['attachements'])) {
                foreach ($this->options['attachements'] as $a) {
                    //Create the attachment with your data
                    $attachment = Swift_Attachment::newInstance($a['data'], $a['filename'], $a['mimetype']);
                    //echo "attaching: " . $a['filename'] . " " . $a['mimetype'] . "<br />";
                    //Attach it to the message
                    $mail->attach($attachment);
                }
            }
            return $mail->send();
        }
        catch (Exception $e) {
            // e.g. Swift_RfcComplianceException: Address in mailbox given [zorro1@freesurf.ch> ] does not comply with RFC 2822
            // TODO: somehow log/return the error
        }
        return FALSE;
    }
}

class StyledMessage extends Message
{
    var $title;

    function buildMultipartMessage ($subject, $body, $recipients = array()) {
        // generate html and plain-text version

        $display = new PageDisplayBase($this->page);
        $display->charset = 'utf-8';
        $body_plain = $display->convertToPlain($body);
        $body_html = $display->formatParagraphs($body);

        $title = !empty($this->title) ? $this->title : $this->page->site_description['title'];

        $title_html = $display->formatText($title);

        $footer = Page::gettext('We sent this email to you at %s about your account registered on <a href="%s">%s</a>.', $this->lang);

        $body_html = '<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8" /></head>
<body bgcolor="#FFFFFF" marginwidth="4" marginheight="4" leftmargin="4"
topmargin="4">'
                   . '<table cellspacing="4" cellpadding="4" border="0">'
                   . '<tr><td><font face="Arial,Helvetica,sans-serif" size="4" color="#b10c17">'. $title_html . '</font></td></tr>'
                   . '<tr><td bgcolor="#FFFFFF"><font face="Arial,Helvetica,sans-serif">'
                   . $body_html
                   . '</font></td><tr>'
                   . (!empty($recipients['to'])
                      ? sprintf('<tr><td align="center"><font face="Verdana,Arial,sans-serif" color="#666" style="font-size:xx-small;">%s</font></td></tr>',
                                sprintf($footer,
                                        $recipients['to'],
                                        $this->page->buildLinkFull(array()),
                                        $this->page->site_description['title']
                                        ))
                      : '')
                   . '</table>'
                   . '</body></html>';

        $mail = new MailMessage($subject);
        $mail->attachPlain($body_plain);

        $logo = 'media/logo.jpg';
        $replace = array('%logo%' => $this->page->BASE_URL . $logo);

        /*
        try {
          $replace['%logo%'] = $mail->embed(Swift_Image::fromPath($this->page->BASE_DIR . $logo));
        }
        catch (Exception $e) {
            ; // ignore - url will be embedded
        } */
        foreach ($replace as $key => $value)
            $body_html = preg_replace('/' . preg_quote($key, '/') . '/', $value, $body_html);

        $mail->attachHtml($body_html);

        return $mail;
    }

}

class MessagePlaceholder
{
    var $message;

    static function getInstance ($message) {
        switch ($message->lang) {
            case 'de_DE':
            case 'de_CH':
                return new MessagePlaceholderGerman($message);
                break;
            default:
                return new MessagePlaceholder ($message);
                break;
        }
    }

    protected function __construct ($message) {
        $this->message = $message;
    }

    protected function fetchUser ($id) {
        return $this->message->fetchUser($id);
    }

    function replace ($matches) {
        $ret = '';
        switch ($matches[1]) {
            case 'salutation_name':
                if (isset($this->message->id_to)) {
                    $user = $this->fetchUser($this->message->id_to);
                    if (isset($user) && !empty($user['fname'])) {
                        $ret = Page::gettext('Hello', $this->message->lang)
                             . ' ' . $user['fname'];
                    }
                }
                if (empty($ret))
                    $ret = Page::gettext('Dear user', $this->message->lang);
                /* var_dump($ret);
                exit; */
                break;
            case 'your_account_linked':
                $ret = '[' . $this->message->page->buildLinkFull(array('pn' => 'account')) . ' your account]';
                break;
            case 'site':
                $ret = $this->message->page->site_description['title'];
                break;
            case 'signature':
                $ret = Page::gettext('Best regards', $this->message->lang)
                     . "\n\n" . $this->message->page->site_description['title'];
                break;
            default:
                if (isset($this->message->options['replace'])
                    && array_key_exists($matches[1], $this->message->options['replace'])) {
                    $ret = $this->message->options['replace'][$matches[1]];
                }
        }

        return $ret;
    }
}

class MessagePlaceholderGerman extends MessagePlaceholder
{
    function replace ($matches) {
        $ret = '';
        switch ($matches[1]) {
            case 'salutation_name':
              if (isset($this->message->id_to)) {
                $user = $this->fetchUser($this->message->id_to);
                if (isset($user)) {
                    $ret = ('F' == $user['sex'] ? 'Sehr geehrte Frau' : 'Sehr geehrter Herr')
                         . ' ' . $user['name'];
                    break;
                }
              }

            default:
                $ret = parent::replace($matches);
        }

        return $ret;
    }
}
