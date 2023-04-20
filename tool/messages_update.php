<?php
/*
 * messages_update.php
 *
 * Update $GETTEXT_MESSAGES
 *
 * (c) 2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-04-20 dbu
 *
 * Changes:
 *
 */

define('INC_PATH', __DIR__ . '/../inc/');
include_once INC_PATH . 'local.inc.php';
include_once INC_PATH . 'sitesettings.inc.php';

use Spatie\SimpleExcel\SimpleExcelReader;

class MessagesApplication extends Zend_Application
{
    function run() {
        $pathToFile = INC_PATH . '/messages/messages.xlsx';

        $GETTEXT_MESSAGES = [];

        SimpleExcelReader::create($pathToFile)->getRows()
           ->each(function($rowProperties) use (& $GETTEXT_MESSAGES) {
                // process the row
                $src = null;

                foreach ($rowProperties as $locale => $val) {
                    if ('' == $locale) {
                        $src = $val;
                    }
                    else if (!is_null($src) && !empty($val)) {
                        if (!array_key_exists($locale, $GETTEXT_MESSAGES)) {
                            $GETTEXT_MESSAGES[$locale] = [];
                        }

                        $GETTEXT_MESSAGES[$locale][$src] = $val;
                    }
                }
            });

        foreach ($GETTEXT_MESSAGES as $locale => $messages) {
            if (empty($messages)) {
                continue;
            }

            // alternative: $messages_str = var_export($messages);
            $dumper = new \Nette\PhpGenerator\Dumper;
            $messages_str = $dumper->dump($messages);

            $content = <<<EOT
\$GETTEXT_MESSAGES = {$messages_str};
EOT;

            file_put_contents(INC_PATH . '/messages/' . $locale . '.inc.php', '<' . '?php' . "\n" . $content);
        }
    }
}

$application = new MessagesApplication('', []);
$application->bootstrap();
$application->run();
