<?php

namespace nova\framework\log;


class File
{
    // 获取完整路径
    public static function valid($fileName): bool
    {
        return preg_match('/^[\w_\-.]+$/', $fileName) == 1;
    }

    // 检测文件或目录是否存在

    public static function cpDir($src, $dest): bool
    {
        $srcPath = self::path($src);
        $destPath = self::path($dest);

        if (!self::exists($srcPath) || !is_dir($srcPath)) {
            return false;
        }

        self::mkDir($destPath); // 确保目标目录存在

        $dir = opendir($srcPath);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $srcPath . DS . $file;
            $destFile = $destPath . DS . $file;

            if (is_dir($srcFile)) {
                self::cpDir($srcFile, $destFile);
            } else {
                self::cpFile($srcFile, $destFile);
            }
        }

        closedir($dir);
        return true;
    }

    // 检测是否为合规的文件名（不含路径符号）

    public static function path(...$path): string
    {
        return ROOT_PATH . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path);
    }

    // 如果目录不存在，则递归创建目录

    public static function exists($path): bool
    {
        return file_exists(self::path($path));
    }

    // 复制文件

    public static function mkDir($dir): bool
    {
        if (!self::exists($dir)) {
            return mkdir($dir, 0777, true);
        }
        return true;
    }

    // 递归复制目录

    public static function cpFile($src, $dest): bool
    {
        $srcPath = self::path($src);
        $destPath = self::path($dest);

        if (self::exists($srcPath) && is_file($srcPath)) {
            self::mkDir(dirname($destPath)); // 确保目标目录存在
            return copy($srcPath, $destPath);
        }
        return false;
    }

    // 获取文件名

    public static function fileName($path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    // 获取文件后缀
    public static function ext($path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }
}
