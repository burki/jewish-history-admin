<?php
/*
 * PresentationService.php
 *
 * Methods to check/update sources and articles in the frontend
 *
 * (c) 2019 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2019-02-13 dbu
 *
 *
 */

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PresentationService
{
    static $PREFIX = 'jgo:';
    static $LANG_SETTINGS = [
        'deu' => [
            'locale' => 'de',
            'source' => 'quelle',
            'article' => 'article',
            'url' => 'https://juedische-geschichte-online.net',
        ],
        'eng' => [
            'url' => 'https://jewish-history-online.net',
            'locale' => 'en',
        ],
    ];
    static $PRESENTATION_ROOT_DIR = null;
    static $TEI_PATH = 'app/Resources/AppBundle/data/tei';

    var $dbconn;

    /**
     * e.g.jgo:article-18
     */
    static function buildArticleUid($id, $type)
    {
        return sprintf('%s%s-%d',
                       self::$PREFIX, $type, $id);
    }

    function __construct($dbconn, $options = [])
    {
        $this->dbconn = $dbconn;

        if (array_key_exists('lang_settings', $options)) {
            self::$LANG_SETTINGS = array_replace_recursive(self::$LANG_SETTINGS, $options['lang_settings']);
        }

        if (is_null(self::$PRESENTATION_ROOT_DIR)) {
            // TODO: check options
            $presentation = realpath(INC_PATH . '/../../presentation'); // guess from default structure
            if (false !== $presentation) {
                self::$PRESENTATION_ROOT_DIR = $presentation;
            }
        }
    }

    function lookupArticleInfoFromFname($fname)
    {
        $info = null;

        if (preg_match('/(.*)_final\.xml$/i', $fname, $matches)) {
            // might be a candidate
            $parts = array_reverse(explode('_', $matches[1]));

            if (count($parts) >= 2 && count($parts) <= 4) {
                if (2 == count($parts)) {
                    // german source
                    $info = [
                        'type' => 'source',
                        'lang' => 'deu',
                    ];
                }
                else if (ucfirst($parts[0]) == 'Interpretation') {
                    if (3 == count($parts)) {
                        // german interpretation
                        $info = [
                            'type' => 'article',
                            'lang' => 'deu',
                        ];
                    }
                }
                else if (3 == count($parts) && 'Engl' == ucfirst($parts[0])) {
                    // english source
                    $info = [
                        'type' => 'source',
                        'lang' => 'eng',
                    ];
                }
                else if (4 == count($parts) && 'Engl' == ucfirst($parts[0]) && ucfirst($parts[1]) == 'Interpretation') {
                    // english interpretation
                    $info = [
                        'type' => 'article',
                        'lang' => 'eng',
                    ];
                }
            }
        }

        return is_null($info) ? false : $info;
    }

    function lookupArticle($id, $fname)
    {
        $info = $this->lookupArticleInfoFromFname($fname);
        if (false === $info) {
            return false; // doesn't look like a valid source or article
        }

        $uid = self::buildArticleUid($id, $info['type']);
        $querystr = sprintf("SELECT COUNT(*) AS how_many"
                            . " FROM article"
                            . " WHERE uid='%s' AND language='%s' AND status <> -1",
                            $uid, $info['lang']);
        $this->dbconn->query($querystr);
        if ($this->dbconn->next_record()) {
            if (1 == $this->dbconn->Record['how_many']) {
                $info['uid'] = $uid;

                return $info;
            }
        }

        return false;
    }

    function buildPresentationUrl($type, $uid, $lang)
    {
        $urlParts = [ self::$LANG_SETTINGS[$lang]['url'] ];
        $urlParts[] = array_key_exists($type, self::$LANG_SETTINGS[$lang])
            ? self::$LANG_SETTINGS[$lang][$type] : $type;
        $urlParts[] = $uid;

        return implode('/', $urlParts);
    }

    function buildTeiFname($type, $uid, $lang)
    {
        if (!array_key_exists($lang, self::$LANG_SETTINGS)) {
            return false;
        }

        if (!preg_match('/(source|article)\-(\d+)$/', $uid, $matches)) {
            return false;
        }

        $fnameParts = [
            self::$PRESENTATION_ROOT_DIR,
            self::$TEI_PATH,
            sprintf('%s-%05d.%s.tei',
                    $matches[1], $matches[2], self::$LANG_SETTINGS[$lang]['locale']),
        ];

        return implode('/', $fnameParts);
    }

    function allowRefresh($fnameUpload, $fnamePresentation)
    {
        if (!file_exists($fnameUpload) || !is_readable($fnameUpload)) {
            return false;
        }

        if (!is_writable(dirname($fnamePresentation))) {
            return false;
        }

        if (!file_exists($fnamePresentation)) {
            return true; // doesn't exist yet, so we don't overwrite newer version
        }

        $modifiedUpload = filemtime($fnameUpload);
        $modifiedPresentation = filemtime($fnamePresentation);

        if ($modifiedPresentation > $modifiedUpload) {
            // if they are identical, we allow refresh but don't need to copy
            if (md5_file($fnameUpload) == md5_file($fnamePresentation)) {
                return 1;
            }

            // version on presentation is newer, so don't overwrite
            return 0;
        }

        return true;
    }

    function refreshFile($fnameUpload, $fnamePresentation)
    {
        return copy($fnameUpload, $fnamePresentation);
    }

    function buildRefreshCommand($type, $uid, $lang)
    {
        $phpBinaryFinder = new PhpExecutableFinder();

        $phpBinaryPath = $phpBinaryFinder->find();

        if (false === $phpBinaryPath) {
            return false;
        }

        return [
            $phpBinaryPath,
            sprintf('%s/bin/console',
                    self::$PRESENTATION_ROOT_DIR),
            'article:refresh',
            $this->buildTeiFname($type, $uid, $lang),
        ];
    }

    function runRefreshCommand($type, $uid, $lang)
    {
        $process = new Process($this->buildRefreshCommand($type, $uid, $lang));
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            return false;
        }

        return $process->getOutput();
    }
}
