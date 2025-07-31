<?php

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\core;

/**
 * 文件操作工具类
 *
 * 提供了一系列静态方法用于文件和目录的操作：
 * - 文件名验证
 * - 路径处理
 * - 文件/目录的创建、复制、删除
 * - 文件读写
 * - 文件信息获取
 *
 * @package nova\framework\core
 */
class File
{
    /**
     * 验证文件名是否合法（不含路径符号）
     * @param  string|null $fileName 文件名
     * @return bool        是否合法
     */
    public static function isValidFileName($fileName): bool
    {
        if ($fileName === null) {
            return false;
        }
        return preg_match('/^[\w_\-.]+$/', $fileName) === 1;
    }

    /**
     * 获取完整路径
     * @param  string ...$path 路径片段
     * @return string 完整路径
     */
    public static function path(...$path): string
    {
        return ROOT_PATH . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path);
    }

    /**
     * 递归复制目录
     * @param  string $src  源目录路径
     * @param  string $dest 目标目录路径
     * @return bool   是否复制成功
     */
    public static function copyDirectory($src, $dest): bool
    {
        try {
            $srcPath = realpath($src);
            $destPath = rtrim($dest, '/\\');

            if (!$srcPath || !is_dir($srcPath) || !is_readable($srcPath)) {
                return false;
            }

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
                    if (!self::copyDirectory($srcFile, $destFile)) {
                        $success = false;
                        break;
                    }
                } else {
                    if (!self::copyFile($srcFile, $destFile)) {
                        $success = false;
                        break;
                    }
                }
            }

            closedir($dir);
            return $success;
        } catch (\ErrorException $e) {
            Logger::error("复制失败: " . $e->getMessage(),$e->getTrace());
            return false;
        }
    }

    /**
     * 递归创建目录
     * @param  string $dir 目录路径
     * @return bool   是否创建成功
     */
    public static function mkDir($dir): bool
    {
        Logger::info("File::mkDir $dir",(new \Exception())->getTrace());
        if (!self::exists($dir)) {
            try {
                mkdir($dir, 0755, true);
                return true;
            } catch (\ErrorException $e) {
                if(str_contains($e->getMessage(), 'File exists')) return true;
                Logger::error("创建目录失败: " . $e->getMessage(),$e->getTrace());
                return false;
            }
        }
        return true;
    }

    /**
     * 检测文件或目录是否存在
     * @param  string $path 路径
     * @return bool   是否存在
     */
    public static function exists($path): bool
    {
        return file_exists($path);
    }

    /**
     * 复制文件
     * @param  string $src  源文件路径
     * @param  string $dest 目标文件路径
     * @return bool   是否复制成功
     */
    public static function copyFile($src, $dest): bool
    {
        try {
            if (is_file($src) && is_readable($src)) {
                self::mkDir(dirname($dest));
                return copy($src, $dest);
            }
            return false;
        } catch (\ErrorException $e) {
            Logger::error("复制文件失败: " . $e->getMessage(),$e->getTrace());
            return false;
        }
    }

    /**
     * 获取文件名（不含扩展名）
     * @param  string $path 文件路径
     * @return string 文件名
     */
    public static function fileName($path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * 获取文件扩展名
     * @param  string $path 文件路径
     * @return string 文件扩展名
     */
    public static function ext($path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * 写入文件内容
     * @param  string     $file 文件路径
     * @param  string     $body 文件内容
     * @throws \Exception 写入失败时抛出异常
     */
    public static function write(string $file, string $body): void
    {
        try {
            $dir = dirname($file);
            self::mkDir($dir);
            if (file_put_contents($file, $body) === false) {
                throw new \Exception("写入文件失败");
            }
        } catch (\ErrorException $e) {
            Logger::error("删除文件或目录失败: " . $e->getMessage(),$e->getTrace());
            throw $e;
        }
    }

    /**
     * 删除文件或目录
     * @param  string     $dir 文件或目录路径
     * @throws \Exception 删除失败时抛出异常
     */
    public static function del(string $dir,bool $onlyFile = false): void
    {
        try {

            Logger::info("File::delete($onlyFile) $dir",(new \Exception())->getTrace());

            if (!file_exists($dir)) {
                return;
            }

            if (is_dir($dir)) {
                $files = scandir($dir);
                if(!$files) return;
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        self::del($dir . DIRECTORY_SEPARATOR . $file,$onlyFile);
                    }
                }
                if(!$onlyFile) rmdir($dir);
            } else {
                unlink($dir);
            }
        } catch (\ErrorException $e) {
            if(str_contains($e->getMessage(), 'No such file or directory')) return;
            Logger::error("删除文件或目录失败: " . $e->getMessage(),$e->getTrace());
        }
    }
}
