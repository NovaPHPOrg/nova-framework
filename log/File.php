<?php
declare(strict_types=1);

namespace nova\framework\log;


class File
{
    // 获取完整路径
    public static function valid($fileName): bool
    {
        if ($fileName == null) return false;
        return preg_match('/^[\w_\-.]+$/', $fileName) == 1;
    }
    // 检测文件或目录是否存在
    public static function cpDir($src, $dest): bool
    {
        // 确保使用规范化的路径
        $srcPath = realpath($src);
        $destPath = rtrim($dest, '/\\');

        // 检查源目录是否存在且可读
        if (!$srcPath || !is_dir($srcPath) || !is_readable($srcPath)) {
            return false;
        }

        // 创建目标目录
        if (!self::mkDir($destPath)) {
            return false;
        }

        $dir = opendir($srcPath);
        if ($dir === false) {
            return false;
        }

        $success = true;
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $srcPath . DIRECTORY_SEPARATOR . $file;
            $destFile = $destPath . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcFile)) {
                if (!self::cpDir($srcFile, $destFile)) {
                    $success = false;
                    break;
                }
            } else {
                if (!self::cpFile($srcFile, $destFile)) {
                    $success = false;
                    break;
                }
            }
        }

        closedir($dir);
        return $success;
    }

    // 检测是否为合规的文件名（不含路径符号）

    public static function path(...$path): string
    {
        return ROOT_PATH . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path);
    }

    // 如果目录不存在，则递归创建目录

    public static function exists($path): bool
    {
        return file_exists($path);
    }

    // 复制文件

    public static function mkDir($dir): bool
    {
        if (!self::exists($dir)) {
            if (mkdir($dir, 0755, true)) {  // 修改权限为 0755
                chmod($dir, 0755);  // 确保权限设置正确
                return true;
            }
            return false;
        }
        return true;
    }

    // 递归复制目录

    public static function cpFile($src, $dest): bool
    {
        if (is_file($src) && is_readable($src)) {
            self::mkDir(dirname($dest)); // 确保目标目录存在
            return copy($src, $dest);
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

    public static function write(string $file, string $body): void
    {
        $dir = dirname($file);
        self::mkDir($dir);
        file_put_contents($file, $body);
    }

    public static function del(string $dir): void
    {
        if (!file_exists($dir)) return;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    self::del($dir . DIRECTORY_SEPARATOR . $file);
                }
            }
            rmdir($dir);
        } else {
            unlink($dir);
        }
    }
}
