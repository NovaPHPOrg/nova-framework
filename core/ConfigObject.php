<?php

declare(strict_types=1);

namespace nova\framework\core;

/**
 * 配置对象：类名 LoginConfig → 节点 login → config/login.config.php。
 * 有改动时整节点写回，不做字段级补丁。
 */
class ConfigObject extends Instance
{
    private string $node;

    /** @var array<string, mixed> */
    private array $initialData;

    final public function __construct()
    {
        $this->node = $this->inferNode();
        $cfg = Context::instance()->config()->get($this->node, []);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        foreach ($cfg as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = Text::parseType($this->$k, $v);
            }
        }
        $this->initialData = $this->snapshot();
    }

    public function __toString(): string
    {
        return md5(json_encode($this->snapshot()));
    }

    public function __destruct()
    {
        global $context;
        if (!$context instanceof Context) {
            return;
        }
        $current = $this->snapshot();
        if ($current === $this->initialData) {
            return;
        }
        $context->config()->set($this->node, $current);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $cfg = get_object_vars($this);
        unset($cfg['node'], $cfg['initialData']);
        return $cfg;
    }

    private function inferNode(): string
    {
        $short = (strrchr(static::class, '\\'))
            ? substr(strrchr(static::class, '\\'), 1)
            : static::class;
        return lcfirst((string)preg_replace('/Config$/', '', $short));
    }
}
