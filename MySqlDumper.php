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
     * @param string $host The database host
     * @param string $name The database name
     * @param string $user The database user name
     * @param string $pass The database password
     * @param string $backupDir The full path to backup directory
     * @param bool|string $bzip2 If you want to compress dumped sql file with bzip2 compressor pass:
     *                           - on windows the path to bzip compressor directory,
     *                           - on linux and other OS true value.
     */
    public function __construct($host, $name, $user, $pass, $backupDir, $bzip2 = false)
    {
        $this->backup = new MySqlBackup($backupDir);
        $this->dbConfig = array(
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
        );

        $this->setMySqlData();
        $this->setBzip2Compressor($bzip2);
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
     * @param bool $optimizeTable If true optimizes all database tables before dump.
     * @param callable $fileNameFn Customises the backup filename. Callback must returns
     *                             backup filename without extension.
     *
     * @return string Dumped database filename.
     */
    public function dump($optimizeTable = true, callable $fileNameFn = null)
    {
        if ($optimizeTable) {
            $this->optimizeTable();
        }

        $fileName = $this->prepareFileName($fileNameFn);
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
     * @param callable $fileNameFn
     *
     * @return string The backup filename
     */
    private function prepareFileName(callable $fileNameFn = null)
    {
        if ($fileNameFn) {
            $fileName = (string) $fileNameFn($this->dbConfig['name'], $this->dbConfig['host']);
        } else {
            $fileName = 'db-' . $this->dbConfig['name'] . '-' . date("Y-m-d_H-i-s", time());
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
     * @param bool|string $bzip2
     */
    private function setBzip2Compressor($bzip2)
    {
        if (PHP_OS == 'WINNT') {
            if (!empty($bzip2)) {
                $this->bzip2Dir = str_replace('/', '\\', rtrim($bzip2, '\/') . '/');
            }
        } elseif ($bzip2 === true) {
            $this->bzip2Dir = '';
        }
    }

}
