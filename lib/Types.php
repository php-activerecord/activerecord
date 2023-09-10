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
 * @phpstan-type HasManyOptions array{
 *  limit?: int,
 *  offset?: int,
 *  primary_key?: string|array<string>,
 *  group?: string,
 *  order?: string,
 *  through?: string
 * }
 * @phpstan-type BelongsToOptions array{
 *  conditions?: array<mixed>,
 *  foreign_key?: string,
 *  class_name?: class-string,
 *  primary_key?: string
 * }
 * @phpstan-type DelegateOptions array{
 *  to: string,
 *  prefix?: string,
 *  delegate: array<string>
 * }
 * @phpstan-type RelationOptions array{
 *  conditions?: array<WhereClause>,
 *  limit?: int,
 *  offset?: int,
 *  order?: string,
 *  select?: string,
 *  joins?: string|array<string>,
 *  include?: string|array<string>,
 *  readonly?: bool,
 *  group?: string,
 *  from?: string,
 *  having?: string,
 *  mapped_names?: array<string, string>
 * }
 */
abstract class Types
{
}
