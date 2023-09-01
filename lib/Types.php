<?php

namespace ActiveRecord;

/**
 * @phpstan-type Attributes array<string,mixed>
 * @phpstan-type PrimaryKey array<int|string>|string|int|null
 * @phpstan-type QueryOptions array{
 *  conditions?: mixed,
 *  limit?: int,
 *  order?: string,
 *  set?: string|array<string, mixed>
 * }
 * @phpstan-type DelegateOptions array{
 *  to: string,
 *  prefix?: string,
 *  delegate?: array<string>
 * }
 */
abstract class Types
{
}
