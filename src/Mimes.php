<?php

namespace React\Http;

class Mimes
{
    private static $mimes = [
        'hqx' => 'application/mac-binhex40',
        'cpt' => 'application/mac-compactpro',
        'csv' => 'text/x-comma-separated-values',
        'bin' => 'application/macbinary',
        'dms' => 'application/octet-stream',
        'lha' => 'application/octet-stream',
        'lzh' => 'application/octet-stream',
        'exe' => 'application/octet-stream',
        'class' => 'application/octet-stream',
        'psd' => 'application/x-photoshop',
        'so' => 'application/octet-stream',
        'sea' => 'application/octet-stream',
        'dll' => 'application/octet-stream',
        'oda' => 'application/oda',
        'pdf' => 'application/pdf',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        'smi' => 'application/smil',
        'smil'  => 'application/smil',
        'mif' => 'application/vnd.mif',
        'xls' => 'application/excel',
        'ppt' => 'application/powerpoint',
        'wbxml' => 'application/wbxml',
        'wmlc'  => 'application/wmlc',
        'dcr' => 'application/x-director',
        'dir' => 'application/x-director',
        'dxr' => 'application/x-director',
        'dvi' => 'application/x-dvi',
        'gtar'  => 'application/x-gtar',
        'gz' => 'application/x-gzip',
        'php' => 'application/x-httpd-php',
        'php4'  => 'application/x-httpd-php',
        'php3'  => 'application/x-httpd-php',
        'phtml' => 'application/x-httpd-php',
        'phps'  => 'application/x-httpd-php-source',
        'js' => 'application/x-javascript',
        'swf' => 'application/x-shockwave-flash',
        'sit' => 'application/x-stuffit',
        'tar' => 'application/x-tar',
        'tgz' => 'application/x-tar',
        'xhtml' => 'application/xhtml+xml',
        'xht' => 'application/xhtml+xml',
        'zip' => 'application/x-zip',
        'mid' => 'audio/midi',
        'midi'  => 'audio/midi',
        'mpga'  => 'audio/mpeg',
        'mp2' => 'audio/mpeg',
        'mp3' => 'audio/mpeg',
        'aif' => 'audio/x-aiff',
        'aiff'  => 'audio/x-aiff',
        'aifc'  => 'audio/x-aiff',
        'ram' => 'audio/x-pn-realaudio',
        'rm' => 'audio/x-pn-realaudio',
        'rpm' => 'audio/x-pn-realaudio-plugin',
        'ra' => 'audio/x-realaudio',
        'rv' => 'video/vnd.rn-realvideo',
        'wav' => 'audio/x-wav',
        'bmp' => 'image/bmp',
        'gif' => 'image/gif',
        'jpeg'  => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'png' => 'image/png',
        'tiff'  => 'image/tiff',
        'tif' => 'image/tiff',
        'css' => 'text/css',
        'html'  => 'text/html',
        'htm' => 'text/html',
        'shtml' => 'text/html',
        'txt' => 'text/plain',
        'text'  => 'text/plain',
        'log' => 'text/plain',
        'rtx' => 'text/richtext',
        'rtf' => 'text/rtf',
        'xml' => 'text/xml',
        'xsl' => 'text/xml',
        'mpeg'  => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'movie' => 'video/x-sgi-movie',
        'doc' => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'word'  => 'application/msword',
        'xl' => 'application/excel',
        'eml' => 'message/rfc822',
        'json'  => 'application/json'
    ];

    /**
     * Tries to detect file's mime type by checking its extension, or using fallbacks such as Finfo
     *
     * @param $file
     * @return string
     */
    public static function detect($file)
    {
        $ext = explode('.', $file)[0];

        /*
           The reason why it's checking by extension first, is because it's more reliable in cases such as css and js files, even php, since they don't have any headers
           so it's harder to determine the file mime type.
           @link http://stackoverflow.com/a/5226315/1150735
        */
        if (isset(self::$mimes[$ext]))
        {
            return self::$mimes[$ext];
        }

        // Checking via fileinfo extension
        if (class_exists('finfo'))
        {
            // @link http://php.net/manual/en/ref.fileinfo.php#85348
            $magic = PHP_OS === 'Linux' ? '/usr/share/misc/magic' : '';
            $finfo = new \finfo(FILEINFO_MIME, $magic);
            return $finfo->file($file);
        }

        // And last try via mime_content_type
        if (function_exists('mime_content_type'))
        {
            return mime_content_type($file);
        }

        // Well, at least we have a default
        return 'application/octet-stream';
    }
}