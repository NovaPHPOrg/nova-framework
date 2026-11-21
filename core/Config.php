<?php

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

/**
 * 配置管理：主文件 + config/{node}.config.php 双源。
 *
 * - ROOT_PATH/config.php：部署级键（debug、domain、framework_start…）
 * - CONFIG_PATH/{node}.config.php：对象节点（login、marketplace、corn…）
 * - 读：拆分文件优先；无拆分文件时回退主文件同名键（兼容旧单体）
 * - 写：按顶层段分流；首次写入节点时从主文件剥除该键
 */
class Config
{
    /** @var list<string> 永远留在主 config.php 的顶层键 */
    private const array MAIN_KEYS = [
        'debug',
        'timezone',
        'version',
        'ip',
        'port',
        'default_route',
        'domain',
        'framework_start',
        'cache_driver',
        'cpu_cores',
    ];

    /** @var array<string, mixed> */
    protected array $config = [];

    private string $mainPath;

    private string $nodesDir;

    /** @var list<string> 本进程改过的点分路径 */
    private array $dirtyPaths = [];

    public function __construct()
    {
        $root = $this->rootPath();
        $this->mainPath = $root . DIRECTORY_SEPARATOR . 'config.php';
        $this->nodesDir = defined('CONFIG_PATH') ? CONFIG_PATH : ($root . DIRECTORY_SEPARATOR . 'config');
        $this->loadConfig();
    }

    private function rootPath(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
    }

