# phpseek

phpseek is a simple low-level virtual filesystem, similar to PHAR, with a focus on fast retrieval times and low memory usage.

**Requires PHP 7.1.**


## Installation

``` bash
$ curl -S https://raw.githubusercontent.com/dajoho/phpseek/master/phpseek.php > /usr/local/bin/phpseek
$ chmod +x /usr/local/bin/phpseek
```

## Usage

``` bash
$ phpseek pack /Path/To/Folder/example/
```
After executing the command, you will find a compressed version of the `example` folder in the file `example.psa`, located in folder: `/Path/To/Folder/`.

There are no other command line arguments.


## Unpacking / Accessing files

`.psa` archives contain an index of all files. There is currently no library to access the files, but in the mean time, you can use this example code:

### Access the index
``` php
    $psa = fopen($this->archive, "rb");
    $pos = filesize($this->archive) - 1;
    $i = 0;
    $chr = null;
    do {
        fseek($psa, $pos);
        $chr = fread($psa, 1);
        $pos--;
        $i++;
    } while ($chr != ":" && $i < 1024);
    $size = fread($psa, $i);
    $headerPos = $pos - $size + 1;
    fseek($psa, ($headerPos));
    $seekbytes = strtoupper(bin2hex(fread($psa, 15)));
    if ($seekbytes == "0E500E480E500E530E450E450E4B0E") {
        fseek($psa, $headerPos + 15);
        $index = fread($psa, $size);
        print_r($index);
    }
    fclose($psa);
    die();
```

### Stream a file to the browser
This will extract any file from a `psa` and deliver it in chunks to the browser. You can use any content-type you like or save the contents of the file in a variable for processing.

``` php
    // retrieval
    $startPos = 176811;
    $size = 2541;
    $psa = fopen($this->archive, "rb");
    header("Content-Type:application/octet-stream");
    $context = inflate_init(ZLIB_ENCODING_DEFLATE);
    while ($size > 0) {
        fseek($psa, $startPos);
        $startPos+=16384;
        $nextChunkSize = ($size >= 16384) ? 16384 : $size;
        // stream, bitches!
        echo inflate_add($context, fread($psa, $nextChunkSize), ZLIB_SYNC_FLUSH);
        $size -= $nextChunkSize;
    }
    fclose($psa);
```