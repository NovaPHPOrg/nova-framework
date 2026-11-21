<?php

declare(strict_types=1);

namespace nova\framework\core;

use RuntimeException;

/**
 * 配置对象基类。
 *
 * 节点名由类名推导（LoginConfig → login），读写走 Context::config()。
 * 析构时只提交变更过的字段到 config/{node}.config.php，避免整节点覆盖。
 */
class ConfigObject extends Instance
{
    private string $node;

    /** @var array<string, mixed> */
    private array $initialData;

    /**
     * @throws RuntimeException
     */
    final public function __construct()
    {
        $this->node = $this->inferNode();
        $this->loadConfig();
        $this->initialData = $this->getConfig();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        $cfg = get_object_vars($this);
        unset($cfg['node'], $cfg['initialData']);
        return $cfg;
    }

    public function __toString(): string
    {
        return md5(json_encode($this->getConfig()));
    }

    private function loadConfig(): void
    {
        $cfg = Context::instance()->config()->get($this->node, $this->getConfig());
        if (!is_array($cfg)) {
            $cfg = $this->getConfig();
        }
        $this->map($cfg);
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function map(array $cfg): void
    {
        foreach ($cfg as $k => $v) {
            if (!property_exists($this, $k)) {
                continue;
            }
            $this->$k = Text::parseType($this->$k, $v);
        }
    }

    public function __destruct()
    {
        $current = $this->getConfig();
        $changed = [];
        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $this->initialData) || $this->initialData[$key] !== $value) {
                $changed[$this->node . '.' . $key] = $value;
            }
        }
        if ($changed === []) {
            return;
        }
        Context::instance()->config()->setMany($changed);
    }

    private function inferNode(): string
    {
        $short = (strrchr(static::class, '\\'))
            ? substr(strrchr(static::class, '\\'), 1)
            : static::class;

        $key = preg_replace('/Config$/', '', $short);

        return lcfirst($key);
    }
}