    /**
     * 先落盘未保存改动，再从磁盘重新合并加载。
     */
    public function reloadFromDisk(): void
    {
        if ($this->dirtyPaths !== []) {
            $this->save();
        }
        $this->loadConfig();
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePhpArray(string $path): array
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMainDisk(): array
    {
        return $this->requirePhpArray($this->mainPath);
    }

    /**
     * @return array<string, mixed> node => 节点数组
     */
    private function readSplitDisk(): array
    {
        if (!is_dir($this->nodesDir)) {
            return [];
        }
        $nodes = [];
        foreach (glob($this->nodesDir . DIRECTORY_SEPARATOR . '*.config.php') ?: [] as $file) {
            $base = basename($file, '.config.php');
            if ($base === '' || $base === 'example') {
                continue;
            }
            $nodes[$base] = $this->requirePhpArray($file);
        }
        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExampleConfig(): array
    {
        return $this->requirePhpArray($this->rootPath() . DIRECTORY_SEPARATOR . 'example.config.php');
    }

    private function loadConfig(): void
    {
        $example = $this->readExampleConfig();
        $main = $this->readMainDisk();
        if ($main === [] && !is_file($this->mainPath)) {
            if ($example === []) {
                exit('配置文件不存在：config.php');
            }
            $main = $example;
        }

        $disk = $main;
        foreach ($this->readSplitDisk() as $node => $value) {
            $disk[$node] = $value;
        }

        $this->config = $disk;
        $this->dirtyPaths = [];

        foreach ($example as $key => $value) {
            if (array_key_exists($key, $this->config)) {
                continue;
            }
            $this->config[$key] = $value;
            // 仅主文件键缺省时标脏写回；对象节点交给类默认值 / 首次显式保存
            if ($this->isMainKey($key)) {
                $this->dirtyPaths[] = $key;
            }
        }
    }

    private function isMainKey(string $key): bool
    {
        return in_array($key, self::MAIN_KEYS, true);
    }

    private function nodeFile(string $node): string
    {
        return $this->nodesDir . DIRECTORY_SEPARATOR . $node . '.config.php';
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $cursor = $this->config;
        foreach ($keys as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$key];
        }
        return $cursor;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function merge(string $path, array $value): void
    {
        $original = $this->get($path, []);
        if (!is_array($original)) {
            $original = [];
        }
        $this->set($path, $this->mergeArrays($original, $value));
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    private function mergeArrays(array $original, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
                $original[$key] = $this->mergeArrays($original[$key], $value);
            } else {
                $original[$key] = $value;
            }
        }
        return $original;
    }

    /**
     * @param bool $immediate false 时只改内存并标脏，由调用方统一 save
     */
    public function set(string $path, mixed $value, bool $immediate = true): void
    {
        $this->assignPath($this->config, $path, $value);
        if (!in_array($path, $this->dirtyPaths, true)) {
            $this->dirtyPaths[] = $path;
        }
        if ($immediate) {
            $this->save();
        }
    }

    /**
     * @param array<string, mixed> $pathValues 点分路径 => 值
     */
    public function setMany(array $pathValues): void
    {
        foreach ($pathValues as $path => $value) {
            $this->set((string)$path, $value, false);
        }
        $this->save();
    }

    /**
     * @param array<string, mixed> $root
     */
    private function assignPath(array &$root, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $cursor = &$root;
        $last = array_pop($keys);
        foreach ($keys as $key) {
            if (!isset($cursor[$key]) || !is_array($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor = &$cursor[$key];
        }
        $cursor[$last] = $value;
    }

    /**
     * @param array<string, mixed> $root
     */
    private function readPath(array $root, string $path): mixed
    {
        $keys = explode('.', $path);
        $cursor = $root;
        foreach ($keys as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return null;
            }
            $cursor = $cursor[$key];
        }
        return $cursor;
    }

    public function save(): void
    {
        if ($this->dirtyPaths === []) {
            return;
        }

        /** @var array<string, list<string>> $byTop */
        $byTop = [];
        foreach ($this->dirtyPaths as $path) {
            $top = explode('.', $path, 2)[0];
            $byTop[$top][] = $path;
        }

        $peelFromMain = [];
        $mainTouched = false;

        foreach ($byTop as $top => $paths) {
            if ($this->isMainKey($top)) {
                $mainTouched = true;
                continue;
            }
            $this->saveNodeFile($top, $paths);
            $peelFromMain[] = $top;
        }

        if ($mainTouched || $peelFromMain !== []) {
            $this->saveMainFile($byTop, $peelFromMain);
        }

        $this->dirtyPaths = [];
    }

    /**
     * @param list<string> $paths
     */
    private function saveNodeFile(string $node, array $paths): void
    {
        if (!is_dir($this->nodesDir) && !mkdir($this->nodesDir, 0755, true) && !is_dir($this->nodesDir)) {
            throw new RuntimeException("无法创建配置目录：{$this->nodesDir}");
        }

        $file = $this->nodeFile($node);
        $diskNode = $this->requirePhpArray($file);
        if ($diskNode === []) {
            $fromMain = $this->readMainDisk()[$node] ?? null;
            if (is_array($fromMain)) {
                $diskNode = $fromMain;
            } elseif (isset($this->config[$node]) && is_array($this->config[$node])) {
                $diskNode = $this->config[$node];
            }
        }

        foreach ($paths as $path) {
            if ($path === $node) {
                $value = $this->config[$node] ?? [];
                $diskNode = is_array($value) ? $value : [];
                continue;
            }
            $rest = substr($path, strlen($node) + 1);
            $this->assignPath($diskNode, $rest, $this->readPath($this->config, $path));
        }

        $this->writePhpArray($file, $diskNode);
        $this->config[$node] = $diskNode;
    }

    /**
     * @param array<string, list<string>> $byTop
     * @param list<string>                $peelFromMain
     */
    private function saveMainFile(array $byTop, array $peelFromMain): void
    {
        $disk = $this->readMainDisk();
        if ($disk === [] && !is_file($this->mainPath)) {
            foreach (self::MAIN_KEYS as $key) {
                if (array_key_exists($key, $this->config)) {
                    $disk[$key] = $this->config[$key];
                }
            }
        }

        foreach ($byTop as $top => $paths) {
            if (!$this->isMainKey($top)) {
                continue;
            }
            foreach ($paths as $path) {
                $this->assignPath($disk, $path, $this->readPath($this->config, $path));
            }
        }

        foreach ($peelFromMain as $node) {
            unset($disk[$node]);
        }

        $this->writePhpArray($this->mainPath, $disk);

        foreach ($disk as $key => $value) {
            if ($this->isMainKey((string)$key)) {
                $this->config[$key] = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePhpArray(string $path, array $data): void
    {
        $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("无法保存配置文件：{$path}");
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }
}
