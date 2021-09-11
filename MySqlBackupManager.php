<?php

namespace ArturDoruch\MySqlDumper;

/**
 * Manages MySQL database backups.
 *
 * @author Artur Doruch <arturdoruch@interia.pl>
 */
class MySqlBackupManager
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
        if (!file_exists($backupDir) && !@mkdir($backupDir, 0777, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Backup directory with a path "%s" could not be created.', $backupDir
            ));
        }

        $this->backupDir = realpath($backupDir);
    }

    /**
     * @return string
     */
    public function getBackupDir()
    {
        return $this->backupDir;
    }

    /**
     * Gets backup files, by default sorted from newest to oldest.
     *
     * @param callable $sort
     *
     * @return \SplFileInfo[]
     */
    public function all(callable $sort = null)
    {
        static $extensions = ['sql', 'bz2', 'gz', 'zip'];

        $directory = new \RecursiveDirectoryIterator($this->backupDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveCallbackFilterIterator($directory, function (\SplFileInfo $file) use ($extensions) {
            return in_array($file->getExtension(), $extensions);
        });
        $files = iterator_to_array($files);

        if (!$sort) {
            $sort = function (\SplFileInfo $file1, \SplFileInfo $file2) {
                return $file1->getCTime() < $file2->getCTime();
            };
        }

        usort($files, $sort);

        return $files;
    }

    /**
     * Removes backup file.
     *
     * @param string $filename The path of the file to remove relative to the backup directory.
     *
     * @throws \Exception
     */
    public function remove($filename)
    {
        if (!file_exists($filePath = $this->prepareFilePath($filename))) {
            throw new \RuntimeException(sprintf('The backup file "%s" does not exist.', $filename));
        }

        if (!unlink($filePath)) {
            throw new \RuntimeException(sprintf('The backup file "%s" could not be removed.', $filename));
        }
    }

    /**
     * Removes backups but leaves the newest.
     *
     * @param int $leave The number of backup files to not remove.
     */
    public function removeOld($leave = 1)
    {
        $backups = $this->all();

        foreach ($backups as $i => $backup) {
            if ($i >= $leave) {
                $this->remove($backup->getFilename());
            }
        }
    }

    /**
     * @param string $filename The path of the backup file relative to the backup directory.
     *
     * @return string Full path to the backup file.
     */
    public function prepareFilePath($filename)
    {
        return $this->backupDir . DIRECTORY_SEPARATOR . $filename;
    }
}
