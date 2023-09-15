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
 *  group?: string,
 *  limit?: int,
 *  offset?: int,
 *  order?: string,
 *  primary_key?: string|array<string>,
 *  through?: string
 * }
 * @phpstan-type HasAndBelongsToManyOptions array{
 *  join_table?: string,
 *  foreign_key?: string,
 *  association_foreign_key?: string,
 *  uniq?: bool,
 *  validate?: bool
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
 *  from?: string,
 *  group?: string,
 *  having?: string,
 *  include?: string|array<string>,
 *  joins?: string|array<string>,
 *  limit?: int,
 *  mapped_names?: array<string, string>,
 *  offset?: int,
 *  order?: string,
 *  readonly?: bool,
 *  distinct?: bool,
 *  select?: string|array<string>,
 * }
 */
abstract class Types
{
}
