<?php

namespace ArturDoruch\Tool\MySqlDumper;

/**
 * Dumps and restores mysql database.
 *
 * @author Artur Doruch <arturdoruch@interia.pl>
 */
class MySqlDumper
{
    /**
     * Database access data as string
     *
     * @var string
     */
    private $dbAccess;

    /**
     * Database access data
     *
     * @var array
     */
    private $dbConfig;

    /**
     * @var string
     */
    private $mysqlDir;

    /**
     * @var string
     */
    private $bzip2Dir;

    /**
     * @var MySqlBackup
     */
    private $backup;

    /**
     * @param string $host           The database host
     * @param string $name           The database name
     * @param string $user           The database user name
     * @param string $pass           The database password
     * @param string $backupDir      The full path to backup directory
     * @param bool|string $bzip2Path If you want to compress dumped sql file with bzip2 compressor pass:
     *                               - on windows the path to bzip compressor directory,
     *                               - on linux and other OS true value.
     */
    public function __construct($host, $name, $user, $pass, $backupDir, $bzip2Path = false)
    {
        $this->backup = new MySqlBackup($backupDir);
        $this->dbConfig = array(
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
        );

        $this->setMySqlData();
        $this->setBzip2Dir($bzip2Path);
    }

    /**
     * @return MySqlBackup
     */
    public function getBackup()
    {
        return $this->backup;
    }

    /**
     * Dumps mysql database into file.
     *
     * @param bool $optimizeTable         If true optimizes all database tables before dump.
     * @param callable $fileNameFormatter Formats the backup filename. The callback receives two arguments
     *                                    in following order: $host - database host name, $name - database name.
     *                                    The callback must return filename without extension.
     *
     * @return string Dumped database filename.
     * @throws \RuntimeException
     */
    public function dump($optimizeTable = true, callable $fileNameFormatter = null)
    {
        if ($optimizeTable) {
            $this->optimizeTable();
        }

        $fileName = $this->prepareFileName($fileNameFormatter);
        $command = $this->prepareMySqlCommand('mysqldump');

        if ($this->bzip2Dir !== null) {
            $fileName .= '.bz2';
            $command .= ' | ' . $this->bzip2Dir . 'bzip2';
        }

        $command .= ' > ' . $this->backup->getFilePath($fileName);

        $this->runProcess($command);

        return $fileName;
    }

    /**
     * Restores (imports) mysql database from backup file.
     *
     * @param string $fileName The mysql backup filename.
     *
     * @throws \RuntimeException
     */
    public function restore($fileName)
    {
        if (empty($fileName) || !file_exists($filePath = $this->backup->getFilePath($fileName))) {
            throw new \RuntimeException(
                sprintf('The backup file <b>%s</b> is not exits.', $fileName)
            );
        }

        if (preg_match('/\.bz2$/i', $fileName)) {
            if (PHP_OS == 'WINNT' && $this->bzip2Dir === null) {
                throw new \RuntimeException('Import compressed sql file failure. The path to bzip2 compressor is not set.');
            }

            $command = $this->bzip2Dir . 'bunzip2 < ' . $filePath . ' | ' . $this->prepareMySqlCommand('mysql');
        } else {
            $command = $this->prepareMySqlCommand('mysql') . ' < ' . $filePath;
        }

        $this->runProcess($command);
    }

    /**
     * Optimizes all tables in database.
     */
    private function optimizeTable()
    {
        $command = $this->prepareMySqlCommand('mysqlcheck --optimize');
        $this->runProcess($command);
    }

    /**
     * @param string $command
     *
     * @throws \RuntimeException
     */
    private function runProcess($command)
    {
        $process = proc_open($command, array(
                array('pipe', 'r'),
                array('pipe', 'w'),
                array('pipe', 'w')
            ), $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to launch a new process.');
        }

        $error = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode > 0) {
            throw new \RuntimeException(sprintf(
                    'Process "%s" failure with code "%s". Error: "%s"',
                    $command, $exitCode, mb_convert_encoding($error, 'UTF-8', 'UTF-8')
                ));
        }
    }

    /**
     * @param callable $fileNameFormatter
     *
     * @return string The backup filename
     */
    private function prepareFileName(callable $fileNameFormatter = null)
    {
        if ($fileNameFormatter) {
            $fileName = (string) $fileNameFormatter($this->dbConfig['host'], $this->dbConfig['name']);
        } else {
            $fileName = $this->dbConfig['host'] . '-' . $this->dbConfig['name'] . '-' . date("Y-m-d_H-i-s", time());
        }

        return $fileName . '.sql';
    }

    /**
     * @param string $program The CLI mysql program name
     *
     * @return string
     */
    private function prepareMySqlCommand($program)
    {
        return $this->mysqlDir . $program . $this->dbAccess;
    }


    private function setMySqlData()
    {
        if (isset($_SERVER['MYSQL_HOME'])) {
            $this->mysqlDir = $_SERVER['MYSQL_HOME'] . '\\';
        }

        $this->dbAccess = sprintf(
            ' --user=%s --password=%s --host=%s %s',
            $this->dbConfig['user'],
            $this->dbConfig['pass'],
            $this->dbConfig['host'],
            $this->dbConfig['name']
        );
    }

    /**
     * Sets bzip2 compressor path
     *
     * @param bool|string $path
     */
    private function setBzip2Dir($path)
    {
        if (PHP_OS == 'WINNT') {
            if (!empty($path)) {
                $this->bzip2Dir = str_replace('/', '\\', rtrim($path, '\/') . '/');

                $bzip2 = $this->bzip2Dir . 'bzip2.exe';
                if (!is_executable($bzip2)) {
                    throw new \RuntimeException(sprintf(
                            'The %s is not executable. Set proper path to bzip2 compressor directory or false.', $bzip2
                        ));
                }
            }
        } elseif ($path === true) {
            $this->bzip2Dir = '';
        }
    }
}
