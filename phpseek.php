#!/usr/bin/env php
<?php
namespace dajoho\phpseek;
use Exception;
use RecursiveIteratorIterator;

/**
 * Class phpseek
 *
 * @package   Dajoho\Gojumble
 * @author    Dave Holloway <dh@holloware.de>
 * @license   Commercial <https://holloware.de/commercial>
 * @link      https://gojumble.com
 * @namespace dajoho\phpseek
 */
class phpseek
{
    const CURRENT_VERSION = "1.0.0";
    const ERROR_ARGUMENT_MISSING = 1;
    const ERROR_COMMAND_MISSING = 2;
    const ERROR_DIRECTORY_NOT_FOUND = 3;
    const ERROR_DIRECTORY_NOT_WRITABLE = 4;
    const ERROR_WRITE_ERROR = 5;
    const COMPRESSION_LEVEL = 9;

    /**
     * @var array Array of command line arguments
     */
    private $arguments;

    /**
     * @var array Available commands
     */
    private $availableCommands = ['pack'];

    /**
     * @var array Files to ignore while packing
     */
    private $ignoredFiles = ['.', '..', '.DS_Store', 'thumbs.db'];

    /**
     * @var string phpseek's binary header
     */
    private $seekHeader = "0E500E480E500E530E450E450E4B0E";

    /**
     * @param $args
     */
    public function main($args)
    {
        $this->arguments = $args;
        try {
            $cmd = $this->getCommand();
            switch ($cmd) {
                case "pack":
                    $this->pack($this->getArg(2));
                break;
            }
        } catch (Exception $e) {
            $this->printHelp($e);
            exit;
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getCommand()
    {
        $command = $this->getArg(1);
        if (in_array($command, $this->availableCommands) === true) {
            return $command;
        }
        throw new Exception(
            'Command missing. Valid commands: '.
            implode(',', $this->availableCommands),
            self::ERROR_COMMAND_MISSING
        );
    }

    /**
     * @param $idx
     *
     * @return string
     * @throws Exception
     */
    protected function getArg($idx)
    {
        if (array_key_exists($idx, $this->arguments) === true) {
            return $this->arguments[$idx];
        }
        throw new Exception("Argument at position ".($idx)." missing.", self::ERROR_ARGUMENT_MISSING);
    }

    /**
     * @param null $exception
     */
    private function printHelp($exception = null)
    {
        $this->printLine("phpseek ".self::CURRENT_VERSION);
        $this->printLine("by Dave Holloway (dajoho)");
        $this->printLine("usage:");
        $this->printLine("    phpseek pack <directory>");
        if ($exception instanceof Exception) {
            $this->printLine($exception->getMessage());
        }
    }

    /**
     * @param $line
     */
    protected function printLine($line)
    {
        echo $line."\n";
    }

    /**
     * @param $directory
     *
     * @throws Exception
     */
    protected function pack($directory)
    {
        if (is_dir($directory) === false) {
            throw new Exception("Directory not found: $directory", self::ERROR_DIRECTORY_NOT_FOUND);
        }
        if (is_writable($directory) === false) {
            throw new Exception("Directory not writable: $directory", self::ERROR_DIRECTORY_NOT_WRITABLE);
        }

        $target = dirname($directory)."/".basename($directory).".psa";
        $this->printLine($target);

        $psa = fopen($target, "wb");
        $seekBytes = hex2bin($this->seekHeader);
        $seekBytesLength = strlen($seekBytes);

        $index = $seekBytes;
        $pos = 0;
        foreach (new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            $base = basename($file);
            if (in_array($base, $this->ignoredFiles) === true) {
                continue;
            }
            $pos += $seekBytesLength;
            $startPos = $pos;
            $virtualPath = str_replace($directory, '', $file);
            $filesize = filesize($file);
            $hash = substr(sha1_file($file), 0, 8);
            $mtime = filemtime($file);
            $line = str_pad($pos, 10, "0", STR_PAD_LEFT)." ";
            $line .= $hash." ";
            $line .= str_pad($virtualPath, 40, " ", STR_PAD_LEFT)." ";
            $line .= str_pad(round($filesize/1024, 2)."KB", 10, " ", STR_PAD_LEFT)." ";
            fwrite($psa, $seekBytes);
            $br = fopen($file, 'rb');
            $compressedSize = 0;
            $context = deflate_init(ZLIB_ENCODING_DEFLATE);
            while (feof($br) !== true) {
                $chunk = fread($br, 32768);
                $compressedChunk = deflate_add($context, $chunk, ZLIB_SYNC_FLUSH);
                $compressedSize += strlen($compressedChunk);
                if (fwrite($psa, $compressedChunk) === FALSE) {
                    throw new Exception('Write error: '.$file, self::ERROR_WRITE_ERROR);
                }
            }
            $line .= ' --> '.str_pad(round($compressedSize/1024, 2)."KB", 10, " ", STR_PAD_LEFT)." ";
            $line .= str_pad(round(($compressedSize/$filesize)*100, 2)."%", 7, " ", STR_PAD_LEFT);
            $this->printLine($line);
            $pos += $compressedSize;
            fclose($br);
            $index .= "$virtualPath\t$startPos\t$compressedSize\t$mtime\n";
        }
        $index = trim($index);
        fwrite($psa, $index);
        fwrite($psa, ":".mb_strlen($index));
        fclose($psa);
    }
}

call_user_func(function () use ($argv) {
    (PHP_SAPI !== "cli") ? die("phpseek is a command line tool only.") : true;
    (version_compare(PHP_VERSION, '7.1.0', '<')) ? die("phpseek requires php7\n") : true;
    (new phpseek())->main($argv);
});
