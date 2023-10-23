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
 *  primary_key?: string|list<string>,
 *  through?: string
 * }
 * @phpstan-type HasAndBelongsToManyOptions array{
 *  join_table?: string,
 *  foreign_key?: string,
 *  association_foreign_key?: string,
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
 *  delegate: list<string>
 * }
 * @phpstan-type RelationOptions array{
 *  conditions?: list<WhereClause>,
 *  from?: string,
 *  group?: string,
 *  having?: string,
 *  include?: string|list<string>,
 *  joins?: string|list<string>,
 *  limit?: int,
 *  mapped_names?: array<string, string>,
 *  offset?: int,
 *  order?: string,
 *  readonly?: bool,
 *  distinct?: bool,
 *  select?: string|list<string>,
 * }
 */
abstract class Types
{
}
