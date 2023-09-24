<?php

namespace ActiveRecord\cache;

interface iCache
{
    public function flush(): void;

    /**
     * @param list<string>|string $key
     */
    public function read(array|string $key): mixed;

    public function write(string $key, mixed $value, int $expire = 0): bool;

    public function delete(string $key): bool;
}
