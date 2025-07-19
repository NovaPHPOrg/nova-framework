<?php

declare(strict_types=1);

namespace nova\framework\http;

use nova\framework\core\ArgObject;

class UploadModel extends ArgObject
{
    public string $name = "";
    public string $type = "";
    public string $tmp_name = "";
    public int $error = 0;
    public int $size = 0;
    public string $full_path = "";
}
