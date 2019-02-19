<?php
/*
 * RsyncService.php
 *
 * Methods to fetch files from remote
 *
 * (c) 2019 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2019-02-19 dbu
 *
 *
 */

use AFM\Rsync\Rsync;
use AFM\Rsync\Command;

class RsyncService
{
    static function parseList($res)
    {
        $lines = preg_split('/$\R?^/m', $res);

        $utc = new DateTimeZone('Europe/Berlin'); // Hidrive doesn't seem to report in UTC

        $files = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line, 5);
            if (count($parts) < 5) {
                continue;
            }

            $fname = $parts[4];
            if (in_array($fname, [ '.', '..' ])) {
                continue;
            }

            $date = \DateTime::createFromFormat('Y/m/d H:i:s', $parts[2] . ' ' . $parts[3], $utc);
            $date->setTimezone($utc);

            $files[$fname] = [
                'size' => (int)str_replace(',', '', $parts[1]),
                'privs' => $parts[0],
                'mtime' => $date->getTimeStamp(),
            ];
        }

        return $files;
    }

    function __construct($options = [])
    {
        $this->options = $options;

        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            $this->options['ssh_config']['private_key'] = str_replace('\\', '/', $this->options['ssh_config']['private_key']);
        }
    }

    function buildRemote($path)
    {
        $sshConfig = & $this->options['ssh_config'];

        return $sshConfig['username'] . '@' . $sshConfig['host'] . ':' . $path;
    }

    function buildCommand($origin, $target, $argument = null)
    {
        $rsync = new Rsync($this->options['rsync_config']);

        $command = $rsync->getCommand($origin, $target);

        if (!is_null($argument)) {
            $command->addArgument($argument);
        }

        // manually add corrected version, doesn't work with keys with spaces!
        $sshConfig = & $this->options['ssh_config'];
        $ssh = new Command($sshConfig['executable']);
        $ssh->addArgument('i', $sshConfig['private_key']);
        $ssh->addArgument('o', 'StrictHostKeyChecking=no');
        $ssh->addArgument('o', 'UserKnownHostsFile=/dev/null');
        $ssh->addArgument('o', 'LogLevel=ERROR'); // https://superuser.com/a/1328919

        $command->addArgument('e', str_replace("'", '', (string)$ssh->getCommand()));

        return $command;
    }

    function executeCommand($command)
    {
        $cmdString = (string)$command;

        $res = `$cmdString`;

        return $res;
    }

    function listRemote($path)
    {
        $command = $this->buildCommand('', $this->buildRemote($path), 'list-only');

        $res = $this->executeCommand($command);

        return self::parseList($res);
    }

    function sync($origin, $target)
    {
        $command = $this->buildCommand($origin, $target);

        exec((string)$command, $output, $retval);

        return $retval;
    }

    function fetchFromRemote($remote, $local)
    {
        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            // we have to replace leading drive names and replace backslash to make cygwin-rsync happy
            $local = preg_replace('/^([A-Z])\:/', '/cygdrive/\1', $local);
            $local = str_replace('\\', '/', $local);
        }

        return $this->sync($this->buildRemote($remote), $local);
    }
}
