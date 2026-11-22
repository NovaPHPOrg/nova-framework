<?php

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

/**
 * 配置：主文件 + config/{node}.config.php。
 *
 * 读：拆分文件覆盖主文件同名键（旧单体零迁移）。
 * 写：脏节点整份写入拆分文件；主文件只剥离「已经有节点文件」的键。
 *     tempfile + rename；符号链接写到真实目标。example 缺键只补内存。
 */
class Config
{
    /** @var list<string> 即使是数组也留在主文件的键 */
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

    /** @var array<string, true> 本进程改过的顶层键 */
    private array $dirtyTops = [];

    private bool $saving = false;

    public function __construct()
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
        $this->mainPath = $root . DIRECTORY_SEPARATOR . 'config.php';
        $this->nodesDir = defined('CONFIG_PATH') ? CONFIG_PATH : ($root . DIRECTORY_SEPARATOR . 'config');
        $this->load();
    }

    public function reloadFromDisk(): void
    {
        if ($this->dirtyTops !== []) {
            $this->save();
        }
        $this->load();
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $cursor = $this->config;
        foreach (explode('.', $path) as $key) {
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
        $this->set($path, array_replace_recursive($original, $value));
    }

    public function set(string $path, mixed $value, bool $immediate = true): void
    {
        $this->assign($this->config, $path, $value);
        $this->dirtyTops[explode('.', $path, 2)[0]] = true;
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

    public function save(): void
    {
        if ($this->dirtyTops === [] || $this->saving) {
            return;
        }

        $this->saving = true;
        try {
            foreach (array_keys($this->dirtyTops) as $top) {
                if ($this->isNode($top)) {
                    $this->writeNode($top);
                }
            }
            $this->writeMain();
            $this->dirtyTops = [];
        } finally {
            $this->saving = false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    public function __destruct()
    {
        $this->save();
    }

    private function load(): void
    {
        $example = $this->readPhp($this->root() . DIRECTORY_SEPARATOR . 'example.config.php');
        $main = $this->readPhp($this->mainPath);
        if ($main === [] && !is_file($this->mainPath)) {
            if ($example === []) {
                exit('配置文件不存在：config.php');
            }
            $main = $example;
        }

        $this->config = $main;
        if (is_dir($this->nodesDir)) {
            foreach (glob($this->nodesDir . DIRECTORY_SEPARATOR . '*.config.php') ?: [] as $file) {
                $node = basename($file, '.config.php');
                if ($node === '' || $node === 'example') {
                    continue;
                }
                $this->config[$node] = $this->readPhp($file);
            }
        }

        $this->dirtyTops = [];
        foreach ($example as $key => $value) {
            if (!array_key_exists($key, $this->config)) {
                $this->config[$key] = $value;
            }
        }
    }

    private function root(): string
    {
        return defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
    }

    private function isNode(string $top): bool
    {
        if (in_array($top, self::MAIN_KEYS, true)) {
            return false;
        }
        if (is_file($this->nodePath($top))) {
            return true;
        }
        return is_array($this->config[$top] ?? null);
    }

    private function nodePath(string $node): string
    {
        return $this->nodesDir . DIRECTORY_SEPARATOR . $node . '.config.php';
    }

    private function writeNode(string $node): void
    {
        if (!is_dir($this->nodesDir) && !mkdir($this->nodesDir, 0755, true) && !is_dir($this->nodesDir)) {
            throw new RuntimeException("无法创建配置目录：{$this->nodesDir}");
        }
        $value = $this->config[$node] ?? [];
        $this->writePhp($this->nodePath($node), is_array($value) ? $value : []);
    }

    private function writeMain(): void
    {
        $out = [];
        foreach ($this->config as $key => $value) {
            $key = (string)$key;
            // 没落盘的节点不许剥——否则 set(version) 会把 db/login 写丢
            if (!in_array($key, self::MAIN_KEYS, true) && is_file($this->nodePath($key))) {
                continue;
            }
            $out[$key] = $value;
        }
        $this->writePhp($this->mainPath, $out);
    }

    /**
     * @return array<string, mixed>
     */
    private function readPhp(string $path): array
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
     * @param array<string, mixed> $data
     */
    private function writePhp(string $path, array $data): void
    {
        // Docker: config.php → /data/config.php；rename 链接本身不会写到卷里
        if (is_link($path)) {
            $link = readlink($path);
            if ($link !== false && $link !== '') {
                $path = $link[0] === '/' ? $link : dirname($path) . DIRECTORY_SEPARATOR . $link;
            }
        }
        $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        $tmp = dirname($path) . DIRECTORY_SEPARATOR . '.' . basename($path) . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content) === false) {
            @unlink($tmp);
            throw new RuntimeException("无法写入临时配置：{$tmp}");
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("无法保存配置文件：{$path}");
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    /**
     * @param array<string, mixed> $root
     */
    private function assign(array &$root, string $path, mixed $value): void
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
}
