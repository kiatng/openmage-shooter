<?php
/**
 * @category   Kiatng
 * @package    Kiatng_Shooter
 * @copyright  Copyright (c) 2025 Ng Kiat Siong
 * @license    GNU GPL v3.0
 */

class Kiatng_Shooter_Helper_File
{
    /**
     * Read last lines of file
     *
     * @param string $filepath Path to the file to read
     * @param int $lines Number of lines to read (must be positive)
     * @return string|false Returns the last n lines or false on error
     */
    public function tail(string $filepath, int $lines = 10)
    {
        $f = fopen($filepath, "rb");
        if ($f === false) {
            return false;
        }

        try {
            // Sets buffer size based on number of lines
            $buffer = $lines < 10 ? 512 : 4096;

            // Jump to last character
            fseek($f, -1, SEEK_END);

            // Adjust line count if file doesn't end with newline
            if (fread($f, 1) != "\n") {
                $lines -= 1;
            }

            // Start reading
            $output = '';
            $chunk = '';

            while (ftell($f) > 0 && $lines >= 0) {
                $seek = min(ftell($f), $buffer);
                fseek($f, -$seek, SEEK_CUR);
                $output = ($chunk = fread($f, $seek)) . $output;
                fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
                $lines -= substr_count($chunk, "\n");
            }

            // Trim excess lines
            while ($lines++ < 0) {
                $output = substr($output, strpos($output, "\n") + 1);
            }

            return trim($output);
        } finally {
            fclose($f);
        }
    }

    /**
     * Get the latest file in a directory
     *
     * @param string $dir Path to the directory to search
     * @return string Returns the path to the latest file or an empty string if no files are found
     */
    public function latest(string $dir): string
    {
        if (!is_dir($dir)) {
            return '';
        }

        if (function_exists('exec') && $latest = exec("ls -t $dir | head -1")) {
            return $dir . DS . $latest;
        }

        $latest = '';
        $latestTime = 0;

        try {
            $handle = opendir($dir);
            if ($handle === false) {
                return '';
            }

            while (false !== ($file = readdir($handle))) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $fullPath = $dir . DS . $file;
                $mtime = filemtime($fullPath);
                if ($mtime > $latestTime) {
                    $latestTime = $mtime;
                    $latest = $fullPath;
                }
            }
        } finally {
            if (isset($handle) && $handle !== false) {
                closedir($handle);
            }
        }

        return $latest;
    }
}
