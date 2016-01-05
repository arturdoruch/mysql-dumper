<?php
/**
 * @author Artur Doruch <arturdoruch@interia.pl>
 */

namespace ArturDoruch\Tool\MySqlDumper;

/**
 *  Manages mysql database backups.
 */
class MySqlBackup
{
    /**
     * @var string
     */
    private $backupDir;

    /**
     * @param string $backupDir
     */
    public function __construct($backupDir)
    {
        if (!file_exists($backupDir)) {
            if (!@mkdir($backupDir, 0777, true)) {
                throw new \InvalidArgumentException(sprintf(
                        'The backup directory for given path "%s", could not be created.', $backupDir
                    ));
            }
        }

        $this->backupDir = $backupDir;
    }

    /**
     * Gets backup files list sorted from newest to oldest.
     *
     * @param callable $sort
     *
     * @return \SplFileInfo[]
     */
    public function all(callable $sort = null)
    {
        static $extensions = array(
            'sql', 'bz2', 'gz', 'zip'
        );

        $iterator = new \RecursiveDirectoryIterator($this->backupDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveCallbackFilterIterator($iterator, function (\SplFileInfo $file) use ($extensions) {
                return in_array($file->getExtension(), $extensions);
            });

        $files = array();
        foreach ($iterator as $file) {
            $files[] = $file;
        }

        if (!$sort) {
            $sort = function ($a, $b) {
                return $a->getCTime() < $b->getCTime();
            };
        }

        usort($files, $sort);

        return $files;
    }

    /**
     * Removes backup file by filename.
     *
     * @param string $fileName The backup filename related to backup directory.
     *
     * @throws \Exception
     */
    public function remove($fileName)
    {
        if (!file_exists($filePath = $this->getFilePath($fileName))) {
            throw new \RuntimeException(sprintf('The backup file %s is not exist.', $fileName));
        }

        if (!unlink($filePath)) {
            throw new \RuntimeException(sprintf('Failure removing backup file %s.', $fileName));
        }
    }

    /**
     * Removes backups but leaves the newest.
     *
     * @param int $leave The number of backup files to not remove.
     */
    public function removeOld($leave = 5)
    {
        $backups = $this->all();

        foreach ($backups as $i => $backup) {
            if ($i >= $leave) {
                $this->remove($backup->getFilename());
            }
        }
    }

    /**
     * @param string $fileName Backup filename.
     *
     * @return string The full path to backup file.
     */
    public function getFilePath($fileName)
    {
        return $this->backupDir . DIRECTORY_SEPARATOR . $fileName;
    }

}
 